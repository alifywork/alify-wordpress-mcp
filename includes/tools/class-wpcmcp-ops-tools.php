<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class WPCMCP_Ops_Tools {
    const WEBHOOK_OPTION = 'wpcmcp_webhooks';
    const WEBHOOK_EVENTS = array(
        'save_post',
        'user_register',
        'comment_post',
        'woocommerce_order_status_changed',
    );

    public static function bootstrap() {
        add_action( 'save_post', array( __CLASS__, 'dispatch_save_post' ), 20, 3 );
        add_action( 'user_register', array( __CLASS__, 'dispatch_user_register' ), 20, 1 );
        add_action( 'comment_post', array( __CLASS__, 'dispatch_comment_post' ), 20, 3 );
        add_action( 'woocommerce_order_status_changed', array( __CLASS__, 'dispatch_woo_order_status' ), 20, 4 );
    }

    public static function definitions( $can_read, $can_write ) {
        $tools = array();

        if ( $can_read ) {
            $tools[] = self::t( 'wordpress.cron_list_events', 'List WP-Cron Events', 'List scheduled WP-Cron hooks, next run times, recurrences and arguments.', array(), array(), true, false, true );
            $tools[] = self::t( 'wordpress.webhook_list', 'List MCP Webhooks', 'List outgoing webhooks managed by this MCP plugin. Signing keys are never returned.', array(), array(), true, false, true );
            $tools[] = self::t( 'wordpress.site_health_verify', 'Verify Site Health', 'Run a lightweight post-change verification: front page HTTP check, REST index, cron availability, theme/plugin state and recent PHP error-log tail when readable.', array(
                'urls' => array( 'type' => 'array', 'items' => array( 'type' => 'string', 'format' => 'uri' ), 'maxItems' => 10 ),
            ), array(), true, false, true );
        }

        if ( $can_write ) {
            $tools[] = self::t( 'wordpress.cron_run_event', 'Run WP-Cron Event Now', 'Run one scheduled cron hook now with the stored arguments. Requires confirm=true.', array(
                'hook' => array( 'type' => 'string' ),
                'timestamp' => array( 'type' => 'integer', 'minimum' => 1 ),
                'confirm' => array( 'type' => 'boolean' ),
            ), array( 'hook', 'timestamp', 'confirm' ), false, true, false );
            $tools[] = self::t( 'wordpress.cron_schedule_event', 'Schedule WP-Cron Event', 'Schedule a single or recurring WP-Cron event for an existing hook. Requires confirm=true.', array(
                'hook' => array( 'type' => 'string' ),
                'timestamp' => array( 'type' => 'integer', 'minimum' => 1 ),
                'recurrence' => array( 'type' => 'string' ),
                'args' => array( 'type' => 'array' ),
                'confirm' => array( 'type' => 'boolean' ),
            ), array( 'hook', 'timestamp', 'confirm' ), false, true, false );
            $tools[] = self::t( 'wordpress.cron_unschedule_event', 'Unschedule WP-Cron Event', 'Unschedule one matching WP-Cron event. Requires confirm=true.', array(
                'hook' => array( 'type' => 'string' ),
                'timestamp' => array( 'type' => 'integer', 'minimum' => 1 ),
                'args' => array( 'type' => 'array' ),
                'confirm' => array( 'type' => 'boolean' ),
            ), array( 'hook', 'timestamp', 'confirm' ), false, true, true );
            $tools[] = self::t( 'wordpress.webhook_create', 'Create MCP Webhook', 'Create a signed outgoing HTTPS webhook for an allowlisted WordPress event.', array(
                'name' => array( 'type' => 'string' ),
                'url' => array( 'type' => 'string', 'format' => 'uri' ),
                'events' => array( 'type' => 'array', 'minItems' => 1, 'items' => array( 'type' => 'string', 'enum' => self::WEBHOOK_EVENTS ) ),
                'active' => array( 'type' => 'boolean' ),
            ), array( 'name', 'url', 'events' ), false, false, false );
            $tools[] = self::t( 'wordpress.webhook_update', 'Update MCP Webhook', 'Update an MCP-managed outgoing webhook.', array(
                'webhook_id' => array( 'type' => 'string' ),
                'name' => array( 'type' => 'string' ),
                'url' => array( 'type' => 'string', 'format' => 'uri' ),
                'events' => array( 'type' => 'array', 'minItems' => 1, 'items' => array( 'type' => 'string', 'enum' => self::WEBHOOK_EVENTS ) ),
                'active' => array( 'type' => 'boolean' ),
            ), array( 'webhook_id' ), false, false, true );
            $tools[] = self::t( 'wordpress.webhook_delete', 'Delete MCP Webhook', 'Delete an MCP-managed webhook. Requires confirm=true.', array(
                'webhook_id' => array( 'type' => 'string' ),
                'confirm' => array( 'type' => 'boolean' ),
            ), array( 'webhook_id', 'confirm' ), false, true, true );
            $tools[] = self::t( 'wordpress.webhook_test', 'Test MCP Webhook', 'Send a signed test payload to one MCP-managed webhook. Requires confirm=true.', array(
                'webhook_id' => array( 'type' => 'string' ),
                'confirm' => array( 'type' => 'boolean' ),
            ), array( 'webhook_id', 'confirm' ), false, true, false );
        }

        return $tools;
    }

    private static function t( $name, $title, $description, $properties, $required, $read_only, $destructive, $idempotent ) {
        return WPCMCP_Tools::tool( $name, $title, $description, $properties, $required, $read_only, $destructive, $idempotent, true );
    }

    public static function execute( $name, array $args ) {
        switch ( $name ) {
            case 'wordpress.cron_list_events': return self::cron_list();
            case 'wordpress.cron_run_event': return self::cron_run( $args );
            case 'wordpress.cron_schedule_event': return self::cron_schedule( $args );
            case 'wordpress.cron_unschedule_event': return self::cron_unschedule( $args );
            case 'wordpress.webhook_list': return self::webhook_list();
            case 'wordpress.webhook_create': return self::webhook_create( $args );
            case 'wordpress.webhook_update': return self::webhook_update( $args );
            case 'wordpress.webhook_delete': return self::webhook_delete( $args );
            case 'wordpress.webhook_test': return self::webhook_test( $args );
            case 'wordpress.site_health_verify': return self::site_health_verify( $args );
            default: return null;
        }
    }

    private static function can_manage() {
        return current_user_can( 'manage_options' );
    }

    private static function cron_list() {
        if ( ! self::can_manage() ) return new WP_Error( 'forbidden', 'Cron inspection requires manage_options.' );
        $cron = _get_cron_array();
        $items = array();
        foreach ( (array) $cron as $timestamp => $hooks ) {
            foreach ( (array) $hooks as $hook => $events ) {
                foreach ( (array) $events as $key => $event ) {
                    $items[] = array(
                        'timestamp' => (int) $timestamp,
                        'next_run_gmt' => gmdate( 'c', (int) $timestamp ),
                        'hook' => $hook,
                        'schedule' => isset( $event['schedule'] ) ? $event['schedule'] : false,
                        'interval' => isset( $event['interval'] ) ? (int) $event['interval'] : 0,
                        'args' => isset( $event['args'] ) ? $event['args'] : array(),
                        'event_key' => $key,
                    );
                }
            }
        }
        usort( $items, static function ( $a, $b ) { return $a['timestamp'] <=> $b['timestamp']; } );
        return array( 'events' => $items );
    }

    private static function find_cron_event( $hook, $timestamp ) {
        $cron = _get_cron_array();
        if ( empty( $cron[ $timestamp ][ $hook ] ) ) return false;
        foreach ( $cron[ $timestamp ][ $hook ] as $event ) return $event;
        return false;
    }

    private static function cron_run( array $args ) {
        if ( ! self::can_manage() ) return new WP_Error( 'forbidden', 'Running cron events requires manage_options.' );
        if ( empty( $args['confirm'] ) ) return new WP_Error( 'confirmation_required', 'Running a cron event requires confirm=true.' );

        $hook = sanitize_key( $args['hook'] );
        $timestamp = absint( $args['timestamp'] );
        $event = self::find_cron_event( $hook, $timestamp );
        if ( ! $event ) return new WP_Error( 'not_found', 'Scheduled cron event not found.' );

        $stored_args = isset( $event['args'] ) ? $event['args'] : array();
        do_action_ref_array( $hook, $stored_args );

        return array( 'success' => true, 'hook' => $hook, 'timestamp' => $timestamp, 'args' => $stored_args );
    }

    private static function cron_schedule( array $args ) {
        if ( ! self::can_manage() ) return new WP_Error( 'forbidden', 'Scheduling cron events requires manage_options.' );
        if ( empty( $args['confirm'] ) ) return new WP_Error( 'confirmation_required', 'Scheduling a cron event requires confirm=true.' );

        $hook = sanitize_key( $args['hook'] );
        if ( ! has_action( $hook ) ) return new WP_Error( 'unknown_hook', 'No callback is currently registered for this cron hook.' );

        $timestamp = absint( $args['timestamp'] );
        if ( $timestamp < time() - 60 ) return new WP_Error( 'invalid_timestamp', 'Cron timestamp must be in the future.' );
        $event_args = isset( $args['args'] ) && is_array( $args['args'] ) ? $args['args'] : array();
        $recurrence = isset( $args['recurrence'] ) ? sanitize_key( $args['recurrence'] ) : '';

        if ( $recurrence ) {
            $schedules = wp_get_schedules();
            if ( ! isset( $schedules[ $recurrence ] ) ) return new WP_Error( 'invalid_recurrence', 'Unknown WordPress cron recurrence.' );
            $result = wp_schedule_event( $timestamp, $recurrence, $hook, $event_args, true );
        } else {
            $result = wp_schedule_single_event( $timestamp, $hook, $event_args, true );
        }

        if ( is_wp_error( $result ) || false === $result ) return is_wp_error( $result ) ? $result : new WP_Error( 'schedule_failed', 'WordPress could not schedule the cron event.' );

        return array( 'success' => true, 'hook' => $hook, 'timestamp' => $timestamp, 'recurrence' => $recurrence ?: null, 'args' => $event_args );
    }

    private static function cron_unschedule( array $args ) {
        if ( ! self::can_manage() ) return new WP_Error( 'forbidden', 'Unscheduling cron events requires manage_options.' );
        if ( empty( $args['confirm'] ) ) return new WP_Error( 'confirmation_required', 'Unscheduling a cron event requires confirm=true.' );

        $hook = sanitize_key( $args['hook'] );
        $timestamp = absint( $args['timestamp'] );
        $event_args = isset( $args['args'] ) && is_array( $args['args'] ) ? $args['args'] : array();
        $result = wp_unschedule_event( $timestamp, $hook, $event_args, true );

        if ( is_wp_error( $result ) || false === $result ) return is_wp_error( $result ) ? $result : new WP_Error( 'unschedule_failed', 'WordPress could not unschedule the cron event.' );

        return array( 'success' => true, 'hook' => $hook, 'timestamp' => $timestamp );
    }

    private static function webhooks() {
        $hooks = get_option( self::WEBHOOK_OPTION, array() );
        return is_array( $hooks ) ? $hooks : array();
    }

    private static function save_webhooks( array $hooks ) {
        update_option( self::WEBHOOK_OPTION, $hooks, false );
    }

    private static function safe_webhook_url( $url ) {
        $url = esc_url_raw( $url, array( 'https' ) );
        if ( ! $url || 'https' !== strtolower( (string) wp_parse_url( $url, PHP_URL_SCHEME ) ) || ! wp_http_validate_url( $url ) ) {
            return new WP_Error( 'invalid_webhook_url', 'Webhook URL must be a valid public HTTPS URL.' );
        }
        $host = wp_parse_url( $url, PHP_URL_HOST );
        if ( ! $host || in_array( strtolower( $host ), array( 'localhost', '127.0.0.1', '::1' ), true ) ) return new WP_Error( 'invalid_webhook_url', 'Local/private webhook destinations are not allowed.' );
        return $url;
    }

    private static function normalize_events( $events ) {
        return array_values( array_intersect( self::WEBHOOK_EVENTS, array_values( array_unique( array_map( 'sanitize_key', (array) $events ) ) ) ) );
    }

    private static function public_webhook( array $hook ) {
        return array(
            'id' => $hook['id'],
            'name' => $hook['name'],
            'url' => $hook['url'],
            'events' => $hook['events'],
            'active' => ! empty( $hook['active'] ),
            'created_gmt' => $hook['created_gmt'],
            'last_status' => isset( $hook['last_status'] ) ? $hook['last_status'] : null,
            'last_delivery_gmt' => isset( $hook['last_delivery_gmt'] ) ? $hook['last_delivery_gmt'] : null,
        );
    }

    private static function webhook_list() {
        if ( ! self::can_manage() ) return new WP_Error( 'forbidden', 'Webhook inspection requires manage_options.' );
        $items = array();
        foreach ( self::webhooks() as $hook ) $items[] = self::public_webhook( $hook );
        return array( 'webhooks' => $items );
    }

    private static function webhook_create( array $args ) {
        if ( ! self::can_manage() ) return new WP_Error( 'forbidden', 'Creating webhooks requires manage_options.' );
        $url = self::safe_webhook_url( $args['url'] );
        if ( is_wp_error( $url ) ) return $url;
        $events = self::normalize_events( $args['events'] );
        if ( ! $events ) return new WP_Error( 'invalid_events', 'At least one supported webhook event is required.' );

        $id = wp_generate_uuid4();
        $hook = array(
            'id' => $id,
            'name' => sanitize_text_field( $args['name'] ),
            'url' => $url,
            'events' => $events,
            'active' => ! array_key_exists( 'active', $args ) || (bool) $args['active'],
            'created_gmt' => gmdate( 'c' ),
        );

        $hooks = self::webhooks();
        $hooks[ $id ] = $hook;
        self::save_webhooks( $hooks );
        return self::public_webhook( $hook );
    }

    private static function webhook_update( array $args ) {
        if ( ! self::can_manage() ) return new WP_Error( 'forbidden', 'Updating webhooks requires manage_options.' );
        $id = sanitize_text_field( $args['webhook_id'] );
        $hooks = self::webhooks();
        if ( ! isset( $hooks[ $id ] ) ) return new WP_Error( 'not_found', 'Webhook not found.' );

        $hook = $hooks[ $id ];
        if ( array_key_exists( 'name', $args ) ) $hook['name'] = sanitize_text_field( $args['name'] );
        if ( array_key_exists( 'url', $args ) ) {
            $url = self::safe_webhook_url( $args['url'] );
            if ( is_wp_error( $url ) ) return $url;
            $hook['url'] = $url;
        }
        if ( array_key_exists( 'events', $args ) ) {
            $events = self::normalize_events( $args['events'] );
            if ( ! $events ) return new WP_Error( 'invalid_events', 'At least one supported webhook event is required.' );
            $hook['events'] = $events;
        }
        if ( array_key_exists( 'active', $args ) ) $hook['active'] = (bool) $args['active'];

        $hooks[ $id ] = $hook;
        self::save_webhooks( $hooks );
        return self::public_webhook( $hook );
    }

    private static function webhook_delete( array $args ) {
        if ( ! self::can_manage() ) return new WP_Error( 'forbidden', 'Deleting webhooks requires manage_options.' );
        if ( empty( $args['confirm'] ) ) return new WP_Error( 'confirmation_required', 'Deleting a webhook requires confirm=true.' );

        $id = sanitize_text_field( $args['webhook_id'] );
        $hooks = self::webhooks();
        if ( ! isset( $hooks[ $id ] ) ) return new WP_Error( 'not_found', 'Webhook not found.' );
        unset( $hooks[ $id ] );
        self::save_webhooks( $hooks );
        return array( 'success' => true, 'webhook_id' => $id );
    }

    private static function signing_key( $id ) {
        return hash_hmac( 'sha256', 'wpcmcp-webhook:' . $id, wp_salt( 'auth' ) );
    }

    private static function send_webhook( array $hook, $event, array $payload ) {
        $body = wp_json_encode( array(
            'event' => $event,
            'delivery_id' => wp_generate_uuid4(),
            'sent_gmt' => gmdate( 'c' ),
            'site_url' => home_url( '/' ),
            'payload' => $payload,
        ), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );

        $signature = hash_hmac( 'sha256', $body, self::signing_key( $hook['id'] ) );
        $response = wp_safe_remote_post(
            $hook['url'],
            array(
                'timeout' => 10,
                'redirection' => 2,
                'headers' => array(
                    'Content-Type' => 'application/json',
                    'X-WPCMCP-Event' => $event,
                    'X-WPCMCP-Signature' => 'sha256=' . $signature,
                ),
                'body' => $body,
            )
        );

        $status = is_wp_error( $response ) ? 0 : (int) wp_remote_retrieve_response_code( $response );
        return array(
            'success' => ! is_wp_error( $response ) && $status >= 200 && $status < 300,
            'status' => $status,
            'error' => is_wp_error( $response ) ? $response->get_error_message() : null,
        );
    }

    private static function deliver_event( $event, array $payload ) {
        $hooks = self::webhooks();
        $changed = false;

        foreach ( $hooks as $id => &$hook ) {
            if ( empty( $hook['active'] ) || ! in_array( $event, (array) $hook['events'], true ) ) continue;
            $result = self::send_webhook( $hook, $event, $payload );
            $hook['last_status'] = $result['status'];
            $hook['last_delivery_gmt'] = gmdate( 'c' );
            $changed = true;
        }
        unset( $hook );

        if ( $changed ) self::save_webhooks( $hooks );
    }

    public static function dispatch_save_post( $post_id, $post, $update ) {
        if ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) return;
        self::deliver_event( 'save_post', array(
            'post_id' => (int) $post_id,
            'post_type' => $post->post_type,
            'status' => $post->post_status,
            'update' => (bool) $update,
        ) );
    }

    public static function dispatch_user_register( $user_id ) {
        self::deliver_event( 'user_register', array( 'user_id' => (int) $user_id ) );
    }

    public static function dispatch_comment_post( $comment_id, $approved, $commentdata ) {
        self::deliver_event( 'comment_post', array(
            'comment_id' => (int) $comment_id,
            'post_id' => isset( $commentdata['comment_post_ID'] ) ? (int) $commentdata['comment_post_ID'] : 0,
            'approved' => $approved,
        ) );
    }

    public static function dispatch_woo_order_status( $order_id, $from, $to, $order ) {
        self::deliver_event( 'woocommerce_order_status_changed', array(
            'order_id' => (int) $order_id,
            'from' => sanitize_key( $from ),
            'to' => sanitize_key( $to ),
        ) );
    }

    private static function webhook_test( array $args ) {
        if ( ! self::can_manage() ) return new WP_Error( 'forbidden', 'Testing webhooks requires manage_options.' );
        if ( empty( $args['confirm'] ) ) return new WP_Error( 'confirmation_required', 'Testing a webhook requires confirm=true.' );

        $id = sanitize_text_field( $args['webhook_id'] );
        $hooks = self::webhooks();
        if ( ! isset( $hooks[ $id ] ) ) return new WP_Error( 'not_found', 'Webhook not found.' );

        $result = self::send_webhook( $hooks[ $id ], 'test', array( 'message' => 'WP ChatGPT MCP webhook test' ) );
        return array( 'webhook_id' => $id ) + $result;
    }

    private static function check_url( $url ) {
        $url = esc_url_raw( $url, array( 'http', 'https' ) );
        if ( ! $url ) return array( 'url' => $url, 'ok' => false, 'error' => 'invalid_url' );
        $response = wp_safe_remote_get( $url, array( 'timeout' => 10, 'redirection' => 3 ) );
        if ( is_wp_error( $response ) ) return array( 'url' => $url, 'ok' => false, 'error' => $response->get_error_message() );
        $status = (int) wp_remote_retrieve_response_code( $response );
        return array( 'url' => $url, 'ok' => $status >= 200 && $status < 400, 'status' => $status );
    }

    private static function error_log_tail() {
        if ( ! defined( 'WP_DEBUG_LOG' ) || ! WP_DEBUG_LOG ) return null;
        $path = true === WP_DEBUG_LOG ? WP_CONTENT_DIR . '/debug.log' : WP_DEBUG_LOG;
        if ( ! is_string( $path ) || ! file_exists( $path ) || ! is_readable( $path ) ) return null;
        $size = filesize( $path );
        if ( false === $size ) return null;
        $read = min( 16384, $size );
        $handle = fopen( $path, 'rb' );
        if ( ! $handle ) return null;
        if ( $size > $read ) fseek( $handle, -$read, SEEK_END );
        $data = stream_get_contents( $handle );
        fclose( $handle );
        return $data ? substr( $data, -8192 ) : '';
    }

    private static function site_health_verify( array $args ) {
        if ( ! current_user_can( 'manage_options' ) ) return new WP_Error( 'forbidden', 'Site health verification requires manage_options.' );

        $urls = array( home_url( '/' ), rest_url() );
        foreach ( isset( $args['urls'] ) ? (array) $args['urls'] : array() as $url ) {
            $url = esc_url_raw( $url );
            if ( $url && 0 === strpos( $url, home_url() ) ) $urls[] = $url;
        }
        $urls = array_values( array_unique( $urls ) );

        $checks = array();
        foreach ( $urls as $url ) $checks[] = self::check_url( $url );

        $active_plugins = function_exists( 'get_option' ) ? (array) get_option( 'active_plugins', array() ) : array();
        $theme = wp_get_theme();

        return array(
            'ok' => ! in_array( false, wp_list_pluck( $checks, 'ok' ), true ),
            'http_checks' => $checks,
            'wp_cron_disabled' => defined( 'DISABLE_WP_CRON' ) && DISABLE_WP_CRON,
            'next_cron_gmt' => function_exists( 'wp_next_scheduled' ) ? self::next_cron_gmt() : null,
            'active_theme' => array( 'stylesheet' => $theme->get_stylesheet(), 'version' => $theme->get( 'Version' ) ),
            'active_plugin_count' => count( $active_plugins ),
            'debug_log_tail' => self::error_log_tail(),
        );
    }

    private static function next_cron_gmt() {
        $cron = _get_cron_array();
        if ( ! $cron ) return null;
        $timestamps = array_keys( $cron );
        sort( $timestamps, SORT_NUMERIC );
        return $timestamps ? gmdate( 'c', (int) $timestamps[0] ) : null;
    }
}
