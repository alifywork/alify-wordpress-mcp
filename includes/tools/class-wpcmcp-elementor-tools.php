<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class WPCMCP_Elementor_Tools {
    public static function definitions( $can_read, $can_write ) {
        if ( ! self::available() ) return array();

        $tools = array();

        if ( $can_read ) {
            $tools[] = self::t( 'wordpress.elementor_status', 'Elementor Status', 'Report Elementor availability, version and document/widget APIs.', array(), array(), true, false, true );
            $tools[] = self::t( 'wordpress.elementor_get_document', 'Get Elementor Document', 'Get Elementor document metadata, page settings, element tree and SHA-256 revision fingerprint.', array(
                'post_id' => array( 'type' => 'integer', 'minimum' => 1 ),
            ), array( 'post_id' ), true, false, true );
            $tools[] = self::t( 'wordpress.elementor_get_element', 'Get Elementor Element', 'Find one Elementor container/section/column/widget recursively by element ID.', array(
                'post_id' => array( 'type' => 'integer', 'minimum' => 1 ),
                'element_id' => array( 'type' => 'string' ),
            ), array( 'post_id', 'element_id' ), true, false, true );
            $tools[] = self::t( 'wordpress.elementor_list_widgets', 'List Elementor Widget Types', 'List registered Elementor widget types available on this site.', array(), array(), true, false, true );
            $tools[] = self::t( 'wordpress.elementor_render_document', 'Render Elementor Document', 'Render the current Elementor document HTML for inspection.', array(
                'post_id' => array( 'type' => 'integer', 'minimum' => 1 ),
            ), array( 'post_id' ), true, false, true );
        }

        if ( $can_write ) {
            $tools[] = self::t( 'wordpress.elementor_enable_document', 'Enable Elementor for Content', 'Mark an editable post/page/CPT as built with Elementor and initialize its Elementor document.', array(
                'post_id' => array( 'type' => 'integer', 'minimum' => 1 ),
            ), array( 'post_id' ), false, false, true );
            $tools[] = self::t( 'wordpress.elementor_replace_document', 'Replace Elementor Document', 'Replace the complete Elementor element tree and optional page settings. Requires the latest SHA-256 from elementor_get_document and confirm=true.', array(
                'post_id' => array( 'type' => 'integer', 'minimum' => 1 ),
                'elements' => array( 'type' => 'array' ),
                'settings' => array( 'type' => 'object', 'additionalProperties' => true ),
                'expected_sha256' => array( 'type' => 'string', 'minLength' => 64, 'maxLength' => 64 ),
                'confirm' => array( 'type' => 'boolean' ),
            ), array( 'post_id', 'elements', 'expected_sha256', 'confirm' ), false, true, true );
            $tools[] = self::t( 'wordpress.elementor_add_container', 'Add Elementor Container', 'Add a modern Elementor container at document root or inside another container.', array(
                'post_id' => array( 'type' => 'integer', 'minimum' => 1 ),
                'parent_id' => array( 'type' => 'string' ),
                'index' => array( 'type' => 'integer', 'minimum' => 0 ),
                'settings' => array( 'type' => 'object', 'additionalProperties' => true ),
            ), array( 'post_id' ), false, false, false );
            $tools[] = self::t( 'wordpress.elementor_add_widget', 'Add Elementor Widget', 'Add a registered Elementor widget to a container/column or document root.', array(
                'post_id' => array( 'type' => 'integer', 'minimum' => 1 ),
                'parent_id' => array( 'type' => 'string' ),
                'index' => array( 'type' => 'integer', 'minimum' => 0 ),
                'widget_type' => array( 'type' => 'string' ),
                'settings' => array( 'type' => 'object', 'additionalProperties' => true ),
            ), array( 'post_id', 'widget_type' ), false, false, false );
            $tools[] = self::t( 'wordpress.elementor_add_element', 'Add Raw Elementor Element', 'Add a validated Elementor element object at root or below a parent. Use for sections, columns, containers and advanced/nested widgets.', array(
                'post_id' => array( 'type' => 'integer', 'minimum' => 1 ),
                'parent_id' => array( 'type' => 'string' ),
                'index' => array( 'type' => 'integer', 'minimum' => 0 ),
                'element' => array( 'type' => 'object', 'additionalProperties' => true ),
            ), array( 'post_id', 'element' ), false, false, false );
            $tools[] = self::t( 'wordpress.elementor_update_element', 'Update Elementor Element', 'Merge or replace settings on an existing Elementor element. Optionally update widget type when valid.', array(
                'post_id' => array( 'type' => 'integer', 'minimum' => 1 ),
                'element_id' => array( 'type' => 'string' ),
                'settings' => array( 'type' => 'object', 'additionalProperties' => true ),
                'replace_settings' => array( 'type' => 'boolean' ),
                'widget_type' => array( 'type' => 'string' ),
            ), array( 'post_id', 'element_id' ), false, false, true );
            $tools[] = self::t( 'wordpress.elementor_duplicate_element', 'Duplicate Elementor Element', 'Duplicate an Elementor element tree with new element IDs and insert it next to the original or under a target parent.', array(
                'post_id' => array( 'type' => 'integer', 'minimum' => 1 ),
                'element_id' => array( 'type' => 'string' ),
                'parent_id' => array( 'type' => 'string' ),
                'index' => array( 'type' => 'integer', 'minimum' => 0 ),
            ), array( 'post_id', 'element_id' ), false, false, false );
            $tools[] = self::t( 'wordpress.elementor_move_element', 'Move Elementor Element', 'Move an existing Elementor element to a new parent/root and index.', array(
                'post_id' => array( 'type' => 'integer', 'minimum' => 1 ),
                'element_id' => array( 'type' => 'string' ),
                'parent_id' => array( 'type' => 'string' ),
                'index' => array( 'type' => 'integer', 'minimum' => 0 ),
            ), array( 'post_id', 'element_id' ), false, false, true );
            $tools[] = self::t( 'wordpress.elementor_delete_element', 'Delete Elementor Element', 'Delete an Elementor element and its nested children. Requires confirm=true.', array(
                'post_id' => array( 'type' => 'integer', 'minimum' => 1 ),
                'element_id' => array( 'type' => 'string' ),
                'confirm' => array( 'type' => 'boolean' ),
            ), array( 'post_id', 'element_id', 'confirm' ), false, true, true );
            $tools[] = self::t( 'wordpress.elementor_update_page_settings', 'Update Elementor Page Settings', 'Merge or replace Elementor document/page settings.', array(
                'post_id' => array( 'type' => 'integer', 'minimum' => 1 ),
                'settings' => array( 'type' => 'object', 'additionalProperties' => true ),
                'replace' => array( 'type' => 'boolean' ),
            ), array( 'post_id', 'settings' ), false, false, true );
            $tools[] = self::t( 'wordpress.elementor_clear_cache', 'Clear Elementor Cache', 'Clear Elementor generated files/cache. Requires manage_options and confirm=true.', array(
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
            case 'wordpress.elementor_status': return self::status();
            case 'wordpress.elementor_get_document': return self::get_document_tool( $args );
            case 'wordpress.elementor_get_element': return self::get_element_tool( $args );
            case 'wordpress.elementor_list_widgets': return self::list_widgets();
            case 'wordpress.elementor_render_document': return self::render_document( $args );
            case 'wordpress.elementor_enable_document': return self::enable_document( $args );
            case 'wordpress.elementor_replace_document': return self::replace_document( $args );
            case 'wordpress.elementor_add_container': return self::add_container( $args );
            case 'wordpress.elementor_add_widget': return self::add_widget( $args );
            case 'wordpress.elementor_add_element': return self::add_element_tool( $args );
            case 'wordpress.elementor_update_element': return self::update_element( $args );
            case 'wordpress.elementor_duplicate_element': return self::duplicate_element( $args );
            case 'wordpress.elementor_move_element': return self::move_element( $args );
            case 'wordpress.elementor_delete_element': return self::delete_element( $args );
            case 'wordpress.elementor_update_page_settings': return self::update_page_settings( $args );
            case 'wordpress.elementor_clear_cache': return self::clear_cache( $args );
            default: return null;
        }
    }

    private static function available() {
        return did_action( 'elementor/loaded' ) || class_exists( '\\Elementor\\Plugin' );
    }

    private static function plugin() {
        return self::available() && class_exists( '\\Elementor\\Plugin' ) ? \Elementor\Plugin::$instance : null;
    }

    private static function status() {
        $plugin = self::plugin();
        return array(
            'available' => (bool) $plugin,
            'version' => defined( 'ELEMENTOR_VERSION' ) ? ELEMENTOR_VERSION : null,
            'pro_version' => defined( 'ELEMENTOR_PRO_VERSION' ) ? ELEMENTOR_PRO_VERSION : null,
            'documents_api' => $plugin && isset( $plugin->documents ),
            'widgets_api' => $plugin && isset( $plugin->widgets_manager ),
            'files_manager' => $plugin && isset( $plugin->files_manager ),
            'data_structure_version' => '0.4',
        );
    }

    private static function document( $post_id, $write = false ) {
        $post_id = absint( $post_id );
        if ( ! $post_id || ! get_post( $post_id ) ) return new WP_Error( 'not_found', 'WordPress content not found.' );
        if ( ! current_user_can( $write ? 'edit_post' : 'read_post', $post_id ) ) return new WP_Error( 'forbidden', 'You cannot access this WordPress content.' );

        $plugin = self::plugin();
        if ( ! $plugin || ! isset( $plugin->documents ) ) return new WP_Error( 'elementor_unavailable', 'Elementor document API is unavailable.' );

        $document = $plugin->documents->get( $post_id, false );
        if ( ! $document ) return new WP_Error( 'elementor_document_unavailable', 'Elementor could not create or resolve a document for this content.' );

        if ( $write && method_exists( $document, 'is_editable_by_current_user' ) && ! $document->is_editable_by_current_user() ) {
            return new WP_Error( 'forbidden', 'Elementor does not allow the current user to edit this document.' );
        }
        return $document;
    }

    private static function elements( $document ) {
        $elements = method_exists( $document, 'get_elements_data' ) ? $document->get_elements_data() : array();
        return is_array( $elements ) ? $elements : array();
    }

    private static function settings( $document ) {
        if ( method_exists( $document, 'get_settings' ) ) {
            $settings = $document->get_settings();
            return is_array( $settings ) ? $settings : array();
        }
        $post_id = method_exists( $document, 'get_main_id' ) ? $document->get_main_id() : 0;
        $settings = $post_id ? get_post_meta( $post_id, '_elementor_page_settings', true ) : array();
        return is_array( $settings ) ? $settings : array();
    }

    private static function fingerprint( array $elements, array $settings ) {
        return hash( 'sha256', wp_json_encode( array( 'elements' => $elements, 'settings' => $settings ), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) );
    }

    private static function document_record( $document ) {
        $post = $document->get_post();
        $elements = self::elements( $document );
        $settings = self::settings( $document );
        return array(
            'post_id' => (int) $post->ID,
            'post_type' => $post->post_type,
            'status' => $post->post_status,
            'title' => $post->post_title,
            'built_with_elementor' => method_exists( $document, 'is_built_with_elementor' ) ? (bool) $document->is_built_with_elementor() : 'builder' === get_post_meta( $post->ID, '_elementor_edit_mode', true ),
            'document_type' => get_post_meta( $post->ID, '_elementor_template_type', true ),
            'settings' => $settings,
            'elements' => $elements,
            'sha256' => self::fingerprint( $elements, $settings ),
            'editor_url' => method_exists( $document, 'get_edit_url' ) ? $document->get_edit_url() : null,
            'permalink' => get_permalink( $post->ID ),
        );
    }

    private static function get_document_tool( array $args ) {
        $document = self::document( $args['post_id'], false );
        return is_wp_error( $document ) ? $document : self::document_record( $document );
    }

    private static function find_element_recursive( array $elements, $id, &$path = array() ) {
        foreach ( $elements as $index => $element ) {
            if ( ! is_array( $element ) ) continue;
            $current_path = array_merge( $path, array( $index ) );
            if ( isset( $element['id'] ) && (string) $element['id'] === (string) $id ) {
                return array( 'element' => $element, 'path' => $current_path );
            }
            if ( ! empty( $element['elements'] ) && is_array( $element['elements'] ) ) {
                $child_path = array_merge( $current_path, array( 'elements' ) );
                $found = self::find_element_recursive( $element['elements'], $id, $child_path );
                if ( $found ) return $found;
            }
        }
        return false;
    }

    private static function get_element_tool( array $args ) {
        $document = self::document( $args['post_id'], false );
        if ( is_wp_error( $document ) ) return $document;
        $path = array();
        $found = self::find_element_recursive( self::elements( $document ), sanitize_text_field( $args['element_id'] ), $path );
        return $found ?: new WP_Error( 'not_found', 'Elementor element not found.' );
    }

    private static function list_widgets() {
        $plugin = self::plugin();
        if ( ! $plugin || ! isset( $plugin->widgets_manager ) ) return new WP_Error( 'elementor_unavailable', 'Elementor widgets API unavailable.' );
        $items = array();
        foreach ( $plugin->widgets_manager->get_widget_types() as $name => $widget ) {
            $items[] = array(
                'name' => $name,
                'title' => method_exists( $widget, 'get_title' ) ? $widget->get_title() : $name,
                'categories' => method_exists( $widget, 'get_categories' ) ? $widget->get_categories() : array(),
            );
        }
        return array( 'widgets' => $items );
    }

    private static function render_document( array $args ) {
        $document = self::document( $args['post_id'], false );
        if ( is_wp_error( $document ) ) return $document;
        $plugin = self::plugin();
        if ( ! $plugin || ! isset( $plugin->frontend ) || ! method_exists( $plugin->frontend, 'get_builder_content_for_display' ) ) {
            return new WP_Error( 'elementor_render_unavailable', 'Elementor frontend rendering API unavailable.' );
        }
        return array( 'post_id' => absint( $args['post_id'] ), 'html' => $plugin->frontend->get_builder_content_for_display( absint( $args['post_id'] ), true ) );
    }

    private static function save( $document, array $elements, array $settings = null ) {
        $data = array( 'elements' => array_values( $elements ) );
        if ( null !== $settings ) $data['settings'] = $settings;
        try {
            $document->save( $data );
        } catch ( \Throwable $e ) {
            return new WP_Error( 'elementor_save_failed', $e->getMessage() );
        }
        self::clear_post_cache( $document->get_post()->ID );
        $fresh = self::document( $document->get_post()->ID, false );
        return is_wp_error( $fresh ) ? $fresh : self::document_record( $fresh );
    }

    private static function clear_post_cache( $post_id ) {
        clean_post_cache( $post_id );
        delete_post_meta( $post_id, '_elementor_element_cache' );
        if ( class_exists( '\\Elementor\\Core\\Files\\CSS\\Post' ) ) {
            try {
                $css = new \Elementor\Core\Files\CSS\Post( $post_id );
                if ( method_exists( $css, 'delete' ) ) $css->delete();
            } catch ( \Throwable $e ) {
                // Cache regeneration is best-effort; document data is already saved.
            }
        }
    }

    private static function enable_document( array $args ) {
        $document = self::document( $args['post_id'], true );
        if ( is_wp_error( $document ) ) return $document;
        if ( method_exists( $document, 'set_is_built_with_elementor' ) ) {
            $document->set_is_built_with_elementor( true );
        } else {
            update_post_meta( absint( $args['post_id'] ), '_elementor_edit_mode', 'builder' );
        }
        if ( method_exists( $document, 'save' ) ) {
            try { $document->save( array() ); } catch ( \Throwable $e ) { return new WP_Error( 'elementor_enable_failed', $e->getMessage() ); }
        }
        return self::document_record( self::document( $args['post_id'], false ) );
    }

    private static function replace_document( array $args ) {
        if ( empty( $args['confirm'] ) ) return new WP_Error( 'confirmation_required', 'Replacing the complete Elementor document requires confirm=true.' );
        $document = self::document( $args['post_id'], true );
        if ( is_wp_error( $document ) ) return $document;
        $current_elements = self::elements( $document );
        $current_settings = self::settings( $document );
        $expected = strtolower( sanitize_text_field( $args['expected_sha256'] ) );
        if ( ! hash_equals( self::fingerprint( $current_elements, $current_settings ), $expected ) ) {
            return new WP_Error( 'elementor_document_conflict', 'The Elementor document changed after it was read. Read it again and use the latest SHA-256.' );
        }
        $elements = self::normalize_elements( $args['elements'] );
        if ( is_wp_error( $elements ) ) return $elements;
        $settings = isset( $args['settings'] ) && is_array( $args['settings'] ) ? $args['settings'] : $current_settings;
        return self::save( $document, $elements, $settings );
    }

    private static function new_id() {
        return substr( strtolower( wp_generate_password( 8, false, false ) ), 0, 8 );
    }

    private static function normalize_element( array $element ) {
        if ( empty( $element['id'] ) ) $element['id'] = self::new_id();
        $element['id'] = preg_replace( '/[^A-Za-z0-9_-]/', '', (string) $element['id'] );
        if ( '' === $element['id'] ) $element['id'] = self::new_id();
        if ( empty( $element['elType'] ) ) return new WP_Error( 'invalid_element', 'Elementor element requires elType.' );
        $element['elType'] = sanitize_key( $element['elType'] );
        $element['isInner'] = ! empty( $element['isInner'] );
        $element['settings'] = isset( $element['settings'] ) && is_array( $element['settings'] ) ? $element['settings'] : array();
        $element['elements'] = isset( $element['elements'] ) && is_array( $element['elements'] ) ? $element['elements'] : array();

        if ( 'widget' === $element['elType'] ) {
            if ( empty( $element['widgetType'] ) ) return new WP_Error( 'invalid_widget', 'Widget elements require widgetType.' );
            $widget = self::validate_widget_type( $element['widgetType'] );
            if ( is_wp_error( $widget ) ) return $widget;
            $element['widgetType'] = $widget;
        }

        $children = array();
        foreach ( $element['elements'] as $child ) {
            if ( ! is_array( $child ) ) return new WP_Error( 'invalid_element', 'Nested Elementor elements must be objects.' );
            $normalized = self::normalize_element( $child );
            if ( is_wp_error( $normalized ) ) return $normalized;
            $children[] = $normalized;
        }
        $element['elements'] = $children;
        return $element;
    }

    private static function normalize_elements( $elements ) {
        if ( ! is_array( $elements ) ) return new WP_Error( 'invalid_elements', 'Elementor elements must be an array.' );
        $out = array();
        foreach ( $elements as $element ) {
            if ( ! is_array( $element ) ) return new WP_Error( 'invalid_element', 'Each Elementor element must be an object.' );
            $normalized = self::normalize_element( $element );
            if ( is_wp_error( $normalized ) ) return $normalized;
            $out[] = $normalized;
        }
        return $out;
    }

    private static function validate_widget_type( $widget_type ) {
        $widget_type = sanitize_key( $widget_type );
        $plugin = self::plugin();
        if ( ! $plugin || ! isset( $plugin->widgets_manager ) ) return new WP_Error( 'elementor_unavailable', 'Elementor widget manager unavailable.' );
        $widgets = $plugin->widgets_manager->get_widget_types();
        return isset( $widgets[ $widget_type ] ) ? $widget_type : new WP_Error( 'invalid_widget_type', 'The requested Elementor widget type is not registered on this site.' );
    }

    private static function insert_element_recursive( array &$elements, $parent_id, array $element, $index = null ) {
        if ( '' === (string) $parent_id ) {
            $index = null === $index ? count( $elements ) : min( max( 0, (int) $index ), count( $elements ) );
            array_splice( $elements, $index, 0, array( $element ) );
            return true;
        }
        foreach ( $elements as &$candidate ) {
            if ( ! is_array( $candidate ) ) continue;
            if ( isset( $candidate['id'] ) && (string) $candidate['id'] === (string) $parent_id ) {
                if ( ! isset( $candidate['elements'] ) || ! is_array( $candidate['elements'] ) ) $candidate['elements'] = array();
                $index = null === $index ? count( $candidate['elements'] ) : min( max( 0, (int) $index ), count( $candidate['elements'] ) );
                array_splice( $candidate['elements'], $index, 0, array( $element ) );
                return true;
            }
            if ( ! empty( $candidate['elements'] ) && self::insert_element_recursive( $candidate['elements'], $parent_id, $element, $index ) ) return true;
        }
        return false;
    }

    private static function add_container( array $args ) {
        $element = array(
            'id' => self::new_id(),
            'elType' => 'container',
            'isInner' => false,
            'settings' => isset( $args['settings'] ) && is_array( $args['settings'] ) ? $args['settings'] : array(),
            'elements' => array(),
        );
        $args['element'] = $element;
        return self::add_element_tool( $args );
    }

    private static function add_widget( array $args ) {
        $widget = self::validate_widget_type( $args['widget_type'] );
        if ( is_wp_error( $widget ) ) return $widget;
        $args['element'] = array(
            'id' => self::new_id(),
            'elType' => 'widget',
            'widgetType' => $widget,
            'isInner' => false,
            'settings' => isset( $args['settings'] ) && is_array( $args['settings'] ) ? $args['settings'] : array(),
            'elements' => array(),
        );
        return self::add_element_tool( $args );
    }

    private static function add_element_tool( array $args ) {
        $document = self::document( $args['post_id'], true );
        if ( is_wp_error( $document ) ) return $document;
        $element = self::normalize_element( $args['element'] );
        if ( is_wp_error( $element ) ) return $element;
        $elements = self::elements( $document );
        $parent_id = isset( $args['parent_id'] ) ? sanitize_text_field( $args['parent_id'] ) : '';
        $index = isset( $args['index'] ) ? absint( $args['index'] ) : null;
        if ( ! self::insert_element_recursive( $elements, $parent_id, $element, $index ) ) return new WP_Error( 'parent_not_found', 'Elementor parent element not found.' );
        $result = self::save( $document, $elements, self::settings( $document ) );
        if ( is_wp_error( $result ) ) return $result;
        $result['added_element_id'] = $element['id'];
        return $result;
    }

    private static function update_element_recursive( array &$elements, $id, array $args ) {
        foreach ( $elements as &$element ) {
            if ( ! is_array( $element ) ) continue;
            if ( isset( $element['id'] ) && (string) $element['id'] === (string) $id ) {
                if ( isset( $args['settings'] ) && is_array( $args['settings'] ) ) {
                    $current = isset( $element['settings'] ) && is_array( $element['settings'] ) ? $element['settings'] : array();
                    $element['settings'] = ! empty( $args['replace_settings'] ) ? $args['settings'] : array_replace_recursive( $current, $args['settings'] );
                }
                if ( isset( $args['widget_type'] ) ) {
                    if ( 'widget' !== ( isset( $element['elType'] ) ? $element['elType'] : '' ) ) return new WP_Error( 'not_widget', 'widget_type can only be changed on widget elements.' );
                    $widget = self::validate_widget_type( $args['widget_type'] );
                    if ( is_wp_error( $widget ) ) return $widget;
                    $element['widgetType'] = $widget;
                }
                return true;
            }
            if ( ! empty( $element['elements'] ) ) {
                $updated = self::update_element_recursive( $element['elements'], $id, $args );
                if ( $updated ) return $updated;
            }
        }
        return false;
    }

    private static function update_element( array $args ) {
        $document = self::document( $args['post_id'], true );
        if ( is_wp_error( $document ) ) return $document;
        $elements = self::elements( $document );
        $updated = self::update_element_recursive( $elements, sanitize_text_field( $args['element_id'] ), $args );
        if ( is_wp_error( $updated ) ) return $updated;
        if ( ! $updated ) return new WP_Error( 'not_found', 'Elementor element not found.' );
        return self::save( $document, $elements, self::settings( $document ) );
    }

    private static function remove_element_recursive( array &$elements, $id, &$removed = null, &$parent_id = '' ) {
        foreach ( $elements as $index => &$element ) {
            if ( ! is_array( $element ) ) continue;
            if ( isset( $element['id'] ) && (string) $element['id'] === (string) $id ) {
                $removed = $element;
                array_splice( $elements, $index, 1 );
                return true;
            }
            if ( ! empty( $element['elements'] ) ) {
                $parent_id = isset( $element['id'] ) ? (string) $element['id'] : '';
                if ( self::remove_element_recursive( $element['elements'], $id, $removed, $parent_id ) ) return true;
            }
        }
        return false;
    }

    private static function regenerate_ids( array $element ) {
        $element['id'] = self::new_id();
        if ( ! empty( $element['elements'] ) && is_array( $element['elements'] ) ) {
            foreach ( $element['elements'] as $i => $child ) {
                if ( is_array( $child ) ) $element['elements'][ $i ] = self::regenerate_ids( $child );
            }
        }
        return $element;
    }

    private static function duplicate_element( array $args ) {
        $document = self::document( $args['post_id'], true );
        if ( is_wp_error( $document ) ) return $document;
        $elements = self::elements( $document );
        $path = array();
        $found = self::find_element_recursive( $elements, sanitize_text_field( $args['element_id'] ), $path );
        if ( ! $found ) return new WP_Error( 'not_found', 'Elementor element not found.' );
        $copy = self::regenerate_ids( $found['element'] );
        $parent_id = isset( $args['parent_id'] ) ? sanitize_text_field( $args['parent_id'] ) : '';
        $index = isset( $args['index'] ) ? absint( $args['index'] ) : null;
        if ( ! self::insert_element_recursive( $elements, $parent_id, $copy, $index ) ) return new WP_Error( 'parent_not_found', 'Elementor target parent not found.' );
        $result = self::save( $document, $elements, self::settings( $document ) );
        if ( is_wp_error( $result ) ) return $result;
        $result['duplicated_element_id'] = $copy['id'];
        return $result;
    }

    private static function move_element( array $args ) {
        $document = self::document( $args['post_id'], true );
        if ( is_wp_error( $document ) ) return $document;
        $elements = self::elements( $document );
        $removed = null;
        $old_parent = '';
        $id = sanitize_text_field( $args['element_id'] );
        if ( ! self::remove_element_recursive( $elements, $id, $removed, $old_parent ) || ! $removed ) return new WP_Error( 'not_found', 'Elementor element not found.' );
        $parent_id = isset( $args['parent_id'] ) ? sanitize_text_field( $args['parent_id'] ) : '';
        if ( $parent_id === $id ) return new WP_Error( 'invalid_parent', 'An element cannot be moved inside itself.' );
        $index = isset( $args['index'] ) ? absint( $args['index'] ) : null;
        if ( ! self::insert_element_recursive( $elements, $parent_id, $removed, $index ) ) return new WP_Error( 'parent_not_found', 'Elementor target parent not found.' );
        return self::save( $document, $elements, self::settings( $document ) );
    }

    private static function delete_element( array $args ) {
        if ( empty( $args['confirm'] ) ) return new WP_Error( 'confirmation_required', 'Deleting an Elementor element requires confirm=true.' );
        $document = self::document( $args['post_id'], true );
        if ( is_wp_error( $document ) ) return $document;
        $elements = self::elements( $document );
        $removed = null;
        $parent = '';
        if ( ! self::remove_element_recursive( $elements, sanitize_text_field( $args['element_id'] ), $removed, $parent ) ) return new WP_Error( 'not_found', 'Elementor element not found.' );
        $result = self::save( $document, $elements, self::settings( $document ) );
        if ( is_wp_error( $result ) ) return $result;
        $result['deleted_element_id'] = $args['element_id'];
        return $result;
    }

    private static function update_page_settings( array $args ) {
        $document = self::document( $args['post_id'], true );
        if ( is_wp_error( $document ) ) return $document;
        $current = self::settings( $document );
        $settings = ! empty( $args['replace'] ) ? $args['settings'] : array_replace_recursive( $current, $args['settings'] );
        return self::save( $document, self::elements( $document ), $settings );
    }

    private static function clear_cache( array $args ) {
        if ( empty( $args['confirm'] ) ) return new WP_Error( 'confirmation_required', 'Clearing Elementor generated files/cache requires confirm=true.' );
        if ( ! current_user_can( 'manage_options' ) ) return new WP_Error( 'forbidden', 'Clearing Elementor cache requires manage_options.' );
        $plugin = self::plugin();
        if ( ! $plugin || ! isset( $plugin->files_manager ) || ! method_exists( $plugin->files_manager, 'clear_cache' ) ) return new WP_Error( 'elementor_cache_unavailable', 'Elementor files manager cache API unavailable.' );
        $plugin->files_manager->clear_cache();
        return array( 'success' => true );
    }
}
