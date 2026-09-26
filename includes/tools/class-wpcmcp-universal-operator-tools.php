<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class WPCMCP_Ajax_Die_Exception extends Exception {
    public $response;
    public function __construct( $message = '', $response = null ) {
        parent::__construct( (string) $message );
        $this->response = $response;
    }
}

class WPCMCP_Universal_Operator_Tools {
    public static function definitions( $can_read, $can_write ) {
        $tools = array();

        if ( $can_read ) {
            $tools[] = self::t( 'wordpress.plugin_operator_list_settings', 'List Plugin Settings', 'Discover registered WordPress settings that can be attributed to an installed plugin.', array(
                'plugin' => array( 'type' => 'string' ),
            ), array( 'plugin' ), true, false, true );

            $tools[] = self::t( 'wordpress.plugin_operator_get_setting', 'Get Plugin Setting', 'Read one discovered registered plugin setting. Secret-looking option names are blocked.', array(
                'plugin' => array( 'type' => 'string' ),
                'option' => array( 'type' => 'string' ),
            ), array( 'plugin', 'option' ), true, false, true );

            $tools[] = self::t( 'wordpress.plugin_operator_list_ajax_actions', 'List Plugin Admin AJAX Actions', 'Discover authenticated wp_ajax_* actions whose callbacks belong to an installed plugin.', array(
                'plugin' => array( 'type' => 'string' ),
            ), array( 'plugin' ), true, false, true );

            $tools[] = self::t( 'wordpress.plugin_operator_list_admin_pages', 'List Plugin Admin Pages', 'Discover WordPress admin menu/submenu pages whose callbacks belong to an installed plugin.', array(
                'plugin' => array( 'type' => 'string' ),
            ), array( 'plugin' ), true, false, true );

            $tools[] = self::t( 'wordpress.plugin_operator_diagnose', 'Diagnose Plugin Operation', 'Explain which interfaces are available for an installed plugin and which path GPT should use for the requested intent.', array(
                'plugin' => array( 'type' => 'string' ),
                'intent' => array( 'type' => 'string' ),
            ), array( 'plugin', 'intent' ), true, false, true );
        }

        if ( $can_write ) {
            $tools[] = self::t( 'wordpress.plugin_operator_update_setting', 'Update Plugin Setting', 'Update one discovered registered plugin setting through WordPress update_option after registered sanitization. Requires manage_options and confirm=true.', array(
                'plugin' => array( 'type' => 'string' ),
                'option' => array( 'type' => 'string' ),
                'value' => array(),
                'confirm' => array( 'type' => 'boolean' ),
            ), array( 'plugin', 'option', 'value', 'confirm' ), false, true, true );

            $tools[] = self::t( 'wordpress.plugin_operator_call_ajax', 'Call Plugin Admin AJAX Action', 'Invoke only authenticated wp_ajax_* callbacks attributed to the target plugin, as the current WordPress user. Requires confirm=true. Plugin nonce/capability checks still run.', array(
                'plugin' => array( 'type' => 'string' ),
                'action' => array( 'type' => 'string' ),
                'params' => array( 'type' => 'object', 'additionalProperties' => true ),
                'confirm' => array( 'type' => 'boolean' ),
            ), array( 'plugin', 'action', 'confirm' ), false, true, false );
        }

        return $tools;
    }

    private static function t( $name, $title, $description, $properties, $required, $read_only, $destructive, $idempotent ) {
        return WPCMCP_Tools::tool( $name, $title, $description, $properties, $required, $read_only, $destructive, $idempotent );
    }

