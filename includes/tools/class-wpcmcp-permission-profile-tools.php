<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class WPCMCP_Permission_Profile_Tools {
    const OPTION = 'wpcmcp_permission_profile';

    public static function bootstrap() {
        add_filter( 'wpcmcp_tool_definitions', array( __CLASS__, 'filter_tools' ), 20, 4 );
    }

    public static function definitions( $can_read, $can_write ) {
        $tools = array();
        if ( $can_read ) {
            $tools[] = self::t( 'wordpress.permission_profile_get', 'Get MCP Permission Profile', 'Get the active MCP permission profile and its policy summary.', array(), array(), true, false, true );
            $tools[] = self::t( 'wordpress.permission_profile_list', 'List MCP Permission Profiles', 'List built-in MCP permission profiles.', array(), array(), true, false, true );
        }
        if ( $can_write ) {
            $tools[] = self::t( 'wordpress.permission_profile_set', 'Set MCP Permission Profile', 'Set the MCP tool exposure profile. Requires manage_options and confirm=true.', array(
                'profile' => array( 'type' => 'string', 'enum' => array( 'read_only', 'content_manager', 'developer', 'full_admin' ) ),
                'confirm' => array( 'type' => 'boolean' ),
            ), array( 'profile', 'confirm' ), false, true, true );
        }
        return $tools;
    }

    private static function t( $name, $title, $description, $properties, $required, $read_only, $destructive, $idempotent ) {
        return WPCMCP_Tools::tool( $name, $title, $description, $properties, $required, $read_only, $destructive, $idempotent );
    }

    public static function execute( $name, array $args ) {
        switch ( $name ) {
            case 'wordpress.permission_profile_get': return self::get_profile();
            case 'wordpress.permission_profile_list': return array( 'profiles' => self::profiles() );
            case 'wordpress.permission_profile_set': return self::set_profile( $args );
            default: return null;
        }
    }

    public static function profiles() {
        return array(
            'read_only' => array(
                'title' => 'Read Only',
                'description' => 'Expose inspection tools only. All mutating tools are hidden.',
            ),
            'content_manager' => array(
                'title' => 'Content Manager',
                'description' => 'Content, media, taxonomy, comments, forms and supported builder editing; blocks plugin/theme/system/user administration.',
            ),
            'developer' => array(
                'title' => 'Developer',
                'description' => 'Content plus builders, ACF, plugin discovery, theme development, cron, diagnostics and extension lifecycle; blocks user-role administration and MCP permission-profile escalation except by an administrator.',
            ),
            'full_admin' => array(
                'title' => 'Full Admin',
                'description' => 'Expose all tools permitted by OAuth scopes, plugin settings and native WordPress capabilities.',
            ),
        );
    }

    private static function current_profile() {
        $profile = sanitize_key( get_option( self::OPTION, 'full_admin' ) );
        return isset( self::profiles()[ $profile ] ) ? $profile : 'full_admin';
    }

    private static function get_profile() {
        $profile = self::current_profile();
        $profiles = self::profiles();
        return array(
            'profile' => $profile,
            'policy' => $profiles[ $profile ],
        );
    }

    private static function set_profile( array $args ) {
        if ( ! current_user_can( 'manage_options' ) ) return new WP_Error( 'forbidden', 'Changing the MCP permission profile requires manage_options.' );
        if ( empty( $args['confirm'] ) ) return new WP_Error( 'confirmation_required', 'Changing the MCP permission profile requires confirm=true.' );

        $profile = sanitize_key( $args['profile'] );
        $profiles = self::profiles();
        if ( ! isset( $profiles[ $profile ] ) ) return new WP_Error( 'invalid_profile', 'Unknown MCP permission profile.' );

        update_option( self::OPTION, $profile, false );
        return array( 'success' => true, 'profile' => $profile, 'policy' => $profiles[ $profile ] );
    }

    private static function starts_with_any( $name, array $prefixes ) {
        foreach ( $prefixes as $prefix ) if ( 0 === strpos( $name, $prefix ) ) return true;
        return false;
    }

    private static function always_allowed( $name ) {
        return 0 === strpos( $name, 'wordpress.permission_profile_' );
    }

    private static function content_manager_allowed( $name, $read_only ) {
        if ( $read_only ) return true;
        if ( self::always_allowed( $name ) ) return true;

        $prefixes = array(
            'wordpress.create_content',
            'wordpress.update_content',
            'wordpress.trash_content',
            'wordpress.restore_content',
            'wordpress.create_post',
            'wordpress.update_post',
            'wordpress.delete_post',
            'wordpress.create_page',
            'wordpress.update_page',
            'wordpress.delete_page',
            'wordpress.upload_media',
            'wordpress.update_media',
            'wordpress.set_featured_image',
            'wordpress.create_term',
            'wordpress.update_term',
            'wordpress.assign_terms',
            'wordpress.create_comment',
            'wordpress.update_comment',
            'wordpress.moderate_comment',
            'wordpress.create_menu',
            'wordpress.update_menu',
            'wordpress.create_menu_item',
            'wordpress.update_menu_item',
            'wordpress.assign_menu_location',
            'wordpress.update_acf_field',
            'wordpress.update_acf_fields',
            'wordpress.acf_update_values',
            'wordpress.cf7_',
            'wordpress.elementor_',
            'wordpress.wpbakery_',
            'wordpress.divi_',
            'wordpress.muffin_',
        );
        return self::starts_with_any( $name, $prefixes );
    }

    private static function developer_allowed( $name, $read_only ) {
        if ( $read_only ) return true;
        if ( self::always_allowed( $name ) ) return true;

        $blocked = array(
            'wordpress.create_user',
            'wordpress.update_user',
            'wordpress.set_user_roles',
            'wordpress.delete_user',
        );
        return ! in_array( $name, $blocked, true );
    }

    public static function filter_tools( $tools, $can_read, $can_write, $scopes ) {
        if ( ! is_array( $tools ) ) return $tools;
        $profile = self::current_profile();

        if ( 'full_admin' === $profile ) return $tools;

        $filtered = array();
        foreach ( $tools as $tool ) {
            if ( empty( $tool['name'] ) ) continue;
            $name = $tool['name'];
            $read_only = ! empty( $tool['annotations']['readOnlyHint'] );

            if ( 'read_only' === $profile ) {
                if ( $read_only || self::always_allowed( $name ) ) $filtered[] = $tool;
                continue;
            }

            if ( 'content_manager' === $profile ) {
                if ( self::content_manager_allowed( $name, $read_only ) ) $filtered[] = $tool;
                continue;
            }

            if ( 'developer' === $profile ) {
                if ( self::developer_allowed( $name, $read_only ) ) $filtered[] = $tool;
                continue;
            }
        }

        return array_values( $filtered );
    }
}
