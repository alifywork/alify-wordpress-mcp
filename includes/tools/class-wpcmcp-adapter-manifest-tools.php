<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class WPCMCP_Adapter_Manifest_Tools {
    const OPTION = 'wpcmcp_adapter_manifests';

    public static function bootstrap() {
        add_filter( 'wpcmcp_adapter_registry', array( __CLASS__, 'register_manifests' ), 30 );
    }

    public static function definitions( $can_read, $can_write ) {
        $tools = array();

        if ( $can_read ) {
            $tools[] = self::t( 'wordpress.adapter_manifest_list', 'List Adapter Manifests', 'List saved declarative adapter manifests for plugins that do not yet have a native PHP adapter.', array(), array(), true, false, true );
            $tools[] = self::t( 'wordpress.adapter_manifest_get', 'Get Adapter Manifest', 'Get one declarative adapter manifest.', array(
                'id' => array( 'type' => 'string' ),
            ), array( 'id' ), true, false, true );
        }

        if ( $can_write ) {
            $tools[] = self::t( 'wordpress.adapter_manifest_save', 'Create or Update Adapter Manifest', 'Create/update a declarative plugin adapter manifest describing known REST routes, settings, AJAX actions, shortcodes and content models.', array(
                'id' => array( 'type' => 'string' ),
                'name' => array( 'type' => 'string' ),
                'type' => array( 'type' => 'string' ),
                'plugin_matches' => array( 'type' => 'array', 'minItems' => 1, 'items' => array( 'type' => 'string' ) ),
                'capabilities' => array( 'type' => 'array', 'items' => array( 'type' => 'string' ) ),
                'rest_routes' => array( 'type' => 'array', 'items' => array( 'type' => 'string' ) ),
                'settings' => array( 'type' => 'array', 'items' => array( 'type' => 'string' ) ),
                'ajax_actions' => array( 'type' => 'array', 'items' => array( 'type' => 'string' ) ),
                'shortcodes' => array( 'type' => 'array', 'items' => array( 'type' => 'string' ) ),
                'post_types' => array( 'type' => 'array', 'items' => array( 'type' => 'string' ) ),
                'taxonomies' => array( 'type' => 'array', 'items' => array( 'type' => 'string' ) ),
                'notes' => array( 'type' => 'string' ),
            ), array( 'id', 'name', 'plugin_matches' ), false, false, true );
            $tools[] = self::t( 'wordpress.adapter_manifest_learn', 'Learn Plugin Adapter Manifest', 'Auto-discover an installed plugin using the Universal Plugin Operator and save a declarative capability manifest for future sessions.', array(
                'plugin' => array( 'type' => 'string' ),
                'id' => array( 'type' => 'string' ),
                'name' => array( 'type' => 'string' ),
                'confirm' => array( 'type' => 'boolean' ),
            ), array( 'plugin', 'confirm' ), false, false, true );
            $tools[] = self::t( 'wordpress.adapter_manifest_delete', 'Delete Adapter Manifest', 'Delete one declarative adapter manifest. Requires confirm=true.', array(
                'id' => array( 'type' => 'string' ),
                'confirm' => array( 'type' => 'boolean' ),
            ), array( 'id', 'confirm' ), false, true, true );
        }

        return $tools;
    }

    private static function t( $name, $title, $description, $properties, $required, $read_only, $destructive, $idempotent ) {
        return WPCMCP_Tools::tool( $name, $title, $description, $properties, $required, $read_only, $destructive, $idempotent );
    }

    public static function execute( $name, array $args ) {
        switch ( $name ) {
            case 'wordpress.adapter_manifest_list': return self::list_manifests();
            case 'wordpress.adapter_manifest_get': return self::get_manifest( $args );
            case 'wordpress.adapter_manifest_save': return self::save_manifest( $args );
            case 'wordpress.adapter_manifest_learn': return self::learn_manifest( $args );
            case 'wordpress.adapter_manifest_delete': return self::delete_manifest( $args );
            default: return null;
        }
    }

    private static function manifests() {
        $items = get_option( self::OPTION, array() );
        return is_array( $items ) ? $items : array();
    }

    private static function save_all( array $items ) {
        update_option( self::OPTION, $items, false );
    }

    private static function normalize_list( $items, $mode = 'text' ) {
        $out = array();
        foreach ( (array) $items as $item ) {
            $item = 'key' === $mode ? sanitize_key( $item ) : sanitize_text_field( $item );
            if ( '' !== $item ) $out[] = $item;
        }
        return array_values( array_unique( $out ) );
    }

    private static function public_manifest( array $manifest ) {
        return array(
            'id' => $manifest['id'],
            'name' => $manifest['name'],
            'type' => $manifest['type'],
            'plugin_matches' => $manifest['plugin_matches'],
            'capabilities' => $manifest['capabilities'],
            'rest_routes' => $manifest['rest_routes'],
            'settings' => $manifest['settings'],
            'ajax_actions' => $manifest['ajax_actions'],
            'shortcodes' => $manifest['shortcodes'],
            'post_types' => $manifest['post_types'],
            'taxonomies' => $manifest['taxonomies'],
            'notes' => $manifest['notes'],
            'updated_gmt' => $manifest['updated_gmt'],
        );
    }

    private static function list_manifests() {
        $out = array();
        foreach ( self::manifests() as $manifest ) $out[] = self::public_manifest( $manifest );
        return array( 'manifests' => $out );
    }

    private static function get_manifest( array $args ) {
        $id = sanitize_key( $args['id'] );
        $items = self::manifests();
        return isset( $items[ $id ] ) ? self::public_manifest( $items[ $id ] ) : new WP_Error( 'not_found', 'Adapter manifest not found.' );
    }

    private static function save_manifest( array $args ) {
        if ( ! current_user_can( 'manage_options' ) ) return new WP_Error( 'forbidden', 'Saving adapter manifests requires manage_options.' );

        $id = sanitize_key( $args['id'] );
        if ( ! $id ) return new WP_Error( 'invalid_id', 'A valid adapter manifest ID is required.' );

        $matches = self::normalize_list( $args['plugin_matches'] );
        if ( ! $matches ) return new WP_Error( 'invalid_matches', 'At least one plugin match string is required.' );

        $manifest = array(
            'id' => $id,
            'name' => sanitize_text_field( $args['name'] ),
            'type' => isset( $args['type'] ) ? sanitize_key( $args['type'] ) : 'plugin',
            'plugin_matches' => $matches,
            'capabilities' => self::normalize_list( isset( $args['capabilities'] ) ? $args['capabilities'] : array(), 'key' ),
            'rest_routes' => self::normalize_list( isset( $args['rest_routes'] ) ? $args['rest_routes'] : array() ),
            'settings' => self::normalize_list( isset( $args['settings'] ) ? $args['settings'] : array() ),
            'ajax_actions' => self::normalize_list( isset( $args['ajax_actions'] ) ? $args['ajax_actions'] : array(), 'key' ),
            'shortcodes' => self::normalize_list( isset( $args['shortcodes'] ) ? $args['shortcodes'] : array(), 'key' ),
            'post_types' => self::normalize_list( isset( $args['post_types'] ) ? $args['post_types'] : array(), 'key' ),
            'taxonomies' => self::normalize_list( isset( $args['taxonomies'] ) ? $args['taxonomies'] : array(), 'key' ),
            'notes' => isset( $args['notes'] ) ? sanitize_textarea_field( $args['notes'] ) : '',
            'updated_gmt' => gmdate( 'c' ),
        );

        $items = self::manifests();
        $items[ $id ] = $manifest;
        self::save_all( $items );

        return self::public_manifest( $manifest );
    }

    private static function learn_manifest( array $args ) {
        if ( ! current_user_can( 'manage_options' ) ) return new WP_Error( 'forbidden', 'Learning adapter manifests requires manage_options.' );
        if ( empty( $args['confirm'] ) ) return new WP_Error( 'confirmation_required', 'Learning and storing an adapter manifest requires confirm=true.' );
        if ( ! class_exists( 'WPCMCP_Plugin_Operator_Tools' ) || ! class_exists( 'WPCMCP_Universal_Operator_Tools' ) ) {
            return new WP_Error( 'operator_unavailable', 'Universal Plugin Operator is unavailable.' );
        }

        $discovery = WPCMCP_Plugin_Operator_Tools::execute(
            'wordpress.plugin_operator_discover',
            array( 'plugin' => $args['plugin'] )
        );
        if ( is_wp_error( $discovery ) ) return $discovery;

        $settings = WPCMCP_Universal_Operator_Tools::execute(
            'wordpress.plugin_operator_list_settings',
            array( 'plugin' => $discovery['plugin'] )
        );
        if ( is_wp_error( $settings ) ) $settings = array( 'settings' => array() );

        $ajax = WPCMCP_Universal_Operator_Tools::execute(
            'wordpress.plugin_operator_list_ajax_actions',
            array( 'plugin' => $discovery['plugin'] )
        );
        if ( is_wp_error( $ajax ) ) $ajax = array( 'actions' => array() );

        $slug = ! empty( $discovery['slug'] ) ? sanitize_key( $discovery['slug'] ) : sanitize_key( basename( $discovery['plugin'], '.php' ) );
        $id = ! empty( $args['id'] ) ? sanitize_key( $args['id'] ) : $slug;
        $name = ! empty( $args['name'] ) ? sanitize_text_field( $args['name'] ) : sanitize_text_field( $discovery['name'] );

        $post_types = array();
        foreach ( get_post_types( array(), 'objects' ) as $post_type ) {
            if ( false !== strpos( strtolower( $post_type->name ), strtolower( $slug ) ) ) $post_types[] = $post_type->name;
        }

        $taxonomies = array();
        foreach ( get_taxonomies( array(), 'objects' ) as $taxonomy ) {
            if ( false !== strpos( strtolower( $taxonomy->name ), strtolower( $slug ) ) ) $taxonomies[] = $taxonomy->name;
        }

        $rest_routes = array();
        foreach ( isset( $discovery['rest_routes'] ) ? (array) $discovery['rest_routes'] : array() as $route ) {
            if ( ! empty( $route['route'] ) ) $rest_routes[] = $route['route'];
        }

        $shortcodes = array();
        foreach ( isset( $discovery['shortcodes'] ) ? (array) $discovery['shortcodes'] : array() as $shortcode ) {
            if ( ! empty( $shortcode['tag'] ) ) $shortcodes[] = $shortcode['tag'];
        }

        $setting_names = array();
        foreach ( isset( $settings['settings'] ) ? (array) $settings['settings'] : array() as $setting ) {
            if ( ! empty( $setting['option'] ) ) $setting_names[] = $setting['option'];
        }

        $ajax_actions = array();
        foreach ( isset( $ajax['actions'] ) ? (array) $ajax['actions'] : array() as $action ) {
            if ( ! empty( $action['action'] ) ) $ajax_actions[] = $action['action'];
        }

        $capabilities = array();
        if ( $rest_routes ) $capabilities[] = 'rest_api';
        if ( $setting_names ) $capabilities[] = 'registered_settings';
        if ( $ajax_actions ) $capabilities[] = 'admin_ajax';
        if ( $shortcodes ) $capabilities[] = 'shortcodes';
        if ( $post_types ) $capabilities[] = 'post_types';
        if ( $taxonomies ) $capabilities[] = 'taxonomies';

        return self::save_manifest(
            array(
                'id' => $id,
                'name' => $name,
                'type' => 'learned_plugin',
                'plugin_matches' => array( $discovery['plugin'], $slug, $discovery['name'] ),
                'capabilities' => $capabilities,
                'rest_routes' => $rest_routes,
                'settings' => $setting_names,
                'ajax_actions' => $ajax_actions,
                'shortcodes' => $shortcodes,
                'post_types' => $post_types,
                'taxonomies' => $taxonomies,
                'notes' => 'Auto-learned from live plugin registration state. Relearn after major plugin upgrades if capabilities change.',
            )
        );
    }

    private static function delete_manifest( array $args ) {
        if ( ! current_user_can( 'manage_options' ) ) return new WP_Error( 'forbidden', 'Deleting adapter manifests requires manage_options.' );
        if ( empty( $args['confirm'] ) ) return new WP_Error( 'confirmation_required', 'Deleting an adapter manifest requires confirm=true.' );

        $id = sanitize_key( $args['id'] );
        $items = self::manifests();
        if ( ! isset( $items[ $id ] ) ) return new WP_Error( 'not_found', 'Adapter manifest not found.' );
        unset( $items[ $id ] );
        self::save_all( $items );

        return array( 'success' => true, 'id' => $id );
    }

    public static function register_manifests( $adapters ) {
        if ( ! is_array( $adapters ) ) $adapters = array();

        foreach ( self::manifests() as $id => $manifest ) {
            $adapter_id = 'manifest-' . sanitize_key( $id );
            if ( isset( $adapters[ $adapter_id ] ) ) continue;

            $adapters[ $adapter_id ] = array(
                'name' => $manifest['name'],
                'type' => $manifest['type'],
                'mode' => 'declarative',
                'match' => $manifest['plugin_matches'],
                'available' => static function () { return true; },
                'tool_prefixes' => array( 'wordpress.plugin_operator_' ),
                'capabilities' => $manifest['capabilities'],
                'manifest' => self::public_manifest( $manifest ),
            );
        }

        return $adapters;
    }
}
