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
        if ( array_key_exists( 'object_id', $args ) ) $data['