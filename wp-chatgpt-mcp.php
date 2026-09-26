<?php
/**
 * Plugin Name: WP ChatGPT MCP
 * Plugin URI: https://alify.site/
 * Description: Connect ChatGPT directly to WordPress through a remote Model Context Protocol (MCP) server with OAuth 2.1-style authorization. No OpenAI API key required.
 * Version: 2.5.1
 * Requires at least: 6.4
 * Requires PHP: 7.4
 * Author: ALIFY
 * Author URI: https://alify.site/
 * License: GPL-2.0-or-later
 * Text Domain: wp-chatgpt-mcp
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'WPCMCP_VERSION', '2.5.1' );
define( 'WPCMCP_DB_VERSION', '2.5.1' );
define( 'WPCMCP_FILE', __FILE__ );
define( 'WPCMCP_DIR', plugin_dir_path( __FILE__ ) );
define( 'WPCMCP_URL', plugin_dir_url( __FILE__ ) );

require_once WPCMCP_DIR . 'includes/class-wpcmcp-db.php';
require_once WPCMCP_DIR . 'includes/class-wpcmcp-oauth.php';
require_once WPCMCP_DIR . 'includes/class-wpcmcp-tools.php';
require_once WPCMCP_DIR . 'includes/tools/class-wpcmcp-content-extras-tools.php';
require_once WPCMCP_DIR . 'includes/tools/class-wpcmcp-community-tools.php';
require_once WPCMCP_DIR . 'includes/tools/class-wpcmcp-admin-tools.php';
require_once WPCMCP_DIR . 'includes/tools/class-wpcmcp-extension-manager-tools.php';
require_once WPCMCP_DIR . 'includes/tools/class-wpcmcp-theme-source-tools.php';
require_once WPCMCP_DIR . 'includes/tools/class-wpcmcp-woocommerce-tools.php';
require_once WPCMCP_DIR . 'includes/tools/class-wpcmcp-structure-tools.php';
require_once WPCMCP_DIR . 'includes/tools/class-wpcmcp-acf-pro-tools.php';
require_once WPCMCP_DIR . 'includes/tools/class-wpcmcp-elementor-tools.php';
require_once WPCMCP_DIR . 'includes/tools/class-wpcmcp-wpbakery-tools.php';
require_once WPCMCP_DIR . 'includes/tools/class-wpcmcp-divi-tools.php';
require_once WPCMCP_DIR . 'includes/tools/class-wpcmcp-muffin-tools.php';
require_once WPCMCP_DIR . 'includes/tools/class-wpcmcp-theme-developer-tools.php';
require_once WPCMCP_DIR . 'includes/tools/class-wpcmcp-plugin-operator-tools.php';
require_once WPCMCP_DIR . 'includes/tools/class-wpcmcp-cf7-tools.php';
require_once WPCMCP_DIR . 'includes/class-wpcmcp-adapter-registry.php';
require_once WPCMCP_DIR . 'includes/tools/class-wpcmcp-universal-operator-tools.php';
require_once WPCMCP_DIR . 'includes/tools/class-wpcmcp-transaction-tools.php';
require_once WPCMCP_DIR . 'includes/tools/class-wpcmcp-ops-tools.php';
require_once WPCMCP_DIR . 'includes/tools/class-wpcmcp-permission-profile-tools.php';
require_once WPCMCP_DIR . 'includes/tools/class-wpcmcp-database-diagnostics-tools.php';
require_once WPCMCP_DIR . 'includes/tools/class-wpcmcp-workflow-tools.php';
require_once WPCMCP_DIR . 'includes/tools/class-wpcmcp-secret-vault-tools.php';
require_once WPCMCP_DIR . 'includes/tools/class-wpcmcp-adapter-manifest-tools.php';
require_once WPCMCP_DIR . 'includes/class-wpcmcp-server.php';
require_once WPCMCP_DIR . 'admin/class-wpcmcp-admin.php';

final class WPCMCP_Plugin {
    private static $instance = null;

    public static function instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        WPCMCP_DB::instance();
        WPCMCP_DB::maybe_upgrade();
        WPCMCP_DB::schedule_maintenance();
        WPCMCP_Structure_Tools::bootstrap();
        WPCMCP_ACF_Pro_Tools::bootstrap();
        WPCMCP_Ops_Tools::bootstrap();
        WPCMCP_Permission_Profile_Tools::bootstrap();
        WPCMCP_Adapter_Manifest_Tools::bootstrap();
        WPCMCP_OAuth::instance();
        add_action( 'init', array( __CLASS__, 'maybe_refresh_rewrites' ), 99 );
        WPCMCP_Server::instance();

        if ( is_admin() ) {
            WPCMCP_Admin::instance();
        }
    }

    public static function maybe_refresh_rewrites() {
        $rewrite_version = (string) get_option( 'wpcmcp_rewrite_version', '' );
        if ( WPCMCP_VERSION === $rewrite_version ) {
            return;
        }

        WPCMCP_OAuth::register_rewrites();
        flush_rewrite_rules( false );
        update_option( 'wpcmcp_rewrite_version', WPCMCP_VERSION, false );
    }

    public static function activate() {
        WPCMCP_DB::install();
        WPCMCP_DB::schedule_maintenance();
        WPCMCP_OAuth::register_rewrites();
        flush_rewrite_rules();
        update_option( 'wpcmcp_rewrite_version', WPCMCP_VERSION, false );

        if ( false === get_option( 'wpcmcp_settings', false ) ) {
            add_option(
                'wpcmcp_settings',
                array(
                    'read_tools'  => 1,
                    'write_tools' => 1,
                    'log_days'    => 30,
                ),
                '',
                false
            );
        }
    }

    public static function deactivate() {
        WPCMCP_DB::unschedule_maintenance();
        flush_rewrite_rules();
    }
}

register_activation_hook( __FILE__, array( 'WPCMCP_Plugin', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'WPCMCP_Plugin', 'deactivate' ) );
add_action( 'plugins_loaded', array( 'WPCMCP_Plugin', 'instance' ) );
add_action( 'wpcmcp_daily_maintenance', array( 'WPCMCP_DB', 'maintenance' ) );
