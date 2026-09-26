<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class WPCMCP_OAuth {
    private static $instance = null;
    const ACCESS_TTL  = 3600;
    const REFRESH_TTL = 2592000;

    public static function instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action( 'init', array( __CLASS__, 'register_rewrites' ) );
        add_filter( 'query_vars', array( $this, 'query_vars' ) );
        add_action( 'template_redirect', array( $this, 'serve_well_known' ), 0 );
        add_action( 'admin_post_wpcmcp_oauth_authorize', array( $this, 'authorize' ) );
        add_action( 'admin_post_nopriv_wpcmcp_oauth_authorize', array( $this, 'authorize' ) );
        add_action( 'admin_post_wpcmcp_oauth_decision', array( $this, 'decision' ) );
        add_action( 'rest_api_init', array( $this, 'register_rest_routes' ) );
        add_filter( 'rest_pre_serve_request', array( $this, 'rest_security_headers' ), 10, 4 );
    }

    public static function register_rewrites() {
        add_rewrite_rule( '^\.well-known/oauth-protected-resource/?$', 'index.php?wpcmcp_well_known=protected', 'top' );
        add_rewrite_rule( '^\.well-known/oauth-protected-resource/wp-json/wp-chatgpt-mcp/v1/mcp/?$', 'index.php?wpcmcp_well_known=protected', 'top' );
        add_rewrite_rule( '^\.well-known/oauth-authorization-server/?$', 'index.php?wpcmcp_well_known=authorization', 'top' );
        add_rewrite_rule( '^\.well-known/openid-configuration/?$', 'index.php?wpcmcp_well_known=authorization', 'top' );
    }

    public function query_vars( $vars ) {
        $vars[] = 'wpcmcp_well_known';
        return $vars;
    }

    public static function resource_url() {
        return rest_url( 'wp-chatgpt-mcp/v1/mcp' );
    }

    public static function issuer_url() {
        return untrailingslashit( home_url( '/' ) );
    }

    public static function protected_metadata_url() {
        return rest_url( 'wp-chatgpt-mcp/v1/oauth/protected-resource-metadata' );
    }

    public static function authorization_metadata_url() {
        return rest_url( 'wp-chatgpt-mcp/v1/oauth/authorization-server-metadata' );
    }

    public function serve_well_known() {
        $kind = get_query_var( 'wpcmcp_well_known' );
        if ( ! $kind ) {
            return;
        }

        nocache_headers();
        header( 'Content-Type: application/json; charset=' . get_option( 'blog_charset' ) );

        if ( 'protected' === $kind ) {
            echo wp_json_encode( self::protected_resource_metadata_payload(), JSON_UNESCAPED_SLASHES );
            exit;
        }

        echo wp_json_encode( self::authorization_server_metadata_payload(), JSON_UNESCAPED_SLASHES );
        exit;
    }

    public function rest_security_headers( $served, $result, $request, $server ) {
        if ( 0 === strpos( $request->get_route(), '/wp-chatgpt-mcp/v1/oauth/' ) ) {
            header( 'Cache-Control: no-store, no-cache, must-revalidate, max-age=0' );
            header( 'Pragma: no-cache' );
            header( 'X-Content-Type-Options: nosniff' );
        }
        return $served;
    }

    public function register_rest_routes() {
        register_rest_route(
            'wp-chatgpt-mcp/v1',
            '/oauth/protected-resource-metadata',
            array(
                'methods'             => 'GET',
                'callback'            => array( $this, 'protected_resource_metadata_rest' ),
                'permission_callback' => '__return_true',
            )
        );

        register_rest_route(
            'wp-chatgpt-mcp/v1',
            '/oauth/authorization-server-metadata',
            array(
                'methods'             => 'GET',
                'callback'            => array( $this, 'authorization_server_metadata_rest' ),
                'permission_callback' => '__return_true',
            )
        );

        register_rest_route(
            'wp-chatgpt-mcp/v1',
            '/oauth/register',
            array(
                'methods'             => 'POST',
                'callback'            => array( $this, 'register_client' ),
                'permission_callback' => '__return_true',
            )
        );

        register_rest_route(
            'wp-chatgpt-mcp/v1',
            '/oauth/token',
            array(
                'methods'             => 'POST',
                'callback'            => array( $this, 'token' ),
                'permission_callback' => '__return_true',
            )
        );
    }

    private static function protected_resource_metadata_payload() {
        return array(
            'resource'                 => self::resource_url(),
            'authorization_servers'    => array( self::issuer_url() ),
            'scopes_supported'         => array( 'wordpress.read', 'wordpress.write' ),
            'bearer_methods_supported' => array( 'header' ),
            'resource_name'             => get_bloginfo( 'name' ) . ' WordPress MCP',
            'authorization_server_metadata' => self::authorization_metadata_url(),
        );
    }

    private static function authorization_server_metadata_payload() {
        return array(
            'issuer'                                   => self::issuer_url(),
            'authorization_endpoint'                   => admin_url( 'admin-post.php?action=wpcmcp_oauth_authorize' ),
            'token_endpoint'                           => rest_url( 'wp-chatgpt-mcp/v1/oauth/token' ),
            'registration_endpoint'                    => rest_url( 'wp-chatgpt-mcp/v1/oauth/register' ),
            'client_id_metadata_document_supported'    => true,
            'authorization_response_iss_parameter_supported' => true,
            'response_types_supported'                 => array( 'code' ),
            'grant_types_supported'                    => array( 'authorization_code', 'refresh_token' ),
            'code_challenge_methods_supported'         => array( 'S256' ),
            'token_endpoint_auth_methods_supported'    => array( 'none' ),
            'scopes_supported'                         => array( 'wordpress.read', 'wordpress.write' ),
            'service_documentation'                    => admin_url( 'admin.php?page=wpcmcp' ),
        );
    }

    public function protected_resource_metadata_rest() {
        $response = new WP_REST_Response( self::protected_resource_metadata_payload(), 200 );
        $response->header( 'Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0' );
        $response->header( 'Pragma', 'no-cache' );
        return $response;
    }

    public function authorization_server_metadata_rest() {
        $response = new WP_REST_Response( self::authorization_server_metadata_payload(), 200 );
        $response->header( 'Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0' );
        $response->header( 'Pragma', 'no-cache' );
        return $response;
    }

    public function register_client( WP_REST_Request $request ) {
        global $wpdb;
        if ( ! $this->rate_limit( 'register', 20, HOUR_IN_SECONDS ) ) {
            return new WP_REST_Response( array( 'error' => 'slow_down' ), 429 );
        }
        $body = $request->get_json_params();
        if ( ! is_array( $body ) ) {
            $body = $request->get_body_params();
        }

        $redirects = isset( $body['redirect_uris'] ) && is_array( $body['redirect_uris'] ) ? array_values( $body['redirect_uris'] ) : array();
        $valid = array();
        foreach ( $redirects as $uri ) {
            $uri = esc_url_raw( $uri );
            if ( $this->valid_redirect_uri( $uri ) ) {
                $valid[] = $uri;
            }
        }
        if ( empty( $valid ) ) {
            return new WP_REST_Response( array( 'error' => 'invalid_redirect_uri' ), 400 );
        }

        $client_id = 'wpcmcp_' . wp_generate_password( 40, false, false );
        $name = isset( $body['client_name'] ) ? sanitize_text_field( $body['client_name'] ) : 'MCP Client';
        $application_type = isset( $body['application_type'] ) ? sanitize_key( $body['application_type'] ) : 'web';
        if ( ! in_array( $application_type, array( 'web', 'native' ), true ) ) {
            $application_type = 'web';
        }

        $inserted = $wpdb->insert(
            WPCMCP_DB::table( 'clients' ),
            array(
                'client_id'                  => $client_id,
                'client_name'                => $name,
                'redirect_uris'              => wp_json_encode( $valid ),
                'grant_types'                => wp_json_encode( array( 'authorization_code', 'refresh_token' ) ),
                'response_types'             => wp_json_encode( array( 'code' ) ),
                'token_endpoint_auth_method' => 'none',
                'created_at'                 => current_time( 'mysql', true ),
            ),
            array( '%s', '%s', '%s', '%s', '%s', '%s', '%s' )
        );
        if ( false === $inserted ) {
            WPCMCP_DB::log( 'oauth_client_registered', '', 'error', 0, $client_id, 'client_insert_failed' );
            return new WP_REST_Response( array( 'error' => 'server_error', 'error_description' => 'Client registration storage failed.' ), 500 );
        }

        WPCMCP_DB::log( 'oauth_client_registered', '', 'success', 0, $client_id, $name );

        return new WP_REST_Response(
            array(
                'client_id'                  => $client_id,
                'client_name'                => $name,
                'redirect_uris'              => $valid,
                'grant_types'                => array( 'authorization_code', 'refresh_token' ),
                'response_types'             => array( 'code' ),
                'token_endpoint_auth_method' => 'none',
                'client_id_issued_at'         => time(),
                'application_type'            => $application_type,
            ),
            201
        );
    }

    private function sanitize_client_id( $client_id ) {
        $client_id = trim( (string) $client_id );
        if ( 0 === strpos( $client_id, 'https://' ) ) {
            return esc_url_raw( $client_id );
        }
        return sanitize_text_field( $client_id );
    }

    private function valid_redirect_uri( $uri ) {
        if ( ! $uri ) {
            return false;
        }
        $parts = wp_parse_url( $uri );
        if ( empty( $parts['scheme'] ) || empty( $parts['host'] ) ) {
            return false;
        }
        if ( 'https' === strtolower( $parts['scheme'] ) ) {
            return true;
        }
        $host = strtolower( $parts['host'] );
        return 'http' === strtolower( $parts['scheme'] ) && in_array( $host, array( 'localhost', '127.0.0.1', '::1' ), true );
    }

    private function get_client( $client_id ) {
        global $wpdb;
        $row = $wpdb->get_row(
            $wpdb->prepare( 'SELECT * FROM ' . WPCMCP_DB::table( 'clients' ) . ' WHERE client_id = %s', $client_id ) // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
        );
        if ( $row ) {
            return $row;
        }

        // MCP 2026-07-28 prefers Client ID Metadata Documents (CIMD).
        if ( 0 === strpos( $client_id, 'https://' ) ) {
            $metadata = $this->fetch_client_metadata_document( $client_id );
            if ( is_wp_error( $metadata ) ) {
                return null;
            }
            return (object) array(
                'client_id'                  => $client_id,
                'client_name'                => $metadata['client_name'],
                'redirect_uris'              => wp_json_encode( $metadata['redirect_uris'] ),
                'grant_types'                => wp_json_encode( isset( $metadata['grant_types'] ) ? $metadata['grant_types'] : array( 'authorization_code' ) ),
                'response_types'             => wp_json_encode( isset( $metadata['response_types'] ) ? $metadata['response_types'] : array( 'code' ) ),
                'token_endpoint_auth_method' => isset( $metadata['token_endpoint_auth_method'] ) ? $metadata['token_endpoint_auth_method'] : 'none',
            );
        }
        return null;
    }

    private function fetch_client_metadata_document( $client_id ) {
        $parts = wp_parse_url( $client_id );
        if (
            empty( $parts['scheme'] ) || 'https' !== strtolower( $parts['scheme'] ) ||
            empty( $parts['host'] ) || empty( $parts['path'] ) || '/' === $parts['path'] ||
            ! empty( $parts['user'] ) || ! empty( $parts['pass'] ) || ! empty( $parts['fragment'] ) || ! empty( $parts['query'] )
        ) {
            return new WP_Error( 'invalid_client_metadata_url', 'Invalid Client ID Metadata Document URL.' );
        }

        $response = wp_safe_remote_get(
            $client_id,
            array(
                'timeout'     => 8,
                'redirection' => 2,
                'headers'     => array( 'Accept' => 'application/json' ),
            )
        );
        if ( is_wp_error( $response ) ) {
            return $response;
        }
        if ( 200 !== (int) wp_remote_retrieve_response_code( $response ) ) {
            return new WP_Error( 'client_metadata_unavailable', 'Client metadata document could not be loaded.' );
        }
        $body = json_decode( wp_remote_retrieve_body( $response ), true );
        if ( ! is_array( $body ) || empty( $body['client_id'] ) || ! hash_equals( $client_id, (string) $body['client_id'] ) ) {
            return new WP_Error( 'invalid_client_metadata', 'Client metadata document client_id does not match its URL.' );
        }
        if ( empty( $body['client_name'] ) || empty( $body['redirect_uris'] ) || ! is_array( $body['redirect_uris'] ) ) {
            return new WP_Error( 'invalid_client_metadata', 'Client metadata document is missing required fields.' );
        }

        $redirects = array();
        foreach ( $body['redirect_uris'] as $uri ) {
            $uri = esc_url_raw( $uri );
            if ( $this->valid_redirect_uri( $uri ) ) {
                $redirects[] = $uri;
            }
        }
        if ( empty( $redirects ) ) {
            return new WP_Error( 'invalid_client_metadata', 'Client metadata document has no valid redirect URIs.' );
        }

        return array(
            'client_id'                  => $client_id,
            'client_name'                => sanitize_text_field( $body['client_name'] ),
            'redirect_uris'              => array_values( array_unique( $redirects ) ),
            'grant_types'                => ! empty( $body['grant_types'] ) && is_array( $body['grant_types'] ) ? array_values( $body['grant_types'] ) : array( 'authorization_code' ),
            'response_types'             => ! empty( $body['response_types'] ) && is_array( $body['response_types'] ) ? array_values( $body['response_types'] ) : array( 'code' ),
            'token_endpoint_auth_method' => isset( $body['token_endpoint_auth_method'] ) ? sanitize_text_field( $body['token_endpoint_auth_method'] ) : 'none',
        );
    }

    private function valid_pkce_challenge( $challenge ) {
        return is_string( $challenge ) && 1 === preg_match( '/^[A-Za-z0-9_-]{43}$/', $challenge );
    }

    private function valid_pkce_verifier( $verifier ) {
        return is_string( $verifier ) && 1 === preg_match( '/^[A-Za-z0-9._~-]{43,128}$/', $verifier );
    }

    public function authorize() {
        $params = wp_unslash( $_GET ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $client_id = isset( $params['client_id'] ) ? $this->sanitize_client_id( $params['client_id'] ) : '';
        $redirect_uri = isset( $params['redirect_uri'] ) ? esc_url_raw( $params['redirect_uri'] ) : '';
        $response_type = isset( $params['response_type'] ) ? sanitize_key( $params['response_type'] ) : '';
        $code_challenge = isset( $params['code_challenge'] ) ? sanitize_text_field( $params['code_challenge'] ) : '';
        $challenge_method = isset( $params['code_challenge_method'] ) ? sanitize_text_field( $params['code_challenge_method'] ) : '';
        $state = isset( $params['state'] ) ? sanitize_text_field( $params['state'] ) : '';
        $resource = isset( $params['resource'] ) ? esc_url_raw( $params['resource'] ) : '';
        $scope = isset( $params['scope'] ) ? sanitize_text_field( $params['scope'] ) : self::default_scope_string();

        $client = $this->get_client( $client_id );
        if ( ! $client || 'code' !== $response_type || 'S256' !== $challenge_method || ! $this->valid_pkce_challenge( $code_challenge ) ) {
            wp_die( esc_html__( 'Invalid OAuth authorization request.', 'wp-chatgpt-mcp' ), 400 );
        }

        $redirects = json_decode( $client->redirect_uris, true );
        if ( ! is_array( $redirects ) || ! in_array( $redirect_uri, $redirects, true ) ) {
            wp_die( esc_html__( 'Unregistered OAuth redirect URI.', 'wp-chatgpt-mcp' ), 400 );
        }

        if ( untrailingslashit( $resource ) !== untrailingslashit( self::resource_url() ) ) {
            wp_die( esc_html__( 'Invalid MCP resource audience.', 'wp-chatgpt-mcp' ), 400 );
        }

        if ( ! is_user_logged_in() ) {
            wp_safe_redirect( wp_login_url( $this->current_authorize_url() ) );
            exit;
        }

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'Only a WordPress administrator can authorize ChatGPT MCP access.', 'wp-chatgpt-mcp' ), 403 );
        }

        $scopes = $this->normalize_scopes( $scope );
        $payload = array(
            'client_id'      => $client_id,
            'redirect_uri'   => $redirect_uri,
            'code_challenge' => $code_challenge,
            'state'          => $state,
            'resource'       => $resource,
            'scope'          => implode( ' ', $scopes ),
            'iat'            => time(),
        );
        $signed = $this->sign_payload( $payload );

        status_header( 200 );
        nocache_headers();
        ?><!doctype html>
        <html <?php language_attributes(); ?>>
        <head>
            <meta charset="<?php bloginfo( 'charset' ); ?>">
            <meta name="viewport" content="width=device-width,initial-scale=1">
            <title><?php esc_html_e( 'Authorize ChatGPT MCP', 'wp-chatgpt-mcp' ); ?></title>
            <?php wp_admin_css( 'login', true ); ?>
            <style>body{background:#f0f0f1}.wpcmcp-consent{max-width:560px;margin:6vh auto;background:#fff;border:1px solid #dcdcde;border-radius:12px;padding:28px;box-shadow:0 8px 30px rgba(0,0,0,.06)}.wpcmcp-consent h1{font-size:24px;margin:0 0 14px}.wpcmcp-consent ul{background:#f6f7f7;padding:16px 16px 16px 34px;border-radius:8px}.wpcmcp-actions{display:flex;gap:10px;margin-top:24px}.wpcmcp-actions button{padding:10px 18px}.wpcmcp-muted{color:#646970}</style>
        </head>
        <body>
            <div class="wpcmcp-consent">
                <h1><?php esc_html_e( 'Connect ChatGPT to this WordPress site?', 'wp-chatgpt-mcp' ); ?></h1>
                <p><strong><?php echo esc_html( $client->client_name ); ?></strong> <?php esc_html_e( 'is requesting MCP access to', 'wp-chatgpt-mcp' ); ?> <strong><?php bloginfo( 'name' ); ?></strong>.</p>
                <ul>
                    <?php if ( in_array( 'wordpress.read', $scopes, true ) ) : ?><li><?php esc_html_e( 'Read posts, pages, custom post types and supported ACF data.', 'wp-chatgpt-mcp' ); ?></li><?php endif; ?>
                    <?php if ( in_array( 'wordpress.write', $scopes, true ) ) : ?><li><strong><?php esc_html_e( 'Create, modify, moderate, install, activate or delete authorized WordPress resources when a write tool is invoked. Permanent/destructive actions require explicit confirmation.', 'wp-chatgpt-mcp' ); ?></strong></li><?php endif; ?>
                </ul>
                <p class="wpcmcp-muted"><?php esc_html_e( 'ChatGPT may separately ask you to confirm write actions. You can revoke this connection from the plugin admin page.', 'wp-chatgpt-mcp' ); ?></p>
                <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                    <input type="hidden" name="action" value="wpcmcp_oauth_decision">
                    <input type="hidden" name="request" value="<?php echo esc_attr( $signed ); ?>">
                    <?php wp_nonce_field( 'wpcmcp_oauth_decision' ); ?>
                    <div class="wpcmcp-actions">
                        <button type="submit" class="button button-primary" name="decision" value="allow"><?php esc_html_e( 'Allow', 'wp-chatgpt-mcp' ); ?></button>
                        <button type="submit" class="button" name="decision" value="deny"><?php esc_html_e( 'Deny', 'wp-chatgpt-mcp' ); ?></button>
                    </div>
                </form>
            </div>
        </body></html><?php
        exit;
    }

    private function current_authorize_url() {
        // Build the login return URL from the configured site origin rather than
        // the untrusted Host header to avoid host-header poisoning.
        $uri = isset( $_SERVER['REQUEST_URI'] ) ? wp_unslash( $_SERVER['REQUEST_URI'] ) : '/';
        $parts = wp_parse_url( $uri );
        $path = isset( $parts['path'] ) ? $parts['path'] : '/';
        $query = isset( $parts['query'] ) ? '?' . $parts['query'] : '';
        return esc_url_raw( home_url( $path . $query ) );
    }

    private function sign_payload( array $payload ) {
        $json = wp_json_encode( $payload );
        $data = rtrim( strtr( base64_encode( $json ), '+/', '-_' ), '=' );
        $sig  = hash_hmac( 'sha256', $data, wp_salt( 'auth' ) );
        return $data . '.' . $sig;
    }

    private function verify_payload( $signed ) {
        $parts = explode( '.', (string) $signed, 2 );
        if ( 2 !== count( $parts ) ) {
            return false;
        }
        list( $data, $sig ) = $parts;
        $expected = hash_hmac( 'sha256', $data, wp_salt( 'auth' ) );
        if ( ! hash_equals( $expected, $sig ) ) {
            return false;
        }
        $base64 = strtr( $data, '-_', '+/' );
        $padding = strlen( $base64 ) % 4;
        if ( $padding ) {
            $base64 .= str_repeat( '=', 4 - $padding );
        }
        $decoded = base64_decode( $base64, true );
        if ( false === $decoded ) {
            return false;
        }
        $payload = json_decode( $decoded, true );
        if ( ! is_array( $payload ) || empty( $payload['iat'] ) || abs( time() - (int) $payload['iat'] ) > 600 ) {
            return false;
        }
        return $payload;
    }

    public function decision() {
        if ( ! is_user_logged_in() || ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'Not allowed.', 'wp-chatgpt-mcp' ), 403 );
        }
        check_admin_referer( 'wpcmcp_oauth_decision' );

        $request = isset( $_POST['request'] ) ? sanitize_text_field( wp_unslash( $_POST['request'] ) ) : '';
        $decision = isset( $_POST['decision'] ) ? sanitize_key( $_POST['decision'] ) : 'deny';
        $payload = $this->verify_payload( $request );
        if ( ! $payload ) {
            wp_die( esc_html__( 'Authorization request expired or invalid.', 'wp-chatgpt-mcp' ), 400 );
        }

        $redirect = $payload['redirect_uri'];
        $state = $payload['state'];

        if ( 'allow' !== $decision ) {
            $url = add_query_arg( array_filter( array( 'error' => 'access_denied', 'state' => $state, 'iss' => self::issuer_url() ) ), $redirect );
            wp_redirect( $url ); // phpcs:ignore WordPress.Security.SafeRedirect.wp_redirect_wp_redirect
            exit;
        }

        global $wpdb;
        $plain_code = wp_generate_password( 64, false, false );
        $inserted = $wpdb->insert(
            WPCMCP_DB::table( 'codes' ),
            array(
                'code_hash'      => hash( 'sha256', $plain_code ),
                'client_id'      => $payload['client_id'],
                'user_id'        => get_current_user_id(),
                'redirect_uri'   => $payload['redirect_uri'],
                'code_challenge' => $payload['code_challenge'],
                'scope'          => $payload['scope'],
                'resource'       => $payload['resource'],
                'expires_at'     => gmdate( 'Y-m-d H:i:s', time() + 600 ),
                'created_at'     => current_time( 'mysql', true ),
            ),
            array( '%s', '%s', '%d', '%s', '%s', '%s', '%s', '%s', '%s' )
        );
        if ( false === $inserted ) {
            WPCMCP_DB::log( 'oauth_authorized', '', 'error', get_current_user_id(), $payload['client_id'], 'authorization_code_insert_failed' );
            wp_die( esc_html__( 'WordPress could not create the authorization code. Please try again.', 'wp-chatgpt-mcp' ), 500 );
        }

        WPCMCP_DB::log( 'oauth_authorized', '', 'success', get_current_user_id(), $payload['client_id'], $payload['scope'] );

        $url = add_query_arg( array_filter( array( 'code' => $plain_code, 'state' => $state, 'iss' => self::issuer_url() ) ), $redirect );
        wp_redirect( $url ); // phpcs:ignore WordPress.Security.SafeRedirect.wp_redirect_wp_redirect
        exit;
    }

    public function token( WP_REST_Request $request ) {
        if ( ! $this->rate_limit( 'token', 60, HOUR_IN_SECONDS ) ) {
            return new WP_REST_Response( array( 'error' => 'slow_down' ), 429 );
        }
        $params = $request->get_body_params();
        if ( empty( $params ) ) {
            $json = $request->get_json_params();
            if ( is_array( $json ) ) {
                $params = $json;
            }
        }
        $grant = isset( $params['grant_type'] ) ? sanitize_text_field( $params['grant_type'] ) : '';
        if ( 'authorization_code' === $grant ) {
            return $this->exchange_code( $params );
        }
        if ( 'refresh_token' === $grant ) {
            return $this->refresh_token( $params );
        }
        return new WP_REST_Response( array( 'error' => 'unsupported_grant_type' ), 400 );
    }

    private function exchange_code( array $params ) {
        global $wpdb;
        $code = isset( $params['code'] ) ? (string) $params['code'] : '';
        $client_id = isset( $params['client_id'] ) ? $this->sanitize_client_id( $params['client_id'] ) : '';
        $redirect_uri = isset( $params['redirect_uri'] ) ? esc_url_raw( $params['redirect_uri'] ) : '';
        $verifier = isset( $params['code_verifier'] ) ? (string) $params['code_verifier'] : '';
        if ( ! $this->valid_pkce_verifier( $verifier ) ) {
            return new WP_REST_Response( array( 'error' => 'invalid_grant' ), 400 );
        }
        $resource = isset( $params['resource'] ) ? esc_url_raw( $params['resource'] ) : '';

        $row = $wpdb->get_row(
            $wpdb->prepare( 'SELECT * FROM ' . WPCMCP_DB::table( 'codes' ) . ' WHERE code_hash = %s', hash( 'sha256', $code ) ) // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
        );
        if ( ! $row || $row->used_at || strtotime( $row->expires_at . ' UTC' ) < time() ) {
            return new WP_REST_Response( array( 'error' => 'invalid_grant' ), 400 );
        }
        if ( ! hash_equals( (string) $row->client_id, $client_id ) || ! hash_equals( (string) $row->redirect_uri, $redirect_uri ) ) {
            return new WP_REST_Response( array( 'error' => 'invalid_grant' ), 400 );
        }
        if ( untrailingslashit( $resource ) !== untrailingslashit( $row->resource ) ) {
            return new WP_REST_Response( array( 'error' => 'invalid_target' ), 400 );
        }
        $challenge = rtrim( strtr( base64_encode( hash( 'sha256', $verifier, true ) ), '+/', '-_' ), '=' );
        if ( ! hash_equals( (string) $row->code_challenge, $challenge ) ) {
            return new WP_REST_Response( array( 'error' => 'invalid_grant' ), 400 );
        }

        $consumed = $wpdb->query(
            $wpdb->prepare(
                'UPDATE ' . WPCMCP_DB::table( 'codes' ) . ' SET used_at = %s WHERE id = %d AND used_at IS NULL',
                current_time( 'mysql', true ),
                (int) $row->id
            )
        ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
        if ( 1 !== (int) $consumed ) {
            return new WP_REST_Response( array( 'error' => 'invalid_grant' ), 400 );
        }
        return $this->issue_tokens( $client_id, (int) $row->user_id, $row->scope, $row->resource );
    }

    private function refresh_token( array $params ) {
        global $wpdb;
        $refresh = isset( $params['refresh_token'] ) ? (string) $params['refresh_token'] : '';
        $client_id = isset( $params['client_id'] ) ? $this->sanitize_client_id( $params['client_id'] ) : '';
        $resource = isset( $params['resource'] ) ? esc_url_raw( $params['resource'] ) : '';

        $row = $wpdb->get_row(
            $wpdb->prepare( 'SELECT * FROM ' . WPCMCP_DB::table( 'tokens' ) . ' WHERE refresh_hash = %s', hash( 'sha256', $refresh ) ) // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
        );
        if ( ! $row || $row->revoked_at || strtotime( $row->refresh_expires_at . ' UTC' ) < time() ) {
            return new WP_REST_Response( array( 'error' => 'invalid_grant' ), 400 );
        }
        if ( ! hash_equals( (string) $row->client_id, $client_id ) || untrailingslashit( $resource ) !== untrailingslashit( $row->resource ) ) {
            return new WP_REST_Response( array( 'error' => 'invalid_grant' ), 400 );
        }

        $now = current_time( 'mysql', true );
        $rotated = $wpdb->query(
            $wpdb->prepare(
                'UPDATE ' . WPCMCP_DB::table( 'tokens' ) . ' SET revoked_at = %s, updated_at = %s WHERE id = %d AND revoked_at IS NULL',
                $now,
                $now,
                (int) $row->id
            )
        ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
        if ( 1 !== (int) $rotated ) {
            return new WP_REST_Response( array( 'error' => 'invalid_grant' ), 400 );
        }
        return $this->issue_tokens( $client_id, (int) $row->user_id, $row->scope, $row->resource );
    }

    private function issue_tokens( $client_id, $user_id, $scope, $resource ) {
        global $wpdb;
        $access  = 'wpa_' . wp_generate_password( 64, false, false );
        $refresh = 'wpr_' . wp_generate_password( 72, false, false );
        $now = current_time( 'mysql', true );

        $inserted = $wpdb->insert(
            WPCMCP_DB::table( 'tokens' ),
            array(
                'access_hash'       => hash( 'sha256', $access ),
                'refresh_hash'      => hash( 'sha256', $refresh ),
                'client_id'         => $client_id,
                'user_id'           => $user_id,
                'scope'             => $scope,
                'resource'          => $resource,
                'access_expires_at' => gmdate( 'Y-m-d H:i:s', time() + self::ACCESS_TTL ),
                'refresh_expires_at'=> gmdate( 'Y-m-d H:i:s', time() + self::REFRESH_TTL ),
                'created_at'        => $now,
                'updated_at'        => $now,
            ),
            array( '%s', '%s', '%s', '%d', '%s', '%s', '%s', '%s', '%s', '%s' )
        );
        if ( false === $inserted ) {
            WPCMCP_DB::log( 'oauth_token_issued', '', 'error', $user_id, $client_id, 'token_insert_failed' );
            return new WP_REST_Response( array( 'error' => 'server_error', 'error_description' => 'Token storage failed.' ), 500 );
        }

        WPCMCP_DB::log( 'oauth_token_issued', '', 'success', $user_id, $client_id, $scope );

        return new WP_REST_Response(
            array(
                'access_token'  => $access,
                'token_type'    => 'Bearer',
                'expires_in'    => self::ACCESS_TTL,
                'refresh_token' => $refresh,
                'scope'         => $scope,
            ),
            200
        );
    }

    public function authenticate_request( WP_REST_Request $request ) {
        global $wpdb;
        $header = $request->get_header( 'authorization' );
        if ( ! $header || 0 !== stripos( $header, 'Bearer ' ) ) {
            return new WP_Error( 'wpcmcp_unauthorized', 'Authorization required.', array( 'status' => 401 ) );
        }
        $token = trim( substr( $header, 7 ) );
        if ( ! $token ) {
            return new WP_Error( 'wpcmcp_unauthorized', 'Invalid bearer token.', array( 'status' => 401 ) );
        }

        $row = $wpdb->get_row(
            $wpdb->prepare( 'SELECT * FROM ' . WPCMCP_DB::table( 'tokens' ) . ' WHERE access_hash = %s', hash( 'sha256', $token ) ) // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
        );
        if ( ! $row || $row->revoked_at || strtotime( $row->access_expires_at . ' UTC' ) < time() ) {
            return new WP_Error( 'wpcmcp_unauthorized', 'Access token is invalid or expired.', array( 'status' => 401 ) );
        }
        if ( untrailingslashit( $row->resource ) !== untrailingslashit( self::resource_url() ) ) {
            return new WP_Error( 'wpcmcp_unauthorized', 'Token audience is invalid.', array( 'status' => 401 ) );
        }

        $user = get_user_by( 'id', (int) $row->user_id );
        if ( ! $user ) {
            return new WP_Error( 'wpcmcp_unauthorized', 'WordPress user no longer exists.', array( 'status' => 401 ) );
        }
        wp_set_current_user( $user->ID );

        return array(
            'user_id'   => (int) $row->user_id,
            'client_id' => $row->client_id,
            'scopes'    => $this->normalize_scopes( $row->scope ),
            'token_id'  => (int) $row->id,
        );
    }

    private function normalize_scopes( $scope ) {
        $requested = array_filter( preg_split( '/\s+/', (string) $scope ) );
        $allowed = array( 'wordpress.read', 'wordpress.write' );
        $scopes = array_values( array_intersect( $requested, $allowed ) );
        if ( empty( $scopes ) ) {
            $scopes[] = 'wordpress.read';
        }
        if ( in_array( 'wordpress.write', $scopes, true ) && ! in_array( 'wordpress.read', $scopes, true ) ) {
            $scopes[] = 'wordpress.read';
        }
        return array_values( array_unique( $scopes ) );
    }

    public static function unauthorized_response( $message = 'Authorization required.' ) {
        $response = new WP_REST_Response( array( 'error' => 'unauthorized', 'error_description' => $message ), 401 );
        $response->header( 'WWW-Authenticate', 'Bearer resource_metadata="' . self::protected_metadata_url() . '" scope="' . self::default_scope_string() . '"' );
        return $response;
    }

    public static function default_scope_string() {
        $settings = get_option( 'wpcmcp_settings', array( 'read_tools' => 1, 'write_tools' => 1 ) );
        $scopes = array( 'wordpress.read' );
        if ( ! empty( $settings['write_tools'] ) ) {
            $scopes[] = 'wordpress.write';
        }
        return implode( ' ', $scopes );
    }

    private function client_ip() {
        $remote = isset( $_SERVER['REMOTE_ADDR'] ) ? trim( (string) wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
        $ip = filter_var( $remote, FILTER_VALIDATE_IP ) ? $remote : 'unknown';

        // Proxy headers are attacker-controlled unless the site operator explicitly
        // opts in. WPCMCP_TRUST_PROXY_HEADERS should only be enabled behind a trusted
        // reverse proxy/CDN that overwrites these headers.
        if ( defined( 'WPCMCP_TRUST_PROXY_HEADERS' ) && WPCMCP_TRUST_PROXY_HEADERS ) {
            $candidates = array();
            if ( ! empty( $_SERVER['HTTP_CF_CONNECTING_IP'] ) ) {
                $candidates[] = trim( (string) wp_unslash( $_SERVER['HTTP_CF_CONNECTING_IP'] ) );
            }
            if ( ! empty( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) {
                $forwarded = explode( ',', (string) wp_unslash( $_SERVER['HTTP_X_FORWARDED_FOR'] ) );
                if ( ! empty( $forwarded[0] ) ) {
                    $candidates[] = trim( $forwarded[0] );
                }
            }
            if ( ! empty( $_SERVER['HTTP_X_REAL_IP'] ) ) {
                $candidates[] = trim( (string) wp_unslash( $_SERVER['HTTP_X_REAL_IP'] ) );
            }
            foreach ( $candidates as $candidate ) {
                if ( filter_var( $candidate, FILTER_VALIDATE_IP ) ) {
                    $ip = $candidate;
                    break;
                }
            }
        }

        return (string) apply_filters( 'wpcmcp_client_ip', $ip, $remote );
    }

    private function rate_limit( $bucket, $limit, $window ) {
        $ip = $this->client_ip();
        $key = 'wpcmcp_rl_' . hash( 'sha256', $bucket . '|' . $ip );
        $data = get_transient( $key );
        if ( ! is_array( $data ) ) {
            set_transient( $key, array( 'count' => 1 ), $window );
            return true;
        }
        $count = isset( $data['count'] ) ? absint( $data['count'] ) : 0;
        if ( $count >= $limit ) {
            return false;
        }
        $data['count'] = $count + 1;
        set_transient( $key, $data, $window );
        return true;
    }

    public static function revoke_token_id( $id ) {
        global $wpdb;
        return $wpdb->update(
            WPCMCP_DB::table( 'tokens' ),
            array( 'revoked_at' => current_time( 'mysql', true ), 'updated_at' => current_time( 'mysql', true ) ),
            array( 'id' => absint( $id ) ),
            array( '%s', '%s' ),
            array( '%d' )
        );
    }
}
