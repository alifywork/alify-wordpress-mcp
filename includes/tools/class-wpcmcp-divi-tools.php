<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class WPCMCP_Divi_Tools {
    public static function definitions( $can_read, $can_write ) {
        if ( ! self::available() ) return array();
        $tools = array();
        if ( $can_read ) {
            $tools[] = self::t( 'wordpress.divi_status', 'Divi Status', 'Report Divi Builder availability, detected generation, and content storage mode.', array(), array(), true, false, true );
            $tools[] = self::t( 'wordpress.divi_get_document', 'Get Divi Document', 'Read Divi builder content with parsed WordPress blocks when present and a SHA-256 fingerprint.', array(
                'post_id' => array( 'type' => 'integer', 'minimum' => 1 ),
            ), array( 'post_id' ), true, false, true );
        }
        if ( $can_write ) {
            $tools[] = self::t( 'wordpress.divi_enable_document', 'Enable Divi for Content', 'Mark editable content as using Divi Builder.', array(
                'post_id' => array( 'type' => 'integer', 'minimum' => 1 ),
            ), array( 'post_id' ), false, false, true );
            $tools[] = self::t( 'wordpress.divi_replace_document', 'Replace Divi Document', 'Replace complete Divi builder content. Requires latest SHA-256 and confirm=true.', array(
                'post_id' => array( 'type' => 'integer', 'minimum' => 1 ),
                'content' => array( 'type' => 'string' ),
                'expected_sha256' => array( 'type' => 'string', 'minLength' => 64, 'maxLength' => 64 ),
                'confirm' => array( 'type' => 'boolean' ),
            ), array( 'post_id', 'content', 'expected_sha256', 'confirm' ), false, true, true );
            $tools[] = self::t( 'wordpress.divi_add_legacy_module', 'Add Legacy Divi Module', 'Append/prepend a registered Divi shortcode module such as et_pb_text, et_pb_button, et_pb_row or et_pb_section.', array(
                'post_id' => array( 'type' => 'integer', 'minimum' => 1 ),
                'tag' => array( 'type' => 'string' ),
                'attributes' => array( 'type' => 'object', 'additionalProperties' => true ),
                'inner_content' => array( 'type' => 'string' ),
                'position' => array( 'type' => 'string', 'enum' => array( 'append', 'prepend' ) ),
            ), array( 'post_id', 'tag' ), false, false, false );
            $tools[] = self::t( 'wordpress.divi_add_block', 'Add Divi 5 Block', 'Append/prepend a registered Divi block to block-based Divi content.', array(
                'post_id' => array( 'type' => 'integer', 'minimum' => 1 ),
                'block_name' => array( 'type' => 'string' ),
                'attrs' => array( 'type' => 'object', 'additionalProperties' => true ),
                'inner_blocks' => array( 'type' => 'array' ),
                'position' => array( 'type' => 'string', 'enum' => array( 'append', 'prepend' ) ),
            ), array( 'post_id', 'block_name' ), false, false, false );
        }
        return $tools;
    }

    private static function t( $name, $title, $description, $properties, $required, $read_only, $destructive, $idempotent ) {
        return WPCMCP_Tools::tool( $name, $title, $description, $properties, $required, $read_only, $destructive, $idempotent );
    }

    public static function execute( $name, array $args ) {
        switch ( $name ) {
            case 'wordpress.divi_status': return self::status();
            case 'wordpress.divi_get_document': return self::get_document( $args );
            case 'wordpress.divi_enable_document': return self::enable_document( $args );
            case 'wordpress.divi_replace_document': return self::replace_document( $args );
            case 'wordpress.divi_add_legacy_module': return self::add_legacy_module( $args );
            case 'wordpress.divi_add_block': return self::add_block( $args );
            default: return null;
        }
    }

    private static function available() {
        return defined( 'ET_BUILDER_VERSION' ) || defined( 'ET_CORE_VERSION' ) || class_exists( 'ET_Builder_Module' ) || function_exists( 'et_pb_is_pagebuilder_used' );
    }

    private static function status() {
        $generation = class_exists( '\\ET\\Builder\\Packages\\Module\\Module' ) ? 'divi5_or_newer' : 'legacy_or_mixed';
        return array(
            'available' => self::available(),
            'builder_version' => defined( 'ET_BUILDER_VERSION' ) ? ET_BUILDER_VERSION : null,
            'theme_version' => defined( 'ET_CORE_VERSION' ) ? ET_CORE_VERSION : null,
            'generation' => $generation,
            'supports_blocks' => function_exists( 'parse_blocks' ) && function_exists( 'serialize_blocks' ),
            'legacy_shortcode_support' => shortcode_exists( 'et_pb_section' ) || class_exists( 'ET_Builder_Module' ),
        );
    }

    private static function editable_post( $post_id, $write = false ) {
        $post_id = absint( $post_id );
        $post = get_post( $post_id );
        if ( ! $post ) return new WP_Error( 'not_found', 'WordPress content not found.' );
        if ( ! current_user_can( $write ? 'edit_post' : 'read_post', $post_id ) ) return new WP_Error( 'forbidden', 'You cannot access this content.' );
        return $post;
    }

    private static function record( $post ) {
        $content = (string) $post->post_content;
        return array(
            'post_id' => (int) $post->ID,
            'post_type' => $post->post_type,
            'title' => get_the_title( $post ),
            'content' => $content,
            'blocks' => function_exists( 'parse_blocks' ) && has_blocks( $content ) ? parse_blocks( $content ) : array(),
            'sha256' => hash( 'sha256', $content ),
            'divi_enabled' => 'on' === (string) get_post_meta( $post->ID, '_et_pb_use_builder', true ),
        );
    }

    private static function get_document( array $args ) {
        $post = self::editable_post( $args['post_id'], false );
        return is_wp_error( $post ) ? $post : self::record( $post );
    }

    private static function save_content( $post_id, $content ) {
        $result = wp_update_post( wp_slash( array( 'ID' => absint( $post_id ), 'post_content' => (string) $content ) ), true );
        if ( is_wp_error( $result ) ) return $result;
        update_post_meta( $post_id, '_et_pb_use_builder', 'on' );
        clean_post_cache( $post_id );
        return self::record( get_post( $post_id ) );
    }

    private static function enable_document( array $args ) {
        $post = self::editable_post( $args['post_id'], true );
        if ( is_wp_error( $post ) ) return $post;
        update_post_meta( $post->ID, '_et_pb_use_builder', 'on' );
        return self::record( get_post( $post->ID ) );
    }

    private static function replace_document( array $args ) {
        if ( empty( $args['confirm'] ) ) return new WP_Error( 'confirmation_required', 'Replacing complete Divi builder content requires confirm=true.' );
        $post = self::editable_post( $args['post_id'], true );
        if ( is_wp_error( $post ) ) return $post;
        $current = (string) $post->post_content;
        if ( ! hash_equals( hash( 'sha256', $current ), strtolower( sanitize_text_field( $args['expected_sha256'] ) ) ) ) {
            return new WP_Error( 'divi_document_conflict', 'Divi content changed after it was read. Read it again and use the latest SHA-256.' );
        }
        return self::save_content( $post->ID, $args['content'] );
    }

    private static function shortcode_string( $tag, array $attributes, $inner ) {
        $tag = sanitize_key( $tag );
        if ( 0 !== strpos( $tag, 'et_pb_' ) || ! shortcode_exists( $tag ) ) return new WP_Error( 'invalid_divi_module', 'Requested legacy Divi shortcode module is not registered.' );
        $parts = array();
        foreach ( $attributes as $key => $value ) {
            $key = sanitize_key( $key );
            if ( '' === $key || is_array( $value ) || is_object( $value ) ) continue;
            $parts[] = $key . '="' . esc_attr( (string) $value ) . '"';
        }
        return '[' . $tag . ( $parts ? ' ' . implode( ' ', $parts ) : '' ) . ']' . (string) $inner . '[/' . $tag . ']';
    }

    private static function add_legacy_module( array $args ) {
        $post = self::editable_post( $args['post_id'], true );
        if ( is_wp_error( $post ) ) return $post;
        $shortcode = self::shortcode_string(
            $args['tag'],
            isset( $args['attributes'] ) && is_array( $args['attributes'] ) ? $args['attributes'] : array(),
            isset( $args['inner_content'] ) ? $args['inner_content'] : ''
        );
        if ( is_wp_error( $shortcode ) ) return $shortcode;
        $content = (string) $post->post_content;
        $content = isset( $args['position'] ) && 'prepend' === $args['position'] ? $shortcode . "
" . $content : $content . "
" . $shortcode;
        return self::save_content( $post->ID, trim( $content ) );
    }

    private static function normalize_block( $block ) {
        if ( ! is_array( $block ) || empty( $block['blockName'] ) ) return new WP_Error( 'invalid_block', 'Divi block requires blockName.' );
        $name = sanitize_text_field( $block['blockName'] );
        if ( 0 !== strpos( $name, 'divi/' ) && 0 !== strpos( $name, 'et/' ) ) return new WP_Error( 'invalid_divi_block', 'Only registered Divi block namespaces are accepted.' );
        $registry = WP_Block_Type_Registry::get_instance();
        if ( ! $registry->is_registered( $name ) ) return new WP_Error( 'invalid_divi_block', 'Requested Divi block is not registered.' );
        return array(
            'blockName' => $name,
            'attrs' => isset( $block['attrs'] ) && is_array( $block['attrs'] ) ? $block['attrs'] : array(),
            'innerBlocks' => isset( $block['innerBlocks'] ) && is_array( $block['innerBlocks'] ) ? $block['innerBlocks'] : array(),
            'innerHTML' => isset( $block['innerHTML'] ) ? (string) $block['innerHTML'] : '',
            'innerContent' => isset( $block['innerContent'] ) && is_array( $block['innerContent'] ) ? $block['innerContent'] : array( null ),
        );
    }

    private static function add_block( array $args ) {
        if ( ! function_exists( 'parse_blocks' ) || ! function_exists( 'serialize_blocks' ) ) return new WP_Error( 'block_api_unavailable', 'WordPress block serialization API is unavailable.' );
        $post = self::editable_post( $args['post_id'], true );
        if ( is_wp_error( $post ) ) return $post;
        $block = self::normalize_block( array(
            'blockName' => $args['block_name'],
            'attrs' => isset( $args['attrs'] ) ? $args['attrs'] : array(),
            'innerBlocks' => isset( $args['inner_blocks'] ) ? $args['inner_blocks'] : array(),
        ) );
        if ( is_wp_error( $block ) ) return $block;
        $blocks = parse_blocks( (string) $post->post_content );
        if ( isset( $args['position'] ) && 'prepend' === $args['position'] ) array_unshift( $blocks, $block );
        else $blocks[] = $block;
        return self::save_content( $post->ID, serialize_blocks( $blocks ) );
    }
}
