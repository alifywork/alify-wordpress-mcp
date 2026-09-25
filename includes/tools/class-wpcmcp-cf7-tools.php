<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class WPCMCP_CF7_Tools {
    public static function definitions( $can_read, $can_write ) {
        if ( ! self::available() ) return array();
        $tools = array();

        if ( $can_read ) {
            $tools[] = self::t( 'wordpress.cf7_list_forms', 'List Contact Form 7 Forms', 'List Contact Form 7 forms.', array(), array(), true, false, true );
            $tools[] = self::t( 'wordpress.cf7_get_form', 'Get Contact Form 7 Form', 'Get one Contact Form 7 form including form markup, mail settings, messages and shortcode.', array(
                'form_id' => array( 'type' => 'integer', 'minimum' => 1 ),
            ), array( 'form_id' ), true, false, true );
        }

        if ( $can_write ) {
            $tools[] = self::t( 'wordpress.cf7_create_form', 'Create Contact Form 7 Form', 'Create a Contact Form 7 form using CF7 native save APIs.', array(
                'title' => array( 'type' => 'string' ),
                'locale' => array( 'type' => 'string' ),
                'form' => array( 'type' => 'string' ),
                'mail' => array( 'type' => 'object', 'additionalProperties' => true ),
                'mail_2' => array( 'type' => 'object', 'additionalProperties' => true ),
                'messages' => array( 'type' => 'object', 'additionalProperties' => true ),
                'additional_settings' => array( 'type' => 'string' ),
            ), array( 'title' ), false, false, false );
            $tools[] = self::t( 'wordpress.cf7_update_form', 'Update Contact Form 7 Form', 'Update an existing Contact Form 7 form using CF7 native save APIs.', array(
                'form_id' => array( 'type' => 'integer', 'minimum' => 1 ),
                'title' => array( 'type' => 'string' ),
                'locale' => array( 'type' => 'string' ),
                'form' => array( 'type' => 'string' ),
                'mail' => array( 'type' => 'object', 'additionalProperties' => true ),
                'mail_2' => array( 'type' => 'object', 'additionalProperties' => true ),
                'messages' => array( 'type' => 'object', 'additionalProperties' => true ),
                'additional_settings' => array( 'type' => 'string' ),
            ), array( 'form_id' ), false, false, true );
            $tools[] = self::t( 'wordpress.cf7_delete_form', 'Delete Contact Form 7 Form', 'Move a Contact Form 7 form to Trash, or permanently delete it when permanent=true and confirm=true.', array(
                'form_id' => array( 'type' => 'integer', 'minimum' => 1 ),
                'permanent' => array( 'type' => 'boolean' ),
                'confirm' => array( 'type' => 'boolean' ),
            ), array( 'form_id' ), false, true, true );
        }

        return $tools;
    }

    private static function t( $name, $title, $description, $properties, $required, $read_only, $destructive, $idempotent ) {
        return WPCMCP_Tools::tool( $name, $title, $description, $properties, $required, $read_only, $destructive, $idempotent );
    }

    public static function execute( $name, array $args ) {
        switch ( $name ) {
            case 'wordpress.cf7_list_forms': return self::list_forms();
            case 'wordpress.cf7_get_form': return self::get_form( $args );
            case 'wordpress.cf7_create_form': return self::create_form( $args );
            case 'wordpress.cf7_update_form': return self::update_form( $args );
            case 'wordpress.cf7_delete_form': return self::delete_form( $args );
            default: return null;
        }
    }

    private static function available() {
        return class_exists( 'WPCF7_ContactForm' ) && function_exists( 'wpcf7_contact_form' ) && function_exists( 'wpcf7_save_contact_form' );
    }

    private static function can_edit_all() {
        return current_user_can( 'wpcf7_edit_contact_forms' ) || current_user_can( 'publish_pages' ) || current_user_can( 'manage_options' );
    }

    private static function can_edit_one( $id ) {
        return current_user_can( 'wpcf7_edit_contact_form', absint( $id ) ) || self::can_edit_all();
    }

    private static function record( $form ) {
        if ( ! $form ) return null;
        $id = method_exists( $form, 'id' ) ? (int) $form->id() : 0;
        $props = method_exists( $form, 'get_properties' ) ? $form->get_properties() : array();
        return array(
            'id' => $id,
            'title' => method_exists( $form, 'title' ) ? $form->title() : '',
            'locale' => method_exists( $form, 'locale' ) ? $form->locale() : '',
            'form' => isset( $props['form'] ) ? $props['form'] : '',
            'mail' => isset( $props['mail'] ) ? $props['mail'] : array(),
            'mail_2' => isset( $props['mail_2'] ) ? $props['mail_2'] : array(),
            'messages' => isset( $props['messages'] ) ? $props['messages'] : array(),
            'additional_settings' => isset( $props['additional_settings'] ) ? $props['additional_settings'] : '',
            'shortcode' => method_exists( $form, 'shortcode' ) ? $form->shortcode() : ( $id ? '[contact-form-7 id="' . $id . '"]' : '' ),
        );
    }

    private static function list_forms() {
        if ( ! current_user_can( 'edit_posts' ) && ! current_user_can( 'manage_options' ) ) {
            return new WP_Error( 'forbidden', 'You cannot inspect Contact Form 7 forms.' );
        }
        $forms = WPCF7_ContactForm::find( array( 'posts_per_page' => -1, 'orderby' => 'title', 'order' => 'ASC' ) );
        $items = array();
        foreach ( (array) $forms as $form ) {
            $items[] = self::record( $form );
        }
        return array( 'forms' => $items );
    }

    private static function get_form( array $args ) {
        $id = absint( $args['form_id'] );
        $form = wpcf7_contact_form( $id );
        if ( ! $form ) return new WP_Error( 'not_found', 'Contact Form 7 form not found.' );
        if ( ! current_user_can( 'edit_posts' ) && ! current_user_can( 'manage_options' ) ) {
            return new WP_Error( 'forbidden', 'You cannot inspect this Contact Form 7 form.' );
        }
        return self::record( $form );
    }

    private static function normalize_save_data( array $args, $id ) {
        $data = array( 'id' => (int) $id );
        foreach ( array( 'title', 'locale', 'form', 'mail', 'mail_2', 'messages', 'additional_settings' ) as $key ) {
            if ( array_key_exists( $key, $args ) ) $data[ $key ] = $args[ $key ];
        }
        return $data;
    }

    private static function create_form( array $args ) {
        if ( ! self::can_edit_all() ) return new WP_Error( 'forbidden', 'You cannot create Contact Form 7 forms.' );
        $saved = wpcf7_save_contact_form( self::normalize_save_data( $args, -1 ), 'save' );
        if ( ! $saved ) return new WP_Error( 'cf7_save_failed', 'Contact Form 7 could not create the form.' );
        return self::record( $saved );
    }

    private static function update_form( array $args ) {
        $id = absint( $args['form_id'] );
        if ( ! self::can_edit_one( $id ) ) return new WP_Error( 'forbidden', 'You cannot update this Contact Form 7 form.' );
        if ( ! wpcf7_contact_form( $id ) ) return new WP_Error( 'not_found', 'Contact Form 7 form not found.' );
        $saved = wpcf7_save_contact_form( self::normalize_save_data( $args, $id ), 'save' );
        if ( ! $saved ) return new WP_Error( 'cf7_save_failed', 'Contact Form 7 could not update the form.' );
        return self::record( $saved );
    }

    private static function delete_form( array $args ) {
        $id = absint( $args['form_id'] );
        if ( ! self::can_edit_one( $id ) ) return new WP_Error( 'forbidden', 'You cannot delete this Contact Form 7 form.' );
        $post = get_post( $id );
        if ( ! $post || 'wpcf7_contact_form' !== $post->post_type ) return new WP_Error( 'not_found', 'Contact Form 7 form not found.' );

        $permanent = ! empty( $args['permanent'] );
        if ( $permanent && empty( $args['confirm'] ) ) {
            return new WP_Error( 'confirmation_required', 'Permanent Contact Form 7 deletion requires confirm=true.' );
        }

        $result = $permanent ? wp_delete_post( $id, true ) : wp_trash_post( $id );
        if ( ! $result ) return new WP_Error( 'delete_failed', 'WordPress could not delete the Contact Form 7 form.' );

        return array(
            'success' => true,
            'form_id' => $id,
            'permanent' => $permanent,
        );
    }
}
