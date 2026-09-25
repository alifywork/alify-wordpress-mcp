<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class WPCMCP_Extension_Manager_Tools {
    public static function definitions( $can_read, $can_write ) {
        if ( ! $can_write ) return array();
        return array(
            self::t( 'wordpress.install_plugin', 'Install WordPress.org Plugin', 'Install a plugin by its exact WordPress.org slug. Arbitrary ZIP URLs are not accepted.', array( 'slug' => array( 'type' => 'string' ) ), array( 'slug' ), false, false, false, true ),
            self::t( 'wordpress.activate_plugin', 'Activate Plugin', 'Activate an installed plugin by plugin file or unambiguous slug.', array( 'plugin' => array( 'type' => 'string' ) ), array( 'plugin' ), false, false, true ),
            self::t( 'wordpress.deactivate_plugin', 'Deactivate Plugin', 'Deactivate an installed plugin. This MCP plugin cannot deactivate itself.', array( 'plugin' => array( 'type' => 'string' ) ), array( 'plugin' ), false, true, true ),
            self::t( 'wordpress.update_plugin', 'Update Plugin', 'Update an installed plugin using WordPress core update data.', array( 'plugin' => array( 'type' => 'string' ) ), array( 'plugin' ), false, true, false, true ),
            self::t( 'wordpress.delete_plugin', 'Delete Plugin', 'Permanently delete an inactive plugin. Requires confirm=true.', array( 'plugin' => array( 'type' => 'string' ), 'confirm' => array( 'type' => 'boolean', 'description' => 'Must be true.' ) ), array( 'plugin', 'confirm' ), false, true, true ),
            self::t( 'wordpress.install_theme', 'Install WordPress.org Theme', 'Install a theme by its exact WordPress.org slug. Arbitrary ZIP URLs are not accepted.', array( 'slug' => array( 'type' => 'string' ) ), array( 'slug' ), false, false, false, true ),
            self::t( 'wordpress.activate_theme', 'Activate Theme', 'Activate an installed theme by stylesheet slug.', array( 'stylesheet' => array( 'type' => 'string' ) ), array( 'stylesheet' ), false, true, true ),
            self::t( 'wordpress.update_theme', 'Update Theme', 'Update an installed theme using WordPress core update data.', array( 'stylesheet' => array( 'type' => 'string' ) ), array( 'stylesheet' ), false, true, false, true ),
            self::t( 'wordpress.delete_theme', 'Delete Theme', 'Permanently delete an inactive theme. Active theme and its parent cannot be deleted. Requires confirm=true.', array( 'stylesheet' => array( 'type' => 'string' ), 'confirm' => array( 'type' => 'boolean', 'description' => 'Must be true.' ) ), array( 'stylesheet', 'confirm' ), false, true, true ),
        );
    }

    private static function t( $name, $title, $description, $properties, $required, $read_only, $destructive, $idempotent, $open_world = false ) {
        return WPCMCP_Tools::tool( $name, $title, $description, $properties, $required, $read_only, $destructive, $idempotent, $open_world );
    }

    public static function execute( $name, array $args ) {
        switch ( $name ) {
            case 'wordpress.install_plugin': return self::install_plugin( $args );
            case 'wordpress.activate_plugin': return self::activate_plugin_tool( $args );
            case 'wordpress.deactivate_plugin': return self::deactivate_plugin_tool( $args );
            case 'wordpress.update_plugin': return self::update_plugin( $args );
            case 'wordpress.delete_plugin': return self::delete_plugin( $args );
            case 'wordpress.install_theme': return self::install_theme( $args );
            case 'wordpress.activate_theme': return self::activate_theme( $args );
            case 'wordpress.update_theme': return self::update_theme( $args );
            case 'wordpress.delete_theme': return self::delete_theme_tool( $args );
            default: return null;
        }
    }

    private static function load_plugin_admin() {
        require_once ABSPATH . 'wp-admin/includes/plugin.php';
        require_once ABSPATH . 'wp-admin/includes/plugin-install.php';
        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
    }

    private static function load_theme_admin() {
        require_once ABSPATH . 'wp-admin/includes/theme.php';
        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
    }

    private static function resolve_plugin( $needle ) {
        self::load_plugin_admin();
        $needle = strtolower( sanitize_text_field( $needle ) );
        if ( '' === $needle ) return new WP_Error( 'invalid_plugin', 'Plugin file or slug is required.' );
        $plugins = get_plugins();
        if ( isset( $plugins[ $needle ] ) ) return $needle;
        $matches = array();
        foreach ( $plugins as $file => $data ) {
            $slug = dirname( $file );
            if ( '.' === $slug ) $slug = basename( $file, '.php' );
            if ( strtolower( $file ) === $needle || strtolower( $slug ) === $needle || strtolower( basename( $file, '.php' ) ) === $needle ) $matches[] = $file;
        }
        $matches = array_values( array_unique( $matches ) );
        if ( 1 === count( $matches ) ) return $matches[0];
        if ( 1 < count( $matches ) ) return new WP_Error( 'ambiguous_plugin', 'Plugin identifier matched more than one installed plugin; use the exact plugin file.' );
        return new WP_Error( 'not_found', 'Installed plugin not found.' );
    }

    private static function official_package( $url ) {
        $url = esc_url_raw( $url, array( 'https' ) );
        $host = strtolower( (string) wp_parse_url( $url, PHP_URL_HOST ) );
        if ( ! $url || 'https' !== strtolower( (string) wp_parse_url( $url, PHP_URL_SCHEME ) ) || ( 'wordpress.org' !== $host && '.wordpress.org' !== substr( $host, -14 ) ) ) {
            return new WP_Error( 'untrusted_package', 'WordPress.org did not return a trusted HTTPS package URL.' );
        }
        return $url;
    }

    private static function upgrader_result( $result, $operation, $identifier ) {
        if ( is_wp_error( $result ) ) return $result;
        if ( ! $result ) return new WP_Error( 'upgrade_failed', 'WordPress could not complete the ' . $operation . ' operation.' );
        return array( 'success' => true, 'operation' => $operation, 'identifier' => $identifier );
    }

    private static function install_plugin( array $args ) {
        if ( ! current_user_can( 'install_plugins' ) ) return new WP_Error( 'forbidden', 'You cannot install plugins.' );
        self::load_plugin_admin();
        $slug = isset( $args['slug'] ) ? sanitize_key( $args['slug'] ) : '';
        if ( '' === $slug ) return new WP_Error( 'invalid_slug', 'Exact WordPress.org plugin slug is required.' );
        $api = plugins_api( 'plugin_information', array( 'slug' => $slug, 'fields' => array( 'sections' => false ) ) );
        if ( is_wp_error( $api ) || empty( $api->download_link ) ) return is_wp_error( $api ) ? $api : new WP_Error( 'not_found', 'WordPress.org plugin package not found.' );
        $package = self::official_package( $api->download_link );
        if ( is_wp_error( $package ) ) return $package;
        $upgrader = new Plugin_Upgrader( new Automatic_Upgrader_Skin() );
        $result = $upgrader->install( $package );
        if ( is_wp_error( $result ) || ! $result ) return self::upgrader_result( $result, 'install_plugin', $slug );
        return array( 'success' => true, 'operation' => 'install_plugin', 'slug' => $slug, 'plugin' => $upgrader->plugin_info(), 'active' => false );
    }

    private static function activate_plugin_tool( array $args ) {
        if ( ! current_user_can( 'activate_plugins' ) ) return new WP_Error( 'forbidden', 'You cannot activate plugins.' );
        $plugin = self::resolve_plugin( isset( $args['plugin'] ) ? $args['plugin'] : '' );
        if ( is_wp_error( $plugin ) ) return $plugin;
        if ( is_plugin_active( $plugin ) ) return array( 'success' => true, 'plugin' => $plugin, 'active' => true, 'changed' => false );
        $result = activate_plugin( $plugin, '', false, true );
        if ( is_wp_error( $result ) ) return $result;
        return array( 'success' => true, 'plugin' => $plugin, 'active' => true, 'changed' => true );
    }

    private static function deactivate_plugin_tool( array $args ) {
        if ( ! current_user_can( 'activate_plugins' ) ) return new WP_Error( 'forbidden', 'You cannot deactivate plugins.' );
        $plugin = self::resolve_plugin( isset( $args['plugin'] ) ? $args['plugin'] : '' );
        if ( is_wp_error( $plugin ) ) return $plugin;
        if ( plugin_basename( WPCMCP_FILE ) === $plugin ) return new WP_Error( 'self_deactivation_blocked', 'The MCP plugin cannot deactivate itself through MCP.' );
        if ( is_multisite() && is_plugin_active_for_network( $plugin ) ) return new WP_Error( 'network_plugin', 'Network-active plugins must be managed by a network administrator outside this site-scoped MCP connection.' );
        if ( ! is_plugin_active( $plugin ) ) return array( 'success' => true, 'plugin' => $plugin, 'active' => false, 'changed' => false );
        deactivate_plugins( $plugin, true, false );
        return array( 'success' => true, 'plugin' => $plugin, 'active' => is_plugin_active( $plugin ), 'changed' => true );
    }

    private static function update_plugin( array $args ) {
        if ( ! current_user_can( 'update_plugins' ) ) return new WP_Error( 'forbidden', 'You cannot update plugins.' );
        $plugin = self::resolve_plugin( isset( $args['plugin'] ) ? $args['plugin'] : '' );
        if ( is_wp_error( $plugin ) ) return $plugin;
        if ( plugin_basename( WPCMCP_FILE ) === $plugin ) return new WP_Error( 'self_update_blocked', 'The MCP plugin cannot update itself through MCP.' );
        wp_update_plugins();
        $updates = get_site_transient( 'update_plugins' );
        if ( empty( $updates->response[ $plugin ] ) ) return array( 'success' => true, 'plugin' => $plugin, 'changed' => false, 'message' => 'No update is currently available.' );
        self::load_plugin_admin();
        $upgrader = new Plugin_Upgrader( new Automatic_Upgrader_Skin() );
        $result = $upgrader->upgrade( $plugin );
        $response = self::upgrader_result( $result, 'update_plugin', $plugin );
        if ( is_wp_error( $response ) ) return $response;
        $response['changed'] = true;
        return $response;
    }

    private static function delete_plugin( array $args ) {
        if ( ! current_user_can( 'delete_plugins' ) ) return new WP_Error( 'forbidden', 'You cannot delete plugins.' );
        if ( ! isset( $args['confirm'] ) || true !== $args['confirm'] ) return new WP_Error( 'confirmation_required', 'Plugin deletion requires confirm=true.' );
        $plugin = self::resolve_plugin( isset( $args['plugin'] ) ? $args['plugin'] : '' );
        if ( is_wp_error( $plugin ) ) return $plugin;
        if ( plugin_basename( WPCMCP_FILE ) === $plugin ) return new WP_Error( 'self_delete_blocked', 'The MCP plugin cannot delete itself through MCP.' );
        if ( is_plugin_active( $plugin ) || ( is_multisite() && is_plugin_active_for_network( $plugin ) ) ) return new WP_Error( 'plugin_active', 'Deactivate the plugin before deleting it.' );
        $result = delete_plugins( array( $plugin ) );
        if ( is_wp_error( $result ) || ! $result ) return is_wp_error( $result ) ? $result : new WP_Error( 'delete_failed', 'WordPress could not delete the plugin.' );
        return array( 'success' => true, 'plugin' => $plugin, 'deleted_permanently' => true );
    }

    private static function install_theme( array $args ) {
        if ( ! current_user_can( 'install_themes' ) ) return new WP_Error( 'forbidden', 'You cannot install themes.' );
        self::load_theme_admin();
        $slug = isset( $args['slug'] ) ? sanitize_key( $args['slug'] ) : '';
        if ( '' === $slug ) return new WP_Error( 'invalid_slug', 'Exact WordPress.org theme slug is required.' );
        $api = themes_api( 'theme_information', array( 'slug' => $slug, 'fields' => array( 'sections' => false ) ) );
        if ( is_wp_error( $api ) || empty( $api->download_link ) ) return is_wp_error( $api ) ? $api : new WP_Error( 'not_found', 'WordPress.org theme package not found.' );
        $package = self::official_package( $api->download_link );
        if ( is_wp_error( $package ) ) return $package;
        $upgrader = new Theme_Upgrader( new Automatic_Upgrader_Skin() );
        $result = $upgrader->install( $package );
        if ( is_wp_error( $result ) || ! $result ) return self::upgrader_result( $result, 'install_theme', $slug );
        $theme = $upgrader->theme_info();
        return array( 'success' => true, 'operation' => 'install_theme', 'stylesheet' => $theme ? $theme->get_stylesheet() : $slug, 'active' => false );
    }

    private static function activate_theme( array $args ) {
        if ( ! current_user_can( 'switch_themes' ) ) return new WP_Error( 'forbidden', 'You cannot activate themes.' );
        $stylesheet = isset( $args['stylesheet'] ) ? sanitize_key( $args['stylesheet'] ) : '';
        $theme = wp_get_theme( $stylesheet );
        if ( ! $theme->exists() || $theme->errors() ) return new WP_Error( 'invalid_theme', 'Installed theme not found or contains errors.' );
        $current = wp_get_theme();
        if ( $current->get_stylesheet() === $stylesheet ) return array( 'success' => true, 'stylesheet' => $stylesheet, 'active' => true, 'changed' => false );
        switch_theme( $stylesheet );
        return array( 'success' => true, 'stylesheet' => $stylesheet, 'active' => wp_get_theme()->get_stylesheet() === $stylesheet, 'changed' => true );
    }

    private static function update_theme( array $args ) {
        if ( ! current_user_can( 'update_themes' ) ) return new WP_Error( 'forbidden', 'You cannot update themes.' );
        $stylesheet = isset( $args['stylesheet'] ) ? sanitize_key( $args['stylesheet'] ) : '';
        if ( ! wp_get_theme( $stylesheet )->exists() ) return new WP_Error( 'not_found', 'Installed theme not found.' );
        wp_update_themes();
        $updates = get_site_transient( 'update_themes' );
        if ( empty( $updates->response[ $stylesheet ] ) ) return array( 'success' => true, 'stylesheet' => $stylesheet, 'changed' => false, 'message' => 'No update is currently available.' );
        self::load_theme_admin();
        $upgrader = new Theme_Upgrader( new Automatic_Upgrader_Skin() );
        $result = $upgrader->upgrade( $stylesheet );
        $response = self::upgrader_result( $result, 'update_theme', $stylesheet );
        if ( is_wp_error( $response ) ) return $response;
        $response['changed'] = true;
        return $response;
    }

    private static function delete_theme_tool( array $args ) {
        if ( ! current_user_can( 'delete_themes' ) ) return new WP_Error( 'forbidden', 'You cannot delete themes.' );
        if ( ! isset( $args['confirm'] ) || true !== $args['confirm'] ) return new WP_Error( 'confirmation_required', 'Theme deletion requires confirm=true.' );
        $stylesheet = isset( $args['stylesheet'] ) ? sanitize_key( $args['stylesheet'] ) : '';
        $theme = wp_get_theme( $stylesheet );
        if ( ! $theme->exists() ) return new WP_Error( 'not_found', 'Installed theme not found.' );
        $current = wp_get_theme();
        if ( $stylesheet === $current->get_stylesheet() || $stylesheet === $current->get_template() ) return new WP_Error( 'active_theme', 'The active theme or its parent cannot be deleted.' );
        self::load_theme_admin();
        $result = delete_theme( $stylesheet );
        if ( is_wp_error( $result ) || ! $result ) return is_wp_error( $result ) ? $result : new WP_Error( 'delete_failed', 'WordPress could not delete the theme.' );
        return array( 'success' => true, 'stylesheet' => $stylesheet, 'deleted_permanently' => true );
    }
}
