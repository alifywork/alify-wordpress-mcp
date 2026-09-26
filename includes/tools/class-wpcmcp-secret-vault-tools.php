<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class WPCMCP_Secret_Vault_Tools {
    const OPTION = 'wpcmcp_secret_vault';

    public static function definitions( $can_read, $can_write ) {
        $tools = array();

        if ( $can_read ) {
            $tools[] = self::t( 'wordpress.secret_vault_list', 'List Secret Vault Entries', 'List secret names and metadata. Secret values are never returned.', array(), array(), true, false, true );
            $tools[] = self::t( 'wordpress.secret_vault_has', 'Check Secret Vault Entry', 'Check whether a named secret exists without revealing its value.', array(
                'name' => array( 'type' => 'string' ),
            ), array( 'name' ), true, false, true );
        }

        if ( $can_write ) {
            $tools[] = self::t( 'wordpress.secret_vault_set', 'Store Secret', 'Encrypt and store a write-only secret. The stored value is never returned. Requires confirm=true.', array(
                'name' => array( 'type' => 'string' ),
                'value' => array( 'type' => 'string', 'minLength' => 1 ),
                'description' => array( 'type' => 'string' ),
                'confirm' => array( 'type' => 'boolean' ),
            ), array( 'name', 'value', 'confirm' ), false, true, true );
            $tools[] = self::t( 'wordpress.secret_vault_delete', 'Delete Secret', 'Delete a stored secret. Requires confirm=true.', array(
                'name' => array( 'type' => 'string' ),
                'confirm' => array( 'type' => 'boolean' ),
            ), array( 'name', 'confirm' ), false, true, true );
            $tools[] = self::t( 'wordpress.secret_vault_apply_option', 'Apply Secret to WordPress Option', 'Decrypt a stored secret server-side and write it to an explicit WordPress option without returning the plaintext. Requires manage_options and confirm=true.', array(
                'name' => array( 'type' => 'string' ),
                'option' => array( 'type' => 'string' ),
                'confirm' => array( 'type' => 'boolean' ),
            ), array( 'name', 'option', 'confirm' ), false, true, true );
        }

        return $tools;
    }

    private static function t( $name, $title, $description, $properties, $required, $read_only, $destructive, $idempotent ) {
        return WPCMCP_Tools::tool( $name, $title, $description, $properties, $required, $read_only, $destructive, $idempotent );
    }

    public static function execute( $name, array $args ) {
        switch ( $name ) {
            case 'wordpress.secret_vault_list': return self::list_entries();
            case 'wordpress.secret_vault_has': return self::has_entry( $args );
            case 'wordpress.secret_vault_set': return self::set_entry( $args );
            case 'wordpress.secret_vault_delete': return self::delete_entry( $args );
            case 'wordpress.secret_vault_apply_option': return self::apply_option( $args );
            default: return null;
        }
    }

    private static function can_manage() {
        return current_user_can( 'manage_options' );
    }

    private static function entries() {
        $entries = get_option( self::OPTION, array() );
        return is_array( $entries ) ? $entries : array();
    }

    private static function save_entries( array $entries ) {
        update_option( self::OPTION, $entries, false );
    }

    private static function normalize_name( $name ) {
        $name = sanitize_key( $name );
        return strlen( $name ) <= 100 ? $name : substr( $name, 0, 100 );
    }

    private static function key_material() {
        if ( ! function_exists( 'openssl_encrypt' ) || ! function_exists( 'openssl_decrypt' ) ) {
            return new WP_Error( 'openssl_required', 'OpenSSL is required for the MCP secret vault.' );
        }
        $material = wp_salt( 'auth' ) . '|' . wp_salt( 'secure_auth' ) . '|' . home_url( '/' );
        return hash( 'sha256', $material, true );
    }

    private static function encrypt( $plaintext ) {
        $key = self::key_material();
        if ( is_wp_error( $key ) ) return $key;

        $cipher = 'aes-256-gcm';
        $iv_len = openssl_cipher_iv_length( $cipher );
        if ( ! $iv_len ) return new WP_Error( 'cipher_unavailable', 'AES-256-GCM is unavailable.' );

        try {
            $iv = random_bytes( $iv_len );
        } catch ( Throwable $e ) {
            return new WP_Error( 'random_failed', 'Secure random generation failed.' );
        }

        $tag = '';
        $ciphertext = openssl_encrypt( $plaintext, $cipher, $key, OPENSSL_RAW_DATA, $iv, $tag );
        if ( false === $ciphertext ) return new WP_Error( 'encrypt_failed', 'Secret encryption failed.' );

        return array(
            'cipher' => $cipher,
            'iv' => base64_encode( $iv ),
            'tag' => base64_encode( $tag ),
            'ciphertext' => base64_encode( $ciphertext ),
        );
    }

    private static function decrypt_entry( array $entry ) {
        $key = self::key_material();
        if ( is_wp_error( $key ) ) return $key;

        $cipher = isset( $entry['cipher'] ) ? $entry['cipher'] : 'aes-256-gcm';
        $iv = isset( $entry['iv'] ) ? base64_decode( $entry['iv'], true ) : false;
        $tag = isset( $entry['tag'] ) ? base64_decode( $entry['tag'], true ) : false;
        $ciphertext = isset( $entry['ciphertext'] ) ? base64_decode( $entry['ciphertext'], true ) : false;
        if ( false === $iv || false === $tag || false === $ciphertext ) return new WP_Error( 'secret_corrupt', 'Stored secret data is invalid.' );

        $plaintext = openssl_decrypt( $ciphertext, $cipher, $key, OPENSSL_RAW_DATA, $iv, $tag );
        if ( false === $plaintext ) return new WP_Error( 'decrypt_failed', 'Stored secret could not be decrypted.' );
        return $plaintext;
    }

    private static function public_record( $name, array $entry ) {
        return array(
            'name' => $name,
            'description' => isset( $entry['description'] ) ? $entry['description'] : '',
            'created_gmt' => isset( $entry['created_gmt'] ) ? $entry['created_gmt'] : null,
            'updated_gmt' => isset( $entry['updated_gmt'] ) ? $entry['updated_gmt'] : null,
            'created_by' => isset( $entry['created_by'] ) ? (int) $entry['created_by'] : 0,
        );
    }

    private static function list_entries() {
        if ( ! self::can_manage() ) return new WP_Error( 'forbidden', 'Secret vault inspection requires manage_options.' );
        $items = array();
        foreach ( self::entries() as $name => $entry ) $items[] = self::public_record( $name, $entry );
        return array( 'secrets' => $items );
    }

    private static function has_entry( array $args ) {
        if ( ! self::can_manage() ) return new WP_Error( 'forbidden', 'Secret vault inspection requires manage_options.' );
        $name = self::normalize_name( $args['name'] );
        $entries = self::entries();
        return array( 'name' => $name, 'exists' => isset( $entries[ $name ] ) );
    }

    private static function set_entry( array $args ) {
        if ( ! self::can_manage() ) return new WP_Error( 'forbidden', 'Storing secrets requires manage_options.' );
        if ( empty( $args['confirm'] ) ) return new WP_Error( 'confirmation_required', 'Storing a secret requires confirm=true.' );

        $name = self::normalize_name( $args['name'] );
        if ( ! $name ) return new WP_Error( 'invalid_name', 'A valid secret name is required.' );

        $encrypted = self::encrypt( (string) $args['value'] );
        if ( is_wp_error( $encrypted ) ) return $encrypted;

        $entries = self::entries();
        $created = isset( $entries[ $name ]['created_gmt'] ) ? $entries[ $name ]['created_gmt'] : gmdate( 'c' );

        $entries[ $name ] = $encrypted + array(
            'description' => isset( $args['description'] ) ? sanitize_text_field( $args['description'] ) : '',
            'created_gmt' => $created,
            'updated_gmt' => gmdate( 'c' ),
            'created_by' => get_current_user_id(),
        );

        self::save_entries( $entries );
        return array( 'success' => true ) + self::public_record( $name, $entries[ $name ] );
    }

    private static function delete_entry( array $args ) {
        if ( ! self::can_manage() ) return new WP_Error( 'forbidden', 'Deleting secrets requires manage_options.' );
        if ( empty( $args['confirm'] ) ) return new WP_Error( 'confirmation_required', 'Deleting a secret requires confirm=true.' );

        $name = self::normalize_name( $args['name'] );
        $entries = self::entries();
        if ( ! isset( $entries[ $name ] ) ) return new WP_Error( 'not_found', 'Secret not found.' );
        unset( $entries[ $name ] );
        self::save_entries( $entries );

        return array( 'success' => true, 'name' => $name );
    }

    private static function apply_option( array $args ) {
        if ( ! self::can_manage() ) return new WP_Error( 'forbidden', 'Applying secrets requires manage_options.' );
        if ( empty( $args['confirm'] ) ) return new WP_Error( 'confirmation_required', 'Applying a secret requires confirm=true.' );

        $name = self::normalize_name( $args['name'] );
        $option = sanitize_text_field( $args['option'] );
        if ( ! $option || strlen( $option ) > 191 || preg_match( '/[\x00-\x1F\x7F]/', $option ) ) return new WP_Error( 'invalid_option', 'A valid WordPress option name is required.' );

        $entries = self::entries();
        if ( ! isset( $entries[ $name ] ) ) return new WP_Error( 'not_found', 'Secret not found.' );

        $plaintext = self::decrypt_entry( $entries[ $name ] );
        if ( is_wp_error( $plaintext ) ) return $plaintext;

        update_option( $option, $plaintext );
        WPCMCP_DB::log( 'secret_applied', '', 'success', get_current_user_id(), '', 'secret=' . $name . ';option=' . $option );

        return array(
            'success' => true,
            'secret' => $name,
            'option' => $option,
            'value_returned' => false,
        );
    }
}
