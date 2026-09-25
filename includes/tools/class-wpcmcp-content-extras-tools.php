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
                    'option_page' => array( 'type' => 'string', 'minLength' => 1, 'maxLength' => 191, 'description' => 'Optional registered ACF option-page menu_slug, for example alify-services-archive.' ),
                    'post_id' => array( 'type' => 'string', 'minLength' => 1, 'maxLength' => 191, 'description' => 'Optional registered ACF option-page storage post_id, for example services_archive. Numeric IDs may be supplied as digit strings.' ),
                ), array( 'field', 'value' ), false, false, true );
            }
        }

        return $tools;
    }

    private static function t( $name, $title, $description, $properties, $required, $read_only, $destructive, $idempotent ) {
        return WPCMCP_Tools::tool( $name, $title, $description, $properties, $required, $read_only, $destructive, $idempotent );
    }

    public static function execute( $name, array $args ) {
        switch ( $name ) {
            case 'wordpress.update_media': return self::update_media( $args );
            case 'wordpress.delete_media': return self::delete_media( $args );
            case 'wordpress.restore_content': return self::restore_content( $args );
            case 'wordpress.delete_content_permanently': return self::delete_content_permanently( $args );
            case 'wordpress.list_taxonomies': return self::list_taxonomies();
            case 'wordpress.list_terms': return self::list_terms( $args );
            case 'wordpress.get_term': return self::get_term( $args );
            case 'wordpress.create_term': return self::create_term( $args );
            case 'wordpress.update_term': return self::update_term( $args );
            case 'wordpress.delete_term': return self::delete_term( $args );
            case 'wordpress.assign_terms': return self::assign_terms( $args );
            case 'wordpress.list_revisions': return self::list_revisions( $args );
            case 'wordpress.get_revision': return self::get_revision( $args );
            case 'wordpress.restore_revision': return self::restore_revision( $args );
            case 'wordpress.delete_revision': return self::delete_revision( $args );
            case 'wordpress.list_acf_field_groups': return self::list_acf_field_groups();
            case 'wordpress.get_acf_field_group': return self::get_acf_field_group( $args );
            case 'wordpress.list_acf_option_pages': return self::list_acf_option_pages();
            case 'wordpress.update_acf_fields': return self::update_acf_fields( $args );
            case 'wordpress.get_acf_options': return self::get_acf_options( $args );
            case 'wordpress.update_acf_option': return self::update_acf_option( $args );
            default: return null;
        }
    }

    private static function allowed_content( $post ) {
        if ( ! $post ) return false;
        $object = get_post_type_object( $post->post_type );
        return $object && $object->public && 'attachment' !== $post->post_type;
    }

    private static function confirm( array $args ) {
        return isset( $args['confirm'] ) && true === $args['confirm'];
    }

    private static function update_media( array $args ) {
        $id = isset( $args['media_id'] ) ? absint( $args['media_id'] ) : 0;
        $post = get_post( $id );
        if ( ! $post || 'attachment' !== $post->post_type ) return new WP_Error( 'not_found', 'Media attachment not found.' );
        if ( ! current_user_can( 'edit_post', $id ) ) return new WP_Error( 'forbidden', 'You cannot edit this media attachment.' );
        $update = array( 'ID' => $id );
        if ( array_key_exists( 'title', $args ) ) $update['post_title'] = sanitize_text_field( $args['title'] );
        if ( array_key_exists( 'caption', $args ) ) $update['post_excerpt'] = sanitize_textarea_field( $args['caption'] );
        if ( array_key_exists( 'description', $args ) ) $update['post_content'] = wp_kses_post( $args['description'] );
        if ( 1 < count( $update ) ) {
            $result = wp_update_post( wp_slash( $update ), true );
            if ( is_wp_error( $result ) ) return $result;
        }
        if ( array_key_exists( 'alt_text', $args ) ) update_post_meta( $id, '_wp_attachment_image_alt', sanitize_text_field( $args['alt_text'] ) );
        if ( 1 === count( $update ) && ! array_key_exists( 'alt_text', $args ) ) return new WP_Error( 'no_changes', 'No media fields were supplied.' );
        return array( 'success' => true, 'media_id' => $id, 'title' => get_the_title( $id ), 'url' => wp_get_attachment_url( $id ), 'alt_text' => get_post_meta( $id, '_wp_attachment_image_alt', true ) );
    }

    private static function delete_media( array $args ) {
        $id = isset( $args['media_id'] ) ? absint( $args['media_id'] ) : 0;
        $post = get_post( $id );
        if ( ! $post || 'attachment' !== $post->post_type ) return new WP_Error( 'not_found', 'Media attachment not found.' );
        if ( ! self::confirm( $args ) ) return new WP_Error( 'confirmation_required', 'Permanent media deletion requires confirm=true.' );
        if ( ! current_user_can( 'delete_post', $id ) ) return new WP_Error( 'forbidden', 'You cannot delete this media attachment.' );
        $result = wp_delete_attachment( $id, true );
        if ( ! $result ) return new WP_Error( 'delete_failed', 'WordPress could not delete this attachment.' );
        return array( 'success' => true, 'media_id' => $id, 'deleted_permanently' => true );
    }

    private static function restore_content( array $args ) {
        $id = isset( $args['id'] ) ? absint( $args['id'] ) : 0;
        $post = get_post( $id );
        if ( ! self::allowed_content( $post ) || 'trash' !== $post->post_status ) return new WP_Error( 'not_found', 'Trashed content not found.' );
        if ( ! current_user_can( 'edit_post', $id ) ) return new WP_Error( 'forbidden', 'You cannot restore this content.' );
        $result = wp_untrash_post( $id );
        if ( ! $result ) return new WP_Error( 'restore_failed', 'WordPress could not restore this content.' );
        return array( 'success' => true, 'id' => $id, 'status' => get_post_status( $id ) );
    }

    private static function delete_content_permanently( array $args ) {
        $id = isset( $args['id'] ) ? absint( $args['id'] ) : 0;
        $post = get_post( $id );
        if ( ! self::allowed_content( $post ) ) return new WP_Error( 'not_found', 'Content not found.' );
        if ( ! self::confirm( $args ) ) return new WP_Error( 'confirmation_required', 'Permanent content deletion requires confirm=true.' );
        if ( ! current_user_can( 'delete_post', $id ) ) return new WP_Error( 'forbidden', 'You cannot permanently delete this content.' );
        $result = wp_delete_post( $id, true );
        if ( ! $result ) return new WP_Error( 'delete_failed', 'WordPress could not permanently delete this content.' );
        return array( 'success' => true, 'id' => $id, 'deleted_permanently' => true );
    }

    private static function taxonomy( $name ) {
        $name = sanitize_key( $name );
        $taxonomy = get_taxonomy( $name );
        return $taxonomy && $taxonomy->public ? $taxonomy : false;
    }

    private static function list_taxonomies() {
        $items = array();
        foreach ( get_taxonomies( array( 'public' => true ), 'objects' ) as $taxonomy ) {
            $items[] = array( 'name' => $taxonomy->name, 'label' => $taxonomy->label, 'hierarchical' => (bool) $taxonomy->hierarchical, 'object_types' => array_values( $taxonomy->object_type ) );
        }
        return array( 'taxonomies' => $items );
    }

    private static function term_record( $term ) {
        return array( 'id' => (int) $term->term_id, 'taxonomy' => $term->taxonomy, 'name' => $term->name, 'slug' => $term->slug, 'description' => $term->description, 'parent' => (int) $term->parent, 'count' => (int) $term->count );
    }

    private static function list_terms( array $args ) {
        $taxonomy = self::taxonomy( isset( $args['taxonomy'] ) ? $args['taxonomy'] : '' );
        if ( ! $taxonomy ) return new WP_Error( 'invalid_taxonomy', 'Public taxonomy not found.' );
        $per_page = min( 50, max( 1, isset( $args['per_page'] ) ? absint( $args['per_page'] ) : 10 ) );
        $page = max( 1, isset( $args['page'] ) ? absint( $args['page'] ) : 1 );
        $query = array( 'taxonomy' => $taxonomy->name, 'hide_empty' => ! empty( $args['hide_empty'] ), 'number' => $per_page, 'offset' => ( $page - 1 ) * $per_page, 'orderby' => 'name', 'order' => 'ASC' );
        if ( ! empty( $args['search'] ) ) $query['search'] = sanitize_text_field( $args['search'] );
        $terms = get_terms( $query );
        if ( is_wp_error( $terms ) ) return $terms;
        $count_query = $query;
        unset( $count_query['number'], $count_query['offset'], $count_query['orderby'], $count_query['order'] );
        $total = wp_count_terms( $taxonomy->name, $count_query );
        if ( is_wp_error( $total ) ) return $total;
        return array( 'items' => array_map( array( __CLASS__, 'term_record' ), $terms ), 'page' => $page, 'per_page' => $pe