    public static function execute( $name, array $args ) {
        switch ( $name ) {
            case 'wordpress.plugin_operator_list_settings': return self::list_settings( $args );
            case 'wordpress.plugin_operator_get_setting': return self::get_setting( $args );
            case 'wordpress.plugin_operator_update_setting': return self::update_setting( $args );
            case 'wordpress.plugin_operator_list_ajax_actions': return self::list_ajax_actions( $args );
            case 'wordpress.plugin_operator_call_ajax': return self::call_ajax( $args );
            case 'wordpress.plugin_operator_list_admin_pages': return self::list_admin_pages( $args );
            case 'wordpress.plugin_operator_diagnose': return self::diagnose( $args );
            default: return null;
        }
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
            foreach ( array( $file, $slug, basename( $file, '.php' ), isset( $data['Name'] ) ? $data['Name'] : '' ) as $value ) {
                $value = strtolower( (string) $value );
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

    private static function plugin_context( $needle ) {
        $plugin = self::resolve_plugin( $needle );
        if ( is_wp_error( $plugin ) ) return $plugin;

        $main = realpath( WP_PLUGIN_DIR . '/' . $plugin );
        if ( false === $main ) return new WP_Error( 'plugin_file_missing', 'Plugin main file is unavailable.' );

        $slug = dirname( $plugin );
        if ( '.' === $slug ) $slug = basename( $plugin, '.php' );

        return array(
            'plugin' => $plugin,
            'slug' => sanitize_key( $slug ),
            'root' => wp_normalize_path( dirname( $main ) ),
        );
    }

    private static function callback_file( $callback ) {
        try {
            if ( is_string( $callback ) && function_exists( $callback ) ) {
                $r = new ReflectionFunction( $callback );
                return $r->getFileName() ? wp_normalize_path( $r->getFileName() ) : '';
            }
            if ( is_array( $callback ) && 2 === count( $callback ) ) {
                $r = new ReflectionMethod( $callback[0], $callback[1] );
                return $r->getFileName() ? wp_normalize_path( $r->getFileName() ) : '';
            }
            if ( $callback instanceof Closure ) {
                $r = new ReflectionFunction( $callback );
                return $r->getFileName() ? wp_normalize_path( $r->getFileName() ) : '';
            }
        } catch ( Throwable $e ) {
            return '';
        }
        return '';
    }

    private static function callback_belongs( $callback, array $ctx ) {
        $file = self::callback_file( $callback );
        return $file && 0 === strpos( $file, trailingslashit( $ctx['root'] ) );
    }

    private static function secret_option( $name ) {
        return (bool) preg_match( '/(?:password|passwd|secret|token|api[_-]?key|private[_-]?key|client[_-]?secret|license[_-]?key|auth[_-]?key)/i', (string) $name );
    }

    private static function setting_belongs( $name, array $setting, array $ctx ) {
        if ( ! empty( $setting['sanitize_callback'] ) && self::callback_belongs( $setting['sanitize_callback'], $ctx ) ) return true;

        $normalized_slug = str_replace( '-', '_', $ctx['slug'] );
        $name_lc = strtolower( (string) $name );
        return false !== strpos( $name_lc, strtolower( $ctx['slug'] ) ) || false !== strpos( $name_lc, strtolower( $normalized_slug ) );
    }

    private static function settings_for_plugin( array $ctx ) {
        global $wp_registered_settings;
        $items = array();

        foreach ( (array) $wp_registered_settings as $name => $setting ) {
            if ( ! self::setting_belongs( $name, (array) $setting, $ctx ) ) continue;
            $items[ $name ] = array(
                'option' => $name,
                'type' => isset( $setting['type'] ) ? $setting['type'] : 'string',
                'description' => isset( $setting['description'] ) ? $setting['description'] : '',
                'default' => array_key_exists( 'default', $setting ) ? $setting['default'] : null,
                'show_in_rest' => ! empty( $setting['show_in_rest'] ),
                'secret_blocked' => self::secret_option( $name ),
            );
        }

        ksort( $items );
        return $items;
    }

    private static function list_settings( array $args ) {
        if ( ! current_user_can( 'manage_options' ) ) return new WP_Error( 'forbidden', 'Plugin settings discovery requires manage_options.' );
        $ctx = self::plugin_context( $args['plugin'] );
        if ( is_wp_error( $ctx ) ) return $ctx;
        return array( 'plugin' => $ctx['plugin'], 'settings' => array_values( self::settings_for_plugin( $ctx ) ) );
    }

    private static function resolve_setting( array $args ) {
        $ctx = self::plugin_context( $args['plugin'] );
        if ( is_wp_error( $ctx ) ) return $ctx;

        $option = sanitize_text_field( $args['option'] );
        $settings = self::settings_for_plugin( $ctx );
        if ( ! isset( $settings[ $option ] ) ) return new WP_Error( 'setting_not_discovered', 'This option is not a registered setting attributable to the target plugin.' );
        if ( self::secret_option( $option ) ) return new WP_Error( 'secret_setting_blocked', 'Secret/credential-like settings are not readable or writable through the generic operator.' );

        return array( 'context' => $ctx, 'option' => $option, 'setting' => $settings[ $option ] );
    }

    private static function get_setting( array $args ) {
        if ( ! current_user_can( 'manage_options' ) ) return new WP_Error( 'forbidden', 'Reading plugin settings requires manage_options.' );
        $resolved = self::resolve_setting( $args );
        if ( is_wp_error( $resolved ) ) return $resolved;

        return array(
            'plugin' => $resolved['context']['plugin'],
            'option' => $resolved['option'],
            'value' => get_option( $resolved['option'], $resolved['setting']['default'] ),
            'schema' => $resolved['setting'],
        );
    }

    private static function update_setting( array $args ) {
        if ( ! current_user_can( 'manage_options' ) ) return new WP_Error( 'forbidden', 'Updating plugin settings requires manage_options.' );
        if ( empty( $args['confirm'] ) ) return new WP_Error( 'confirmation_required', 'Updating a plugin setting requires confirm=true.' );

        $resolved = self::resolve_setting( $args );
        if ( is_wp_error( $resolved ) ) return $resolved;

        global $wp_registered_settings;
        $registered = isset( $wp_registered_settings[ $resolved['option'] ] ) ? $wp_registered_settings[ $resolved['option'] ] : array();
        $value = $args['value'];

        if ( ! empty( $registered['sanitize_callback'] ) && is_callable( $registered['sanitize_callback'] ) ) {
            $value = call_user_func( $registered['sanitize_callback'], $value );
        }

        $old = get_option( $resolved['option'], null );
        update_option( $resolved['option'], $value );

        return array(
            'success' => true,
            'plugin' => $resolved['context']['plugin'],
            'option' => $resolved['option'],
            'changed' => $old !== get_option( $resolved['option'], null ),
            'value' => get_option( $resolved['option'], null ),
        );
    }

    private static function plugin_ajax_actions( array $ctx ) {
        global $wp_filter;
        $items = array();

        foreach ( (array) $wp_filter as $hook => $hook_obj ) {
            if ( 0 !== strpos( $hook, 'wp_ajax_' ) || 0 === strpos( $hook, 'wp_ajax_nopriv_' ) ) continue;
            $action = substr( $hook, strlen( 'wp_ajax_' ) );
            if ( '' === $action || ! is_object( $hook_obj ) || empty( $hook_obj->callbacks ) ) continue;

            $owned = array();
            foreach ( (array) $hook_obj->callbacks as $priority => $callbacks ) {
                foreach ( (array) $callbacks as $callback_data ) {
                    if ( empty( $callback_data['function'] ) || ! self::callback_belongs( $callback_data['function'], $ctx ) ) continue;
                    $owned[] = array(
                        'priority' => (int) $priority,
                        'accepted_args' => isset( $callback_data['accepted_args'] ) ? (int) $callback_data['accepted_args'] : 1,
                    );
                }
            }

            if ( $owned ) {
                $items[ $action ] = array(
                    'action' => $action,
                    'hook' => $hook,
                    'callbacks' => $owned,
                );
            }
        }

        ksort( $items );
        return $items;
    }

    private static function list_ajax_actions( array $args ) {
        if ( ! current_user_can( 'manage_options' ) && ! current_user_can( 'activate_plugins' ) ) {
            return new WP_Error( 'forbidden', 'Admin-AJAX discovery requires plugin-administration capability.' );
        }
        $ctx = self::plugin_context( $args['plugin'] );
        if ( is_wp_error( $ctx ) ) return $ctx;
        return array( 'plugin' => $ctx['plugin'], 'actions' => array_values( self::plugin_ajax_actions( $ctx ) ) );
    }

    public static function ajax_die_handler( $message, $title = '', $args = array() ) {
        $response = is_array( $args ) && isset( $args['response'] ) ? $args['response'] : null;
        throw new WPCMCP_Ajax_Die_Exception( is_scalar( $message ) ? (string) $message : '', $response );
    }

    public static function return_ajax_die_handler() {
        return array( __CLASS__, 'ajax_die_handler' );
    }

    private static function call_ajax( array $args ) {
        if ( ! is_user_logged_in() ) return new WP_Error( 'forbidden', 'Authenticated Admin-AJAX execution requires a logged-in WordPress user.' );
        if ( empty( $args['confirm'] ) ) return new WP_Error( 'confirmation_required', 'Calling a plugin Admin-AJAX action requires confirm=true.' );

        $ctx = self::plugin_context( $args['plugin'] );
        if ( is_wp_error( $ctx ) ) return $ctx;

        $action = sanitize_key( $args['action'] );
        $actions = self::plugin_ajax_actions( $ctx );
        if ( ! isset( $actions[ $action ] ) ) return new WP_Error( 'ajax_action_not_discovered', 'Authenticated Admin-AJAX action is not attributable to the target plugin.' );

        $hook = 'wp_ajax_' . $action;
        global $wp_filter;
        $callbacks = isset( $wp_filter[ $hook ]->callbacks ) ? $wp_filter[ $hook ]->callbacks : array();

        $owned_callbacks = array();
        foreach ( (array) $callbacks as $priority => $entries ) {
            foreach ( (array) $entries as $entry ) {
                if ( empty( $entry['function'] ) || ! self::callback_belongs( $entry['function'], $ctx ) ) continue;
                $owned_callbacks[] = array(
                    'priority' => (int) $priority,
                    'function' => $entry['function'],
                    'accepted_args' => isset( $entry['accepted_args'] ) ? (int) $entry['accepted_args'] : 1,
                );
            }
        }
        if ( ! $owned_callbacks ) return new WP_Error( 'ajax_action_unavailable', 'No callable target-plugin callback was found for this Admin-AJAX action.' );

        usort( $owned_callbacks, static function ( $a, $b ) { return $a['priority'] <=> $b['priority']; } );

        $old_get = $_GET;
        $old_post = $_POST;
        $old_request = $_REQUEST;

        $params = isset( $args['params'] ) && is_array( $args['params'] ) ? $args['params'] : array();
        $request_params = array_merge( $params, array( 'action' => $action ) );
        $_POST = $request_params;
        $_GET = array();
        $_REQUEST = $request_params;

        add_filter( 'wp_die_ajax_handler', array( __CLASS__, 'return_ajax_die_handler' ), PHP_INT_MAX );

        $output = '';
        $die = null;
        $error = null;

        ob_start();
        try {
            foreach ( $owned_callbacks as $callback ) {
                call_user_func( $callback['function'] );
            }
        } catch ( WPCMCP_Ajax_Die_Exception $e ) {
            $die = array( 'message' => $e->getMessage(), 'response' => $e->response );
        } catch ( Throwable $e ) {
            $error = $e;
        }
        $output = ob_get_clean();

        remove_filter( 'wp_die_ajax_handler', array( __CLASS__, 'return_ajax_die_handler' ), PHP_INT_MAX );
        $_GET = $old_get;
        $_POST = $old_post;
        $_REQUEST = $old_request;

        if ( $error ) return new WP_Error( 'ajax_callback_failed', $error->getMessage() );

        $decoded = json_decode( trim( $output ), true );
        return array(
            'success' => true,
            'plugin' => $ctx['plugin'],
            'action' => $action,
            'output' => $output,
            'json' => JSON_ERROR_NONE === json_last_error() ? $decoded : null,
            'wp_die' => $die,
        );
    }

    private static function list_admin_pages( array $args ) {
        if ( ! current_user_can( 'manage_options' ) && ! current_user_can( 'activate_plugins' ) ) {
            return new WP_Error( 'forbidden', 'Admin-page discovery requires plugin-administration capability.' );
        }

        $ctx = self::plugin_context( $args['plugin'] );
        if ( is_wp_error( $ctx ) ) return $ctx;

        global $menu, $submenu, $_registered_pages;
        $pages = array();

        $collect = static function ( $entry, $parent = '' ) use ( &$pages, $ctx ) {
            if ( ! is_array( $entry ) || count( $entry ) < 3 ) return;
            $slug = isset( $entry[2] ) ? (string) $entry[2] : '';
            $hook = $parent ? get_plugin_page_hookname( $slug, $parent ) : get_plugin_page_hookname( $slug, '' );
            $callback = null;

            if ( isset( $GLOBALS['wp_filter'][ $hook ]->callbacks ) ) {
                foreach ( $GLOBALS['wp_filter'][ $hook ]->callbacks as $entries ) {
                    foreach ( $entries as $data ) {
                        if ( ! empty( $data['function'] ) && self::callback_belongs( $data['function'], $ctx ) ) {
                            $callback = $data['function'];
                            break 2;
                        }
                    }
                }
            }

            if ( ! $callback && false === strpos( strtolower( $slug ), strtolower( $ctx['slug'] ) ) ) return;

            $pages[] = array(
                'menu_title' => isset( $entry[0] ) ? wp_strip_all_tags( $entry[0] ) : '',
                'capability' => isset( $entry[1] ) ? $entry[1] : '',
                'menu_slug' => $slug,
                'parent_slug' => $parent,
                'hook' => $hook,
            );
        };

        foreach ( (array) $menu as $entry ) $collect( $entry, '' );
        foreach ( (array) $submenu as $parent => $entries ) {
            foreach ( (array) $entries as $entry ) $collect( $entry, (string) $parent );
        }

        return array( 'plugin' => $ctx['plugin'], 'admin_pages' => $pages );
    }

    private static function diagnose( array $args ) {
        $ctx = self::plugin_context( $args['plugin'] );
        if ( is_wp_error( $ctx ) ) return $ctx;

        $routes = class_exists( 'WPCMCP_Plugin_Operator_Tools' )
            ? WPCMCP_Plugin_Operator_Tools::execute( 'wordpress.plugin_operator_list_rest_routes', array( 'plugin' => $ctx['plugin'] ) )
            : array( 'routes' => array() );

        $settings = self::settings_for_plugin( $ctx );
        $ajax = self::plugin_ajax_actions( $ctx );
        $adapter = class_exists( 'WPCMCP_Adapter_Registry' )
            ? WPCMCP_Adapter_Registry::execute( 'wordpress.adapter_registry_detect', array( 'plugin' => $ctx['plugin'] ) )
            : null;

        $interfaces = array(
            'dedicated_adapter' => ! is_wp_error( $adapter ) && ! empty( $adapter['dedicated_adapter'] ) ? $adapter['dedicated_adapter'] : null,
            'rest_route_count' => is_array( $routes ) && isset( $routes['routes'] ) ? count( $routes['routes'] ) : 0,
            'registered_setting_count' => count( $settings ),
            'authenticated_ajax_action_count' => count( $ajax ),
        );

        $priority = array();
        if ( ! empty( $interfaces['dedicated_adapter']['available'] ) ) $priority[] = 'dedicated_adapter';
        if ( $interfaces['rest_route_count'] ) $priority[] = 'plugin_rest_api';
        if ( $interfaces['registered_setting_count'] ) $priority[] = 'registered_settings';
        if ( $interfaces['authenticated_ajax_action_count'] ) $priority[] = 'authenticated_admin_ajax';
        $priority[] = 'shortcodes_or_registered_content_models';
        $priority[] = 'safe_source_inspection_then_new_adapter';

        return array(
            'plugin' => $ctx['plugin'],
            'active' => is_plugin_active( $ctx['plugin'] ),
            'intent' => sanitize_textarea_field( $args['intent'] ),
            'interfaces' => $interfaces,
            'recommended_priority' => $priority,
            'notes' => array(
                'Generic credential/secret settings are blocked.',
                'Mutating REST, settings and Admin-AJAX operations require explicit confirmation.',
                'Target plugin capability and nonce checks are not bypassed.',
            ),
        );
    }
}
