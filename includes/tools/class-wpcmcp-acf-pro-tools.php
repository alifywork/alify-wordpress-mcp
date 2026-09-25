<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class WPCMCP_ACF_Pro_Tools {
    const OPTION_PAGES_OPTION = 'wpcmcp_managed_acf_option_pages';

    public static function bootstrap() {
        add_action( 'acf/init', array( __CLASS__, 'register_managed_option_pages' ), 8 );
    }

    public static function register_managed_option_pages() {
        if ( ! function_exists( 'acf_add_options_page' ) ) return;
        $pages = get_option( self::OPTION_PAGES_OPTION, array() );
        if ( ! is_array( $pages ) ) return;
        foreach ( $pages as $slug => $settings ) {
            if ( ! is_array( $settings ) ) continue;
            $settings['menu_slug'] = $slug;
            acf_add_options_page( $settings );
        }
    }

    public static function definitions( $can_read, $can_write ) {
        $tools = array();
        if ( $can_read ) {
            $tools[] = self::t( 'wordpress.acf_status', 'ACF Pro Status', 'Report ACF/ACF Pro availability and which management APIs are available.', array(), array(), true, false, true );
            $tools[] = self::t( 'wordpress.acf_list_field_groups', 'List ACF Field Groups', 'List ACF field groups including key, title, active state, position and location rules.', array(), array(), true, false, true );
            $tools[] = self::t( 'wordpress.acf_get_field_group', 'Get ACF Field Group', 'Get one ACF field group and all nested field definitions.', array(
                'group' => array( 'type' => 'string' ),
            ), array( 'group' ), true, false, true );
            $tools[] = self::t( 'wordpress.acf_list_fields', 'List ACF Fields', 'List fields in one ACF field group, including nested repeater/group/flexible sub fields.', array(
                'group' => array( 'type' => 'string' ),
            ), array( 'group' ), true, false, true );
            $tools[] = self::t( 'wordpress.acf_get_field', 'Get ACF Field Definition', 'Get a single ACF field definition by field key, ID or name.', array(
                'field' => array( 'type' => 'string' ),
            ), array( 'field' ), true, false, true );
            $tools[] = self::t( 'wordpress.acf_get_values', 'Get ACF Values', 'Get ACF values from a post, user, term, comment or options target. Use field keys/names explicitly.', array(
                'target' => array( 'type' => 'string', 'description' => 'ACF post_id target such as 123, user_2, category_3, comment_4, option, options, or a custom option-page post_id.' ),
                'fields' => array( 'type' => 'array', 'minItems' => 1, 'maxItems' => 100, 'items' => array( 'type' => 'string' ) ),
                'format_value' => array( 'type' => 'boolean' ),
            ), array( 'target', 'fields' ), true, false, true );
            $tools[] = self::t( 'wordpress.acf_list_option_pages_managed', 'List MCP-managed ACF Option Pages', 'List persistent ACF option pages created by this MCP plugin.', array(), array(), true, false, true );
        }

        if ( $can_write ) {
            $tools[] = self::t( 'wordpress.acf_create_field_group', 'Create ACF Field Group', 'Create a database-backed ACF field group with full location rules and screen settings.', array(
                'title' => array( 'type' => 'string' ),
                'key' => array( 'type' => 'string' ),
                'location' => array( 'type' => 'array', 'description' => 'ACF location groups. Outer array is OR groups; rules inside each group are AND.' ),
                'settings' => array( 'type' => 'object', 'additionalProperties' => true ),
            ), array( 'title' ), false, false, false );
            $tools[] = self::t( 'wordpress.acf_update_field_group', 'Update ACF Field Group', 'Update title, location rules, active state and other field-group settings.', array(
                'group' => array( 'type' => 'string' ),
                'title' => array( 'type' => 'string' ),
                'location' => array( 'type' => 'array' ),
                'settings' => array( 'type' => 'object', 'additionalProperties' => true ),
            ), array( 'group' ), false, false, true );
            $tools[] = self::t( 'wordpress.acf_duplicate_field_group', 'Duplicate ACF Field Group', 'Duplicate an existing ACF field group and optionally rename it.', array(
                'group' => array( 'type' => 'string' ),
                'title' => array( 'type' => 'string' ),
            ), array( 'group' ), false, false, false );
            $tools[] = self::t( 'wordpress.acf_trash_field_group', 'Trash ACF Field Group', 'Move an ACF field group to Trash.', array(
                'group' => array( 'type' => 'string' ),
            ), array( 'group' ), false, true, true );
            $tools[] = self::t( 'wordpress.acf_delete_field_group', 'Delete ACF Field Group Permanently', 'Permanently delete an ACF field group. Requires confirm=true.', array(
                'group' => array( 'type' => 'string' ),
                'confirm' => array( 'type' => 'boolean' ),
            ), array( 'group', 'confirm' ), false, true, true );
            $tools[] = self::t( 'wordpress.acf_create_field', 'Create ACF Field', 'Create an ACF field of any installed field type. Generic settings supports type-specific settings including choices, return_format, sub_fields, layouts and wrapper settings.', array(
                'parent' => array( 'type' => 'string', 'description' => 'Parent field-group key/ID or parent field key/ID for nested fields.' ),
                'label' => array( 'type' => 'string' ),
                'name' => array( 'type' => 'string' ),
                'type' => array( 'type' => 'string' ),
                'key' => array( 'type' => 'string' ),
                'settings' => array( 'type' => 'object', 'additionalProperties' => true ),
            ), array( 'parent', 'label', 'name', 'type' ), false, false, false );
            $tools[] = self::t( 'wordpress.acf_update_field', 'Update ACF Field', 'Update any ACF field definition, including conditional logic and type-specific settings.', array(
                'field' => array( 'type' => 'string' ),
                'label' => array( 'type' => 'string' ),
                'name' => array( 'type' => 'string' ),
                'type' => array( 'type' => 'string' ),
                'parent' => array( 'type' => 'string' ),
                'settings' => array( 'type' => 'object', 'additionalProperties' => true ),
            ), array( 'field' ), false, false, true );
            $tools[] = self::t( 'wordpress.acf_duplicate_field', 'Duplicate ACF Field', 'Duplicate an ACF field, optionally into another group/parent field.', array(
                'field' => array( 'type' => 'string' ),
                'parent' => array( 'type' => 'string' ),
            ), array( 'field' ), false, false, false );
            $tools[] = self::t( 'wordpress.acf_trash_field', 'Trash ACF Field', 'Move an ACF field to Trash.', array(
                'field' => array( 'type' => 'string' ),
            ), array( 'field' ), false, true, true );
            $tools[] = self::t( 'wordpress.acf_delete_field', 'Delete ACF Field Permanently', 'Permanently delete an ACF field definition. Requires confirm=true.', array(
                'field' => array( 'type' => 'string' ),
                'confirm' => array( 'type' => 'boolean' ),
            ), array( 'field', 'confirm' ), false, true, true );
            $tools[] = self::t( 'wordpress.acf_set_location_rules', 'Set ACF Location Rules', 'Replace a field group location-rule matrix. Use for Post Type, Post Template, Taxonomy, User Role, Options Page and other ACF location conditions.', array(
                'group' => array( 'type' => 'string' ),
                'location' => array( 'type' => 'array' ),
            ), array( 'group', 'location' ), false, false, true );
            $tools[] = self::t( 'wordpress.acf_set_conditional_logic', 'Set ACF Field Conditional Logic', 'Replace an ACF field conditional-logic matrix. Rules may reference sibling field keys and operators such as == or !=.', array(
                'field' => array( 'type' => 'string' ),
                'conditional_logic' => array( 'type' => array( 'array', 'boolean' ) ),
            ), array( 'field', 'conditional_logic' ), false, false, true );
            $tools[] = self::t( 'wordpress.acf_update_values', 'Update ACF Values', 'Update multiple ACF values on a post, user, term, comment or option-page target. Repeater/flexible/group values may be supplied as nested arrays.', array(
                'target' => array( 'type' => 'string' ),
                'fields' => array( 'type' => 'object', 'additionalProperties' => true ),
            ), array( 'target', 'fields' ), false, false, true );
            $tools[] = self::t( 'wordpress.acf_delete_value', 'Delete ACF Value', 'Delete one stored ACF field value from a post, user, term, comment or options target. Requires confirm=true.', array(
                'target' => array( 'type' => 'string' ),
                'field' => array( 'type' => 'string' ),
                'confirm' => array( 'type' => 'boolean' ),
            ), array( 'target', 'field', 'confirm' ), false, true, true );
            $tools[] = self::t( 'wordpress.acf_save_option_page', 'Create or Update ACF Option Page', 'Create or update a persistent ACF PRO option page managed by this MCP plugin.', array(
                'menu_slug' => array( 'type' => 'string' ),
                'page_title' => array( 'type' => 'string' ),
                'menu_title' => array( 'type' => 'string' ),
                'parent_slug' => array( 'type' => 'string' ),
                'capability' => array( 'type' => 'string' ),
                'post_id' => array( 'type' => 'string' ),
                'redirect' => array( 'type' => 'boolean' ),
                'position' => array( 'type' => 'integer' ),
                'icon_url' => array( 'type' => 'string' ),
                'autoload' => array( 'type' => 'boolean' ),
            ), array( 'menu_slug', 'page_title' ), false, false, true );
            $tools[] = self::t( 'wordpress.acf_delete_option_page', 'Delete ACF Option Page', 'Delete an option-page definition created by this MCP plugin. Stored option values are retained. Requires confirm=true.', array(
                'menu_slug' => array( 'type' => 'string' ),
                'confirm' => array( 'type' => 'boolean' ),
            ), array( 'menu_slug', 'confirm' ), false, true, true );
        }
        return $tools;
    }

    private static function t( $name, $title, $description, $properties, $required, $read_only, $destructive, $idempotent ) {
        return WPCMCP_Tools::tool( $name, $title, $description, $properties, $required, $read_only, $destructive, $idempotent );
    }

    public static function execute( $name, array $args ) {
        switch ( $name ) {
            case 'wordpress.acf_status': return self::status();
            case 'wordpress.acf_list_field_groups': return self::list_groups();
            case 'wordpress.acf_get_field_group': return self::get_group( $args );
            case 'wordpress.acf_list_fields': return self::list_fields( $args );
            case 'wordpress.acf_get_field': return self::get_field( $args );
            case 'wordpress.acf_get_values': return self::get_values( $args );
            case 'wordpress.acf_list_option_pages_managed': return self::list_option_pages_managed();
            case 'wordpress.acf_create_field_group': return self::create_group( $args );
            case 'wordpress.acf_update_field_group': return self::update_group( $args );
            case 'wordpress.acf_duplicate_field_group': return self::duplicate_group( $args );
            case 'wordpress.acf_trash_field_group': return self::trash_group( $args );
            case 'wordpress.acf_delete_field_group': return self::delete_group( $args );
            case 'wordpress.acf_create_field': return self::create_field( $args );
            case 'wordpress.acf_update_field': return self::update_field_definition( $args );
            case 'wordpress.acf_duplicate_field': return self::duplicate_field( $args );
            case 'wordpress.acf_trash_field': return self::trash_field( $args );
            case 'wordpress.acf_delete_field': return self::delete_field_definition( $args );
            case 'wordpress.acf_set_location_rules': return self::set_location_rules( $args );
            case 'wordpress.acf_set_conditional_logic': return self::set_conditional_logic( $args );
            case 'wordpress.acf_update_values': return self::update_values( $args );
            case 'wordpress.acf_delete_value': return self::delete_value_tool( $args );
            case 'wordpress.acf_save_option_page': return self::save_option_page( $args );
            case 'wordpress.acf_delete_option_page': return self::delete_option_page( $args );
            default: return null;
        }
    }

    private static function require_acf_admin() {
        if ( ! current_user_can( 'manage_options' ) ) return new WP_Error( 'forbidden', 'ACF structure management requires manage_options.' );
        if ( ! function_exists( 'acf_get_field_groups' ) ) return new WP_Error( 'acf_unavailable', 'ACF is not active or its management APIs are unavailable.' );
        return true;
    }

    private static function status() {
        return array(
            'acf_loaded' => function_exists( 'acf_get_field_groups' ),
            'acf_version' => defined( 'ACF_VERSION' ) ? ACF_VERSION : null,
            'acf_pro' => defined( 'ACF_PRO' ) ? (bool) ACF_PRO : function_exists( 'acf_add_options_page' ),
            'field_group_crud' => function_exists( 'acf_update_field_group' ) && function_exists( 'acf_delete_field_group' ),
            'field_crud' => function_exists( 'acf_update_field' ) && function_exists( 'acf_delete_field' ),
            'option_pages' => function_exists( 'acf_add_options_page' ),
            'value_api' => function_exists( 'get_field' ) && function_exists( 'update_field' ) && function_exists( 'delete_field' ),
        );
    }

    private static function normalize_target( $target ) {
        $target = is_numeric( $target ) ? (string) absint( $target ) : sanitize_text_field( (string) $target );
        if ( '' === $target || strlen( $target ) > 191 || preg_match( '/[\x00-\x1F\x7F]/', $target ) ) return new WP_Error( 'invalid_target', 'Invalid ACF target.' );
        return ctype_digit( $target ) ? (int) $target : $target;
    }

    private static function target_capability_check( $target, $write = false ) {
        if ( is_int( $target ) ) {
            return current_user_can( $write ? 'edit_post' : 'read_post', $target );
        }
        if ( in_array( $target, array( 'option', 'options' ), true ) || 0 === strpos( (string) $target, 'options_' ) ) {
            return current_user_can( 'manage_options' );
        }
        if ( preg_match( '/^user_(\d+)$/', (string) $target, $m ) ) {
            return current_user_can( $write ? 'edit_user' : 'list_users', (int) $m[1] );
        }
