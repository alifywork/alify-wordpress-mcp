<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class WPCMCP_Tools {
    public static function definitions( array $scopes ) {
        $settings = get_option( 'wpcmcp_settings', array( 'read_tools' => 1, 'write_tools' => 1 ) );
        $tools = array();
        $can_read = ! empty( $settings['read_tools'] ) && in_array( 'wordpress.read', $scopes, true );
        $can_write = ! empty( $settings['write_tools'] ) && in_array( 'wordpress.write', $scopes, true );
        if ( $can_read ) {
            $tools = array_merge( $tools, self::read_definitions() );
        }
        if ( $can_write ) {
            $tools = array_merge( $tools, self::write_definitions() );
        }
        foreach ( self::extension_classes() as $class ) {
            if ( class_exists( $class ) ) {
                $tools = array_merge( $tools, $class::definitions( $can_read, $can_write ) );
            }
        }
        return $tools;
    }

    private static function extension_classes() {
        return array(
            'WPCMCP_Content_Extras_Tools',
            'WPCMCP_Community_Tools',
            'WPCMCP_Admin_Tools',
            'WPCMCP_Extension_Manager_Tools',
            'WPCMCP_Theme_Source_Tools',
            'WPCMCP_WooCommerce_Tools',
        );
    }

    private static function read_definitions() {
        $pagination = array(
            'per_page' => array( 'type' => 'integer', 'minimum' => 1, 'maximum' => 50, 'description' => 'Number of records, max 50.' ),
            'page'     => array( 'type' => 'integer', 'minimum' => 1, 'description' => 'Page number.' ),
        );

        return array(
            self::tool( 'wordpress.site_info', 'WordPress Site Info', 'Get basic information about this WordPress site and enabled MCP capabilities.', array(), array(), true, false, true ),
            self::tool( 'wordpress.get_site_info', 'Get Site Info', 'Get WordPress site name, URL, timezone, versions and MCP/ACF availability.', array(), array(), true, false, true ),
            self::tool( 'wordpress.get_wordpress_version', 'Get WordPress Version', 'Get the installed WordPress and PHP versions.', array(), array(), true, false, true ),
            self::tool( 'wordpress.list_post_types', 'List Post Types', 'List public WordPress post types that this connection can access.', array(), array(), true, false, true ),
            self::tool(
                'wordpress.search_content',
                'Search WordPress Content',
                'Search posts, pages, or public custom post types. Returns concise records with IDs, titles, status, type, dates, URLs and excerpts.',
                array(
                    'search'     => array( 'type' => 'string', 'description' => 'Optional search text.' ),
                    'post_types' => array( 'type' => 'array', 'items' => array( 'type' => 'string' ), 'description' => 'Optional post types. Defaults to post and page.' ),
                    'status'     => array( 'type' => 'string', 'description' => 'Optional post status. Defaults to publish.' ),
                ) + $pagination,
                array(), true, false, true
            ),
            self::tool( 'wordpress.get_content', 'Get WordPress Content', 'Get one WordPress post/page/CPT by ID.', array( 'id' => array( 'type' => 'integer', 'minimum' => 1 ) ), array( 'id' ), true, false, true ),
            self::tool( 'wordpress.get_acf_fields', 'Get ACF Fields', 'Get Advanced Custom Fields values for a post when ACF is installed.', array( 'id' => array( 'type' => 'integer', 'minimum' => 1 ) ), array( 'id' ), true, false, true ),

            self::tool( 'wordpress.list_posts', 'List Posts', 'List WordPress posts with pagination, search and status filters.', array( 'search' => array( 'type' => 'string' ), 'status' => array( 'type' => 'string' ) ) + $pagination, array(), true, false, true ),
            self::tool( 'wordpress.get_post', 'Get Post', 'Get one WordPress post by ID.', array( 'id' => array( 'type' => 'integer', 'minimum' => 1 ) ), array( 'id' ), true, false, true ),
            self::tool( 'wordpress.list_pages', 'List Pages', 'List WordPress pages with pagination, search and status filters.', array( 'search' => array( 'type' => 'string' ), 'status' => array( 'type' => 'string' ) ) + $pagination, array(), true, false, true ),
            self::tool( 'wordpress.get_page', 'Get Page', 'Get one WordPress page by ID.', array( 'id' => array( 'type' => 'integer', 'minimum' => 1 ) ), array( 'id' ), true, false, true ),

            self::tool( 'wordpress.list_media', 'List Media', 'List media library attachments.', array( 'search' => array( 'type' => 'string' ), 'mime_type' => array( 'type' => 'string', 'description' => 'Optional MIME type, e.g. image or image/jpeg.' ) ) + $pagination, array(), true, false, true ),
            self::tool( 'wordpress.get_media', 'Get Media', 'Get one media attachment by ID.', array( 'id' => array( 'type' => 'integer', 'minimum' => 1 ) ), array( 'id' ), true, false, true ),

            self::tool( 'wordpress.list_users', 'List Users', 'List WordPress users. Requires the WordPress list_users capability.', array( 'search' => array( 'type' => 'string' ), 'role' => array( 'type' => 'string' ) ) + $pagination, array(), true, false, true ),
            self::tool( 'wordpress.get_user', 'Get User', 'Get one WordPress user by ID. Email is intentionally not returned.', array( 'id' => array( 'type' => 'integer', 'minimum' => 1 ) ), array( 'id' ), true, false, true ),

            self::tool( 'wordpress.list_plugins', 'List Plugins', 'List installed WordPress plugins and activation status. Requires plugin administration capability.', array(), array(), true, false, true ),
            self::tool( 'wordpress.get_plugin_status', 'Get Plugin Status', 'Get status/details for an installed plugin by plugin file or slug.', array( 'plugin' => array( 'type' => 'string', 'description' => 'Plugin file such as akismet/akismet.php or a slug/name fragment.' ) ), array( 'plugin' ), true, false, true ),
            self::tool( 'wordpress.get_active_theme', 'Get Active Theme', 'Get the currently active WordPress theme.', array(), array(), true, false, true ),
            self::tool( 'wordpress.list_themes', 'List Themes', 'List installed WordPress themes. Requires theme administration capability.', array(), array(), true, false, true ),

            self::tool( 'wordpress.list_categories', 'List Categories', 'List WordPress post categories.', array( 'search' => array( 'type' => 'string' ), 'hide_empty' => array( 'type' => 'boolean' ) ) + $pagination, array(), true, false, true ),
            self::tool( 'wordpress.list_tags', 'List Tags', 'List WordPress post tags.', array( 'search' => array( 'type' => 'string' ), 'hide_empty' => array( 'type' => 'boolean' ) ) + $pagination, array(), true, false, true ),
            self::tool( 'wordpress.list_comments', 'List Comments', 'List WordPress comments. Non-moderators are limited to approved comments.', array( 'post_id' => array( 'type' => 'integer', 'minimum' => 1 ), 'status' => array( 'type' => 'string', 'enum' => array( 'approve', 'hold', 'spam', 'trash', 'all' ) ), 'search' => array( 'type' => 'string' ) ) + $pagination, array(), true, false, true ),
            self::tool( 'wordpress.get_comment', 'Get Comment', 'Get one WordPress comment by ID.', array( 'id' => array( 'type' => 'integer', 'minimum' => 1 ) ), array( 'id' ), true, false, true ),
        );
    }

    private static function write_definitions() {
        $content_fields = array(
            'title'   => array( 'type' => 'string' ),
            'content' => array( 'type' => 'string' ),
            'excerpt' => array( 'type' => 'string' ),
            'status'  => array( 'type' => 'string', 'enum' => array( 'draft', 'pending', 'private', 'publish' ) ),
            'slug'    => array( 'type' => 'string' ),
        );

        return array(
            self::tool(
                'wordpress.create_content', 'Create WordPress Content', 'Create a WordPress post, page, or public custom post type. Use draft unless publishing was explicitly requested.',
                array( 'post_type' => array( 'type' => 'string' ) ) + $content_fields,
                array( 'post_type', 'title' ), false, false, false
            ),
            self::tool(
                'wordpress.update_content', 'Update WordPress Content', 'Update an existing WordPress post/page/CPT. Only provided fields are changed.',
                array( 'id' => array( 'type' => 'integer', 'minimum' => 1 ) ) + $content_fields,
                array( 'id' ), false, false, true
            ),
            self::tool( 'wordpress.trash_content', 'Move Content to Trash', 'Move an existing WordPress post/page/CPT to Trash.', array( 'id' => array( 'type' => 'integer', 'minimum' => 1 ) ), array( 'id' ), false, true, true ),
            self::tool(
                'wordpress.update_acf_field', 'Update ACF Field', 'Update one ACF field for a post. ACF must be installed.',
                array(
                    'id'    => array( 'type' => 'integer', 'minimum' => 1 ),
                    'field' => array( 'type' => 'string' ),
                    'value' => array( 'description' => 'New ACF value. May be string, number, boolean, array or object.' ),
                ),
                array( 'id', 'field', 'value' ), false, false, true
            ),

            self::tool( 'wordpress.create_post', 'Create Post', 'Create a WordPress post. Defaults to draft.', $content_fields, array( 'title' ), false, false, false ),
            self::tool( 'wordpress.update_post', 'Update Post', 'Update an existing WordPress post.', array( 'id' => array( 'type' => 'integer', 'minimum' => 1 ) ) + $content_fields, array( 'id' ), false, false, true ),
            self::tool( 'wordpress.delete_post', 'Delete Post', 'Move a WordPress post to Trash. This does not permanently delete it.', array( 'id' => array( 'type' => 'integer', 'minimum' => 1 ) ), array( 'id' ), false, true, true ),
            self::tool( 'wordpress.create_page', 'Create Page', 'Create a WordPress page. Defaults to draft.', $content_fields, array( 'title' ), false, false, false ),
            self::tool( 'wordpress.update_page', 'Update Page', 'Update an existing WordPress page.', array( 'id' => array( 'type' => 'integer', 'minimum' => 1 ) ) + $content_fields, array( 'id' ), false, false, true ),
            self::tool( 'wordpress.delete_page', 'Delete Page', 'Move a WordPress page to Trash. This does not permanently delete it.', array( 'id' => array( 'type' => 'integer', 'minimum' => 1 ) ), array( 'id' ), false, true, true ),
            self::tool(
                'wordpress.upload_media',
                'Upload Media',
                'Download a public HTTPS raster image into the WordPress Media Library. The file must be an allowed WordPress image type and fit within the site upload limit (maximum 10 MB).',
                array(
                    'url'         => array( 'type' => 'string', 'format' => 'uri', 'description' => 'Public HTTPS image URL to download.' ),
                    'filename'    => array( 'type' => 'string', 'description' => 'Optional filename. WordPress will normalize its extension to the detected image type.' ),
                    'title'       => array( 'type' => 'string', 'description' => 'Optional Media Library title.' ),
                    'alt_text'    => array( 'type' => 'string', 'description' => 'Optional accessible alternative text.' ),
                    'caption'     => array( 'type' => 'string', 'description' => 'Optional image caption.' ),
                    'description' => array( 'type' => 'string', 'description' => 'Optional image description; safe HTML is allowed.' ),
                    'post_id'     => array( 'type' => 'integer', 'minimum' => 1, 'description' => 'Optional post/page/CPT ID to use as the attachment parent.' ),
                ),
                array( 'url' ), false, false, false, true
            ),
