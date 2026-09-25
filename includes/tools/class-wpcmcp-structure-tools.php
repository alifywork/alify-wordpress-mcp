<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class WPCMCP_Structure_Tools {
    const POST_TYPES_OPTION = 'wpcmcp_managed_post_types';
    const TAXONOMIES_OPTION = 'wpcmcp_managed_taxonomies';

    public static function bootstrap() {
        add_action( 'init', array( __CLASS__, 'register_managed_structures' ), 9 );
    }

    public static function register_managed_structures() {
        $post_types = get_option( self::POST_TYPES_OPTION, array() );
        if ( is_array( $post_types ) ) {
            foreach ( $post_types as $slug => $args ) {
                if ( ! post_type_exists( $slug ) && is_array( $args ) ) {
                    register_post_type( $slug, $args );
                }
            }
        }

        $taxonomies = get_option( self::TAXONOMIES_OPTION, array() );
        if ( is_array( $taxonomies ) ) {
            foreach ( $taxonomies as $slug => $definition ) {
                if ( taxonomy_exists( $slug ) || ! is_array( $definition ) ) {
                    continue;
                }
                $object_types = isset( $definition['object_types'] ) ? (array) $definition['object_types'] : array( 'post' );
                $args = isset( $definition['args'] ) && is_array( $definition['args'] ) ? $definition['args'] : array();
                register_taxonomy( $slug, $object_types, $args );
            }
        }
    }

    public static function definitions( $can_read, $can_write ) {
        $tools = array();
        if ( $can_read ) {
            $tools[] = self::t( 'wordpress.get_post_type_definition', 'Get Post Type Definition', 'Inspect a registered post type including archive, rewrite, supports, taxonomies and whether it is managed by this MCP plugin.', array(
                'post_type' => array( 'type' => 'string' ),
            ), array( 'post_type' ), true, false, true );
            $tools[] = self::t( 'wordpress.get_taxonomy_definition', 'Get Taxonomy Definition', 'Inspect a registered taxonomy including rewrite, hierarchy, object types and whether it is managed by this MCP plugin.', array(
                'taxonomy' => array( 'type' => 'string' ),
            ), array( 'taxonomy' ), true, false, true );
            $tools[] = self::t( 'wordpress.get_archive_info', 'Get Archive Info', 'Inspect archive URL, rewrite slug, archive enablement and queryability for a public post type.', array(
                'post_type' => array( 'type' => 'string' ),
            ), array( 'post_type' ), true, false, true );
        }

        if ( $can_write ) {
            $tools[] = self::t( 'wordpress.save_post_type_definition', 'Create or Update Post Type', 'Create or update a plugin-managed custom post type, including archive behavior, rewrite slug, supports, REST exposure, hierarchy and taxonomy connections.', array(
                'post_type' => array( 'type' => 'string', 'maxLength' => 20 ),
                'label' => array( 'type' => 'string' ),
                'singular_label' => array( 'type' => 'string' ),
                'description' => array( 'type' => 'string' ),
                'public' => array( 'type' => 'boolean' ),
                'publicly_queryable' => array( 'type' => 'boolean' ),
                'show_ui' => array( 'type' => 'boolean' ),
                'show_in_rest' => array( 'type' => 'boolean' ),
                'hierarchical' => array( 'type' => 'boolean' ),
                'has_archive' => array( 'type' => array( 'boolean', 'string' ) ),
                'rewrite_slug' => array( 'type' => 'string' ),
                'with_front' => array( 'type' => 'boolean' ),
                'menu_icon' => array( 'type' => 'string' ),
                'menu_position' => array( 'type' => 'integer' ),
                'supports' => array( 'type' => 'array', 'items' => array( 'type' => 'string' ) ),
                'taxonomies' => array( 'type' => 'array', 'items' => array( 'type' => 'string' ) ),
                'capability_type' => array( 'type' => 'string' ),
                'map_meta_cap' => array( 'type' => 'boolean' ),
            ), array( 'post_type', 'label' ), false, false, true );
            $tools[] = self::t( 'wordpress.delete_post_type_definition', 'Delete Post Type Definition', 'Delete a post-type definition created by this MCP plugin. Existing content is not deleted. Requires confirm=true.', array(
                'post_type' => array( 'type' => 'string' ),
                'confirm' => array( 'type' => 'boolean' ),
            ), array( 'post_type', 'confirm' ), false, true, true );
            $tools[] = self::t( 'wordpress.save_taxonomy_definition', 'Create or Update Taxonomy', 'Create or update a plugin-managed taxonomy, including hierarchy, rewrite, REST visibility, admin column and connected post types.', array(
                'taxonomy' => array( 'type' => 'string', 'maxLength' => 32 ),
                'label' => array( 'type' => 'string' ),
                'singular_label' => array( 'type' => 'string' ),
                'object_types' => array( 'type' => 'array', 'minItems' => 1, 'items' => array( 'type' => 'string' ) ),
                'description' => array( 'type' => 'string' ),
                'public' => array( 'type' => 'boolean' ),
                'publicly_queryable' => array( 'type' => 'boolean' ),
                'show_ui' => array( 'type' => 'boolean' ),
                'show_in_rest' => array( 'type' => 'boolean' ),
                'show_admin_column' => array( 'type' => 'boolean' ),
                'hierarchical' => array( 'type' => 'boolean' ),
                'rewrite_slug' => array( 'type' => 'string' ),
                'with_front' => array( 'type' => 'boolean' ),
            ), array( 'taxonomy', 'label', 'object_types' ), false, false, true );
            $tools[] = self::t( 'wordpress.delete_taxonomy_definition', 'Delete Taxonomy Definition', 'Delete a taxonomy definition created by this MCP plugin. Existing term rows are not manually purged. Requires confirm=true.', array(
                'taxonomy' => array( 'type' => 'string' ),
                'confirm' => array( 'type' => 'boolean' ),
            ), array( 'taxonomy', 'confirm' ), false, true, true );
            $tools[] = self::t( 'wordpress.flush_rewrite_rules', 'Flush Rewrite Rules', 'Flush WordPress rewrite rules after archive, post-type or taxonomy changes. Requires confirm=true.', array(
                'hard' => array( 'type' => 'boolean' ),
                'confirm' => array( 'type' => 'boolean' ),
            ), array( 'confirm' ), false, true, true );
        }
        return $tools;
