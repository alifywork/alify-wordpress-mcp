<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class WPCMCP_Admin_Tools {
    public static function definitions( $can_read, $can_write ) {
        $tools = array();
        if ( $can_read ) {
            $tools[] = self::t( 'wordpress.list_menus', 'List Navigation Menus', 'List classic WordPress navigation menus and registered theme locations.', array(), array(), true, false, true );
            $tools[] = self::t( 'wordpress.get_menu', 'Get Navigation Menu', 'Get a navigation menu and all of its items.', array(
                'menu_id' => array( 'type' => 'integer', 'minimum' => 1 ),
            ), array( 'menu_id' ), true, false, true );
            $tools[] = self::t( 'wordpress.get_site_settings', 'Get Site Settings', 'Get a safe allowlist of general, reading, discussion, and permalink settings. Secret options are never returned.', array(), array(), true, false, true );
        }
        if ( $can_write ) {
            $tools[] = self::t( 'wordpress.create_menu', 'Create Navigation Menu', 'Create a classic WordPress navigation menu.', array(
                'name' => array( 'type' => 'string' ),
            ), array( 'name' ), false, false, false );
            $tools[] = self::t( 'wordpress.update_menu', 'Update Navigation Menu', 'Rename a classic WordPress navigation menu.', array(
                'menu_id' => array( 'type' => 'integer', 'minimum' => 1 ),
                'name'    => array( 'type' => 'string' ),
            ), array( 'menu_id', 'name' ), false, false, true );
            $tools[] = self::t( 'wordpress.delete_menu', 'Delete Navigation Menu', 'Permanently delete a navigation menu and its items. Requires confirm=true.', array(
                'menu_id' => array( 'type' => 'integer', 'minimum' => 1 ),
                'confirm' => array( 'type' => 'boolean', 'description' => 'Must be true.' ),
            ), array( 'menu_id', 'confirm' ), false, true, true );
            $tools[] = self::t( 'wordpress.create_menu_item', 'Create Menu Item', 'Add a custom link, content item, taxonomy term, or post-type archive to a navigation menu.', self::menu_item_schema( true ), array( 'menu_id', 'type' ), false, false, false );
            $tools[] = self::t( 'wordpress.update_menu_item', 'Update Menu Item', 'Update an existing navigation menu item.', self::menu_item_schema( false ), array( 'menu_id', 'menu_item_id' ), false, false, true );
            $tools[] = self::t( 'wordpress.delete_menu_item', 'Delete Menu Item', 'Permanently delete a navigation menu item. Requires confirm=true.', array(
                'menu_item_id' => array( 'type' => 'integer', 'minimum' => 1 ),
                'confirm'      => array( 'type' => 'boolean', 'description' => 'Must be true.' ),
            ), array( 'menu_item_id', 'confirm' ), false, true, true );
            $tools[] = self::t( 'wordpress.assign_menu_location', 'Assign Menu Location', 'Assign a navigation menu to a registered theme location, or use menu_id=0 to unassign it.', array(
                'location' => array( 'type' => 'string' ),
                'menu_id'  => array( 'type' => 'integer', 'minimum' => 0 ),
            ), array( 'location', 'menu_id' ), false, false, true );
            $tools[] = self::t( 'wordpress.update_site_settings', 'Update Site Settings', 'Update a safe allowlist of general, reading, and discussion settings. URLs and secret/arbitrary options cannot be changed.', array(
                'site_title'            => array( 'type' => 'string' ),
                'tagline'               => array( 'type' => 'string' ),
                'timezone'              => array( 'type' => 'string' ),
                'date_format'           => array( 'type' => 'string' ),
                'time_format'           => array( 'type' => 'string' ),
                'week_starts_on'        => array( 'type' => 'integer', 'minimum' => 0, 'maximum' => 6 ),
                'posts_per_page'        => array( 'type' => 'integer', 'minimum' => 1, 'maximum' => 100 ),
                'homepage_display'      => array( 'type' => 'string', 'enum' => array( 'posts', 'page' ) ),
                'homepage_id'           => array( 'type' => 'integer', 'minimum' => 0 ),
                'posts_page_id'         => array( 'type' => 'integer', 'minimum' => 0 ),
                'default_comment_status'=> array( 'type' => 'string', 'enum' => array( 'open', 'closed' ) ),
            ), array(), false, false, true );
        }
        return $tools;
    }

    private static function t( $name, $title, $description, $properties, $required, $read_only, $destructive, $idempotent ) {
        return WPCMCP_Tools::tool( $name, $title, $description, $properties, $required, $read_only, $destructive, $idempotent );
    }

    private static function menu_item_schema( $creating ) {
        $schema = array(
            'menu_id'       => array( 'type' => 'integer', 'minimum' => 1 ),
            'type'          => array( 'type' => 'string', 'enum' => array( 'custom', 'post_type', 'taxonomy', 'post_type_archive' ) ),
            'object_id'     => array( 'type' => 'integer', 'minimum' => 0 ),
            'object'        => array( 'type' => 'string', 'description' => 'Post type or taxonomy name when required.' ),
            'title'         => array( 'type' => 'string' ),
            'url'           => array( 'type' => 'string', 'format' => 'uri' ),
            'description'   => array( 'type' => 'string' ),
            'attr_title'    => array( 'type' => 'string' ),
            'target'        => array( 'type' => 'string', 'enum' => array( '', '_blank' ) ),
            'classes'       => array( 'type' => 'array', 'items' => array( 'type' => 'string' ) ),
            'xfn'           => array( 'type' => 'string' ),
            'parent_id'     => array( 'type' => 'integer', 'minimum' => 0 ),
            'position'      => array( 'type' => 'integer', 'minimum' => 0 ),
            'status'        => array( 'type' => 'string', 'enum' => array( 'publish', 'draft' ) ),
        );
        if ( ! $creating ) $schema['menu_item_id'] = array( 'type' => 'integer', 'minimum' => 1 );
        return $schema;
    }

    public static function execute( $name, array $args ) {
        switch ( $name ) {
            case 'wordpress.list_menus': return self::list_menus();
            case 'wordpress.get_menu': return self::get_menu( $args );
            case 'wordpress.create_menu': return self::create_menu( $args );
            case 'wordpress.update_menu': return self::update_menu( $args );
            case 'wordpress.delete_menu': return self::delete_menu( $args );
            case 'wordpress.create_menu_item': return self::save_menu_item( $args, true );
            case 'wordpress.update_menu_item': return self::save_menu_item( $args, false );
            case 'wordpress.delete_menu_item': return self::delete_menu_item( $args );
            case 'wordpress.assign_menu_location': return self::assign_menu_location( $args );
            case 'wordpress.get_site_settings': return self::get_site_settings();
            case 'wordpress.update_site_settings': return self::update_site_settings( $args );
            default: return null;
        }
    }

    private static function can_manage() {
        return current_user_can( 'edit_theme_options' );
    }

    private static function menu_record( $menu ) {
        return array( 'id' => (int) $menu->term_id, 'name' => $menu->name, 'slug' => $menu->slug, 'count' => (int) $menu->count );
    }

    private static function item_record( $item ) {
        return array(
            'id'          => (int) $item->ID,
            'title'       => $item->title,
            'type'        => $item->type,
            'object'      => $item->object,
            'object_id'   => (int) $item->object_id,
            'url'         => $item->url,
            'description' => $item->description,
            'attr_title'  => $item->attr_title,
            'target'      => $item->target,
            'classes'     => array_values( array_filter( (array) $item->classes ) ),
            'xfn'         => $item->xfn,
            'parent_id'   => (int) $item->menu_item_parent,
            'position'    => (int) $item->menu_order,
            'status'      => $item->post_status,
        );
    }

    private static function list_menus() {
        if ( ! self::can_manage() ) return new WP_Error( 'forbidden', 'You cannot inspect navigation menus.' );
        $menus = array_map( array( __CLASS__, 'menu_record' ), wp_get_nav_menus() );
        $registered = get_registered_nav_menus();
        $assigned = get_nav_menu_locations();
        $locations = array();
        foreach ( $registered as $slug => $description ) $locations[] = array( 'slug' => $slug, 'description' => $description, 'menu_id' => isset( $assigned[ $slug ] ) ? (int) $assigned[ $slug ] : 0 );
        return array( 'menus' => $menus, 'locations' => $locations );
    }

    private static function get_menu( array $args ) {
        if ( ! self::can_manage() ) return new WP_Error( 'forbidden', 'You cannot inspect navigation menus.' );
        $menu = wp_get_nav_menu_object( isset( $args['menu_id'] ) ? absint( $args['menu_id'] ) : 0 );
        if ( ! $menu || is_wp_error( $menu ) ) return new WP_Error( 'not_found', 'Navigation menu not found.' );
        $items = wp_get_nav_menu_items( $menu->term_id, array( 'post_status' => 'publish,draft' ) );
        return array( 'menu' => self::menu_record( $menu ), 'items' => $items ? array_map( array( __CLASS__, 'item_record' ), $items ) : array() );
    }

    private static function create_menu( array $args ) {
        if ( ! self::can_manage() ) return new WP_Error( 'forbidden', 'You cannot create navigation menus.' );
        $name = isset( $args['name'] ) ? sanitize_text_field( $args['name'] ) : '';
        if ( '' === $name ) return new WP_Error( 'invalid_name', 'Menu name is required.' );
        $id = wp_create_nav_menu( $name );
        if ( is_wp_error( $id ) ) return $id;
        return self::menu_record( wp_get_nav_menu_object( $id ) );
    }

    private static function update_menu( array $args ) {
        if ( ! self::can_manage() ) return new WP_Error( 'forbidden', 'You cannot update navigation menus.' );
        $id = isset( $args['menu_id'] ) ? absint( $args['menu_id'] ) : 0;
        $menu = wp_get_nav_menu_object( $id );
        if ( ! $menu || is_wp_error( $menu ) ) return new WP_Error( 'not_found', 'Navigation menu not found.' );
        $name = isset( $args['name'] ) ? sanitize_text_field( $args['name'] ) : '';
        if ( '' === $name ) return new WP_Error( 'invalid_name', 'Menu name is required.' );
        $result = wp_update_nav_menu_object( $id, array( 'menu-name' => $name ) );
        if ( is_wp_error( $result ) ) return $result;
        return self::menu_record( wp_get_nav_menu_object( $id ) );
    }

    private static function delete_menu( array $args ) {
        if ( ! self::can_manage() ) return new WP_Error( 'forbidden', 'You cannot delete navigation menus.' );
        $id = isset( $args['menu_id'] ) ? absint( $args['menu_id'] ) : 0;
        if ( ! isset( $args['confirm'] ) || true !== $args['confirm'] ) return new WP_Error( 'confirmation_required', 'Menu deletion requires confirm=true.' );
        if ( ! wp_get_nav_menu_object( $id ) ) return new WP_Error( 'not_found', 'Navigation menu not found.' );
        $result = wp_delete_nav_menu( $id );
        if ( is_wp_error( $result ) || ! $result ) return is_wp_error( $result ) ? $result : new WP_Error( 'delete_failed', 'WordPress could not delete the menu.' );
        return array( 'success' => true, 'menu_id' => $id, 'deleted_permanently' => true );
    }

    private static function existing_item_data( $item ) {
        return array(
            'menu-item-type'        => $item->type,
            'menu-item-object-id'   => (int) $item->object_id,
            'menu-item-object'      => $item->object,
            'menu-item-title'       => $item->title,
            'menu-item-url'         => $item->url,
            'menu-item-description' => $item->description,
            'menu-item-attr-title'  => $item->attr_title,
            'menu-item-target'      => $item->target,
            'menu-item-classes'     => implode( ' ', (array) $item->classes ),
            'menu-item-xfn'         => $item->xfn,
            'menu-item-parent-id'   => (int) $item->menu_item_parent,
            'menu-item-position'    => (int) $item->menu_order,
            'menu-item-status'      => $item->post_status,
        );
    }

    private static function save_menu_item( array $args, $creating ) {
        if ( ! self::can_manage() ) return new WP_Error( 'forbidden', 'You cannot modify navigation menu items.' );
        $menu_id = isset( $args['menu_id'] ) ? absint( $args['menu_id'] ) : 0;
        $menu = wp_get_nav_menu_object( $menu_id );
        if ( ! $menu || is_wp_error( $menu ) ) return new WP_Error( 'not_found', 'Navigation menu not found.' );
        $item_id = $creating ? 0 : ( isset( $args['menu_item_id'] ) ? absint( $args['menu_item_id'] ) : 0 );
        $existing_post = $item_id ? get_post( $item_id ) : false;
        $existing = $existing_post ? wp_setup_nav_menu_item( $existing_post ) : false;
        if ( ! $creating && ( ! $existing || 'nav_menu_item' !== $existing->post_type ) ) return new WP_Error( 'not_found', 'Navigation menu item not found.' );
        $data = $existing ? self::existing_item_data( $existing ) : array( 'menu-item-type' => 'custom', 'menu-item-object-id' => 0, 'menu-item-object' => 'custom', 'menu-item-title' => '', 'menu-item-url' => '', 'menu-item-description' => '', 'menu-item-attr-title' => '', 'menu-item-target' => '', 'menu-item-classes' => '', 'menu-item-xfn' => '', 'menu-item-parent-id' => 0, 'menu-item-position' => 0, 'menu-item-status' => 'publish' );

        if ( array_key_exists( 'type', $args ) ) $data['menu-item-type'] = sanitize_key( $args['type'] );
        if ( array_key_exists( 'object_id', $args ) ) $data['menu-item-object-id'] = absint( $args['object_id'] );
        if ( array_key_exists( 'object', $args ) ) $data['menu-item-object'] = sanitize_key( $args['object'] );
        if ( array_key_exists( 'title', $args ) ) $data['menu-item-title'] = sanitize_text_field( $args['title'] );
        if ( array_key_exists( 'url', $args ) ) $data['menu-item-url'] = esc_url_raw( $args['url'], array( 'http', 'https' ) );
        if ( array_key_exists( 'description', $args ) ) $data['menu-item-description'] = sanitize_textarea_field( $args['description'] );
        if ( array_key_exists( 'attr_title', $args ) ) $data['menu-item-attr-title'] = sanitize_text_field( $args['attr_title'] );
        if ( array_key_exists( 'target', $args ) ) $data['menu-item-target'] = '_blank' === $args['target'] ? '_blank' : '';
        if ( array_key_exists( 'classes', $args ) ) $data['menu-item-classes'] = implode( ' ', array_map( 'sanitize_html_class', (array) $args['classes'] ) );
        if ( array_key_exists( 'xfn', $args ) ) $data['menu-item-xfn'] = sanitize_text_field( $args['xfn'] );
        if ( array_key_exists( 'parent_id', $args ) ) $data['menu-item-parent-id'] = absint( $args['parent_id'] );
        if ( array_key_exists( 'position', $args ) ) $data['menu-item-position'] = absint( $args['position'] );
        if ( array_key_exists( 'status', $args ) ) $data['menu-item-status'] = 'draft' === $args['status'] ? 'draft' : 'publish';

        if ( 'custom' === $data['menu-item-type'] ) {
            if ( '' === $data['menu-item-title'] || '' === $data['menu-item-url'] ) return new WP_Error( 'invalid_menu_item', 'Custom links require a title and valid HTTP(S) URL.' );
            $data['menu-item-object'] = 'custom';
        } elseif ( 'post_type' === $data['menu-item-type'] ) {
            $object = get_post( $data['menu-item-object-id'] );
            $type = $object ? get_post_type_object( $object->post_type ) : false;
            if ( ! $object || ! $type || ! $type->public ) return new WP_Error( 'invalid_menu_item', 'Public content object not found.' );
            $data['menu-item-object'] = $object->post_type;
        } elseif ( 'taxonomy' === $data['menu-item-type'] ) {
            $taxonomy = get_taxonomy( $data['menu-item-object'] );
            $term = $taxonomy ? get_term( $data['menu-item-object-id'], $taxonomy->name ) : false;
            if ( ! $taxonomy || ! $taxonomy->public || ! $term || is_wp_error( $term ) ) return new WP_Error( 'invalid_menu_item', 'Public taxonomy term not found.' );
        } elseif ( 'post_type_archive' === $data['menu-item-type'] ) {
            $type = get_post_type_object( $data['menu-item-object'] );
            if ( ! $type || ! $type->public || ! $type->has_archive ) return new WP_Error( 'invalid_menu_item', 'Public post-type archive not found.' );
        } else {
            return new WP_Error( 'invalid_menu_item_type', 'Unsupported menu-item type.' );
        }

        $result = wp_update_nav_menu_item( $menu_id, $item_id, wp_slash( $data ) );
        if ( is_wp_error( $result ) ) return $result;
        return self::item_record( wp_setup_nav_menu_item( get_post( $result ) ) );
    }

    private static function delete_menu_item( array $args ) {
        if ( ! self::can_manage() ) return new WP_Error( 'forbidden', 'You cannot delete navigation menu items.' );
        $id = isset( $args['menu_item_id'] ) ? absint( $args['menu_item_id'] ) : 0;
        $post = get_post( $id );
        if ( ! $post || 'nav_menu_item' !== $post->post_type ) return new WP_Error( 'not_found', 'Navigation menu item not found.' );
        if ( ! isset( $args['confirm'] ) || true !== $args['confirm'] ) return new WP_Error( 'confirmation_required', 'Menu-item deletion requires confirm=true.' );
        if ( ! wp_delete_post( $id, true ) ) return new WP_Error( 'delete_failed', 'WordPress could not delete the menu item.' );
        return array( 'success' => true, 'menu_item_id' => $id, 'deleted_permanently' => true );
    }

    private static function assign_menu_location( array $args ) {
        if ( ! self::can_manage() ) return new WP_Error( 'forbidden', 'You cannot assign navigation menu locations.' );
        $location = isset( $args['location'] ) ? sanitize_key( $args['location'] ) : '';
        $registered = get_registered_nav_menus();
        if ( ! isset( $registered[ $location ] ) ) return new WP_Error( 'invalid_location', 'Theme menu location not found.' );
        $menu_id = isset( $args['menu_id'] ) ? absint( $args['menu_id'] ) : 0;
        $menu = $menu_id ? wp_get_nav_menu_object( $menu_id ) : false;
        if ( $menu_id && ( ! $menu || is_wp_error( $menu ) ) ) return new WP_Error( 'not_found', 'Navigation menu not found.' );
        $locations = get_nav_menu_locations();
        $locations[ $location ] = $menu_id;
        set_theme_mod( 'nav_menu_locations', $locations );
        return array( 'success' => true, 'location' => $location, 'menu_id' => $menu_id );
    }

    private static function get_site_settings() {
        if ( ! current_user_can( 'manage_options' ) ) return new WP_Error( 'forbidden', 'You cannot read site settings.' );
        return array(
            'site_title'             => get_option( 'blogname' ),
            'tagline'                => get_option( 'blogdescription' ),
            'timezone'               => wp_timezone_string(),
            'date_format'            => get_option( 'date_format' ),
            'time_format'            => get_option( 'time_format' ),
            'week_starts_on'         => (int) get_option( 'start_of_week' ),
            'posts_per_page'         => (int) get_option( 'posts_per_page' ),
            'homepage_display'       => get_option( 'show_on_front' ),
            'homepage_id'            => (int) get_option( 'page_on_front' ),
            'posts_page_id'           => (int) get_option( 'page_for_posts' ),
            'default_comment_status' => get_option( 'default_comment_status' ),
            'permalink_structure'    => get_option( 'permalink_structure' ),
        );
    }

    private static function update_site_settings( array $args ) {
        if ( ! current_user_can( 'manage_options' ) ) return new WP_Error( 'forbidden', 'You cannot update site settings.' );
        $map = array(
            'site_title'      => array( 'blogname', 'sanitize_text_field' ),
            'tagline'         => array( 'blogdescription', 'sanitize_text_field' ),
            'timezone'        => array( 'timezone_string', 'sanitize_text_field' ),
            'date_format'     => array( 'date_format', 'sanitize_text_field' ),
            'time_format'     => array( 'time_format', 'sanitize_text_field' ),
            'week_starts_on'  => array( 'start_of_week', 'absint' ),
            'posts_per_page'  => array( 'posts_per_page', 'absint' ),
        );
        if ( array_key_exists( 'timezone', $args ) ) {
            $timezone = sanitize_text_field( $args['timezone'] );
            $valid_timezone = in_array( $timezone, timezone_identifiers_list(), true ) || preg_match( '/^[+-](?:1[0-4]|[0-9])(?:\.5)?$/', $timezone );
            if ( ! $valid_timezone ) return new WP_Error( 'invalid_timezone', 'Timezone must be a valid IANA name or WordPress UTC offset.' );
        }
        foreach ( array( 'homepage_id', 'posts_page_id' ) as $page_field ) {
            if ( ! array_key_exists( $page_field, $args ) ) continue;
            $page_id = absint( $args[ $page_field ] );
            if ( $page_id && 'page' !== get_post_type( $page_id ) ) return new WP_Error( 'invalid_page', $page_field . ' must reference a WordPress page.' );
        }
        $updated = array();
        foreach ( $map as $input => $config ) {
            if ( ! array_key_exists( $input, $args ) ) continue;
            $value = call_user_func( $config[1], $args[ $input ] );
            if ( 'week_starts_on' === $input ) $value = min( 6, $value );
            if ( 'posts_per_page' === $input ) $value = min( 100, max( 1, $value ) );
            update_option( $config[0], $value );
            $updated[] = $input;
        }
        if ( array_key_exists( 'homepage_display', $args ) ) {
            $value = 'page' === $args['homepage_display'] ? 'page' : 'posts';
            update_option( 'show_on_front', $value );
            $updated[] = 'homepage_display';
        }
        foreach ( array( 'homepage_id' => 'page_on_front', 'posts_page_id' => 'page_for_posts' ) as $input => $option ) {
            if ( ! array_key_exists( $input, $args ) ) continue;
            $id = absint( $args[ $input ] );
            update_option( $option, $id );
            $updated[] = $input;
        }
        if ( array_key_exists( 'default_comment_status', $args ) ) {
            $status = 'open' === $args['default_comment_status'] ? 'open' : 'closed';
            update_option( 'default_comment_status', $status );
            $updated[] = 'default_comment_status';
        }
        if ( empty( $updated ) ) return new WP_Error( 'no_changes', 'No supported settings were supplied.' );
        return array( 'success' => true, 'updated' => $updated, 'settings' => self::get_site_settings() );
    }
}
