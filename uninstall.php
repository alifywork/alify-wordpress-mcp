<?php
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit;
}

// Keep connection/audit tables by default so uninstalling the plugin does not silently destroy audit history.
// Remove plugin options only. Database tables may be removed manually if desired.
wp_clear_scheduled_hook( 'wpcmcp_daily_maintenance' );
delete_option( 'wpcmcp_settings' );
delete_option( 'wpcmcp_db_version' );
delete_option( 'wpcmcp_theme_source_backups' );
