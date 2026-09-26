<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class WPCMCP_Transaction_Tools {
    const OPTION = 'wpcmcp_transaction_snapshots';
    const MAX_SNAPSHOTS = 20;

    public static function definitions( $can_read, $can_write ) {
        $tools = array();
        if ( $can_read ) {
            $tools[] = self::t( 'wordpress.transaction_list_snapshots', 'List MCP Snapshots', 'List recent rollback snapshots created by the MCP transaction engine.', array(), array(), true, false, true );
            $tools[] = self::t( 'wordpress.transaction_get_snapshot', 'Get MCP Snapshot', 'Inspect one rollback snapshot without exposing secret-looking option values.', array(
                'snapshot_id' => array( 'type' => 'string' ),
            ), array( 'snapshot_id' ), true, false, true );
            $tools[] = self::t( 'wordpress.transaction_diff', 'Preview Change Diff', 'Compare current WordPress state with proposed post/option changes without writing anything.', array(
                'posts' => array( 'type' => 'array' ),
                'options' => array( 'type' => 'object', 'additionalProperties' => true ),
            ), array(), true, false, true );
        }
        if ( $can_write ) {
            $tools[] = self::t( 'wordpress.transaction_create_snapshot', 'Create Rollback Snapshot', 'Create a rollback snapshot for explicit posts and non-secret options before a multi-step workflow.', array(
                'label' => array( 'type' => 'string' ),
                'post_ids' => array( 'type' => 'array', 'items' => array( 'type' => 'integer', 'minimum' => 1 ), 'maxItems' => 100 ),
                'options' => array( 'type' => 'array', 'items' => array( 'type' => 'string' ), 'maxItems' => 100 ),
            ), array(), false, false, false );
            $tools[] = self::t( 'wordpress.transaction_rollback', 'Rollback MCP Snapshot', 'Restore posts and options captured in a snapshot. Requires confirm=true.', array(
                'snapshot_id' => array( 'type' => 'string' ),
                'confirm' => array( 'type' => 'boolean' ),
            ), array( 'snapshot_id', 'confirm' ), false, true, true );
            $tools[] = self::t( 'wordpress.transaction_delete_snapshot', 'Delete MCP Snapshot', 'Delete one stored rollback snapshot. Requires confirm=true.', array(
                'snapshot_id' => array( 'type' => 'string' ),
                'confirm' => array( 'type' => 'boolean' ),
            ), array( 'snapshot_id', 'confirm' ), false, true, true );
        }
        return $tools;
    }

    private static function t( $name, $title, $description, $properties, $required, $read_only, $destructive, $idempotent ) {
        return WPCMCP_Tools::tool( $name, $title, $description, $properties, $required, $read_only, $destructive, $idempotent );
    }

    public static function execute( $name, array $args ) {
        switch ( $name ) {
            case 'wordpress.transaction_list_snapshots': return self::list_snapshots();
            case 'wordpress.transaction_get_snapshot': return self::get_snapshot_tool( $args );
            case 'wordpress.transaction_diff': return self::diff( $args );
            case 'wordpress.transaction_create_snapshot': return self::create_snapshot( $args );
            case 'wordpress.transaction_rollback': return self::rollback( $args );
            case 'wordpress.transaction_delete_snapshot': return self::delete_snapshot( $args );
            default: return null;
        }
    }

    private static function snapshots() {
        $value = get_option( self::OPTION, array() );
        return is_array( $value ) ? $value : array();
    }

    private static function save_snapshots( array $snapshots ) {
        if ( count( $snapshots ) > self::MAX_SNAPSHOTS ) {
            uasort( $snapshots, static function ( $a, $b ) {
                return strcmp( isset( $a['created_gmt'] ) ? $a['created_gmt'] : '', isset( $b['created_gmt'] ) ? $b['created_gmt'] : '' );
            } );
            while ( count( $snapshots ) > self::MAX_SNAPSHOTS ) array_shift( $snapshots );
        }
        update_option( self::OPTION, $snapshots, false );
    }

    private static function secret_option( $name ) {
        return (bool) preg_match( '/(?:password|passwd|secret|token|api[_-]?key|private[_-]?key|client[_-]?secret|auth[_-]?key|nonce|salt)/i', (string) $name );
    }

    private static function post_state( $post_id ) {
        $post = get_post( absint( $post_id ), ARRAY_A );
        if ( ! $post ) return new WP_Error( 'not_found', 'Post not found: ' . absint( $post_id ) );
        if ( ! current_user_can( 'edit_post', $post_id ) ) return new WP_Error( 'forbidden', 'You cannot snapshot post ' . absint( $post_id ) . '.' );

        $keys = array(
            'ID','post_author','post_date','post_date_gmt','post_content','post_title','post_excerpt','post_status',
            'comment_status','ping_status','post_password','post_name','to_ping','pinged','post_modified',
            'post_modified_gmt','post_content_filtered','post_parent','guid','menu_order','post_type','post_mime_type','comment_count'
        );
        $clean = array();
        foreach ( $keys as $key ) if ( array_key_exists( $key, $post ) ) $clean[ $key ] = $post[ $key ];

        return array(
            'post' => $clean,
            'thumbnail_id' => (int) get_post_thumbnail_id( $post_id ),
        );
    }

    private static function public_snapshot_record( array $snapshot, $include_state = false ) {
        $record = array(
            'id' => $snapshot['id'],
            'label' => $snapshot['label'],
            'created_gmt' => $snapshot['created_gmt'],
            'created_by' => (int) $snapshot['created_by'],
            'post_ids' => array_map( 'intval', array_keys( isset( $snapshot['posts'] ) ? $snapshot['posts'] : array() ) ),
            'options' => array_keys( isset( $snapshot['options'] ) ? $snapshot['options'] : array() ),
        );
        if ( $include_state ) {
            $record['posts'] = isset( $snapshot['posts'] ) ? $snapshot['posts'] : array();
            $record['option_states'] = isset( $snapshot['options'] ) ? $snapshot['options'] : array();
        }
        return $record;
    }

    private static function list_snapshots() {
        if ( ! current_user_can( 'manage_options' ) ) return new WP_Error( 'forbidden', 'Snapshot inspection requires manage_options.' );
        $items = array();
        foreach ( array_reverse( self::snapshots(), true ) as $snapshot ) $items[] = self::public_snapshot_record( $snapshot, false );
        return array( 'snapshots' => $items, 'retention_limit' => self::MAX_SNAPSHOTS );
    }

    private static function find_snapshot( $id ) {
        $id = sanitize_text_field( $id );
        $snapshots = self::snapshots();
        return isset( $snapshots[ $id ] ) ? $snapshots[ $id ] : false;
    }

    private static function get_snapshot_tool( array $args ) {
        if ( ! current_user_can( 'manage_options' ) ) return new WP_Error( 'forbidden', 'Snapshot inspection requires manage_options.' );
        $snapshot = self::find_snapshot( $args['snapshot_id'] );
        return $snapshot ? self::public_snapshot_record( $snapshot, true ) : new WP_Error( 'not_found', 'Snapshot not found.' );
    }

    private static function create_snapshot( array $args ) {
        if ( ! current_user_can( 'manage_options' ) ) return new WP_Error( 'forbidden', 'Creating rollback snapshots requires manage_options.' );

        $posts = array();
        foreach ( array_values( array_unique( array_map( 'absint', isset( $args['post_ids'] ) ? (array) $args['post_ids'] : array() ) ) ) as $post_id ) {
            if ( ! $post_id ) continue;
            $state = self::post_state( $post_id );
            if ( is_wp_error( $state ) ) return $state;
            $posts[ $post_id ] = $state;
        }

        $options = array();
        foreach ( array_values( array_unique( array_map( 'sanitize_text_field', isset( $args['options'] ) ? (array) $args['options'] : array() ) ) ) as $option ) {
            if ( '' === $option ) continue;
            if ( self::secret_option( $option ) ) return new WP_Error( 'secret_option_blocked', 'Secret-looking options cannot be captured by the generic snapshot engine: ' . $option );
            $exists = false !== get_option( $option, false ) || false !== wp_cache_get( $option, 'options' );
            $options[ $option ] = array(
                'exists' => $exists,
                'value' => get_option( $option, null ),
            );
        }

        if ( empty( $posts ) && empty( $options ) ) return new WP_Error( 'empty_snapshot', 'Provide at least one post ID or non-secret option name.' );

        $id = wp_generate_uuid4();
        $snapshot = array(
            'id' => $id,
            'label' => isset( $args['label'] ) ? sanitize_text_field( $args['label'] ) : '',
            'created_gmt' => gmdate( 'c' ),
            'created_by' => get_current_user_id(),
            'posts' => $posts,
            'options' => $options,
        );

        $snapshots = self::snapshots();
        $snapshots[ $id ] = $snapshot;
        self::save_snapshots( $snapshots );

        return self::public_snapshot_record( $snapshot, false );
    }

    private static function normalize_post_proposal( array $proposal ) {
        $id = isset( $proposal['id'] ) ? absint( $proposal['id'] ) : 0;
        if ( ! $id || ! get_post( $id ) ) return new WP_Error( 'not_found', 'Proposed post target not found.' );
        if ( ! current_user_can( 'edit_post', $id ) ) return new WP_Error( 'forbidden', 'You cannot inspect proposed changes for this post.' );

        $allowed = array( 'post_title', 'post_content', 'post_excerpt', 'post_status', 'post_name', 'post_parent', 'menu_order', 'comment_status', 'ping_status' );
        $current = get_post( $id, ARRAY_A );
        $changes = array();

        foreach ( $allowed as $field ) {
            if ( ! array_key_exists( $field, $proposal ) ) continue;
            $before = isset( $current[ $field ] ) ? $current[ $field ] : null;
            $after = $proposal[ $field ];
            if ( $before !== $after ) $changes[ $field ] = array( 'before' => $before, 'after' => $after );
        }

        return array( 'id' => $id, 'changes' => $changes );
    }

    private static function diff( array $args ) {
        $post_diffs = array();
        foreach ( isset( $args['posts'] ) ? (array) $args['posts'] : array() as $proposal ) {
            if ( ! is_array( $proposal ) ) continue;
            $diff = self::normalize_post_proposal( $proposal );
            if ( is_wp_error( $diff ) ) return $diff;
            $post_diffs[] = $diff;
        }

        $option_diffs = array();
        if ( isset( $args['options'] ) && is_array( $args['options'] ) ) {
            if ( ! current_user_can( 'manage_options' ) ) return new WP_Error( 'forbidden', 'Option diff requires manage_options.' );
            foreach ( $args['options'] as $name => $after ) {
                $name = sanitize_text_field( $name );
                if ( self::secret_option( $name ) ) {
                    $option_diffs[ $name ] = array( 'blocked' => true, 'reason' => 'secret-looking option' );
                    continue;
                }
                $before = get_option( $name, null );
                if ( $before !== $after ) $option_diffs[ $name ] = array( 'before' => $before, 'after' => $after );
            }
        }

        return array(
            'dry_run' => true,
            'posts' => $post_diffs,
            'options' => $option_diffs,
            'has_changes' => (bool) ( array_filter( wp_list_pluck( $post_diffs, 'changes' ) ) || $option_diffs ),
        );
    }

    private static function rollback( array $args ) {
        if ( ! current_user_can( 'manage_options' ) ) return new WP_Error( 'forbidden', 'Rollback requires manage_options.' );
        if ( empty( $args['confirm'] ) ) return new WP_Error( 'confirmation_required', 'Rollback requires confirm=true.' );

        $snapshot = self::find_snapshot( $args['snapshot_id'] );
        if ( ! $snapshot ) return new WP_Error( 'not_found', 'Snapshot not found.' );

        $results = array( 'posts' => array(), 'options' => array() );

        foreach ( isset( $snapshot['posts'] ) ? $snapshot['posts'] : array() as $post_id => $state ) {
            $post_id = absint( $post_id );
            if ( ! current_user_can( 'edit_post', $post_id ) ) {
                $results['posts'][ $post_id ] = array( 'success' => false, 'error' => 'forbidden' );
                continue;
            }
            $payload = isset( $state['post'] ) ? $state['post'] : array();
            $payload['ID'] = $post_id;
            $updated = wp_update_post( wp_slash( $payload ), true );
            if ( is_wp_error( $updated ) ) {
                $results['posts'][ $post_id ] = array( 'success' => false, 'error' => $updated->get_error_message() );
                continue;
            }
            $thumb = isset( $state['thumbnail_id'] ) ? absint( $state['thumbnail_id'] ) : 0;
            if ( $thumb ) set_post_thumbnail( $post_id, $thumb );
            else delete_post_thumbnail( $post_id );
            $results['posts'][ $post_id ] = array( 'success' => true );
        }

        foreach ( isset( $snapshot['options'] ) ? $snapshot['options'] : array() as $option => $state ) {
            if ( self::secret_option( $option ) ) {
                $results['options'][ $option ] = array( 'success' => false, 'error' => 'blocked_secret' );
                continue;
            }
            if ( ! empty( $state['exists'] ) ) update_option( $option, $state['value'] );
            else delete_option( $option );
            $results['options'][ $option ] = array( 'success' => true );
        }

        WPCMCP_DB::log( 'transaction_rollback', '', 'success', get_current_user_id(), '', 'snapshot=' . sanitize_text_field( $snapshot['id'] ) );

        return array(
            'success' => true,
            'snapshot_id' => $snapshot['id'],
            'results' => $results,
        );
    }

    private static function delete_snapshot( array $args ) {
        if ( ! current_user_can( 'manage_options' ) ) return new WP_Error( 'forbidden', 'Deleting snapshots requires manage_options.' );
        if ( empty( $args['confirm'] ) ) return new WP_Error( 'confirmation_required', 'Deleting a snapshot requires confirm=true.' );

        $id = sanitize_text_field( $args['snapshot_id'] );
        $snapshots = self::snapshots();
        if ( ! isset( $snapshots[ $id ] ) ) return new WP_Error( 'not_found', 'Snapshot not found.' );
        unset( $snapshots[ $id ] );
        self::save_snapshots( $snapshots );

        return array( 'success' => true, 'snapshot_id' => $id );
    }
}
