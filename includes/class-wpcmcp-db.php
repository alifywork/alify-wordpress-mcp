<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class WPCMCP_DB {
    private static $instance = null;

    public static function instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public static function table( $name ) {
        global $wpdb;
        return $wpdb->prefix . 'wpcmcp_' . $name;
    }

    public static function maybe_upgrade() {
        $installed = (string) get_option( 'wpcmcp_db_version', '' );
        if ( WPCMCP_DB_VERSION !== $installed ) {
            self::install();
        }
    }

    public static function schedule_maintenance() {
        if ( ! wp_next_scheduled( 'wpcmcp_daily_maintenance' ) ) {
            wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', 'wpcmcp_daily_maintenance' );
        }
    }

    public static function unschedule_maintenance() {
        wp_clear_scheduled_hook( 'wpcmcp_daily_maintenance' );
    }

    public static function install() {
        global $wpdb;
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $charset = $wpdb->get_charset_collate();
        $clients = self::table( 'clients' );
        $codes   = self::table( 'codes' );
        $tokens  = self::table( 'tokens' );
        $logs    = self::table( 'logs' );

        dbDelta( "CREATE TABLE {$clients} (
            id bigint unsigned NOT NULL AUTO_INCREMENT,
            client_id varchar(191) NOT NULL,
            client_name varchar(191) NOT NULL DEFAULT '',
            redirect_uris longtext NOT NULL,
            grant_types longtext NULL,
            response_types longtext NULL,
            token_endpoint_auth_method varchar(64) NOT NULL DEFAULT 'none',
            created_at datetime NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY client_id (client_id)
        ) {$charset};" );

        dbDelta( "CREATE TABLE {$codes} (
            id bigint unsigned NOT NULL AUTO_INCREMENT,
            code_hash char(64) NOT NULL,
            client_id varchar(191) NOT NULL,
            user_id bigint unsigned NOT NULL,
            redirect_uri text NOT NULL,
            code_challenge varchar(191) NOT NULL,
            scope text NOT NULL,
            resource text NOT NULL,
            expires_at datetime NOT NULL,
            used_at datetime NULL,
            created_at datetime NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY code_hash (code_hash),
            KEY client_id (client_id),
            KEY user_id (user_id)
        ) {$charset};" );

        dbDelta( "CREATE TABLE {$tokens} (
            id bigint unsigned NOT NULL AUTO_INCREMENT,
            access_hash char(64) NOT NULL,
            refresh_hash char(64) NOT NULL,
            client_id varchar(191) NOT NULL,
            user_id bigint unsigned NOT NULL,
            scope text NOT NULL,
            resource text NOT NULL,
            access_expires_at datetime NOT NULL,
            refresh_expires_at datetime NOT NULL,
            revoked_at datetime NULL,
            created_at datetime NOT NULL,
            updated_at datetime NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY access_hash (access_hash),
            UNIQUE KEY refresh_hash (refresh_hash),
            KEY client_id (client_id),
            KEY user_id (user_id)
        ) {$charset};" );

        dbDelta( "CREATE TABLE {$logs} (
            id bigint unsigned NOT NULL AUTO_INCREMENT,
            event varchar(80) NOT NULL,
            tool_name varchar(128) NOT NULL DEFAULT '',
            status varchar(32) NOT NULL DEFAULT '',
            user_id bigint unsigned NOT NULL DEFAULT 0,
            client_id varchar(191) NOT NULL DEFAULT '',
            details text NULL,
            created_at datetime NOT NULL,
            PRIMARY KEY (id),
            KEY event (event),
            KEY tool_name (tool_name),
            KEY user_id (user_id),
            KEY created_at (created_at)
        ) {$charset};" );

        update_option( 'wpcmcp_db_version', WPCMCP_DB_VERSION, false );
    }

    public static function log( $event, $tool = '', $status = '', $user_id = 0, $client_id = '', $details = '' ) {
        global $wpdb;
        $wpdb->insert(
            self::table( 'logs' ),
            array(
                'event'      => sanitize_key( $event ),
                'tool_name'  => sanitize_text_field( $tool ),
                'status'     => sanitize_key( $status ),
                'user_id'    => absint( $user_id ),
                'client_id'  => sanitize_text_field( $client_id ),
                'details'    => wp_strip_all_tags( substr( (string) $details, 0, 2000 ) ),
                'created_at' => current_time( 'mysql', true ),
            ),
            array( '%s', '%s', '%s', '%d', '%s', '%s', '%s' )
        );
    }



    public static function maintenance() {
        self::prune_logs();
        self::prune_oauth_records();
    }

    public static function prune_oauth_records() {
        global $wpdb;

        $now = current_time( 'mysql', true );
        $client_cutoff = gmdate( 'Y-m-d H:i:s', time() - ( 7 * DAY_IN_SECONDS ) );

        $wpdb->query(
            $wpdb->prepare(
                'DELETE FROM ' . self::table( 'codes' ) . ' WHERE expires_at < %s',
                $now
            )
        ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

        // Remove abandoned dynamically registered clients only when they have never
        // produced an authorization code or token. This limits unauthenticated DCR
        // database growth without deleting connection history.
        $wpdb->query(
            $wpdb->prepare(
                'DELETE c FROM ' . self::table( 'clients' ) . ' c '
                . 'LEFT JOIN ' . self::table( 'codes' ) . ' ac ON ac.client_id = c.client_id '
                . 'LEFT JOIN ' . self::table( 'tokens' ) . ' t ON t.client_id = c.client_id '
                . 'WHERE c.created_at < %s AND ac.id IS NULL AND t.id IS NULL',
                $client_cutoff
            )
        ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
    }

    public static function prune_logs() {
        global $wpdb;
        $settings = get_option( 'wpcmcp_settings', array() );
        $days = max( 1, absint( isset( $settings['log_days'] ) ? $settings['log_days'] : 30 ) );
        $cutoff = gmdate( 'Y-m-d H:i:s', time() - ( DAY_IN_SECONDS * $days ) );
        $wpdb->query( $wpdb->prepare( 'DELETE FROM ' . self::table( 'logs' ) . ' WHERE created_at < %s', $cutoff ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
    }
}
