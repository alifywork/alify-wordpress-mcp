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
