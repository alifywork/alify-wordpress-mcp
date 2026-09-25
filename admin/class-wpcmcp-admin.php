<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class WPCMCP_Admin {
    private static $instance = null;

    public static function instance() {
        if ( null === self::$instance ) self::$instance = new self();
        return self::$instance;
    }

    private function __construct() {
        add_action( 'admin_menu', array( $this, 'menu' ) );
        add_action( 'admin_init', array( $this, 'settings' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'assets' ) );
        add_action( 'admin_post_wpcmcp_revoke', array( $this, 'revoke' ) );
        add_action( 'admin_post_wpcmcp_flush_rewrites', array( $this, 'flush_rewrites' ) );
    }

    public function menu() {
        add_menu_page( 'WP ChatGPT MCP', 'ChatGPT MCP', 'manage_options', 'wpcmcp', array( $this, 'connection_page' ), 'dashicons-rest-api', 58 );
        add_submenu_page( 'wpcmcp', 'Connection', 'Connection', 'manage_options', 'wpcmcp', array( $this, 'connection_page' ) );
        add_submenu_page( 'wpcmcp', 'Settings', 'Settings', 'manage_options', 'wpcmcp-settings', array( $this, 'settings_page' ) );
        add_submenu_page( 'wpcmcp', 'Connections', 'Connections', 'manage_options', 'wpcmcp-connections', array( $this, 'connections_page' ) );
        add_submenu_page( 'wpcmcp', 'Activity Logs', 'Activity Logs', 'manage_options', 'wpcmcp-logs', array( $this, 'logs_page' ) );
    }

    public function settings() {
        register_setting( 'wpcmcp_settings_group', 'wpcmcp_settings', array( $this, 'sanitize_settings' ) );
    }

    public function sanitize_settings( $input ) {
        return array(
            'read_tools' => ! empty( $input['read_tools'] ) ? 1 : 0,
            'write_tools' => ! empty( $input['write_tools'] ) ? 1 : 0,
            'log_days' => min( 365, max( 1, absint( isset( $input['log_days'] ) ? $input['log_days'] : 30 ) ) ),
        );
    }

    public function assets( $hook ) {
        if ( false === strpos( $hook, 'wpcmcp' ) ) return;
        wp_enqueue_style( 'wpcmcp-admin', WPCMCP_URL . 'assets/css/admin.css', array(), WPCMCP_VERSION );
        wp_enqueue_script( 'wpcmcp-admin', WPCMCP_URL . 'assets/js/admin.js', array(), WPCMCP_VERSION, true );
    }

    private function header( $title, $subtitle = '' ) {
        echo '<div class="wrap wpcmcp-wrap"><div class="wpcmcp-head"><div><h1>' . esc_html( $title ) . '</h1>';
        if ( $subtitle ) echo '<p>' . esc_html( $subtitle ) . '</p>';
        echo '</div><div><span class="wpcmcp-version">v' . esc_html( WPCMCP_VERSION ) . '</span><p class="wpcmcp-brand">Made by <a href="' . esc_url( 'https://alify.site/' ) . '" target="_blank" rel="noopener noreferrer">ALIFY</a></p></div></div>';
    }

    public function connection_page() {
        $this->header( 'WP ChatGPT MCP', 'Direct ChatGPT ↔ WordPress connection. No OpenAI API key is used by this plugin.' );
        $https = is_ssl() || 'https' === wp_parse_url( home_url(), PHP_URL_SCHEME );
        $pretty = '' !== get_option( 'permalink_structure' );
        ?>
        <div class="wpcmcp-grid">
            <div class="wpcmcp-card wpcmcp-card-main">
                <div class="wpcmcp-kicker">MCP ENDPOINT</div>
                <h2><?php echo $https && $pretty ? '<span class="wpcmcp-dot ok"></span> Ready for remote connection' : '<span class="wpcmcp-dot warn"></span> Configuration required'; ?></h2>
                <label>Remote MCP URL</label>
                <div class="wpcmcp-copy"><code id="wpcmcp-endpoint"><?php echo esc_html( WPCMCP_OAuth::resource_url() ); ?></code><button class="button" data-copy="#wpcmcp-endpoint">Copy</button></div>
                <p>In ChatGPT Developer Mode, create a custom app, provide this endpoint, choose OAuth when prompted, then scan tools.</p>
                <div class="wpcmcp-statuses">
                    <div><span class="wpcmcp-dot <?php echo $https ? 'ok' : 'bad'; ?>"></span><strong>HTTPS</strong><small><?php echo $https ? 'Enabled' : 'Required for OAuth'; ?></small></div>
                    <div><span class="wpcmcp-dot <?php echo $pretty ? 'ok' : 'bad'; ?>"></span><strong>Pretty permalinks</strong><small><?php echo $pretty ? 'Enabled' : 'Enable in Settings → Permalinks'; ?></small></div>
                    <div><span class="wpcmcp-dot ok"></span><strong>OAuth + PKCE</strong><small>CIMD + DCR fallback</small></div>
                    <div><span class="wpcmcp-dot ok"></span><strong>MCP</strong><small>2026-07-28 + legacy compatibility</small></div>
                </div>
            </div>
            <div class="wpcmcp-card">
                <div class="wpcmcp-kicker">DISCOVERY</div>
                <h2>OAuth metadata</h2>
                <p><strong>Protected resource</strong></p><code class="wpcmcp-block"><?php echo esc_html( WPCMCP_OAuth::protected_metadata_url() ); ?></code>
                <p><strong>Authorization server</strong></p><code class="wpcmcp-block"><?php echo esc_html( home_url( '/.well-known/oauth-authorization-server' ) ); ?></code>
                <p><strong>OIDC discovery fallback</strong></p><code class="wpcmcp-block"><?php echo esc_html( home_url( '/.well-known/openid-configuration' ) ); ?></code>
                <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                    <input type="hidden" name="action" value="wpcmcp_flush_rewrites"><?php wp_nonce_field( 'wpcmcp_flush_rewrites' ); ?>
                    <button class="button">Refresh discovery routes</button>
                </form>
            </div>
        </div>
        <div class="wpcmcp-card">
            <div class="wpcmcp-kicker">TOOLS IN THIS RELEASE</div>
            <div class="wpcmcp-tools">
                <?php foreach ( WPCMCP_Tools::definitions( array( 'wordpress.read', 'wordpress.write' ) ) as $tool ) : ?>
                    <div><code><?php echo esc_html( $tool['name'] ); ?></code><span><?php echo ! empty( $tool['annotations']['readOnlyHint'] ) ? 'Read' : ( ! empty( $tool['annotations']['destructiveHint'] ) ? 'Destructive write' : 'Write' ); ?></span></div>
                <?php endforeach; ?>
            </div>
        </div>
        <div class="wpcmcp-card">
            <div class="wpcmcp-kicker">CHATGPT SETUP</div>
            <ol class="wpcmcp-steps">
                <li>Enable Developer Mode in an eligible ChatGPT workspace/account.</li>
                <li>Go to Settings / Workspace Settings → Apps → Create.</li>
                <li>Paste the MCP URL above and select OAuth authentication when requested.</li>
                <li>Click Scan Tools. ChatGPT will redirect you to this WordPress site to authorize access.</li>
                <li>Approve the connection, return to ChatGPT, then create the app and test read tools first.</li>
            </ol>
        </div>
        </div><?php
    }

    public function settings_page() {
        $this->header( 'MCP Settings', 'Control which WordPress capabilities are exposed to authorized MCP clients.' );
        $s = get_option( 'wpcmcp_settings', array( 'read_tools' => 1, 'write_tools' => 1, 'log_days' => 30 ) );
        ?><div class="wpcmcp-card"><form method="post" action="options.php"><?php settings_fields( 'wpcmcp_settings_group' ); ?>
            <table class="form-table" role="presentation">
                <tr><th>Read tools</th><td><label><input type="checkbox" name="wpcmcp_settings[read_tools]" value="1" <?php checked( ! empty( $s['read_tools'] ) ); ?>> Expose site/content/ACF read tools.</label></td></tr>
                <tr><th>Write tools</th><td><label><input type="checkbox" name="wpcmcp_settings[write_tools]" value="1" <?php checked( ! empty( $s['write_tools'] ) ); ?>> Expose content, media, taxonomy, comment, user, menu, settings, extension and conditional ACF/WooCommerce write tools to OAuth connections with <code>wordpress.write</code>.</label><p class="description">High-risk actions require explicit confirmation. WordPress capability checks are enforced server-side for every operation.</p></td></tr>
                <tr><th>Activity log retention</th><td><input type="number" min="1" max="365" name="wpcmcp_settings[log_days]" value="<?php echo esc_attr( isset( $s['log_days'] ) ? $s['log_days'] : 30 ); ?>"> days</td></tr>
            </table><?php submit_button(); ?>
        </form></div></div><?php
    }

    public function connections_page() {
        global $wpdb;
        $this->header( 'Authorized Connections', 'Access tokens are stored only as irreversible hashes. Revoke a connection at any time.' );
        $rows = $wpdb->get_results( 'SELECT * FROM ' . WPCMCP_DB::table( 'tokens' ) . ' ORDER BY id DESC LIMIT 100' ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
        echo '<div class="wpcmcp-card"><table class="widefat striped"><thead><tr><th>ID</th><th>User</th><th>Client</th><th>Scopes</th><th>Access expiry (UTC)</th><th>Status</th><th></th></tr></thead><tbody>';
        if ( ! $rows ) echo '<tr><td colspan="7">No connections yet.</td></tr>';
        foreach ( $rows as $row ) {
            $user = get_user_by( 'id', (int) $row->user_id );
            $revoked = ! empty( $row->revoked_at );
            echo '<tr><td>' . esc_html( $row->id ) . '</td><td>' . esc_html( $user ? $user->user_login : 'Deleted user' ) . '</td><td><code>' . esc_html( substr( $row->client_id, 0, 24 ) ) . '…</code></td><td>' . esc_html( $row->scope ) . '</td><td>' . esc_html( $row->access_expires_at ) . '</td><td>' . ( $revoked ? '<span class="wpcmcp-badge bad">Revoked</span>' : '<span class="wpcmcp-badge ok">Active</span>' ) . '</td><td>';
            if ( ! $revoked ) {
                $url = wp_nonce_url( admin_url( 'admin-post.php?action=wpcmcp_revoke&id=' . absint( $row->id ) ), 'wpcmcp_revoke_' . absint( $row->id ) );
                echo '<a class="button" href="' . esc_url( $url ) . '" onclick="return confirm(\'Revoke this MCP connection?\')">Revoke</a>';
            }
            echo '</td></tr>';
        }
        echo '</tbody></table></div></div>';
    }

    public function logs_page() {
        global $wpdb;
        WPCMCP_DB::prune_logs();
        $this->header( 'Activity Logs', 'Operational metadata only. Raw content bodies, OAuth tokens and ACF values are not written to these logs.' );
        $rows = $wpdb->get_results( 'SELECT * FROM ' . WPCMCP_DB::table( 'logs' ) . ' ORDER BY id DESC LIMIT 200' ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
        echo '<div class="wpcmcp-card"><table class="widefat striped"><thead><tr><th>Time (UTC)</th><th>Event</th><th>Tool</th><th>Status</th><th>User</th><th>Details</th></tr></thead><tbody>';
        if ( ! $rows ) echo '<tr><td colspan="6">No activity yet.</td></tr>';
        foreach ( $rows as $row ) {
            $user = $row->user_id ? get_user_by( 'id', (int) $row->user_id ) : false;
            echo '<tr><td>' . esc_html( $row->created_at ) . '</td><td><code>' . esc_html( $row->event ) . '</code></td><td>' . esc_html( $row->tool_name ) . '</td><td>' . esc_html( $row->status ) . '</td><td>' . esc_html( $user ? $user->user_login : '—' ) . '</td><td>' . esc_html( $row->details ) . '</td></tr>';
        }
        echo '</tbody></table></div></div>';
    }

    public function revoke() {
        $id = isset( $_GET['id'] ) ? absint( $_GET['id'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        check_admin_referer( 'wpcmcp_revoke_' . $id );
        if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Not allowed.' );
        WPCMCP_OAuth::revoke_token_id( $id );
        WPCMCP_DB::log( 'oauth_revoked', '', 'success', get_current_user_id(), '', 'token_id=' . $id );
        wp_safe_redirect( admin_url( 'admin.php?page=wpcmcp-connections' ) );
        exit;
    }

    public function flush_rewrites() {
        check_admin_referer( 'wpcmcp_flush_rewrites' );
        if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Not allowed.' );
        WPCMCP_OAuth::register_rewrites();
        flush_rewrite_rules();
        wp_safe_redirect( admin_url( 'admin.php?page=wpcmcp&routes=refreshed' ) );
        exit;
    }
}
