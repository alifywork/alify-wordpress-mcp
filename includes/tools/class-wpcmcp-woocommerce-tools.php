<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class WPCMCP_WooCommerce_Tools {
    public static function definitions( $can_read, $can_write ) {
        if ( ! function_exists( 'wc_get_product' ) || ! function_exists( 'wc_get_orders' ) ) return array();
        $tools = array();
        $pagination = array( 'per_page' => array( 'type' => 'integer', 'minimum' => 1, 'maximum' => 50 ), 'page' => array( 'type' => 'integer', 'minimum' => 1 ) );
        if ( $can_read ) {
            $tools[] = self::t( 'woocommerce.list_products', 'List WooCommerce Products', 'List WooCommerce products.', array( 'search' => array( 'type' => 'string' ), 'status' => array( 'type' => 'string' ), 'type' => array( 'type' => 'string' ), 'sku' => array( 'type' => 'string' ) ) + $pagination, array(), true, false, true );
            $tools[] = self::t( 'woocommerce.get_product', 'Get WooCommerce Product', 'Get one WooCommerce product.', array( 'product_id' => array( 'type' => 'integer', 'minimum' => 1 ) ), array( 'product_id' ), true, false, true );
            $tools[] = self::t( 'woocommerce.list_orders', 'List WooCommerce Orders', 'List WooCommerce orders. Customer addresses, phone numbers, emails and payment credentials are omitted.', array( 'status' => array( 'type' => 'string' ), 'customer_id' => array( 'type' => 'integer', 'minimum' => 1 ) ) + $pagination, array(), true, false, true );
            $tools[] = self::t( 'woocommerce.get_order', 'Get WooCommerce Order', 'Get one WooCommerce order without customer contact/address or payment credential data.', array( 'order_id' => array( 'type' => 'integer', 'minimum' => 1 ) ), array( 'order_id' ), true, false, true );
            $tools[] = self::t( 'woocommerce.list_customers', 'List WooCommerce Customers', 'List WooCommerce customer IDs and non-contact profile summaries.', array( 'search' => array( 'type' => 'string' ) ) + $pagination, array(), true, false, true );
            $tools[] = self::t( 'woocommerce.get_customer', 'Get WooCommerce Customer', 'Get a WooCommerce customer profile summary without email, phone, or address.', array( 'customer_id' => array( 'type' => 'integer', 'minimum' => 1 ) ), array( 'customer_id' ), true, false, true );
        }
        if ( $can_write ) {
            $tools[] = self::t( 'woocommerce.create_product', 'Create WooCommerce Product', 'Create a simple WooCommerce product. Defaults to draft.', self::product_schema( false ), array( 'name' ), false, false, false );
            $tools[] = self::t( 'woocommerce.update_product', 'Update WooCommerce Product', 'Update an existing simple or compatible WooCommerce product.', self::product_schema( true ), array( 'product_id' ), false, false, true );
            $tools[] = self::t( 'woocommerce.delete_product', 'Delete WooCommerce Product', 'Move a product to Trash, or permanently delete it when permanent=true and confirm=true.', array( 'product_id' => array( 'type' => 'integer', 'minimum' => 1 ), 'permanent' => array( 'type' => 'boolean' ), 'confirm' => array( 'type' => 'boolean', 'description' => 'Required for permanent deletion.' ) ), array( 'product_id' ), false, true, true );
            $tools[] = self::t( 'woocommerce.update_order_status', 'Update WooCommerce Order Status', 'Update the status of an existing WooCommerce order.', array( 'order_id' => array( 'type' => 'integer', 'minimum' => 1 ), 'status' => array( 'type' => 'string' ), 'note' => array( 'type' => 'string' ), 'manual' => array( 'type' => 'boolean', 'description' => 'Mark this as a manual status change.' ) ), array( 'order_id', 'status' ), false, true, true );
        }
        return $tools;
    }

    private static function t( $name, $title, $description, $properties, $required, $read_only, $destructive, $idempotent ) {
        return WPCMCP_Tools::tool( $name, $title, $description, $properties, $required, $read_only, $destructive, $idempotent );
    }

    private static function product_schema( $include_id ) {
        $schema = array(
            'name'              => array( 'type' => 'string' ),
            'slug'              => array( 'type' => 'string' ),
            'status'            => array( 'type' => 'string', 'enum' => array( 'draft', 'pending', 'private', 'publish' ) ),
            'description'       => array( 'type' => 'string' ),
            'short_description' => array( 'type' => 'string' ),
            'sku'               => array( 'type' => 'string' ),
            'regular_price'     => array( 'type' => 'string' ),
            'sale_price'        => array( 'type' => 'string' ),
            'manage_stock'      => array( 'type' => 'boolean' ),
            'stock_quantity'    => array( 'type' => array( 'integer', 'null' ) ),
            'stock_status'      => array( 'type' => 'string', 'enum' => array( 'instock', 'outofstock', 'onbackorder' ) ),
            'featured'          => array( 'type' => 'boolean' ),
            'catalog_visibility'=> array( 'type' => 'string', 'enum' => array( 'visible', 'catalog', 'search', 'hidden' ) ),
            'image_id'          => array( 'type' => 'integer', 'minimum' => 0 ),
            'gallery_image_ids' => array( 'type' => 'array', 'items' => array( 'type' => 'integer', 'minimum' => 1 ) ),
            'category_ids'      => array( 'type' => 'array', 'items' => array( 'type' => 'integer', 'minimum' => 1 ) ),
            'tag_ids'           => array( 'type' => 'array', 'items' => array( 'type' => 'integer', 'minimum' => 1 ) ),
        );
        if ( $include_id ) $schema['product_id'] = array( 'type' => 'integer', 'minimum' => 1 );
        return $schema;
    }

    public static function execute( $name, array $args ) {
        switch ( $name ) {
            case 'woocommerce.list_products': return self::list_products( $args );
            case 'woocommerce.get_product': return self::get_product( $args );
            case 'woocommerce.create_product': return self::create_product( $args );
            case 'woocommerce.update_product': return self::update_product( $args );
            case 'woocommerce.delete_product': return self::delete_product( $args );
            case 'woocommerce.list_orders': return self::list_orders( $args );
            case 'woocommerce.get_order': return self::get_order( $args );
            case 'woocommerce.update_order_status': return self::update_order_status( $args );
            case 'woocommerce.list_customers': return self::list_customers( $args );
            case 'woocommerce.get_customer': return self::get_customer( $args );
            default: return null;
        }
    }

    private static function available() {
        return function_exists( 'wc_get_product' ) && function_exists( 'wc_get_orders' );
    }

    private static function page( array $args ) { return max( 1, isset( $args['page'] ) ? absint( $args['page'] ) : 1 ); }
    private static function per_page( array $args ) { return min( 50, max( 1, isset( $args['per_page'] ) ? absint( $args['per_page'] ) : 10 ) ); }

    private static function product_record( $product ) {
        $categories = array_map( 'intval', $product->get_category_ids() );
        $tags = array_map( 'intval', $product->get_tag_ids() );
        return array(
            'id' => $product->get_id(), 'name' => $product->get_name(), 'slug' => $product->get_slug(), 'status' => $product->get_status(), 'type' => $product->get_type(),
            'sku' => $product->get_sku(), 'regular_price' => $product->get_regular_price(), 'sale_price' => $product->get_sale_price(), 'price' => $product->get_price(),
            'stock_status' => $product->get_stock_status(), 'stock_quantity' => $product->get_stock_quantity(), 'manage_stock' => $product->get_manage_stock(),
            'featured' => $product->get_featured(), 'catalog_visibility' => $product->get_catalog_visibility(), 'description' => $product->get_description(), 'short_description' => $product->get_short_description(),
            'image_id' => $product->get_image_id(), 'gallery_image_ids' => array_map( 'intval', $product->get_gallery_image_ids() ), 'category_ids' => $categories, 'tag_ids' => $tags,
            'permalink' => $product->get_permalink(), 'modified_gmt' => $product->get_date_modified() ? gmdate( 'Y-m-d H:i:s', $product->get_date_modified()->getTimestamp() ) : null,
        );
    }

    private static function list_products( array $args ) {
        if ( ! self::available() ) return new WP_Error( 'woocommerce_unavailable', 'WooCommerce is not active.' );
        if ( ! current_user_can( 'edit_products' ) ) return new WP_Error( 'forbidden', 'You cannot inspect WooCommerce products.' );
        $query = array( 'limit' => self::per_page( $args ), 'page' => self::page( $args ), 'paginate' => true, 'orderby' => 'modified', 'order' => 'DESC' );
        foreach ( array( 'status', 'type', 'sku' ) as $field ) if ( ! empty( $args[ $field ] ) ) $query[ $field ] = sanitize_text_field( $args[ $field ] );
        if ( ! empty( $args['search'] ) ) $query['s'] = sanitize_text_field( $ar