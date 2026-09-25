<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class WPCMCP_WPBakery_Tools {
    public static function definitions( $can_read, $can_write ) {
        if ( ! self::available() ) return array();
        $tools = array();
        if ( $can_read ) {
            $tools[] = self::t( 'wordpress.wpbakery_status', 'WPBakery Status', 'Report WPBakery Page Builder availability and version.', array(), array(), true, false, true );
            $tools[] = self::t( 'wordpress.wpbakery_get_document', 'Get WPBakery Document', 'Read WPBakery shortcode content from a post/page/CPT with a SHA-256 fingerprint.', array(
                'post_id' => array( 'type' => 'integer', 'minimum' => 1 ),
            ), array( 'post_id' ), true, false, true );
        }
        if ( $can_write ) {
            $tools[] = self::t( 'wordpress.wpbakery_enable_document', 'Enable WPBakery for Content', 'Mark editable content as using WPBakery and preserve its shortcode-based content model.', array(
                'post_id' => array( 'type' => 'integer', 'minimum' => 1 ),
            ), array( 'post_id' ), false, false, true );
            $tools[] = self::t( 'wordpress.wpbakery_replace_document', 'Replace WPBakery Document', 'Replace the complete WPBakery shortcode document. Requires latest SHA-256 and confirm=true.', array(
                'post_id' => array( 'type' => 'integer', 'minimum' => 1 ),
                'content' => array( 'type' => 'string' ),
                'expected_sha256' => array( 'type' => 'string', 'minLength' => 64, 'maxLength' => 64 ),
                'confirm' => array( 'type' => 'boolean' ),
            ), array( 'post_id', 'content', 'expected_sha256', 'confirm' ), false, true, true );
            $tools[] = self::t( 'wordpress.wpbakery_add_element', 'Add WPBakery Element', 'Append or prepend a registered WordPress/WPBakery shortcode element to the builder document.', array(
                'post_id' => array( 'type' => 'integer', 'minimum' => 1 ),
                'tag' => array( 'type' => 'string' ),
                'attributes' => array( 'type' => 'object', 'additionalProperties' => true ),
                'inner_content' => array( 'type' => 'string' ),
                'position' => array( 'type' => 'string', 'enum' => array( 'append', 'prepend' ) ),
            ), array( 'post_id', 'tag' ), false, false, false );
        }
        return $tools;
    }

    private static function t( $name, $title, $description, $properties, $required, $read_only, $destructive, $idempotent ) {
        return WPCMCP_Tools::tool( $name, $title, $description, $properties, $required, $read_only, $destructive, $idempotent );
    }

    public static function execute( $name, array $args ) {
        switch ( $name ) {
            case 'wordpress.wpbakery_status': return self::status();
            case 'wordpress.wpbakery_get_document': return self::get_document( $args );
            case 'wordpress.wpbakery_enable_document': return self::enable_document( $args );
            case 'wordpress.wpbakery_replace_document': return self::replace_document( $args );
            case 'wordpress.wpbakery_add_element': return self::add_element( $args );
            default: return null;
        }
    }

    private static function available() {
        return defined( 'WPB_VC_VERSION' ) || class_exists( 'Vc_Manager' ) || function_exists( 'vc_map' );
    }

    private static function status() {
        return array(
            'available' => self::available(),
            'version' => defined( 'WPB_VC_VERSION' ) ? WPB_VC_VERSION : null,
            'storage' => 'post_content_shortcodes',
        );
    }

    private static function editable_post( $post_id, $write = false ) {
        $post_id = absint( $post_id );
        $post = get_post( $post_id );
        if ( ! $post ) return new WP_Error( 'not_found', 'WordPress content not found.' );
        if ( ! current_user_can( $write ? 'edit_post' : 'read_post', $post_id ) ) return new WP_Error( 'forbidden', 'You cannot access this content.' );
        return $post;
    }

    private static function document_record( $post ) {
        $content = (string) $post->post_content;
        return array(
            'post_id' => (int) $post->ID,
            'post_type' => $post->post_type,
            'title' => get_the_title( $post ),
            'content' => $content,
            'sha256' => hash( 'sha256', $content ),
            'wpbakery_enabled' => 'true' === (string) get_post_meta( $post->ID, '_wpb_vc_js_status', true ),
        );
    }

    private static function get_document( array $args ) {
        $post = self::editable_post( $args['post_id'], false );
        return is_wp_error( $post ) ? $post : self::document_record( $post );
    }

    private static function save_content( $post_id, $content ) {
        $result = wp_update_post( wp_slash( array( 'ID' => absint( $post_id ), 'post_content' => (string) $content ) ), true );
        if ( is_wp_error( $result ) ) return $result;
        update_post_meta( $post_id, '_wpb_vc_js_status', 'true' );
        clean_post_cache( $post_id );
        return self::document_record( get_post( $post_id ) );
    }

    private static function enable_document( array $args ) {
        $post = self::editable_post( $args['post_id'], true );
        if ( is_wp_error( $post ) ) return $post;
        update_post_meta( $post->ID, '_wpb_vc_js_status', 'true' );
        return self::document_record( get_post( $post->ID ) );
    }

    private static function replace_document( array $args ) {
        if ( empty( $args['confirm'] ) ) return new WP_Error( 'confirmation_required', 'Replacing the complete WPBakery document requires confirm=true.' );
        $post = self::editable_post( $args['post_id'], true );
        if ( is_wp_error( $post ) ) return $post;
        $current = (string) $post->post_content;
        if ( ! hash_equals( hash( 'sha256', $current ), strtolower( sanitize_text_field( $args['expected_sha256'] ) ) ) ) {
            return new WP_Error( 'wpbakery_document_conflict', 'WPBakery content changed after it was read. Read it again and use the latest SHA-256.' );
        }
        return self::save_content( $post->ID, $args['content'] );
    }

    private static function shortcode_string( $tag, array $attributes, $inner ) {
        $tag = sanitize_key( $tag );
        if ( ! $tag || ! shortcode_exists( $tag ) ) return new WP_Error( 'invalid_shortcode', 'The requested WPBakery/WordPress shortcode is not registered.' );
        $parts = array();
        foreach ( $attributes as $key => $value ) {
            $key = sanitize_key( $key );
            if ( '' === $key || is_array( $value ) || is_object( $value ) ) continue;
            $parts[] = $key . '="' . esc_attr( (string) $value ) . '"';
        }
        $open = '[' . $tag . ( $parts ? ' ' . implode( ' ', $parts ) : '' ) . ']';
        if ( null === $inner || '' === (string) $inner ) return $open . '[/' . $tag . ']';
        return $open . (string) $inner . '[/' . $tag . ']';
    }

    private static function add_element( array $args ) {
        $post = self::editable_post( $args['post_id'], true );
        if ( is_wp_error( $post ) ) return $post;
        $shortcode = self::shortcode_string(
            $args['tag'],
            isset( $args['attributes'] ) && is_array( $args['attributes'] ) ? $args['attributes'] : array(),
            array_key_exists( 'inner_content', $args ) ? $args['inner_content'] : ''
        );
        if ( is_wp_error( $shortcode ) ) return $shortcode;
        $content = (string) $post->post_content;
        $content = isset( $args['position'] ) && 'prepend' === $args['position'] ? $shortcode . "
" . $content : $content . "
" . $shortcode;
        return self::save_content( $post->ID, trim( $content ) );
    }
}
