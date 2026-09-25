<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class WPCMCP_Content_Extras_Tools {
    public static function definitions( $can_read, $can_write ) {
        $tools = array();
        $pagination = array(
            'per_page' => array( 'type' => 'integer', 'minimum' => 1, 'maximum' => 50 ),
            'page'     => array( 'type' => 'integer', 'minimum' => 1 ),
        );

        if ( $can_read ) {
            $tools[] = self::t( 'wordpress.list_taxonomies', 'List Taxonomies', 'List public taxonomies and their object types/capabilities.', array(), array(), true, false, true );
            $tools[] = self::t( 'wordpress.list_terms', 'List Terms', 'List terms from a public taxonomy.', array(
                'taxonomy'   => array( 'type' => 'string' ),
                'search'     => array( 'type' => 'string' ),
                'hide_empty' => array( 'type' => 'boolean' ),
            ) + $pagination, array( 'taxonomy' ), true, false, true );
            $tools[] = self::t( 'wordpress.get_term', 'Get Term', 'Get one term from a public taxonomy.', array(
                'taxonomy' => array( 'type' => 'string' ),
                'term_id'  => array( 'type' => 'integer', 'minimum' => 1 ),
            ), array( 'taxonomy', 'term_id' ), true, false, true );
            $tools[] = self::t( 'wordpress.list_revisions', 'List Revisions', 'List revisions for editable WordPress content.', array(
                'post_id' => array( 'type' => 'integer', 'minimum' => 1 ),
            ) + $pagination, array( 'post_id' ), true, false, true );
            $tools[] = self::t( 'wordpress.get_revision', 'Get Revision', 'Get the full content of one revision.', array(
                'revision_id' => array( 'type' => 'integer', 'minimum' => 1 ),
            ), array( 'revision_id' ), true, false, true );

            if ( function_exists( 'acf_get_field_groups' ) ) {
                $tools[] = self::t( 'wordpress.list_acf_field_groups', 'List ACF Field Groups', 'List Advanced Custom Fields field groups.', array(), array(), true, false, true );
                $tools[] = self::t( 'wordpress.get_acf_field_group', 'Get ACF Field Group', 'Get an ACF field group and its field definitions.', array(
                    'key' => array( 'type' => 'string' ),
                ), array( 'key' ), true, false, true );
                if ( function_exists( 'acf_get_options_pages' ) ) {
                    $tools[] = self::t( 'wordpress.list_acf_option_pages', 'List ACF Option Pages', 'List registered ACF option pages and their storage post_id values. Requires manage_options.', array(), array(), true, false, true );
                }
                $tools[] = self::t( 'wordpress.get_acf_options', 'Get ACF Options', 'Get explicitly named ACF option-page fields. Supply option_page or post_id when a field is assigned to multiple option pages. Bulk dumping all option values is not supported. Requires manage_options.', array(
                    'fields' => array( 'type' => 'array', 'minItems' => 1, 'maxItems' => 50, 'items' => array( 'type' => 'string' ) ),
                    'option_page' => array( 'type' => 'string', 'minLength' => 1, 'maxLength' => 191, 'description' => 'Optional registered ACF option-page menu_slug, for example alify-services-archive.' ),
                    'post_id' => array( 'type' => 'string', 'minLength' => 1, 'maxLength' => 191, 'description' => 'Optional registered ACF option-page storage post_id, for example services_archive. Numeric IDs may be supplied as digit strings.' ),
                ), array( 'fields' ), true, false, true );
            }
        }

        if ( $can_write ) {
            $tools[] = self::t( 'wordpress.update_media', 'Update Media', 'Update Media Library attachment title, alt text, caption, or description.', array(
                'media_id'    => array( 'type' => 'integer', 'minimum' => 1 ),
                'title'       => array( 'type' => 'string' ),
                'alt_text'    => array( 'type' => 'string' ),
                'caption'     => array( 'type' => 'string' ),
                'description' => array( 'type' => 'string' ),
            ), array( 'media_id' ), false, false, true );
            $tools[] = self::t( 'wordpress.delete_media', 'Delete Media Permanently', 'Permanently delete a Media Library attachment and its uploaded files. Requires confirm=true.', array(
                'media_id' => array( 'type' => 'integer', 'minimum' => 1 ),
                'confirm'  => array( 'type' => 'boolean', 'description' => 'Must be true.' ),
            ), array( 'media_id', 'confirm' ), false, true, true );
            $tools[] = self::t( 'wordpress.restore_content', 'Restore Content', 'Restore a post, page, or public custom post type from Trash.', array(
                'id' => array( 'type' => 'integer', 'minimum' => 1 ),
            ), array( 'id' ), false, false, true );
            $tools[] = self::t( 'wordpress.delete_content_permanently', 'Delete Content Permanently', 'Permanently delete a post, page, or public custom post type. Requires confirm=true.', array(
                'id'      => array( 'type' => 'integer', 'minimum' => 1 ),
                'confirm' => array( 'type' => 'boolean', 'description' => 'Must be true.' ),
            ), array( 'id', 'confirm' ), false, true, true );
            $tools[] = self::t( 'wordpress.create_term', 'Create Term', 'Create a term in a public taxonomy.', array(
                'taxonomy'   => array( 'type' => 'string' ),
                'name'       => array( 'type' => 'string' ),
                'slug'       => array( 'type' => 'string' ),
                'description'=> array( 'type' => 'string' ),
                'parent'     => array( 'type' => 'integer', 'minimum' => 0 ),
            ), array( 'taxonomy', 'name' ), false, false, false );
            $tools[] = self::t( 'wordpress.update_term', 'Update Term', 'Update a taxonomy term.', array(
                'taxonomy'   => array( 'type' => 'string' ),
                'term_id'    => array( 'type' => 'integer', 'minimum' => 1 ),
                'name'       => array( 'type' => 'string' ),
                'slug'       => array( 'type' => 'string' ),
                'description'=> array( 'type' => 'string' ),
                'parent'     => array( 'type' => 'integer', 'minimum' => 0 ),
            ), array( 'taxonomy', 'term_id' ), false, false, true );
            $tools[] = self::t( 'wordpress.delete_term', 'Delete Term', 'Permanently delete a taxonomy term. Requires confirm=true.', array(
                'taxonomy' => array( 'type' => 'string' ),
                'term_id'  => array( 'type' => 'integer', 'minimum' => 1 ),
                'confirm'  => array( 'type' => 'boolean', 'description' => 'Must be true.' ),
            ), array( 'taxonomy', 'term_id', 'confirm' ), false, true, true );
            $tools[] = self::t( 'wordpress.assign_terms', 'Assign Terms', 'Replace or append taxonomy-term assignments for a post/page/CPT.', array(
                'post_id'  => array( 'type' => 'integer', 'minimum' => 1 ),
                'taxonomy' => array( 'type' => 'string' ),
                'term_ids' => array( 'type' => 'array', 'items' => array( 'type' => 'integer', 'minimum' => 1 ) ),
                'append'   => array( 'type' => 'boolean' ),
            ), array( 'post_id', 'taxonomy', 'term_ids' ), false, false, true );
            $tools[] = self::t( 'wordpress.restore_revision', 'Restore Revision', 'Restore a WordPress revision over its parent content.', array(
                'revision_id' => array( 'type' => 'integer', 'minimum' => 1 ),
            ), array( 'revision_id' ), false, false, true );
            $tools[] = self::t( 'wordpress.delete_revision', 'Delete Revision', 'Permanently delete a revision. Requires confirm=true.', array(
                'revision_id' => array( 'type' => 'integer', 'minimum' => 1 ),
                'confirm'     => array( 'type' => 'boolean', 'description' => 'Must be true.' ),
            ), array( 'revision_id', 'confirm' ), false, true, true );

            if ( function_exists( 'update_field' ) ) {
                $tools[] = self::t( 'wordpress.update_acf_fields', 'Update ACF Fields', 'Update multiple ACF values for one post. Repeater/flexible values may be supplied as arrays.', array(
                    'post_id' => array( 'type' => 'integer', 'minimum' => 1 ),
                    'fields'  => array( 'type' => 'object', 'additionalProperties' => true ),
                ), array( 'post_id', 'fields' ), false, false, true );
                $tools[] = self::t( 'wordpress.update_acf_option', 'Update ACF Option', 'Update one ACF option-page field. Supply option_page or post_id when a field is assigned to multiple option pages. Requires manage_options.', array(
                    'field' => array( 'type' => 'string' ),
                    'value' => array( 'description' => 'New ACF value.' ),
                    'option_page' => array( 'type' => 'string', 'minLength'