<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class WPCMCP_Database_Diagnostics_Tools {
    public static function definitions( $can_read, $can_write ) {
        $tools = array();

        if ( $can_read ) {
            $tools[] = self::t( 'wordpress.db_list_tables', 'List WordPress Database Tables', 'List WordPress-prefixed tables with row counts and approximate sizes. No table contents are returned.', array(), array(), true, false, true );
            $tools[] = self::t( 'wordpress.db_describe_table', 'Describe Database Table', 'Describe columns and indexes for one existing WordPress-prefixed table. No row data is returned.', array(
                'table' => array( 'type' => 'string' ),
            ), array( 'table' ), true, false, true );
            $tools[] = self::t( 'wordpress.db_plugin_tables', 'Discover Plugin Database Tables', 'Find WordPress-prefixed database tables whose names appear related to an installed plugin slug.', array(
                'plugin' => array( 'type' => 'string' ),
            ), array( 'plugin' ), true, false, true );
            $tools[] = self::t( 'wordpress.db_autoload_report', 'Autoload Options Report', 'Report the largest autoloaded options by name and serialized size. Option values are never returned.', array(
                'limit' => array( 'type' => 'integer', 'minimum' => 1, 'maximum' => 100 ),
            ), array(), true, false, true );
            $tools[] = self::t( 'wordpress.db_orphan_report', 'Database Orphan Report', 'Report counts of orphaned postmeta, commentmeta, termmeta and usermeta rows without deleting them.', array(), array(), true, false, true );
        }

        if ( $can_write ) {
            $tools[] = self::t( 'wordpress.db_cleanup_expired_transients', 'Clean Expired Transients', 'Delete expired WordPress transients using core timeout semantics. Requires confirm=true.', array(
                'confirm' => array( 'type' => 'boolean' ),
            ), array( 'confirm' ), false, true, true );
        }

        return $tools;
    }

    private static function t( $name, $title, $description, $properties, $required, $read_only, $destructive, $idempotent ) {
        return WPCMCP_Tools::tool( $name, $title, $description, $properties, $required, $read_only, $destructive, $idempotent );
    }

    public static function execute( $name, array $args ) {
        switch ( $name ) {
            case 'wordpress.db_list_tables': return self::list_tables();
            case 'wordpress.db_describe_table': return self::describe_table( $args );
            case 'wordpress.db_plugin_tables': return self::plugin_tables( $args );
            case 'wordpress.db_autoload_report': return self::autoload_report( $args );
            case 'wordpress.db_orphan_report': return self::orphan_report();
            case 'wordpress.db_cleanup_expired_transients': return self::cleanup_expired_transients( $args );
            default: return null;
        }
    }

    private static function can_manage() {
        return current_user_can( 'manage_options' );
    }

    private static function table_names() {
        global $wpdb;
        $like = $wpdb->esc_like( $wpdb->prefix ) . '%';
        $tables = $wpdb->get_col( $wpdb->prepare( 'SHOW TABLES LIKE %s', $like ) );
        return array_values( array_filter( array_map( 'strval', (array) $tables ) ) );
    }

    private static function valid_table( $table ) {
        $table = (string) $table;
        return in_array( $table, self::table_names(), true ) ? $table : '';
    }

    private static function table_stats( $table ) {
        global $wpdb;
        $row = $wpdb->get_row(
            $wpdb->prepare(
                'SELECT TABLE_ROWS, DATA_LENGTH, INDEX_LENGTH FROM information_schema.TABLES WHERE TABLE_SCHEMA = %s AND TABLE_NAME = %s',
                DB_NAME,
                $table
            ),
            ARRAY_A
        );

        return array(
            'table' => $table,
            'rows_approx' => isset( $row['TABLE_ROWS'] ) ? (int) $row['TABLE_ROWS'] : null,
            'data_bytes' => isset( $row['DATA_LENGTH'] ) ? (int) $row['DATA_LENGTH'] : null,
            'index_bytes' => isset( $row['INDEX_LENGTH'] ) ? (int) $row['INDEX_LENGTH'] : null,
        );
    }

    private static function list_tables() {
        if ( ! self::can_manage() ) return new WP_Error( 'forbidden', 'Database diagnostics require manage_options.' );
        $items = array();
        foreach ( self::table_names() as $table ) $items[] = self::table_stats( $table );
        usort( $items, static function ( $a, $b ) {
            return (int) ( $b['data_bytes'] + $b['index_bytes'] ) <=> (int) ( $a['data_bytes'] + $a['index_bytes'] );
        } );
        return array( 'tables' => $items );
    }

    private static function describe_table( array $args ) {
        global $wpdb;
        if ( ! self::can_manage() ) return new WP_Error( 'forbidden', 'Database diagnostics require manage_options.' );

        $table = self::valid_table( $args['table'] );
        if ( ! $table ) return new WP_Error( 'invalid_table', 'Table is not an existing WordPress-prefixed table.' );

        $quote = chr( 96 );
        $quoted = $quote . str_replace( $quote, $quote . $quote, $table ) . $quote;
        $columns = $wpdb->get_results( 'SHOW COLUMNS FROM ' . $quoted, ARRAY_A );
        $indexes = $wpdb->get_results( 'SHOW INDEX FROM ' . $quoted, ARRAY_A );

        $clean_indexes = array();
        foreach ( (array) $indexes as $index ) {
            $clean_indexes[] = array(
                'key_name' => isset( $index['Key_name'] ) ? $index['Key_name'] : '',
                'column_name' => isset( $index['Column_name'] ) ? $index['Column_name'] : '',
                'non_unique' => isset( $index['Non_unique'] ) ? (bool) $index['Non_unique'] : null,
                'seq_in_index' => isset( $index['Seq_in_index'] ) ? (int) $index['Seq_in_index'] : null,
            );
        }

        return array(
            'stats' => self::table_stats( $table ),
            'columns' => $columns,
            'indexes' => $clean_indexes,
        );
    }

    private static function resolve_plugin_slug( $needle ) {
        if ( ! function_exists( 'get_plugins' ) ) require_once ABSPATH . 'wp-admin/includes/plugin.php';
        $needle = strtolower( trim( (string) $needle ) );
        $plugins = get_plugins();
        $matches = array();

        foreach ( $plugins as $file => $data ) {
            $slug = dirname( $file );
            if ( '.' === $slug ) $slug = basename( $file, '.php' );
            foreach ( array( $file, $slug, basename( $file, '.php' ), isset( $data['Name'] ) ? $data['Name'] : '' ) as $value ) {
                $value = strtolower( (string) $value );
                if ( $value === $needle || false !== strpos( $value, $needle ) ) {
                    $matches[ $slug ] = $file;
                    break;
                }
            }
        }

        if ( 1 === count( $matches ) ) return array( 'slug' => (string) array_key_first( $matches ), 'plugin' => (string) reset( $matches ) );
        if ( 1 < count( $matches ) ) return new WP_Error( 'ambiguous_plugin', 'Plugin identifier matched multiple installed plugins.' );
        return new WP_Error( 'not_found', 'Installed plugin not found.' );
    }

    private static function plugin_tables( array $args ) {
        global $wpdb;
        if ( ! self::can_manage() ) return new WP_Error( 'forbidden', 'Database diagnostics require manage_options.' );

        $plugin = self::resolve_plugin_slug( $args['plugin'] );
        if ( is_wp_error( $plugin ) ) return $plugin;

        $tokens = array_values( array_unique( array_filter( preg_split( '/[^a-z0-9]+/', strtolower( $plugin['slug'] ) ) ) ) );
        $items = array();

        foreach ( self::table_names() as $table ) {
            $base = strtolower( preg_replace( '/^' . preg_quote( $wpdb->prefix, '/' ) . '/', '', $table ) );
            $score = 0;
            foreach ( $tokens as $token ) {
                if ( strlen( $token ) < 3 ) continue;
                if ( false !== strpos( $base, $token ) ) $score++;
            }
            if ( $score ) {
                $record = self::table_stats( $table );
                $record['match_score'] = $score;
                $items[] = $record;
            }
        }

        usort( $items, static function ( $a, $b ) { return $b['match_score'] <=> $a['match_score']; } );

        return array(
            'plugin' => $plugin['plugin'],
            'slug' => $plugin['slug'],
            'tables' => $items,
            'note' => 'Table attribution is heuristic by plugin slug tokens; inspect schemas before building a dedicated adapter.',
        );
    }

    private static function autoload_report( array $args ) {
        global $wpdb;
        if ( ! self::can_manage() ) return new WP_Error( 'forbidden', 'Database diagnostics require manage_options.' );

        $limit = min( 100, max( 1, isset( $args['limit'] ) ? absint( $args['limit'] ) : 30 ) );
        $autoload_values = "'yes','on','auto-on','auto'";
        $sql = "SELECT option_name, LENGTH(option_value) AS bytes FROM {$wpdb->options} WHERE autoload IN ({$autoload_values}) ORDER BY LENGTH(option_value) DESC LIMIT " . (int) $limit;
        $rows = $wpdb->get_results( $sql, ARRAY_A );
        $total = (int) $wpdb->get_var( "SELECT COALESCE(SUM(LENGTH(option_value)),0) FROM {$wpdb->options} WHERE autoload IN ({$autoload_values})" );

        $items = array();
        foreach ( (array) $rows as $row ) {
            $items[] = array(
                'option_name' => $row['option_name'],
                'bytes' => (int) $row['bytes'],
                'secret_name' => (bool) preg_match( '/(?:password|secret|token|key)/i', $row['option_name'] ),
            );
        }

        return array( 'autoload_bytes' => $total, 'largest_options' => $items );
    }

    private static function orphan_count( $sql ) {
        global $wpdb;
        return (int) $wpdb->get_var( $sql );
    }

    private static function orphan_report() {
        global $wpdb;
        if ( ! self::can_manage() ) return new WP_Error( 'forbidden', 'Database diagnostics require manage_options.' );

        return array(
            'postmeta' => self::orphan_count( "SELECT COUNT(*) FROM {$wpdb->postmeta} pm LEFT JOIN {$wpdb->posts} p ON p.ID = pm.post_id WHERE p.ID IS NULL" ),
            'commentmeta' => self::orphan_count( "SELECT COUNT(*) FROM {$wpdb->commentmeta} cm LEFT JOIN {$wpdb->comments} c ON c.comment_ID = cm.comment_id WHERE c.comment_ID IS NULL" ),
            'termmeta' => self::orphan_count( "SELECT COUNT(*) FROM {$wpdb->termmeta} tm LEFT JOIN {$wpdb->terms} t ON t.term_id = tm.term_id WHERE t.term_id IS NULL" ),
            'usermeta' => self::orphan_count( "SELECT COUNT(*) FROM {$wpdb->usermeta} um LEFT JOIN {$wpdb->users} u ON u.ID = um.user_id WHERE u.ID IS NULL" ),
        );
    }

    private static function cleanup_expired_transients( array $args ) {
        if ( ! self::can_manage() ) return new WP_Error( 'forbidden', 'Transient cleanup requires manage_options.' );
        if ( empty( $args['confirm'] ) ) return new WP_Error( 'confirmation_required', 'Transient cleanup requires confirm=true.' );

        if ( function_exists( 'delete_expired_transients' ) ) {
            delete_expired_transients( true );
            return array( 'success' => true, 'method' => 'core_delete_expired_transients' );
        }

        return new WP_Error( 'unsupported', 'WordPress expired-transient cleanup API is unavailable.' );
    }
}
