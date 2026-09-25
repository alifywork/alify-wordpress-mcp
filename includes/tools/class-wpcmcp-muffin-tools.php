<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class WPCMCP_Muffin_Tools {
    const META_KEY = 'mfn-page-items';

    public static function definitions( $can_read, $can_write ) {
        if ( ! self::available() ) return array();
        $tools = array();
        if ( $can_read ) {
            $tools[] = self::t( 'wordpress.muffin_status', 'Muffin/BeBuilder Status', 'Report BeTheme/Muffin Builder availability and detected storage format.', array(), array(), true, false, true );
            $tools[] = self::t( 'wordpress.muffin_get_document', 'Get Muffin Builder Document', 'Read decoded mfn-page-items builder data and return a SHA-256 fingerprint of the stored builder payload.', array(
                'post_id' => array( 'type' => 'integer', 'minimum' => 1 ),
            ), array( 'post_id' ), true, false, true );
        }
        if ( $can_write ) {
            $tools[] = self::t( 'wordpress.muffin_replace_document', 'Replace Muffin Builder Document', 'Replace complete Muffin/BeBuilder data while preserving the detected storage encoding. Requires latest SHA-256 and confirm=true.', array(
                'post_id' => array( 'type' => 'integer', 'minimum' => 1 ),
                'data' => array( 'type' => 'array' ),
                'expected_sha256' => array( 'type' => 'string', 'minLength' => 64, 'maxLength' => 64 ),
                'confirm' => array( 'type' => 'boolean' ),
            ), array( 'post_id', 'data', 'expected_sha256', 'confirm' ), false, true, true );
            $tools[] = self::t( 'wordpress.muffin_append_section', 'Append Muffin Builder Section', 'Append a validated section array to Muffin/BeBuilder document data.', array(
                'post_id' => array( 'type' => 'integer', 'minimum' => 1 ),
                'section' => array( 'type' => 'object', 'additionalProperties' => true ),
            ), array( 'post_id', 'section' ), false, false, false );
            $tools[] = self::t( 'wordpress.muffin_update_item', 'Update Muffin Builder Item', 'Merge fields into a builder item addressed by section/wrap/item indexes.', array(
                'post_id' => array( 'type' => 'integer', 'minimum' => 1 ),
                'section_index' => array( 'type' => 'integer', 'minimum' => 0 ),
                'wrap_index' => array( 'type' => 'integer', 'minimum' => 0 ),
                'item_index' => array( 'type' => 'integer', 'minimum' => 0 ),
                'fields' => array( 'type' => 'object', 'additionalProperties' => true ),
            ), array( 'post_id', 'section_index', 'wrap_index', 'item_index', 'fields' ), false, false, true );
            $tools[] = self::t( 'wordpress.muffin_delete_item', 'Delete Muffin Builder Item', 'Delete one Muffin/BeBuilder item by section/wrap/item indexes. Requires confirm=true.', array(
                'post_id' => array( 'type' => 'integer', 'minimum' => 1 ),
                'section_index' => array( 'type' => 'integer', 'minimum' => 0 ),
                'wrap_index' => array( 'type' => 'integer', 'minimum' => 0 ),
                'item_index' => array( 'type' => 'integer', 'minimum' => 0 ),
                'confirm' => array( 'type' => 'boolean' ),
            ), array( 'post_id', 'section_index', 'wrap_index', 'item_index', 'confirm' ), false, true, true );
        }
        return $tools;
    }

    private static function t( $name, $title, $description, $properties, $required, $read_only, $destructive, $idempotent ) {
        return WPCMCP_Tools::tool( $name, $title, $description, $properties, $required, $read_only, $destructive, $idempotent );
    }

    public static function execute( $name, array $args ) {
        switch ( $name ) {
            case 'wordpress.muffin_status': return self::status();
            case 'wordpress.muffin_get_document': return self::get_document( $args );
            case 'wordpress.muffin_replace_document': return self::replace_document( $args );
            case 'wordpress.muffin_append_section': return self::append_section( $args );
            case 'wordpress.muffin_update_item': return self::update_item( $args );
            case 'wordpress.muffin_delete_item': return self::delete_item( $args );
            default: return null;
        }
    }

    private static function available() {
        $theme = wp_get_theme();
        return defined( 'MFN_THEME_VERSION' ) || class_exists( 'Mfn_Builder_Front' ) || false !== stripos( $theme->get( 'Name' ), 'betheme' ) || 'betheme' === strtolower( $theme->get_template() );
    }

    private static function status() {
        return array(
            'available' => self::available(),
            'theme_version' => defined( 'MFN_THEME_VERSION' ) ? MFN_THEME_VERSION : wp_get_theme()->get( 'Version' ),
            'storage_meta_key' => self::META_KEY,
        );
    }

    private static function editable_post( $post_id, $write = false ) {
        $post_id = absint( $post_id );
        $post = get_post( $post_id );
        if ( ! $post ) return new WP_Error( 'not_found', 'WordPress content not found.' );
        if ( ! current_user_can( $write ? 'edit_post' : 'read_post', $post_id ) ) return new WP_Error( 'forbidden', 'You cannot access this content.' );
        return $post;
    }

    private static function safe_unserialize( $value ) {
        if ( ! is_string( $value ) || ! is_serialized( $value ) ) return false;
        $result = @unserialize( $value, array( 'allowed_classes' => false ) );
        return false === $result && 'b:0;' !== $value ? false : $result;
    }

    private static function decode( $raw ) {
        if ( is_array( $raw ) ) return array( 'data' => $raw, 'format' => 'array' );
        $raw = (string) $raw;
        if ( '' === $raw ) return array( 'data' => array(), 'format' => 'serialized' );

        $decoded = self::safe_unserialize( $raw );
        if ( false !== $decoded ) return array( 'data' => is_array( $decoded ) ? $decoded : array(), 'format' => 'serialized' );

        $base64 = base64_decode( $raw, true );
        if ( false !== $base64 ) {
            $decoded = self::safe_unserialize( $base64 );
            if ( false !== $decoded ) return array( 'data' => is_array( $decoded ) ? $decoded : array(), 'format' => 'base64_serialized' );
        }

        $json = json_decode( $raw, true );
        if ( JSON_ERROR_NONE === json_last_error() && is_array( $json ) ) return array( 'data' => $json, 'format' => 'json' );

        return new WP_Error( 'muffin_decode_failed', 'Muffin Builder data format could not be decoded safely.' );
    }

    private static function encode( array $data, $format ) {
        if ( 'base64_serialized' === $format ) return base64_encode( serialize( $data ) );
        if ( 'json' === $format ) return wp_json_encode( $data );
        return serialize( $data );
    }

    private static function record( $post ) {
        $raw = get_post_meta( $post->ID, self::META_KEY, true );
        $decoded = self::decode( $raw );
        if ( is_wp_error( $decoded ) ) return $decoded;
        return array(
            'post_id' => (int) $post->ID,
            'post_type' => $post->post_type,
            'title' => get_the_title( $post ),
            'storage_format' => $decoded['format'],
            'data' => $decoded['data'],
            'sha256' => hash( 'sha256', is_string( $raw ) ? $raw : serialize( $raw ) ),
        );
    }

    private static function get_document( array $args ) {
        $post = self::editable_post( $args['post_id'], false );
        return is_wp_error( $post ) ? $post : self::record( $post );
    }

    private static function save_data( $post_id, array $data, $format ) {
        $encoded = self::encode( $data, $format );
        update_post_meta( $post_id, self::META_KEY, $encoded );
        clean_post_cache( $post_id );
        return self::record( get_post( $post_id ) );
    }

    private static function replace_document( array $args ) {
        if ( empty( $args['confirm'] ) ) return new WP_Error( 'confirmation_required', 'Replacing complete Muffin Builder data requires confirm=true.' );
        $post = self::editable_post( $args['post_id'], true );
        if ( is_wp_error( $post ) ) return $post;
        $raw = get_post_meta( $post->ID, self::META_KEY, true );
        $current_sha = hash( 'sha256', is_string( $raw ) ? $raw : serialize( $raw ) );
        if ( ! hash_equals( $current_sha, strtolower( sanitize_text_field( $args['expected_sha256'] ) ) ) ) return new WP_Error( 'muffin_document_conflict', 'Muffin Builder data changed after it was read. Read it again and use the latest SHA-256.' );
        $decoded = self::decode( $raw );
        if ( is_wp_error( $decoded ) ) return $decoded;
        return self::save_data( $post->ID, array_values( $args['data'] ), $decoded['format'] );
    }

    private static function append_section( array $args ) {
        $post = self::editable_post( $args['post_id'], true );
        if ( is_wp_error( $post ) ) return $post;
        $raw = get_post_meta( $post->ID, self::META_KEY, true );
        $decoded = self::decode( $raw );
        if ( is_wp_error( $decoded ) ) return $decoded;
        $section = $args['section'];
        if ( ! is_array( $section ) ) return new WP_Error( 'invalid_section', 'Muffin section must be an object.' );
        $decoded['data'][] = $section;
        return self::save_data( $post->ID, $decoded['data'], $decoded['format'] );
    }

    private static function locate_item( array &$data, $s, $w, $i ) {
        if ( ! isset( $data[ $s ]['wraps'][ $w ]['items'][ $i ] ) || ! is_array( $data[ $s ]['wraps'][ $w ]['items'][ $i ] ) ) return false;
        return $data[ $s ]['wraps'][ $w ]['items'][ $i ];
    }

    private static function update_item( array $args ) {
        $post = self::editable_post( $args['post_id'], true );
        if ( is_wp_error( $post ) ) return $post;
        $raw = get_post_meta( $post->ID, self::META_KEY, true );
        $decoded = self::decode( $raw );
        if ( is_wp_error( $decoded ) ) return $decoded;
        $s = absint( $args['section_index'] ); $w = absint( $args['wrap_index'] ); $i = absint( $args['item_index'] );
        if ( ! isset( $decoded['data'][ $s ]['wraps'][ $w ]['items'][ $i ] ) || ! is_array( $decoded['data'][ $s ]['wraps'][ $w ]['items'][ $i ] ) ) return new WP_Error( 'not_found', 'Muffin Builder item not found at supplied indexes.' );
        $current = $decoded['data'][ $s ]['wraps'][ $w ]['items'][ $i ];
        $current['fields'] = array_replace_recursive( isset( $current['fields'] ) && is_array( $current['fields'] ) ? $current['fields'] : array(), $args['fields'] );
        $decoded['data'][ $s ]['wraps'][ $w ]['items'][ $i ] = $current;
        return self::save_data( $post->ID, $decoded['data'], $decoded['format'] );
    }

    private static function delete_item( array $args ) {
        if ( empty( $args['confirm'] ) ) return new WP_Error( 'confirmation_required', 'Deleting a Muffin Builder item requires confirm=true.' );
        $post = self::editable_post( $args['post_id'], true );
        if ( is_wp_error( $post ) ) return $post;
        $raw = get_post_meta( $post->ID, self::META_KEY, true );
        $decoded = self::decode( $raw );
        if ( is_wp_error( $decoded ) ) return $decoded;
        $s = absint( $args['section_index'] ); $w = absint( $args['wrap_index'] ); $i = absint( $args['item_index'] );
        if ( ! isset( $decoded['data'][ $s ]['wraps'][ $w ]['items'][ $i ] ) ) return new WP_Error( 'not_found', 'Muffin Builder item not found at supplied indexes.' );
        array_splice( $decoded['data'][ $s ]['wraps'][ $w ]['items'], $i, 1 );
        return self::save_data( $post->ID, $decoded['data'], $decoded['format'] );
    }
}
