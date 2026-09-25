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
    }

    private static function t( $name, $title, $description, $properties, $required, $read_only, $destructive, $idempotent ) {
        return WPCMCP_Tools::tool( $name, $title, $description, $properties, $required, $read_only, $destructive, $idempotent );
    }

    public static function execute( $name, array $args ) {
        switch ( $name ) {
            case 'wordpress.get_post_type_definition': return self::get_post_type_definition( $args );
            case 'wordpress.get_taxonomy_definition': return self::get_taxonomy_definition( $args );
            case 'wordpress.get_archive_info': return self::get_archive_info( $args );
            case 'wordpress.save_post_type_definition': return self::save_post_type_definition( $args );
            case 'wordpress.delete_post_type_definition': return self::delete_post_type_definition( $args );
            case 'wordpress.save_taxonomy_definition': return self::save_taxonomy_definition( $args );
            case 'wordpress.delete_taxonomy_definition': return self::delete_taxonomy_definition( $args );
            case 'wordpress.flush_rewrite_rules': return self::flush_rewrites( $args );
            default: return null;
        }
    }

    private static function ensure_manage_options() {
        return current_user_can( 'manage_options' ) ? true : new WP_Error( 'forbidden', 'This structural operation requires manage_options.' );
    }

    private static function sanitize_supports( $supports ) {
        $allowed = array( 'title', 'editor', 'author', 'thumbnail', 'excerpt', 'trackbacks', 'custom-fields', 'comments', 'revisions', 'page-attributes', 'post-formats' );
        return array_values( array_intersect( array_map( 'sanitize_key', (array) $supports ), $allowed ) );
    }

    private static function get_post_type_definition( array $args ) {
        $slug = sanitize_key( isset( $args['post_type'] ) ? $args['post_type'] : '' );
        $obj = get_post_type_object( $slug );
        if ( ! $obj ) return new WP_Error( 'not_found', 'Post type not found.' );
        $managed = get_option( self::POST_TYPES_OPTION, array() );
        return array(
            'post_type' => $slug,
            'label' => $obj->label,
            'description' => $obj->description,
            'public' => (bool) $obj->public,
            'publicly_queryable' => (bool) $obj->publicly_queryable,
            'show_ui' => (bool) $obj->show_ui,
            'show_in_rest' => (bool) $obj->show_in_rest,
            'hierarchical' => (bool) $obj->hierarchical,
            'has_archive' => $obj->has_archive,
            'rewrite' => $obj->rewrite,
            'supports' => get_all_post_type_supports( $slug ),
            'taxonomies' => get_object_taxonomies( $slug ),
            'managed_by_mcp' => isset( $managed[ $slug ] ),
            'managed_definition' => isset( $managed[ $slug ] ) ? $managed[ $slug ] : null,
        );
    }

    private static function get_taxonomy_definition( array $args ) {
        $slug = sanitize_key( isset( $args['taxonomy'] ) ? $args['taxonomy'] : '' );
        $obj = get_taxonomy( $slug );
        if ( ! $obj ) return new WP_Error( 'not_found', 'Taxonomy not found.' );
        $managed = get_option( self::TAXONOMIES_OPTION, array() );
        return array(
            'taxonomy' => $slug,
            'label' => $obj->label,
            'description' => $obj->description,
            'public' => (bool) $obj->public,
            'publicly_queryable' => (bool) $obj->publicly_queryable,
            'show_ui' => (bool) $obj->show_ui,
            'show_in_rest' => (bool) $obj->show_in_rest,
            'hierarchical' => (bool) $obj->hierarchical,
            'rewrite' => $obj->rewrite,
            'object_types' => array_values( $obj->object_type ),
            'managed_by_mcp' => isset( $managed[ $slug ] ),
            'managed_definition' => isset( $managed[ $slug ] ) ? $managed[ $slug ] : null,
        );
    }

    private static function get_archive_info( array $args ) {
        $slug = sanitize_key( isset( $args['post_type'] ) ? $args['post_type'] : '' );
        $obj = get_post_type_object( $slug );
        if ( ! $obj || ! $obj->public ) return new WP_Error( 'not_found', 'Public post type not found.' );
        return array(
            'post_type' => $slug,
            'has_archive' => $obj->has_archive,
            'archive_url' => $obj->has_archive ? get_post_type_archive_link( $slug ) : null,
            'rewrite' => $obj->rewrite,
            'publicly_queryable' => (bool) $obj->publicly_queryable,
            'query_var' => $obj->query_var,
        );
    }

    private static function save_post_type_definition( array $args ) {
        $cap = self::ensure_manage_options();
        if ( is_wp_error( $cap ) ) return $cap;
        $slug = sanitize_key( isset( $args['post_type'] ) ? $args['post_type'] : '' );
        if ( ! $slug || strlen( $slug ) > 20 ) return new WP_Error( 'invalid_post_type', 'Post type key must be 1-20 safe characters.' );
        if ( post_type_exists( $slug ) ) {
            $managed = get_option( self::POST_TYPES_OPTION, array() );
            if ( ! isset( $managed[ $slug ] ) ) return new WP_Error( 'unmanaged_post_type', 'Existing post types not created by this MCP plugin cannot be overwritten. Use theme-source editing or ACF UI for that definition.' );
        }
        $label = sanitize_text_field( $args['label'] );
        $singular = isset( $args['singular_label'] ) ? sanitize_text_field( $args['singular_label'] ) : $label;
        $has_archive = isset( $args['has_archive'] ) ? $args['has_archive'] : true;
        if ( is_string( $has_archive ) ) $has_archive = sanitize_title( $has_archive );
