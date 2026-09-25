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
            self::tool(
                'wordpress.upload_media_file',
                'Upload ChatGPT File',
                'Upload a ChatGPT-provided raster image into the WordPress Media Library. Use this for images attached, generated, or selected from the ChatGPT file library. The image must fit within the site upload limit (maximum 10 MB).',
                array(
                    'file'        => array(
                        'type'                 => 'object',
                        'description'          => 'Image file supplied by ChatGPT.',
                        'properties'           => array(
                            'download_url' => array( 'type' => 'string', 'format' => 'uri', 'description' => 'Temporary HTTPS download URL supplied by ChatGPT.' ),
                            'file_id'      => array( 'type' => 'string', 'description' => 'ChatGPT file identifier.' ),
                            'mime_type'    => array( 'type' => 'string', 'description' => 'Optional file MIME type supplied by ChatGPT.' ),
                            'file_name'    => array( 'type' => 'string', 'description' => 'Optional original filename supplied by ChatGPT.' ),
                        ),
                        'required'             => array( 'download_url', 'file_id' ),
                        'additionalProperties' => false,
                    ),
                    'filename'    => array( 'type' => 'string', 'description' => 'Optional filename override. WordPress will normalize its extension to the detected image type.' ),
                    'title'       => array( 'type' => 'string', 'description' => 'Optional Media Library title.' ),
                    'alt_text'    => array( 'type' => 'string', 'description' => 'Optional accessible alternative text.' ),
                    'caption'     => array( 'type' => 'string', 'description' => 'Optional image caption.' ),
                    'description' => array( 'type' => 'string', 'description' => 'Optional image description; safe HTML is allowed.' ),
                    'post_id'     => array( 'type' => 'integer', 'minimum' => 1, 'description' => 'Optional post/page/CPT ID to use as the attachment parent.' ),
                ),
                array( 'file' ), false, false, false, true,
                array( 'openai/fileParams' => array( 'file' ) )
            ),
            self::tool(
                'wordpress.set_featured_image',
                'Set Featured Image',
                'Set an existing Media Library image as the featured image for a post, page, or public custom post type.',
                array(
                    'post_id'  => array( 'type' => 'integer', 'minimum' => 1, 'description' => 'Target post, page, or public CPT ID.' ),
                    'media_id' => array( 'type' => 'integer', 'minimum' => 1, 'description' => 'Existing image attachment ID.' ),
                ),
                array( 'post_id', 'media_id' ), false, false, true
            ),
        );
    }

    public static function tool( $name, $title, $description, $properties, $required, $read_only, $destructive, $idempotent, $open_world = false, $meta = array() ) {
        $tool = array(
            'name'        => $name,
            'title'       => $title,
            'description' => $description,
            'inputSchema' => array(
                '$schema'              => 'https://json-schema.org/draft/2020-12/schema',
                'type'                 => 'object',
                'properties'           => (object) $properties,
                'required'             => $required,
                'additionalProperties' => false,
            ),
            'annotations' => array(
                'title'           => $title,
                'readOnlyHint'    => (bool) $read_only,
                'destructiveHint' => (bool) $destructive,
                'idempotentHint'  => (bool) $idempotent,
                'openWorldHint'   => (bool) $open_world,
            ),
        );

        if ( ! empty( $meta ) && is_array( $meta ) ) {
            $tool['_meta'] = $meta;
        }

        return $tool;
    }

    public static function validate_arguments( array $definition, array $args ) {
        if ( empty( $definition['inputSchema'] ) || ! is_array( $definition['inputSchema'] ) ) {
            return true;
        }
        return self::validate_schema_value( $definition['inputSchema'], $args, 'arguments' );
    }

    private static function validate_schema_value( array $schema, $value, $path ) {
        $types = isset( $schema['type'] ) ? (array) $schema['type'] : array();
        if ( ! empty( $types ) ) {
            $valid_type = false;
            foreach ( $types as $type ) {
                if ( self::matches_schema_type( $value, $type ) ) {
                    $valid_type = true;
                    break;
                }
            }
            if ( ! $valid_type ) return new WP_Error( 'invalid_tool_arguments', $path . ' has an invalid type.' );
        }

        if ( isset( $schema['enum'] ) && ! in_array( $value, $schema['enum'], true ) ) {
            return new WP_Error( 'invalid_tool_arguments', $path . ' is not one of the allowed values.' );
        }
        if ( array_key_exists( 'const', $schema ) && $value !== $schema['const'] ) {
            return new WP_Error( 'invalid_tool_arguments', $path . ' must equal the required constant value.' );
        }
        if ( is_int( $value ) || is_float( $value ) ) {
            if ( isset( $schema['minimum'] ) && $value < $schema['minimum'] ) return new WP_Error( 'invalid_tool_arguments', $path . ' is below the minimum.' );
            if ( isset( $schema['maximum'] ) && $value > $schema['maximum'] ) return new WP_Error( 'invalid_tool_arguments', $path . ' exceeds the maximum.' );
        }
        if ( is_string( $value ) ) {
            if ( isset( $schema['minLength'] ) && strlen( $value ) < (int) $schema['minLength'] ) return new WP_Error( 'invalid_tool_arguments', $path . ' is shorter than allowed.' );
            if ( isset( $schema['maxLength'] ) && strlen( $value ) > (int) $schema['maxLength'] ) return new WP_Error( 'invalid_tool_arguments', $path . ' is longer than allowed.' );
            if ( isset( $schema['format'] ) && 'email' === $schema['format'] && ! is_email( $value ) ) return new WP_Error( 'invalid_tool_arguments', $path . ' must be a valid email address.' );
            if ( isset( $schema['format'] ) && 'uri' === $schema['format'] && ! filter_var( $value, FILTER_VALIDATE_URL ) ) return new WP_Error( 'invalid_tool_arguments', $path . ' must be a valid URI.' );
        }
        if ( is_array( $value ) && self::schema_is_array( $schema ) ) {
            if ( isset( $schema['minItems'] ) && count( $value ) < (int) $schema['minItems'] ) return new WP_Error( 'invalid_tool_arguments', $path . ' contains too few items.' );
            if ( isset( $schema['maxItems'] ) && count( $value ) > (int) $schema['maxItems'] ) return new WP_Error( 'invalid_tool_arguments', $path . ' contains too many items.' );
            if ( isset( $schema['items'] ) && is_array( $schema['items'] ) ) {
                foreach ( $value as $index => $item ) {
                    $result = self::validate_schema_value( $schema['items'], $item, $path . '[' . $index . ']' );
                    if ( is_wp_error( $result ) ) return $result;
                }
            }
        }
        if ( is_array( $value ) && self::schema_is_object( $schema ) ) {
            $properties = isset( $schema['properties'] ) ? (array) $schema['properties'] : array();
            foreach ( isset( $schema['required'] ) ? (array) $schema['required'] : array() as $required ) {
                if ( ! array_key_exists( $required, $value ) ) return new WP_Error( 'invalid_tool_arguments', $path . '.' . $required . ' is required.' );
            }
            if ( isset( $schema['additionalProperties'] ) && false === $schema['additionalProperties'] ) {
                foreach ( array_keys( $value ) as $key ) if ( ! array_key_exists( $key, $properties ) ) return new WP_Error( 'invalid_tool_arguments', $path . '.' . $key . ' is not supported.' );
            }
            foreach ( $properties as $key => $property_schema ) {
                if ( ! array_key_exists( $key, $value ) || ! is_array( $property_schema ) ) continue;
                $result = self::validate_schema_value( $property_schema, $value[ $key ], $path . '.' . $key );
                if ( is_wp_error( $result ) ) return $result;
            }
        }
        return true;
    }

    private static function schema_is_array( array $schema ) {
        return isset( $schema['type'] ) && in_array( 'array', (array) $schema['type'], true );
    }

    private static function schema_is_object( array $schema ) {
        return isset( $schema['type'] ) && in_array( 'object', (array) $schema['type'], true );
    }

    private static function matches_schema_type( $value, $type ) {
        switch ( $type ) {
            case 'null': return null === $value;
            case 'boolean': return is_bool( $value );
            case 'integer': return is_int( $value );
            case 'number': return is_int( $value ) || is_float( $value );
            case 'string': return is_string( $value );
            case 'array': return is_array( $value ) && self::is_list_array( $value );
            case 'object': return is_array( $value ) && ( empty( $value ) || ! self::is_list_array( $value ) );
            default: return true;
        }
    }

    private static function is_list_array( array $value ) {
        $index = 0;
        foreach ( $value as $key => $unused ) {
            if ( $key !== $index ) return false;
            $index++;
        }
        return true;
    }

    public static function execute( $name, array $args ) {
        switch ( $name ) {
            case 'wordpress.site_info':
            case 'wordpress.get_site_info':
                return self::site_info();
            case 'wordpress.get_wordpress_version':
                return self::wordpress_version();
            case 'wordpress.list_post_types':
                return self::list_post_types();
            case 'wordpress.search_content':
                return self::search_content( $args );
            case 'wordpress.get_content':
                return self::get_content( $args );
            case 'wordpress.get_acf_fields':
                return self::get_acf_fields( $args );

            case 'wordpress.list_posts':
                return self::list_typed_content( 'post', $args );
            case 'wordpress.get_post':
                return self::get_typed_content( 'post', $args );
            case 'wordpress.create_post':
                return self::create_typed_content( 'post', $args );
            case 'wordpress.update_post':
                return self::update_typed_content( 'post', $args );
            case 'wordpress.delete_post':
                return self::trash_typed_content( 'post', $args );

            case 'wordpress.list_pages':
                return self::list_typed_content( 'page', $args );
            case 'wordpress.get_page':
                return self::get_typed_content( 'page', $args );
            case 'wordpress.create_page':
                return self::create_typed_content( 'page', $args );
            case 'wordpress.update_page':
                return self::update_typed_content( 'page', $args );
            case 'wordpress.delete_page':
                return self::trash_typed_content( 'page', $args );

            case 'wordpress.list_media':
                return self::list_media( $args );
            case 'wordpress.get_media':
                return self::get_media( $args );
            case 'wordpress.upload_media':
                return self::upload_media( $args );
            case 'wordpress.upload_media_file':
                return self::upload_media_file( $args );
            case 'wordpress.set_featured_image':
                return self::set_featured_image( $args );
            case 'wordpress.list_users':
                return self::list_users( $args );
            case 'wordpress.get_user':
                return self::get_user( $args );
            case 'wordpress.list_plugins':
                return self::list_plugins();
            case 'wordpress.get_plugin_status':
                return self::get_plugin_status( $args );
            case 'wordpress.get_active_theme':
                return self::get_active_theme();
            case 'wordpress.list_themes':
                return self::list_themes();
            case 'wordpress.list_categories':
                return self::list_terms( 'category', $args );
            case 'wordpress.list_tags':
                return self::list_terms( 'post_tag', $args );
            case 'wordpress.list_comments':
                return self::list_comments( $args );
            case 'wordpress.get_comment':
                return self::get_comment( $args );

            case 'wordpress.create_content':
                return self::create_content( $args );
            case 'wordpress.update_content':
                return self::update_content( $args );
            case 'wordpress.trash_content':
                return self::trash_content( $args );
            case 'wordpress.update_acf_field':
                return self::update_acf_field( $args );
            default:
                foreach ( self::extension_classes() as $class ) {
                    if ( class_exists( $class ) ) {
                        $result = $class::execute( $name, $args );
                        if ( null !== $result ) {
                            return $result;
                        }
                    }
                }
                return new WP_Error( 'unknown_tool', 'Unknown MCP tool.' );
        }
    }

    private static function site_info() {
        return array(
            'name'                => get_bloginfo( 'name' ),
            'description'         => get_bloginfo( 'description' ),
            'url'                 => home_url( '/' ),
            'wordpress_version'   => get_bloginfo( 'version' ),
            'php_version'         => PHP_VERSION,
            'timezone'            => wp_timezone_string(),
            'acf_available'       => function_exists( 'get_fields' ) && function_exists( 'update_field' ),
            'mcp_plugin_version'  => WPCMCP_VERSION,
            'mcp_modern_protocol' => defined( 'WPCMCP_VERSION' ) ? '2026-07-28' : '',
        );
    }

    private static function wordpress_version() {
        return array(
            'wordpress_version' => get_bloginfo( 'version' ),
            'php_version'       => PHP_VERSION,
            'mcp_plugin_version'=> WPCMCP_VERSION,
        );
    }

    private static function list_post_types() {
        $objects = get_post_types( array( 'public' => true ), 'objects' );
        $out = array();
        foreach ( $objects as $name => $obj ) {
            if ( 'attachment' === $name ) continue;
            $out[] = array(
                'name'         => $name,
                'label'        => $obj->label,
                'rest_base'    => $obj->rest_base ? $obj->rest_base : $name,
                'hierarchical' => (bool) $obj->hierarchical,
            );
        }
        return array( 'post_types' => $out );
    }

    private static function allowed_post_type( $type ) {
        $obj = get_post_type_object( $type );
        return $obj && $obj->public && 'attachment' !== $type;
    }

    private static function search_content( array $args ) {
        $types = isset( $args['post_types'] ) && is_array( $args['post_types'] ) ? array_map( 'sanitize_key', $args['post_types'] ) : array( 'post', 'page' );
        $types = array_values( array_filter( $types, array( __CLASS__, 'allowed_post_type' ) ) );
        if ( empty( $types ) ) return new WP_Error( 'invalid_post_type', 'No permitted public post types were supplied.' );

        $status = isset( $args['status'] ) ? sanitize_key( $args['status'] ) : 'publish';
        $allowed_statuses = current_user_can( 'edit_posts' ) ? array( 'publish', 'draft', 'pending', 'private', 'future' ) : array( 'publish' );
        if ( ! in_array( $status, $allowed_statuses, true ) ) $status = 'publish';

        $per_page = self::per_page( $args );
        $page = self::page( $args );
        $query = new WP_Query(
            array(
                'post_type'      => $types,
                'post_status'    => $status,
                's'              => isset( $args['search'] ) ? sanitize_text_field( $args['search'] ) : '',
                'posts_per_page' => $per_page,
                'paged'          => $page,
                'orderby'        => 'modified',
                'order'          => 'DESC',
                'no_found_rows'  => false,
            )
        );

        $items = array();
        foreach ( $query->posts as $post ) {
            if ( ! current_user_can( 'read_post', $post->ID ) ) continue;
            $items[] = self::content_summary( $post );
        }
        return array( 'items' => $items, 'page' => $page, 'per_page' => $per_page, 'total' => (int) $query->found_posts, 'total_pages' => (int) $query->max_num_pages );
    }

    private static function list_typed_content( $type, array $args ) {
        $args['post_types'] = array( $type );
        return self::search_content( $args );
    }

    private static function content_summary( $post ) {
        return array(
            'id'           => (int) $post->ID,
            'type'         => $post->post_type,
            'status'       => $post->post_status,
            'title'        => get_the_title( $post ),
            'slug'         => $post->post_name,
            'modified_gmt' => $post->post_modified_gmt,
            'url'          => get_permalink( $post ),
            'excerpt'      => wp_trim_words( wp_strip_all_tags( $post->post_excerpt ? $post->post_excerpt : $post->post_content ), 45 ),
        );
    }

    private static function get_content( array $args ) {
        $id = isset( $args['id'] ) ? absint( $args['id'] ) : 0;
        $post = get_post( $id );
        if ( ! $post || ! self::allowed_post_type( $post->post_type ) ) return new WP_Error( 'not_found', 'Content not found.' );
        if ( ! current_user_can( 'read_post', $id ) ) return new WP_Error( 'forbidden', 'You cannot read this content.' );
        return array(
            'id'           => $id,
            'type'         => $post->post_type,
            'status'       => $post->post_status,
            'title'        => get_the_title( $post ),
            'slug'         => $post->post_name,
            'content'      => $post->post_content,
            'excerpt'      => $post->post_excerpt,
            'author_id'    => (int) $post->post_author,
            'date_gmt'     => $post->post_date_gmt,
            'modified_gmt' => $post->post_modified_gmt,
            'url'          => get_permalink( $post ),
        );
    }

    private static function get_typed_content( $type, array $args ) {
        $result = self::get_content( $args );
        if ( is_wp_error( $result ) ) return $result;
        if ( $type !== $result['type'] ) return new WP_Error( 'wrong_content_type', 'The requested ID is not a ' . $type . '.' );
        return $result;
    }

    private static function get_acf_fields( array $args ) {
        if ( ! function_exists( 'get_fields' ) ) return array( 'available' => false, 'message' => 'Advanced Custom Fields is not active.' );
        $id = isset( $args['id'] ) ? absint( $args['id'] ) : 0;
        if ( ! get_post( $id ) || ! current_user_can( 'read_post', $id ) ) return new WP_Error( 'forbidden', 'Content not found or not readable.' );
        $fields = get_fields( $id );
        return array( 'available' => true, 'id' => $id, 'fields' => $fields ? $fields : (object) array() );
    }

    private static function create_content( array $args ) {
        $type = isset( $args['post_type'] ) ? sanitize_key( $args['post_type'] ) : 'post';
        if ( ! self::allowed_post_type( $type ) ) return new WP_Error( 'invalid_post_type', 'This post type is not allowed.' );
        $obj = get_post_type_object( $type );
        $create_cap = isset( $obj->cap->create_posts ) ? $obj->cap->create_posts : $obj->cap->edit_posts;
        if ( ! current_user_can( $create_cap ) ) return new WP_Error( 'forbidden', 'You cannot create this content type.' );

        $status = isset( $args['status'] ) ? sanitize_key( $args['status'] ) : 'draft';
        if ( ! in_array( $status, array( 'draft', 'pending', 'private', 'publish' ), true ) ) $status = 'draft';
        if ( 'publish' === $status && ! current_user_can( $obj->cap->publish_posts ) ) return new WP_Error( 'forbidden', 'You cannot publish this content type.' );

        $postarr = array(
            'post_type'    => $type,
            'post_title'   => isset( $args['title'] ) ? sanitize_text_field( $args['title'] ) : '',
            'post_content' => isset( $args['content'] ) ? wp_kses_post( $args['content'] ) : '',
            'post_excerpt' => isset( $args['excerpt'] ) ? sanitize_textarea_field( $args['excerpt'] ) : '',
            'post_status'  => $status,
        );
        if ( ! empty( $args['slug'] ) ) $postarr['post_name'] = sanitize_title( $args['slug'] );

        $id = wp_insert_post( wp_slash( $postarr ), true );
        if ( is_wp_error( $id ) ) return $id;
        return array( 'success' => true, 'id' => $id, 'type' => $type, 'status' => get_post_status( $id ), 'title' => get_the_title( $id ), 'url' => get_permalink( $id ) );
    }

    private static function create_typed_content( $type, array $args ) {
        $args['post_type'] = $type;
        return self::create_content( $args );
    }

    private static function update_content( array $args ) {
        $id = isset( $args['id'] ) ? absint( $args['id'] ) : 0;
        $post = get_post( $id );
        if ( ! $post || ! self::allowed_post_type( $post->post_type ) ) return new WP_Error( 'not_found', 'Content not found.' );
        if ( ! current_user_can( 'edit_post', $id ) ) return new WP_Error( 'forbidden', 'You cannot edit this content.' );

        $postarr = array( 'ID' => $id );
        if ( array_key_exists( 'title', $args ) ) $postarr['post_title'] = sanitize_text_field( $args['title'] );
        if ( array_key_exists( 'content', $args ) ) $postarr['post_content'] = wp_kses_post( $args['content'] );
        if ( array_key_exists( 'excerpt', $args ) ) $postarr['post_excerpt'] = sanitize_textarea_field( $args['excerpt'] );
        if ( array_key_exists( 'slug', $args ) ) $postarr['post_name'] = sanitize_title( $args['slug'] );
        if ( array_key_exists( 'status', $args ) ) {
            $status = sanitize_key( $args['status'] );
            if ( ! in_array( $status, array( 'draft', 'pending', 'private', 'publish' ), true ) ) return new WP_Error( 'invalid_status', 'Unsupported post status.' );
            $obj = get_post_type_object( $post->post_type );
            if ( 'publish' === $status && ! current_user_can( $obj->cap->publish_posts ) ) return new WP_Error( 'forbidden', 'You cannot publish this content type.' );
            $postarr['post_status'] = $status;
        }
        if ( 1 === count( $postarr ) ) return new WP_Error( 'no_changes', 'No fields were supplied to update.' );

        $result = wp_update_post( wp_slash( $postarr ), true );
        if ( is_wp_error( $result ) ) return $result;
        return array( 'success' => true, 'id' => $id, 'type' => $post->post_type, 'status' => get_post_status( $id ), 'title' => get_the_title( $id ), 'url' => get_permalink( $id ) );
    }

    private static function update_typed_content( $type, array $args ) {
        $id = isset( $args['id'] ) ? absint( $args['id'] ) : 0;
        $post = get_post( $id );
