<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class WPCMCP_Plugin_Operator_Tools {
    const MAX_SOURCE_BYTES = 1048576;

    public static function definitions( $can_read, $can_write ) {
        $tools = array();

        if ( $can_read ) {
            $tools[] = self::t(
                'wordpress.plugin_operator_discover',
                'Discover Plugin Capabilities',
                'Inspect an installed plugin: activation state, metadata, plugin-owned REST routes, shortcodes and readable source files. Use this before operating an unfamiliar plugin.',
                array(
                    'plugin' => array( 'type' => 'string', 'description' => 'Plugin file, slug or name fragment.' ),
                ),
                array( 'plugin' ),
                true, false, true
            );
            $tools[] = self::t(
                'wordpress.plugin_operator_list_rest_routes',
                'List Plugin REST Routes',
                'List non-core REST routes, optionally filtered by namespace prefix or by plugin ownership when a plugin is supplied.',
                array(
                    'plugin' => array( 'type' => 'string' ),
                    'namespace_prefix' => array( 'type' => 'string' ),
                ),
                array(),
                true, false, true
            );
            $tools[] = self::t(
                'wordpress.plugin_operator_list_shortcodes',
                'List Plugin Shortcodes',
                'List registered shortcodes, optionally limited to callbacks whose source file belongs to one installed plugin.',
                array(
                    'plugin' => array( 'type' => 'string' ),
                ),
                array(),
                true, false, true
            );
            $tools[] = self::t(
                'wordpress.plugin_operator_list_source_files',
                'List Plugin Source Files',
                'List safe text/source files from one installed plugin. Requires plugin-administration capability.',
                array(
                    'plugin' => array( 'type' => 'string' ),
                ),
                array( 'plugin' ),
                true, false, true
            );
            $tools[] = self::t(
                'wordpress.plugin_operator_read_source_file',
                'Read Plugin Source File',
                'Read one safe installed-plugin source file for API/schema discovery. Absolute server paths are never returned.',
                array(
                    'plugin' => array( 'type' => 'string' ),
                    'path' => array( 'type' => 'string' ),
                ),
                array( 'plugin', 'path' ),
                true, false, true
            );
        }

        if ( $can_write ) {
            $tools[] = self::t(
                'wordpress.plugin_operator_call_rest',
                'Call Plugin REST Route',
                'Invoke a registered non-core WordPress REST route in-process as the authenticated WordPress user. Native endpoint permission callbacks still run. Mutating methods require confirm=true.',
                array(
                    'route' => array( 'type' => 'string', 'description' => 'Registered route such as /my-plugin/v1/items.' ),
                    'method' => array( 'type' => 'string', 'enum' => array( 'GET', 'POST', 'PUT', 'PATCH', 'DELETE' ) ),
                    'params' => array( 'type' => 'object', 'additionalProperties' => true ),
                    'confirm' => array( 'type' => 'boolean' ),
                ),
                array( 'route', 'method' ),
                false, true, false
            );
        }

        return $tools;
    }

    private static function t( $name, $title, $description, $properties, $required, $read_only, $destructive, $idempotent ) {
        return WPCMCP_Tools::tool( $name, $title, $description, $properties, $required, $read_only, $destructive, $idempotent );
    }

    public static function execute( $name, array $args ) {
        switch ( $name ) {
            case 'wordpress.plugin_operator_discover': return self::discover( $args );
            case 'wordpress.plugin_operator_list_rest_routes': return self::list_rest_routes( $args );
            case 'wordpress.plugin_operator_list_shortcodes': return self::list_shortcodes( $args );
            case 'wordpress.plugin_operator_list_source_files': return self::list_source_files( $args );
            case 'wordpress.plugin_operator_read_source_file': return self::read_source_file( $args );
            case 'wordpress.plugin_operator_call_rest': return self::call_rest( $args );
            default: return null;
        }
    }

    private static function load_plugin_admin() {
        if ( ! function_exists( 'get_plugins' ) ) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }
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
            $haystacks = array(
                strtolower( $file ),
                strtolower( $slug ),
                strtolower( basename( $file, '.php' ) ),
                strtolower( isset( $data['Name'] ) ? $data['Name'] : '' ),
            );
            foreach ( $haystacks as $haystack ) {
                if ( $haystack === $needle || false !== strpos( $haystack, $needle ) ) {
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

    private static function plugin_root( $plugin_file ) {
        $main = realpath( WP_PLUGIN_DIR . '/' . $plugin_file );
        if ( false === $main ) return new WP_Error( 'plugin_file_missing', 'Plugin main file is unavailable.' );
        $dir = wp_normalize_path( dirname( $main ) );
        return array(
            'main' => wp_normalize_path( $main ),
            'root' => $dir,
        );
    }

    private static function callback_file( $callback ) {
        try {
            if ( is_string( $callback ) && function_exists( $callback ) ) {
                $ref = new ReflectionFunction( $callback );
                return $ref->getFileName() ? wp_normalize_path( $ref->getFileName() ) : '';
            }
            if ( is_array( $callback ) && 2 === count( $callback ) ) {
                $ref = new ReflectionMethod( $callback[0], $callback[1] );
                return $ref->getFileName() ? wp_normalize_path( $ref->getFileName() ) : '';
            }
            if ( $callback instanceof Closure ) {
                $ref = new ReflectionFunction( $callback );
                return $ref->getFileName() ? wp_normalize_path( $ref->getFileName() ) : '';
            }
        } catch ( Throwable $e ) {
            return '';
        }
        return '';
    }

    private static function callback_belongs_to_plugin( $callback, $plugin_file ) {
        $root = self::plugin_root( $plugin_file );
        if ( is_wp_error( $root ) ) return false;
        $source = self::callback_file( $callback );
        return $source && 0 === strpos( $source, trailingslashit( $root['root'] ) );
    }

    private static function route_belongs_to_plugin( array $endpoints, $plugin_file ) {
        foreach ( $endpoints as $endpoint ) {
            if ( ! is_array( $endpoint ) || empty( $endpoint['callback'] ) ) continue;
            if ( self::callback_belongs_to_plugin( $endpoint['callback'], $plugin_file ) ) return true;
        }
        return false;
    }

    private static function route_methods( array $endpoints ) {
        $methods = array();
        foreach ( $endpoints as $endpoint ) {
            if ( ! is_array( $endpoint ) || empty( $endpoint['methods'] ) ) continue;
            foreach ( (array) $endpoint['methods'] as $method => $enabled ) {
                if ( $enabled ) $methods[] = strtoupper( $method );
            }
        }
        return array_values( array_unique( $methods ) );
    }

    private static function plugin_route_records( $plugin_file = '', $namespace_prefix = '' ) {
        $routes = rest_get_server()->get_routes();
        $items = array();
        foreach ( $routes as $route => $endpoints ) {
            if ( 0 === strpos( $route, '/wp/' ) || 0 === strpos( $route, '/wp-site-health/' ) || 0 === strpos( $route, '/wp-block-editor/' ) || 0 === strpos( $route, '/wp-chatgpt-mcp/' ) ) {
                continue;
            }
            if ( $namespace_prefix && 0 !== strpos( ltrim( $route, '/' ), ltrim( $namespace_prefix, '/' ) ) ) continue;
            if ( $plugin_file && ! self::route_belongs_to_plugin( (array) $endpoints, $plugin_file ) ) continue;
            $items[] = array(
                'route' => $route,
                'methods' => self::route_methods( (array) $endpoints ),
            );
        }
        return $items;
    }

    private static function shortcode_records( $plugin_file = '' ) {
        global $shortcode_tags;
        $items = array();
        foreach ( (array) $shortcode_tags as $tag => $callback ) {
            if ( $plugin_file && ! self::callback_belongs_to_plugin( $callback, $plugin_file ) ) continue;
            $items[] = array(
                'tag' => $tag,
                'callback_type' => is_string( $callback ) ? 'function' : ( is_array( $callback ) ? 'method' : 'callable' ),
            );
        }
        usort( $items, static function ( $a, $b ) { return strcmp( $a['tag'], $b['tag'] ); } );
        return $items;
    }

    private static function discover( array $args ) {
        if ( ! current_user_can( 'activate_plugins' ) && ! current_user_can( 'manage_options' ) ) {
            return new WP_Error( 'forbidden', 'Plugin discovery requires plugin-administration capability.' );
        }
        $plugin = self::resolve_plugin( $args['plugin'] );
        if ( is_wp_error( $plugin ) ) return $plugin;
        self::load_plugin_admin();
        $data = get_plugins();
        $info = isset( $data[ $plugin ] ) ? $data[ $plugin ] : array();
        $slug = dirname( $plugin );
        if ( '.' === $slug ) $slug = basename( $plugin, '.php' );

        return array(
            'plugin' => $plugin,
            'slug' => $slug,
            'name' => isset( $info['Name'] ) ? $info['Name'] : $plugin,
            'version' => isset( $info['Version'] ) ? $info['Version'] : '',
            'active' => is_plugin_active( $plugin ),
            'network_active' => is_multisite() ? is_plugin_active_for_network( $plugin ) : false,
            'rest_routes' => self::plugin_route_records( $plugin ),
            'shortcodes' => self::shortcode_records( $plugin ),
        );
    }

    private static function list_rest_routes( array $args ) {
        $plugin = '';
        if ( ! empty( $args['plugin'] ) ) {
            $plugin = self::resolve_plugin( $args['plugin'] );
            if ( is_wp_error( $plugin ) ) return $plugin;
        }
        return array(
            'routes' => self::plugin_route_records(
                $plugin,
                isset( $args['namespace_prefix'] ) ? sanitize_text_field( $args['namespace_prefix'] ) : ''
            ),
        );
    }

    private static function list_shortcodes( array $args ) {
        $plugin = '';
        if ( ! empty( $args['plugin'] ) ) {
            $plugin = self::resolve_plugin( $args['plugin'] );
            if ( is_wp_error( $plugin ) ) return $plugin;
        }
        return array( 'shortcodes' => self::shortcode_records( $plugin ) );
    }

    private static function allowed_source_extension( $path ) {
        return in_array( strtolower( pathinfo( $path, PATHINFO_EXTENSION ) ), array( 'php', 'js', 'css', 'json', 'txt', 'md', 'xml', 'yml', 'yaml' ), true );
    }

    private static function list_source_files( array $args ) {
        if ( ! current_user_can( 'activate_plugins' ) && ! current_user_can( 'manage_options' ) ) {
            return new WP_Error( 'forbidden', 'Plugin source inspection requires plugin-administration capability.' );
        }
        $plugin = self::resolve_plugin( $args['plugin'] );
        if ( is_wp_error( $plugin ) ) return $plugin;
        $root = self::plugin_root( $plugin );
        if ( is_wp_error( $root ) ) return $root;

        $items = array();
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator( $root['root'], FilesystemIterator::SKIP_DOTS )
        );
        foreach ( $iterator as $file ) {
            if ( ! $file->isFile() ) continue;
            $absolute = wp_normalize_path( $file->getPathname() );
            if ( false !== strpos( $absolute, '/vendor/' ) || false !== strpos( $absolute, '/node_modules/' ) || false !== strpos( $absolute, '/.git/' ) ) continue;
            $relative = ltrim( substr( $absolute, strlen( $root['root'] ) ), '/' );
            if ( ! self::allowed_source_extension( $relative ) ) continue;
            $items[] = array( 'path' => $relative, 'size_bytes' => (int) $file->getSize() );
            if ( count( $items ) >= 1000 ) break;
        }
        usort( $items, static function ( $a, $b ) { return strcmp( $a['path'], $b['path'] ); } );
        return array( 'plugin' => $plugin, 'files' => $items, 'truncated' => count( $items ) >= 1000 );
    }

    private static function read_source_file( array $args ) {
        if ( ! current_user_can( 'activate_plugins' ) && ! current_user_can( 'manage_options' ) ) {
            return new WP_Error( 'forbidden', 'Plugin source inspection requires plugin-administration capability.' );
        }
        $plugin = self::resolve_plugin( $args['plugin'] );
        if ( is_wp_error( $plugin ) ) return $plugin;
        $root = self::plugin_root( $plugin );
        if ( is_wp_error( $root ) ) return $root;

        $path = trim( wp_normalize_path( (string) $args['path'] ) );
        if ( '' === $path || '/' === substr( $path, 0, 1 ) || 0 !== validate_file( $path ) || ! self::allowed_source_extension( $path ) ) {
            return new WP_Error( 'invalid_path', 'A safe relative plugin source path is required.' );
        }

        $absolute = realpath( $root['root'] . '/' . $path );
        if ( false === $absolute || ! is_file( $absolute ) ) return new WP_Error( 'not_found', 'Plugin source file not found.' );
        $absolute = wp_normalize_path( $absolute );
        if ( 0 !== strpos( $absolute, trailingslashit( $root['root'] ) ) ) return new WP_Error( 'path_escape', 'Plugin source path resolves outside the plugin directory.' );
        if ( filesize( $absolute ) > self::MAX_SOURCE_BYTES ) return new WP_Error( 'file_too_large', 'Plugin source files larger than 1 MB are not returned.' );

        $content = file_get_contents( $absolute );
        if ( false === $content ) return new WP_Error( 'read_failed', 'Plugin source file could not be read.' );
        return array(
            'plugin' => $plugin,
            'path' => $path,
            'content' => $content,
            'sha256' => hash( 'sha256', $content ),
            'size_bytes' => strlen( $content ),
        );
    }

    private static function call_rest( array $args ) {
        $route = '/' . ltrim( sanitize_text_field( $args['route'] ), '/' );
        $method = strtoupper( sanitize_key( $args['method'] ) );
        if ( ! in_array( $method, array( 'GET', 'POST', 'PUT', 'PATCH', 'DELETE' ), true ) ) {
            return new WP_Error( 'invalid_method', 'Unsupported REST method.' );
        }

        if ( 0 === strpos( $route, '/wp/' ) || 0 === strpos( $route, '/wp-site-health/' ) || 0 === strpos( $route, '/wp-block-editor/' ) || 0 === strpos( $route, '/wp-chatgpt-mcp/' ) ) {
            return new WP_Error( 'core_route_blocked', 'Generic Plugin Operator does not call WordPress core or its own MCP routes. Use dedicated MCP tools instead.' );
        }

        $routes = rest_get_server()->get_routes();
        if ( ! isset( $routes[ $route ] ) ) return new WP_Error( 'route_not_found', 'Requested REST route is not registered.' );

        if ( 'GET' !== $method && empty( $args['confirm'] ) ) {
            return new WP_Error( 'confirmation_required', 'Mutating plugin REST calls require confirm=true.' );
        }

        $request = new WP_REST_Request( $method, $route );
        $params = isset( $args['params'] ) && is_array( $args['params'] ) ? $args['params'] : array();
        if ( 'GET' === $method || 'DELETE' === $method ) {
            $request->set_query_params( $params );
        } else {
            $request->set_body_params( $params );
        }

        $response = rest_do_request( $request );
        if ( is_wp_error( $response ) ) return $response;

        return array(
            'status' => $response->get_status(),
            'headers' => $response->get_headers(),
            'data' => $response->get_data(),
        );
    }
}
