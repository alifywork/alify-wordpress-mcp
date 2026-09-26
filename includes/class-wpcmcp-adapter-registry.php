<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class WPCMCP_Adapter_Registry {
    private static $adapters = null;

    public static function definitions( $can_read, $can_write ) {
        if ( ! $can_read ) return array();

        return array(
            self::t(
                'wordpress.adapter_registry_list',
                'List WordPress MCP Adapters',
                'List dedicated plugin/builder adapters known to the MCP plugin and report whether each adapter is currently available.',
                array(),
                array(),
                true, false, true
            ),
            self::t(
                'wordpress.adapter_registry_detect',
                'Detect Best Plugin Adapter',
                'Detect the best dedicated adapter for an installed plugin. Falls back to the Universal Plugin Operator when no dedicated adapter is available.',
                array(
                    'plugin' => array( 'type' => 'string', 'description' => 'Installed plugin file, slug or name fragment.' ),
                ),
                array( 'plugin' ),
                true, false, true
            ),
            self::t(
                'wordpress.adapter_registry_plan',
                'Plan Plugin Operation',
                'Return an execution plan for a natural-language plugin task using a dedicated adapter when possible and the Universal Plugin Operator as fallback.',
                array(
                    'plugin' => array( 'type' => 'string', 'description' => 'Installed plugin file, slug or name fragment.' ),
                    'intent' => array( 'type' => 'string', 'description' => 'What the user wants done with the plugin.' ),
                ),
                array( 'plugin', 'intent' ),
                true, false, true
            ),
        );
    }

    private static function t( $name, $title, $description, $properties, $required, $read_only, $destructive, $idempotent ) {
        return WPCMCP_Tools::tool( $name, $title, $description, $properties, $required, $read_only, $destructive, $idempotent );
    }

    public static function execute( $name, array $args ) {
        switch ( $name ) {
            case 'wordpress.adapter_registry_list':
                return array( 'adapters' => self::adapter_records() );
            case 'wordpress.adapter_registry_detect':
                return self::detect_tool( $args );
            case 'wordpress.adapter_registry_plan':
                return self::plan_tool( $args );
            default:
                return null;
        }
    }

    public static function adapters() {
        if ( null !== self::$adapters ) return self::$adapters;

        $adapters = array(
            'contact-form-7' => array(
                'name' => 'Contact Form 7',
                'type' => 'forms',
                'match' => array( 'contact-form-7/wp-contact-form-7.php', 'contact-form-7' ),
                'available' => static function () { return class_exists( 'WPCF7_ContactForm' ); },
                'tool_prefixes' => array( 'wordpress.cf7_' ),
                'capabilities' => array( 'list_forms', 'get_form', 'create_form', 'update_form', 'delete_form' ),
            ),
            'elementor' => array(
                'name' => 'Elementor',
                'type' => 'page_builder',
                'match' => array( 'elementor/elementor.php', 'elementor' ),
                'available' => static function () { return did_action( 'elementor/loaded' ) || class_exists( '\\Elementor\\Plugin' ); },
                'tool_prefixes' => array( 'wordpress.elementor_' ),
                'capabilities' => array( 'read_document', 'render_document', 'add_container', 'add_widget', 'add_element', 'update_element', 'move_element', 'duplicate_element', 'delete_element', 'replace_document', 'page_settings' ),
            ),
            'wpbakery' => array(
                'name' => 'WPBakery Page Builder',
                'type' => 'page_builder',
                'match' => array( 'js_composer/js_composer.php', 'js_composer', 'wpbakery' ),
                'available' => static function () { return defined( 'WPB_VC_VERSION' ) || class_exists( 'Vc_Manager' ) || function_exists( 'vc_map' ); },
                'tool_prefixes' => array( 'wordpress.wpbakery_' ),
                'capabilities' => array( 'read_document', 'enable_document', 'add_shortcode_element', 'replace_document' ),
            ),
            'divi' => array(
                'name' => 'Divi Builder',
                'type' => 'page_builder',
                'match' => array( 'divi-builder/divi-builder.php', 'divi-builder', 'divi' ),
                'available' => static function () { return defined( 'ET_BUILDER_VERSION' ) || class_exists( 'ET_Builder_Module' ) || function_exists( 'et_pb_is_pagebuilder_used' ); },
                'tool_prefixes' => array( 'wordpress.divi_' ),
                'capabilities' => array( 'read_document', 'enable_document', 'add_legacy_module', 'add_block', 'replace_document' ),
            ),
            'muffin' => array(
                'name' => 'Muffin Builder / BeBuilder',
                'type' => 'page_builder',
                'match' => array( 'betheme', 'muffin', 'bebuilder' ),
                'available' => static function () {
                    $theme = wp_get_theme();
                    return defined( 'MFN_THEME_VERSION' ) || class_exists( 'Mfn_Builder_Front' ) || false !== stripos( $theme->get( 'Name' ), 'betheme' ) || 'betheme' === strtolower( $theme->get_template() );
                },
                'tool_prefixes' => array( 'wordpress.muffin_' ),
                'capabilities' => array( 'read_document', 'replace_document', 'append_section', 'update_item', 'delete_item' ),
            ),
            'woocommerce' => array(
                'name' => 'WooCommerce',
                'type' => 'ecommerce',
                'match' => array( 'woocommerce/woocommerce.php', 'woocommerce' ),
                'available' => static function () { return class_exists( 'WooCommerce' ) || function_exists( 'wc_get_product' ); },
                'tool_prefixes' => array( 'woocommerce.' ),
                'capabilities' => array( 'products', 'orders', 'customers', 'order_status' ),
            ),
            'acf' => array(
                'name' => 'Advanced Custom Fields / ACF PRO',
                'type' => 'content_model',
                'match' => array( 'advanced-custom-fields-pro/acf.php', 'advanced-custom-fields/acf.php', 'advanced-custom-fields', 'acf' ),
                'available' => static function () { return function_exists( 'acf_get_field_groups' ); },
                'tool_prefixes' => array( 'wordpress.acf_', 'wordpress.get_acf_', 'wordpress.update_acf_' ),
                'capabilities' => array( 'field_groups', 'fields', 'location_rules', 'conditional_logic', 'values', 'option_pages' ),
            ),
        );

        $filtered = apply_filters( 'wpcmcp_adapter_registry', $adapters );
        self::$adapters = is_array( $filtered ) ? $filtered : $adapters;
        return self::$adapters;
    }

    private static function load_plugin_admin() {
        if ( ! function_exists( 'get_plugins' ) ) require_once ABSPATH . 'wp-admin/includes/plugin.php';
    }

    private static function resolve_plugin( $needle ) {
        self::load_plugin_admin();
        $needle = strtolower( trim( (string) $needle ) );
        if ( '' === $needle ) return new WP_Error( 'invalid_plugin', 'Plugin file, slug or name is required.' );

        $plugins = get_plugins();
        if ( isset( $plugins[ $needle ] ) ) return $needle;

        $matches = array();
        foreach ( $plugins as $file => $data ) {
            $slug = dirname( $file );
            if ( '.' === $slug ) $slug = basename( $file, '.php' );
            $values = array(
                strtolower( $file ),
                strtolower( $slug ),
                strtolower( basename( $file, '.php' ) ),
                strtolower( isset( $data['Name'] ) ? $data['Name'] : '' ),
            );
            foreach ( $values as $value ) {
                if ( $value === $needle || false !== strpos( $value, $needle ) ) {
                    $matches[] = $file;
                    break;
                }
            }
        }

        $matches = array_values( array_unique( $matches ) );
        if ( 1 === count( $matches ) ) return $matches[0];
        if ( 1 < count( $matches ) ) return new WP_Error( 'ambiguous_plugin', 'Plugin identifier matched multiple installed plugins. Use the exact plugin file.' );
        return new WP_Error( 'not_found', 'Installed plugin not found.' );
    }

    private static function adapter_available( array $adapter ) {
        if ( empty( $adapter['available'] ) || ! is_callable( $adapter['available'] ) ) return false;
        try {
            return (bool) call_user_func( $adapter['available'] );
        } catch ( Throwable $e ) {
            return false;
        }
    }

    private static function adapter_records() {
        $items = array();
        foreach ( self::adapters() as $id => $adapter ) {
            $items[] = array(
                'id' => $id,
                'name' => isset( $adapter['name'] ) ? $adapter['name'] : $id,
                'type' => isset( $adapter['type'] ) ? $adapter['type'] : 'plugin',
                'available' => self::adapter_available( $adapter ),
                'tool_prefixes' => isset( $adapter['tool_prefixes'] ) ? array_values( (array) $adapter['tool_prefixes'] ) : array(),
                'capabilities' => isset( $adapter['capabilities'] ) ? array_values( (array) $adapter['capabilities'] ) : array(),
            );
        }
        return $items;
    }

    private static function matches_plugin( $plugin_file, array $adapter ) {
        $plugin_file_lc = strtolower( $plugin_file );
        $slug = dirname( $plugin_file );
        if ( '.' === $slug ) $slug = basename( $plugin_file, '.php' );
        $slug = strtolower( $slug );

        foreach ( isset( $adapter['match'] ) ? (array) $adapter['match'] : array() as $needle ) {
            $needle = strtolower( (string) $needle );
            if ( '' === $needle ) continue;
            if ( $needle === $plugin_file_lc || $needle === $slug || false !== strpos( $plugin_file_lc, $needle ) || false !== strpos( $slug, $needle ) ) return true;
        }
        return false;
    }

    private static function detect( $plugin_file ) {
        foreach ( self::adapters() as $id => $adapter ) {
            if ( self::matches_plugin( $plugin_file, $adapter ) ) {
                return array(
                    'id' => $id,
                    'name' => isset( $adapter['name'] ) ? $adapter['name'] : $id,
                    'type' => isset( $adapter['type'] ) ? $adapter['type'] : 'plugin',
                    'available' => self::adapter_available( $adapter ),
                    'tool_prefixes' => isset( $adapter['tool_prefixes'] ) ? array_values( (array) $adapter['tool_prefixes'] ) : array(),
                    'capabilities' => isset( $adapter['capabilities'] ) ? array_values( (array) $adapter['capabilities'] ) : array(),
                );
            }
        }
        return null;
    }

    private static function detect_tool( array $args ) {
        $plugin = self::resolve_plugin( $args['plugin'] );
        if ( is_wp_error( $plugin ) ) return $plugin;

        $adapter = self::detect( $plugin );
        return array(
            'plugin' => $plugin,
            'active' => is_plugin_active( $plugin ),
            'dedicated_adapter' => $adapter,
            'fallback' => array(
                'adapter' => 'universal_plugin_operator',
                'discover_tool' => 'wordpress.plugin_operator_discover',
                'rest_tool' => 'wordpress.plugin_operator_call_rest',
                'source_tools' => array(
                    'wordpress.plugin_operator_list_source_files',
                    'wordpress.plugin_operator_read_source_file',
                ),
            ),
        );
    }

    private static function plan_tool( array $args ) {
        $detected = self::detect_tool( $args );
        if ( is_wp_error( $detected ) ) return $detected;

        $intent = sanitize_textarea_field( $args['intent'] );
        $steps = array();

        if ( empty( $detected['active'] ) ) {
            $steps[] = array(
                'action' => 'activate_plugin',
                'tool' => 'wordpress.activate_plugin',
                'plugin' => $detected['plugin'],
            );
        }

        if ( ! empty( $detected['dedicated_adapter'] ) && ! empty( $detected['dedicated_adapter']['available'] ) ) {
            $steps[] = array(
                'action' => 'use_dedicated_adapter',
                'adapter' => $detected['dedicated_adapter']['id'],
                'tool_prefixes' => $detected['dedicated_adapter']['tool_prefixes'],
                'capabilities' => $detected['dedicated_adapter']['capabilities'],
            );
        } else {
            $steps[] = array(
                'action' => 'discover_plugin',
                'tool' => 'wordpress.plugin_operator_discover',
                'plugin' => $detected['plugin'],
            );
            $steps[] = array(
                'action' => 'prefer_native_interface',
                'priority' => array( 'dedicated MCP adapter', 'plugin REST route', 'registered CPT/taxonomy', 'shortcode/public API', 'read-only source inspection' ),
            );
        }

        return array(
            'plugin' => $detected['plugin'],
            'intent' => $intent,
            'dedicated_adapter' => $detected['dedicated_adapter'],
            'steps' => $steps,
            'safety' => 'Use native WordPress/plugin capability checks. Mutating generic REST calls and destructive actions require explicit confirmation where the tool schema requires it.',
        );
    }
}
