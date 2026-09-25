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
            case 'wordpress.get_site_settings'