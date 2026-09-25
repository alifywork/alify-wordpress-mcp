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
        if ( preg_match( '/^comment_(\d+)$/', (string) $target, $m ) ) {
            return current_user_can( $write ? 'edit_comment' : 'moderate_comments', (int) $m[1] );
        }
        if ( preg_match( '/^([a-z0-9_\-]+)_(\d+)$/', (string) $target, $m ) ) {
            $taxonomy = get_taxonomy( sanitize_key( $m[1] ) );
            if ( $taxonomy ) {
                $cap = $write ? $taxonomy->cap->edit_terms : $taxonomy->cap->manage_terms;
                return current_user_can( $cap );
            }
        }
        return current_user_can( 'manage_options' );
    }

    private static function group_record( $group, $with_fields = false ) {
        $record = array(
            'ID' => isset( $group['ID'] ) ? (int) $group['ID'] : 0,
            'key' => isset( $group['key'] ) ? $group['key'] : '',
            'title' => isset( $group['title'] ) ? $group['title'] : '',
            'active' => ! isset( $group['active'] ) || (bool) $group['active'],
            'menu_order' => isset( $group['menu_order'] ) ? (int) $group['menu_order'] : 0,
            'position' => isset( $group['position'] ) ? $group['position'] : '',
            'style' => isset( $group['style'] ) ? $group['style'] : '',
            'label_placement' => isset( $group['label_placement'] ) ? $group['label_placement'] : '',
            'instruction_placement' => isset( $group['instruction_placement'] ) ? $group['instruction_placement'] : '',
            'hide_on_screen' => isset( $group['hide_on_screen'] ) ? $group['hide_on_screen'] : array(),
            'location' => isset( $group['location'] ) ? $group['location'] : array(),
        );
        if ( $with_fields && function_exists( 'acf_get_fields' ) ) $record['fields'] = acf_get_fields( $group ) ?: array();
        return $record;
    }

    private static function list_groups() {
        $cap = self::require_acf_admin();
        if ( is_wp_error( $cap ) ) return $cap;
        $items = array();
        foreach ( acf_get_field_groups() as $group ) $items[] = self::group_record( $group, false );
        return array( 'field_groups' => $items );
    }

    private static function resolve_group( $id ) {
        if ( ! function_exists( 'acf_get_field_group' ) ) return false;
        return acf_get_field_group( is_numeric( $id ) ? absint( $id ) : sanitize_text_field( $id ) );
    }

    private static function get_group( array $args ) {
        $cap = self::require_acf_admin();
        if ( is_wp_error( $cap ) ) return $cap;
        $group = self::resolve_group( $args['group'] );
        return $group ? self::group_record( $group, true ) : new WP_Error( 'not_found', 'ACF field group not found.' );
    }

    private static function list_fields( array $args ) {
        $result = self::get_group( $args );
        if ( is_wp_error( $result ) ) return $result;
        return array( 'group' => $result['key'], 'fields' => $result['fields'] );
    }

    private static function get_field( array $args ) {
        $cap = self::require_acf_admin();
        if ( is_wp_error( $cap ) ) return $cap;
        if ( ! function_exists( 'acf_get_field' ) ) return new WP_Error( 'acf_unavailable', 'ACF field API unavailable.' );
        $field = acf_get_field( is_numeric( $args['field'] ) ? absint( $args['field'] ) : sanitize_text_field( $args['field'] ) );
        return $field ?: new WP_Error( 'not_found', 'ACF field not found.' );
    }

    private static function get_values( array $args ) {
        if ( ! function_exists( 'get_field' ) ) return new WP_Error( 'acf_unavailable', 'ACF value API unavailable.' );
        $target = self::normalize_target( $args['target'] );
        if ( is_wp_error( $target ) ) return $target;
        if ( ! self::target_capability_check( $target, false ) ) return new WP_Error( 'forbidden', 'You cannot read ACF values from this target.' );
        $formatted = ! array_key_exists( 'format_value', $args ) || (bool) $args['format_value'];
        $values = array();
        foreach ( (array) $args['fields'] as $selector ) {
            $selector = sanitize_text_field( $selector );
            if ( '' === $selector ) continue;
            $values[ $selector ] = get_field( $selector, $target, $formatted );
        }
        return array( 'target' => $target, 'values' => $values );
    }

    private static function merge_group_settings( array $group, array $args ) {
        if ( isset( $args['title'] ) ) $group['title'] = sanitize_text_field( $args['title'] );
        if ( isset( $args['location'] ) && is_array( $args['location'] ) ) $group['location'] = $args['location'];
        if ( isset( $args['settings'] ) && is_array( $args['settings'] ) ) {
            foreach ( $args['settings'] as $key => $value ) {
                if ( in_array( $key, array( 'ID', 'key', 'title', 'location' ), true ) ) continue;
                $group[ sanitize_key( $key ) ] = $value;
            }
        }
        return $group;
    }

    private static function create_group( array $args ) {
        $cap = self::require_acf_admin();
        if ( is_wp_error( $cap ) ) return $cap;
        if ( ! function_exists( 'acf_update_field_group' ) ) return new WP_Error( 'acf_unavailable', 'ACF field-group write API unavailable.' );
        $group = array(
            'key' => ! empty( $args['key'] ) ? sanitize_key( $args['key'] ) : 'group_' . wp_generate_password( 13, false, false ),
            'title' => sanitize_text_field( $args['title'] ),
            'location' => isset( $args['location'] ) && is_array( $args['location'] ) ? $args['location'] : array(),
            'active' => true,
        );
        $group = self::merge_group_settings( $group, $args );
        $saved = acf_update_field_group( $group );
        return $saved ? self::group_record( $saved, true ) : new WP_Error( 'acf_save_failed', 'ACF could not create the field group.' );
    }

    private static function update_group( array $args ) {
        $cap = self::require_acf_admin();
        if ( is_wp_error( $cap ) ) return $cap;
        $group = self::resolve_group( $args['group'] );
        if ( ! $group ) return new WP_Error( 'not_found', 'ACF field group not found.' );
        $saved = acf_update_field_group( self::merge_group_settings( $group, $args ) );
        return $saved ? self::group_record( $saved, true ) : new WP_Error( 'acf_save_failed', 'ACF could not update the field group.' );
    }

    private static function duplicate_group( array $args ) {
        $cap = self::require_acf_admin();
        if ( is_wp_error( $cap ) ) return $cap;
        if ( ! function_exists( 'acf_duplicate_field_group' ) ) return new WP_Error( 'acf_unavailable', 'ACF duplicate field-group API unavailable.' );
        $new = acf_duplicate_field_group( $args['group'] );
        if ( ! $new ) return new WP_Error( 'duplicate_failed', 'ACF could not duplicate the field group.' );
        if ( ! empty( $args['title'] ) ) {
            $new['title'] = sanitize_text_field( $args['title'] );
            $new = acf_update_field_group( $new );
        }
        return self::group_record( $new, true );
    }

    private static function trash_group( array $args ) {
        $cap = self::require_acf_admin();
        if ( is_wp_error( $cap ) ) return $cap;
        if ( ! function_exists( 'acf_trash_field_group' ) ) return new WP_Error( 'acf_unavailable', 'ACF trash API unavailable.' );
        return array( 'success' => (bool) acf_trash_field_group( $args['group'] ) );
    }

    private static function delete_group( array $args ) {
        $cap = self::require_acf_admin();
        if ( is_wp_error( $cap ) ) return $cap;
        if ( empty( $args['confirm'] ) ) return new WP_Error( 'confirmation_required', 'Permanent ACF field-group deletion requires confirm=true.' );
        if ( ! function_exists( 'acf_delete_field_group' ) ) return new WP_Error( 'acf_unavailable', 'ACF delete API unavailable.' );
        return array( 'success' => (bool) acf_delete_field_group( $args['group'] ), 'deleted_permanently' => true );
    }

    private static function resolve_parent_id( $parent ) {
        $parent = is_numeric( $parent ) ? absint( $parent ) : sanitize_text_field( $parent );
        $group = self::resolve_group( $parent );
        if ( $group && ! empty( $group['ID'] ) ) return (int) $group['ID'];
        if ( function_exists( 'acf_get_field' ) ) {
            $field = acf_get_field( $parent );
            if ( $field && ! empty( $field['ID'] ) ) return (int) $field['ID'];
        }
        return 0;
    }

    private static function merge_field_settings( array $field, array $args ) {
        if ( isset( $args['label'] ) ) $field['label'] = sanitize_text_field( $args['label'] );
        if ( isset( $args['name'] ) ) $field['name'] = sanitize_key( $args['name'] );
        if ( isset( $args['type'] ) ) $field['type'] = sanitize_key( $args['type'] );
        if ( isset( $args['parent'] ) ) {
            $parent = self::resolve_parent_id( $args['parent'] );
            if ( ! $parent ) return new WP_Error( 'invalid_parent', 'ACF parent group/field could not be resolved.' );
            $field['parent'] = $parent;
        }
        if ( isset( $args['settings'] ) && is_array( $args['settings'] ) ) {
            foreach ( $args['settings'] as $key => $value ) {
                if ( in_array( $key, array( 'ID', 'key', 'parent', 'label', 'name', 'type' ), true ) ) continue;
                $field[ sanitize_key( $key ) ] = $value;
            }
        }
        return $field;
    }

    private static function create_field( array $args ) {
        $cap = self::require_acf_admin();
        if ( is_wp_error( $cap ) ) return $cap;
        if ( ! function_exists( 'acf_update_field' ) ) return new WP_Error( 'acf_unavailable', 'ACF field write API unavailable.' );
        $parent = self::resolve_parent_id( $args['parent'] );
        if ( ! $parent ) return new WP_Error( 'invalid_parent', 'ACF parent group/field could not be resolved.' );
        $field = array(
            'key' => ! empty( $args['key'] ) ? sanitize_key( $args['key'] ) : 'field_' . wp_generate_password( 13, false, false ),
            'label' => sanitize_text_field( $args['label'] ),
            'name' => sanitize_key( $args['name'] ),
            'type' => sanitize_key( $args['type'] ),
            'parent' => $parent,
            'menu_order' => 0,
        );
        $field = self::merge_field_settings( $field, $args );
        if ( is_wp_error( $field ) ) return $field;
        $saved = acf_update_field( $field );
        return $saved ?: new WP_Error( 'acf_save_failed', 'ACF could not create the field.' );
    }

    private static function update_field_definition( array $args ) {
        $cap = self::require_acf_admin();
        if ( is_wp_error( $cap ) ) return $cap;
        if ( ! function_exists( 'acf_get_field' ) || ! function_exists( 'acf_update_field' ) ) return new WP_Error( 'acf_unavailable', 'ACF field write API unavailable.' );
        $field = acf_get_field( is_numeric( $args['field'] ) ? absint( $args['field'] ) : sanitize_text_field( $args['field'] ) );
        if ( ! $field ) return new WP_Error( 'not_found', 'ACF field not found.' );
        $field = self::merge_field_settings( $field, $args );
        if ( is_wp_error( $field ) ) return $field;
        $saved = acf_update_field( $field );
        return $saved ?: new WP_Error( 'acf_save_failed', 'ACF could not update the field.' );
    }

    private static function duplicate_field( array $args ) {
        $cap = self::require_acf_admin();
        if ( is_wp_error( $cap ) ) return $cap;
        if ( ! function_exists( 'acf_duplicate_field' ) ) return new WP_Error( 'acf_unavailable', 'ACF duplicate field API unavailable.' );
        $parent = ! empty( $args['parent'] ) ? self::resolve_parent_id( $args['parent'] ) : 0;
        if ( ! empty( $args['parent'] ) && ! $parent ) return new WP_Error( 'invalid_parent', 'ACF parent could not be resolved.' );
        $saved = acf_duplicate_field( $args['field'], $parent );
        return $saved ?: new WP_Error( 'duplicate_failed', 'ACF could not duplicate the field.' );
    }

    private static function trash_field( array $args ) {
        $cap = self::require_acf_admin();
        if ( is_wp_error( $cap ) ) return $cap;
        if ( ! function_exists( 'acf_trash_field' ) ) return new WP_Error( 'acf_unavailable', 'ACF trash field API unavailable.' );
        return array( 'success' => (bool) acf_trash_field( $args['field'] ) );
    }

    private static function delete_field_definition( array $args ) {
        $cap = self::require_acf_admin();
        if ( is_wp_error( $cap ) ) return $cap;
        if ( empty( $args['confirm'] ) ) return new WP_Error( 'confirmation_required', 'Permanent ACF field deletion requires confirm=true.' );
        if ( ! function_exists( 'acf_delete_field' ) ) return new WP_Error( 'acf_unavailable', 'ACF delete field API unavailable.' );
        return array( 'success' => (bool) acf_delete_field( $args['field'] ), 'deleted_permanently' => true );
    }

    private static function set_location_rules( array $args ) {
        $args['settings'] = array();
        return self::update_group( $args );
    }

    private static function set_conditional_logic( array $args ) {
        $args['settings'] = array( 'conditional_logic' => $args['conditional_logic'] );
        return self::update_field_definition( $args );
    }

    private static function update_values( array $args ) {
        if ( ! function_exists( 'update_field' ) ) return new WP_Error( 'acf_unavailable', 'ACF value API unavailable.' );
        $target = self::normalize_target( $args['target'] );
        if ( is_wp_error( $target ) ) return $target;
        if ( ! self::target_capability_check( $target, true ) ) return new WP_Error( 'forbidden', 'You cannot update ACF values on this target.' );
        $results = array();
        foreach ( (array) $args['fields'] as $selector => $value ) {
            $selector = sanitize_text_field( $selector );
            if ( '' === $selector ) continue;
            $before = function_exists( 'get_field' ) ? get_field( $selector, $target, false ) : null;
            $ok = update_field( $selector, $value, $target );
            $after = function_exists( 'get_field' ) ? get_field( $selector, $target, false ) : null;
            $results[ $selector ] = array( 'success' => false !== $ok || $before == $after, 'changed' => $before != $after );
        }
        return array( 'target' => $target, 'results' => $results );
    }

    private static function delete_value_tool( array $args ) {
        if ( empty( $args['confirm'] ) ) return new WP_Error( 'confirmation_required', 'Deleting an ACF value requires confirm=true.' );
        if ( ! function_exists( 'delete_field' ) ) return new WP_Error( 'acf_unavailable', 'ACF delete-value API unavailable.' );
        $target = self::normalize_target( $args['target'] );
        if ( is_wp_error( $target ) ) return $target;
        if ( ! self::target_capability_check( $target, true ) ) return new WP_Error( 'forbidden', 'You cannot delete ACF values from this target.' );
        return array( 'success' => (bool) delete_field( sanitize_text_field( $args['field'] ), $target ), 'target' => $target );
    }

    private static function list_option_pages_managed() {
        return array( 'option_pages' => get_option( self::OPTION_PAGES_OPTION, array() ) );
    }

    private static function save_option_page( array $args ) {
        $cap = self::require_acf_admin();
        if ( is_wp_error( $cap ) ) return $cap;
        if ( ! function_exists( 'acf_add_options_page' ) ) return new WP_Error( 'acf_pro_required', 'ACF PRO option pages are unavailable.' );
        $slug = sanitize_key( $args['menu_slug'] );
        if ( ! $slug ) return new WP_Error( 'invalid_slug', 'A valid option-page menu_slug is required.' );
        $settings = array(
            'page_title' => sanitize_text_field( $args['page_title'] ),
            'menu_title' => isset( $args['menu_title'] ) ? sanitize_text_field( $args['menu_title'] ) : sanitize_text_field( $args['page_title'] ),
            'menu_slug' => $slug,
            'capability' => isset( $args['capability'] ) ? sanitize_key( $args['capability'] ) : 'manage_options',
            'redirect' => array_key_exists( 'redirect', $args ) ? (bool) $args['redirect'] : false,
        );
        foreach ( array( 'parent_slug', 'post_id', 'icon_url' ) as $field ) {
            if ( isset( $args[ $field ] ) && '' !== (string) $args[ $field ] ) $settings[ $field ] = sanitize_text_field( $args[ $field ] );
        }
        if ( isset( $args['position'] ) ) $settings['position'] = (int) $args['position'];
        if ( isset( $args['autoload'] ) ) $settings['autoload'] = (bool) $args['autoload'];
        $pages = get_option( self::OPTION_PAGES_OPTION, array() );
        $pages[ $slug ] = $settings;
        update_option( self::OPTION_PAGES_OPTION, $pages, false );
        return array( 'success' => true, 'menu_slug' => $slug, 'settings' => $settings );
    }

    private static function delete_option_page( array $args ) {
        $cap = self::require_acf_admin();
        if ( is_wp_error( $cap ) ) return $cap;
        if ( empty( $args['confirm'] ) ) return new WP_Error( 'confirmation_required', 'Deleting an ACF option-page definition requires confirm=true.' );
        $slug = sanitize_key( $args['menu_slug'] );
        $pages = get_option( self::OPTION_PAGES_OPTION, array() );
        if ( ! isset( $pages[ $slug ] ) ) return new WP_Error( 'not_managed', 'This option page is not managed by the MCP plugin.' );
        unset( $pages[ $slug ] );
        update_option( self::OPTION_PAGES_OPTION, $pages, false );
        return array( 'success' => true, 'menu_slug' => $slug, 'stored_values_deleted' => false );
    }
}
