<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class WPCMCP_Server {
    private static $instance = null;
    private $oauth;

    const MODERN_VERSION = '2026-07-28';
    const HEADER_MISMATCH = -32020;
    const UNSUPPORTED_PROTOCOL_VERSION = -32022;

    private $legacy_versions = array( '2025-11-25', '2025-06-18', '2025-03-26' );

    public static function instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        $this->oauth = WPCMCP_OAuth::instance();
        add_action( 'rest_api_init', array( $this, 'register_routes' ) );
        add_filter( 'rest_pre_serve_request', array( $this, 'protocol_headers' ), 10, 4 );
    }

    public function register_routes() {
        register_rest_route(
            'wp-chatgpt-mcp/v1',
            '/mcp',
            array(
                array(
                    'methods'             => 'POST',
                    'callback'            => array( $this, 'post' ),
                    'permission_callback' => '__return_true',
                ),
                array(
                    'methods'             => 'GET',
                    'callback'            => array( $this, 'get' ),
                    'permission_callback' => '__return_true',
                ),
                array(
                    'methods'             => 'DELETE',
                    'callback'            => array( $this, 'delete' ),
                    'permission_callback' => '__return_true',
                ),
            )
        );
    }

    public function protocol_headers( $served, $result, $request, $server ) {
        if ( 0 === strpos( $request->get_route(), '/wp-chatgpt-mcp/v1/mcp' ) ) {
            header( 'Cache-Control: no-store' );
            header( 'X-Content-Type-Options: nosniff' );
            header( 'Vary: Authorization, MCP-Protocol-Version, Mcp-Method, Mcp-Name', false );
        }
        return $served;
    }

    private function authenticate( WP_REST_Request $request ) {
        $auth = $this->oauth->authenticate_request( $request );
        if ( is_wp_error( $auth ) ) {
            return $auth;
        }
        return $auth;
    }

    private function validate_origin( WP_REST_Request $request ) {
        $origin = trim( (string) $request->get_header( 'origin' ) );
        if ( '' === $origin ) {
            return true;
        }

        $allowed = array(
            $this->origin_from_url( home_url( '/' ) ),
            'https://chatgpt.com',
            'https://chat.openai.com',
        );
        $allowed = apply_filters( 'wpcmcp_allowed_origins', array_values( array_filter( $allowed ) ), $request );

        $normalized = $this->origin_from_url( $origin );
        if ( ! $normalized || ! in_array( $normalized, $allowed, true ) ) {
            return new WP_Error( 'wpcmcp_invalid_origin', 'Origin is not allowed for this MCP endpoint.', array( 'status' => 403 ) );
        }
        return true;
    }

    private function origin_from_url( $url ) {
        $parts = wp_parse_url( $url );
        if ( empty( $parts['scheme'] ) || empty( $parts['host'] ) ) {
            return '';
        }
        $origin = strtolower( $parts['scheme'] ) . '://' . strtolower( $parts['host'] );
        if ( ! empty( $parts['port'] ) ) {
            $origin .= ':' . absint( $parts['port'] );
        }
        return $origin;
    }

    private function guard_request( WP_REST_Request $request ) {
        $origin = $this->validate_origin( $request );
        if ( is_wp_error( $origin ) ) {
            return new WP_REST_Response( array( 'error' => 'invalid_origin', 'error_description' => $origin->get_error_message() ), 403 );
        }

        $auth = $this->authenticate( $request );
        if ( is_wp_error( $auth ) ) {
            return WPCMCP_OAuth::unauthorized_response( $auth->get_error_message() );
        }
        return $auth;
    }

    public function get( WP_REST_Request $request ) {
        $auth = $this->guard_request( $request );
        if ( $auth instanceof WP_REST_Response ) {
            return $auth;
        }
        return new WP_REST_Response(
            array(
                'error'   => 'stream_not_supported',
                'message' => 'This WordPress MCP server is stateless and does not expose a GET event stream. Use POST requests.',
            ),
            405
        );
    }

    public function delete( WP_REST_Request $request ) {
        $auth = $this->guard_request( $request );
        if ( $auth instanceof WP_REST_Response ) {
            return $auth;
        }
        return new WP_REST_Response( null, 204 );
    }

    public function post( WP_REST_Request $request ) {
        $auth = $this->guard_request( $request );
        if ( $auth instanceof WP_REST_Response ) {
            return $auth;
        }

        $payload = $request->get_json_params();
        if ( ! is_array( $payload ) ) {
            return $this->jsonrpc_error( null, -32700, 'Parse error', null, 200 );
        }

        if ( ! isset( $payload['jsonrpc'] ) || '2.0' !== (string) $payload['jsonrpc'] ) {
            return $this->jsonrpc_error( isset( $payload['id'] ) ? $payload['id'] : null, -32600, 'Invalid Request', null, 200 );
        }

        if ( ! isset( $payload['method'] ) ) {
            if ( array_key_exists( 'result', $payload ) || array_key_exists( 'error', $payload ) ) {
                return new WP_REST_Response( null, 202 );
            }
            return $this->jsonrpc_error( isset( $payload['id'] ) ? $payload['id'] : null, -32600, 'Invalid Request', null, 200 );
        }

        $id = array_key_exists( 'id', $payload ) ? $payload['id'] : null;
        $method = is_string( $payload['method'] ) ? $payload['method'] : '';
        $params = isset( $payload['params'] ) ? $payload['params'] : array();

        if ( '' === $method || ( isset( $payload['params'] ) && ! is_array( $params ) ) ) {
            return $this->jsonrpc_error( $id, -32600, 'Invalid Request', null, 200 );
        }

        if ( null !== $id && ! is_int( $id ) && ! is_string( $id ) ) {
            return $this->jsonrpc_error( null, -32600, 'Invalid Request', null, 200 );
        }

        if ( null === $id ) {
            return new WP_REST_Response( null, 202 );
        }

        $modern = $this->is_modern_request( $request, $params );
        if ( $modern ) {
            $validation = $this->validate_modern_request( $request, $id, $method, $params );
            if ( $validation instanceof WP_REST_Response ) {
                return $validation;
            }
        }

        switch ( $method ) {
            case 'server/discover':
                if ( ! $modern ) {
                    return $this->jsonrpc_error( $id, -32601, 'Method not found' );
                }
                return $this->discover( $id );

            case 'initialize':
                if ( $modern ) {
                    return $this->jsonrpc_error( $id, -32601, 'Method not found' );
                }
                return $this->initialize( $id, $params );

            case 'ping':
                if ( $modern ) {
                    return $this->jsonrpc_error( $id, -32601, 'Method not found', null, 200, true );
                }
                return $this->jsonrpc_result( $id, (object) array() );

            case 'tools/list':
                return $this->list_tools( $id, $auth, $modern );

            case 'tools/call':
                return $this->call_tool( $id, $params, $auth, $modern );

            default:
                return $this->jsonrpc_error( $id, -32601, 'Method not found', null, 200, $modern );
        }
    }

    private function is_modern_request( WP_REST_Request $request, array $params ) {
        if ( self::MODERN_VERSION === trim( (string) $request->get_header( 'mcp-protocol-version' ) ) ) {
            return true;
        }
        if ( ! empty( $params['_meta']['io.modelcontextprotocol/protocolVersion'] ) && self::MODERN_VERSION === (string) $params['_meta']['io.modelcontextprotocol/protocolVersion'] ) {
            return true;
        }
        return false;
    }

    private function validate_modern_request( WP_REST_Request $request, $id, $method, array $params ) {
        $header_version = trim( (string) $request->get_header( 'mcp-protocol-version' ) );
        $meta = isset( $params['_meta'] ) && is_array( $params['_meta'] ) ? $params['_meta'] : array();
        $meta_version = isset( $meta['io.modelcontextprotocol/protocolVersion'] ) ? (string) $meta['io.modelcontextprotocol/protocolVersion'] : '';

        if ( self::MODERN_VERSION !== $header_version || self::MODERN_VERSION !== $meta_version ) {
            $requested = $header_version ? $header_version : $meta_version;
            return $this->jsonrpc_error(
                $id,
                self::UNSUPPORTED_PROTOCOL_VERSION,
                'Unsupported protocol version',
                array( 'supported' => array( self::MODERN_VERSION ), 'requested' => $requested ),
                400,
                true
            );
        }

        if ( ! isset( $meta['io.modelcontextprotocol/clientCapabilities'] ) || ! is_array( $meta['io.modelcontextprotocol/clientCapabilities'] ) ) {
            return $this->jsonrpc_error( $id, -32602, 'Invalid params', array( 'missing' => 'params._meta.io.modelcontextprotocol/clientCapabilities' ), 400, true );
        }

        if ( isset( $meta['io.modelcontextprotocol/clientInfo'] ) && ! is_array( $meta['io.modelcontextprotocol/clientInfo'] ) ) {
            return $this->jsonrpc_error( $id, -32602, 'Invalid params', array( 'invalid' => 'params._meta.io.modelcontextprotocol/clientInfo' ), 400, true );
        }

        $header_method = trim( (string) $request->get_header( 'mcp-method' ) );
        if ( '' === $header_method || ! hash_equals( $method, $header_method ) ) {
            return $this->jsonrpc_error(
                $id,
                self::HEADER_MISMATCH,
                'MCP HTTP header does not match JSON-RPC body.',
                array( 'header' => 'Mcp-Method', 'expected' => $method, 'received' => $header_method ),
                400,
                true
            );
        }

        if ( 'tools/call' === $method ) {
            $name = isset( $params['name'] ) ? (string) $params['name'] : '';
            $header_name = trim( (string) $request->get_header( 'mcp-name' ) );
            if ( '' === $name || '' === $header_name || ! hash_equals( $name, $header_name ) ) {
                return $this->jsonrpc_error(
                    $id,
                    self::HEADER_MISMATCH,
                    'MCP HTTP header does not match JSON-RPC body.',
                    array( 'header' => 'Mcp-Name', 'expected' => $name, 'received' => $header_name ),
                    400,
                    true
                );
            }
        }

        return true;
    }

    private function discover( $id ) {
        $result = array(
            'supportedVersions' => array_merge( array( self::MODERN_VERSION ), $this->legacy_versions ),
            'capabilities'      => array(
                'tools' => array( 'listChanged' => false ),
            ),
            'instructions'      => $this->instructions(),
            'ttlMs'             => 300000,
            'cacheScope'        => 'private',
        );
        return $this->jsonrpc_result( $id, $result, true );
    }

    private function initialize( $id, array $params ) {
        $requested = isset( $params['protocolVersion'] ) ? sanitize_text_field( $params['protocolVersion'] ) : '';
        $version = in_array( $requested, $this->legacy_versions, true ) ? $requested : $this->legacy_versions[0];

        return $this->jsonrpc_result(
            $id,
            array(
                'protocolVersion' => $version,
                'capabilities'    => array( 'tools' => array( 'listChanged' => false ) ),
                'serverInfo'      => $this->server_info(),
                'instructions'    => $this->instructions(),
            )
        );
    }

    private function list_tools( $id, array $auth, $modern ) {
        $tools = WPCMCP_Tools::definitions( $auth['scopes'] );
        usort(
            $tools,
            static function ( $a, $b ) {
                return strcmp( isset( $a['name'] ) ? $a['name'] : '', isset( $b['name'] ) ? $b['name'] : '' );
            }
        );

        $result = array( 'tools' => $tools );
        if ( $modern ) {
            $result['ttlMs'] = 60000;
            $result['cacheScope'] = 'private';
        }
        return $this->jsonrpc_result( $id, $result, $modern );
    }

    private function call_tool( $id, array $params, array $auth, $modern ) {
        $name = isset( $params['name'] ) ? sanitize_text_field( $params['name'] ) : '';
        $args = isset( $params['arguments'] ) && is_array( $params['arguments'] ) ? $params['arguments'] : array();

        if ( '' === $name ) {
            return $this->jsonrpc_error( $id, -32602, 'Invalid params', array( 'missing' => 'name' ), 200, $modern );
        }

        $definitions = WPCMCP_Tools::definitions( $auth['scopes'] );
        $allowed_names = wp_list_pluck( $definitions, 'name' );
        if ( ! in_array( $name, $allowed_names, true ) ) {
            return $this->jsonrpc_error( $id, -32602, 'Tool is unavailable for this connection or scope.', null, 200, $modern );
        }

        $definition = null;
        foreach ( $definitions as $candidate ) {
            if ( isset( $candidate['name'] ) && $name === $candidate['name'] ) {
                $definition = $candidate;
                break;
            }
        }
        $validation = $definition ? WPCMCP_Tools::validate_arguments( $definition, $args ) : new WP_Error( 'invalid_tool', 'Tool definition was not found.' );
        if ( is_wp_error( $validation ) ) {
            return $this->jsonrpc_error( $id, -32602, 'Invalid tool arguments', array( 'tool' => $name, 'message' => $validation->get_error_message() ), 200, $modern );
        }

        $result = WPCMCP_Tools::execute( $name, $args );
        if ( is_wp_error( $result ) ) {
            WPCMCP_DB::log( 'tool_call', $name, 'error', $auth['user_id'], $auth['client_id'], $result->get_error_message() );
            $tool_result = array(
                'content' => array( array( 'type' => 'text', 'text' => $result->get_error_message() ) ),
                'structuredContent' => array( 'error' => $result->get_error_code(), 'message' => $result->get_error_message() ),
                'isError' => true,
            );
            return $this->jsonrpc_result( $id, $tool_result, $modern );
        }

        $summary = self::log_summary( $name, $args, $result );
        WPCMCP_DB::log( 'tool_call', $name, 'success', $auth['user_id'], $auth['client_id'], $summary );

        return $this->jsonrpc_result(
            $id,
            array(
                'content' => array( array( 'type' => 'text', 'text' => wp_json_encode( $result, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) ) ),
                'structuredContent' => is_array( $result ) ? $result : array( 'result' => $result ),
                'isError' => false,
            ),
            $modern
        );
    }

    private function server_info() {
        return array(
            'name'        => 'wp-chatgpt-mcp',
            'title'       => get_bloginfo( 'name' ) . ' WordPress MCP',
            'version'     => WPCMCP_VERSION,
            'description' => 'Secure WordPress MCP server for ChatGPT, developed by ALIFY.',
            'websiteUrl'  => 'https://alify.site/',
        );
    }

    private function instructions() {
        return 'Use read tools to inspect WordPress. Use write tools only when they match an explicit user request. Prefer draft for new content, resolve exact IDs before mutations, and never invoke destructive, permanent-delete, user-role, plugin, theme, settings, order-status or menu-location actions without clear user intent.';
    }

    private static function log_summary( $name, array $args, $result ) {
        $parts = array( $name );
        if ( isset( $args['id'] ) ) $parts[] = 'id=' . absint( $args['id'] );
        if ( isset( $args['post_type'] ) ) $parts[] = 'type=' . sanitize_key( $args['post_type'] );
        if ( isset( $args['field'] ) ) $parts[] = 'field=' . sanitize_text_field( $args['field'] );
        if ( is_array( $result ) && isset( $result['status'] ) ) $parts[] = 'status=' . sanitize_key( $result['status'] );
        return implode( '; ', $parts );
    }

    private function jsonrpc_result( $id, $result, $modern = false ) {
        if ( $modern && is_array( $result ) ) {
            if ( ! isset( $result['_meta'] ) || ! is_array( $result['_meta'] ) ) {
                $result['_meta'] = array();
            }
            $result['_meta']['io.modelcontextprotocol/serverInfo'] = $this->server_info();
        }
        return new WP_REST_Response( array( 'jsonrpc' => '2.0', 'id' => $id, 'result' => $result ), 200 );
    }

    private function jsonrpc_error( $id, $code, $message, $data = null, $http_status = 200, $modern = false ) {
        $error = array( 'code' => (int) $code, 'message' => (string) $message );
        if ( null !== $data ) $error['data'] = $data;
        return new WP_REST_Response( array( 'jsonrpc' => '2.0', 'id' => $id, 'error' => $error ), $http_status );
    }
}
