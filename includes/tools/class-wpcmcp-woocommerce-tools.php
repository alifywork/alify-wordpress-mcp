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
        if ( ! empty( $args['search'] ) ) $query['s'] = sanitize_text_field( $args['search'] );
        $result = wc_get_products( $query );
        $items = array();
        foreach ( $result->products as $product ) $items[] = self::product_record( $product );
        return array( 'items' => $items, 'page' => self::page( $args ), 'per_page' => self::per_page( $args ), 'total' => (int) $result->total, 'total_pages' => (int) $result->max_num_pages );
    }

    private static function get_product( array $args ) {
        if ( ! self::available() ) return new WP_Error( 'woocommerce_unavailable', 'WooCommerce is not active.' );
        $id = isset( $args['product_id'] ) ? absint( $args['product_id'] ) : 0;
        $product = wc_get_product( $id );
        if ( ! $product ) return new WP_Error( 'not_found', 'WooCommerce product not found.' );
        if ( ! current_user_can( 'edit_post', $id ) ) return new WP_Error( 'forbidden', 'You cannot inspect this product.' );
        return self::product_record( $product );
    }

    private static function apply_product_fields( $product, array $args ) {
        $map = array(
            'name' => 'set_name', 'slug' => 'set_slug', 'status' => 'set_status', 'description' => 'set_description', 'short_description' => 'set_short_description',
            'sku' => 'set_sku', 'regular_price' => 'set_regular_price', 'sale_price' => 'set_sale_price', 'manage_stock' => 'set_manage_stock', 'stock_quantity' => 'set_stock_quantity',
            'stock_status' => 'set_stock_status', 'featured' => 'set_featured', 'catalog_visibility' => 'set_catalog_visibility', 'image_id' => 'set_image_id',
            'gallery_image_ids' => 'set_gallery_image_ids', 'category_ids' => 'set_category_ids', 'tag_ids' => 'set_tag_ids',
        );
        foreach ( $map as $field => $method ) {
            if ( ! array_key_exists( $field, $args ) ) continue;
            $value = $args[ $field ];
            if ( in_array( $field, array( 'name', 'slug', 'sku', 'regular_price', 'sale_price', 'stock_status', 'catalog_visibility', 'status' ), true ) ) $value = sanitize_text_field( $value );
            if ( in_array( $field, array( 'description', 'short_description' ), true ) ) $value = wp_kses_post( $value );
            if ( in_array( $field, array( 'image_id', 'stock_quantity' ), true ) && null !== $value ) $value = absint( $value );
            if ( in_array( $field, array( 'gallery_image_ids', 'category_ids', 'tag_ids' ), true ) ) $value = array_values( array_filter( array_map( 'absint', (array) $value ) ) );
            $product->$method( $value );
        }
        return $product;
    }

    private static function create_product( array $args ) {
        if ( ! self::available() ) return new WP_Error( 'woocommerce_unavailable', 'WooCommerce is not active.' );
        if ( ! current_user_can( 'edit_products' ) ) return new WP_Error( 'forbidden', 'You cannot create WooCommerce products.' );
        $name = isset( $args['name'] ) ? sanitize_text_field( $args['name'] ) : '';
        if ( '' === $name ) return new WP_Error( 'invalid_name', 'Product name is required.' );
        $status = isset( $args['status'] ) ? sanitize_key( $args['status'] ) : 'draft';
        if ( 'publish' === $status && ! current_user_can( 'publish_products' ) ) return new WP_Error( 'forbidden', 'You cannot publish products.' );
        $args['status'] = $status;
        try {
            $product = self::apply_product_fields( new WC_Product_Simple(), $args );
            $id = $product->save();
            return self::product_record( wc_get_product( $id ) );
        } catch ( Exception $e ) {
            return new WP_Error( 'product_create_failed', $e->getMessage() );
        }
    }

    private static function update_product( array $args ) {
        if ( ! self::available() ) return new WP_Error( 'woocommerce_unavailable', 'WooCommerce is not active.' );
        $id = isset( $args['product_id'] ) ? absint( $args['product_id'] ) : 0;
        $product = wc_get_product( $id );
        if ( ! $product ) return new WP_Error( 'not_found', 'WooCommerce product not found.' );
        if ( ! current_user_can( 'edit_post', $id ) ) return new WP_Error( 'forbidden', 'You cannot update this product.' );
        if ( isset( $args['status'] ) && 'publish' === $args['status'] && ! current_user_can( 'publish_products' ) ) return new WP_Error( 'forbidden', 'You cannot publish products.' );
        try {
            self::apply_product_fields( $product, $args )->save();
            return self::product_record( wc_get_product( $id ) );
        } catch ( Exception $e ) {
            return new WP_Error( 'product_update_failed', $e->getMessage() );
        }
    }

    private static function delete_product( array $args ) {
        if ( ! self::available() ) return new WP_Error( 'woocommerce_unavailable', 'WooCommerce is not active.' );
        $id = isset( $args['product_id'] ) ? absint( $args['product_id'] ) : 0;
        $product = wc_get_product( $id );
        if ( ! $product ) return new WP_Error( 'not_found', 'WooCommerce product not found.' );
        if ( ! current_user_can( 'delete_post', $id ) ) return new WP_Error( 'forbidden', 'You cannot delete this product.' );
        $permanent = ! empty( $args['permanent'] );
        if ( $permanent && ( ! isset( $args['confirm'] ) || true !== $args['confirm'] ) ) return new WP_Error( 'confirmation_required', 'Permanent product deletion requires confirm=true.' );
        try {
            $product->delete( $permanent );
            return array( 'success' => true, 'product_id' => $id, 'permanent' => $permanent );
        } catch ( Exception $e ) {
            return new WP_Error( 'product_delete_failed', $e->getMessage() );
        }
    }

    private static function order_record( $order ) {
        $items = array();
        foreach ( $order->get_items() as $item ) $items[] = array( 'item_id' => $item->get_id(), 'product_id' => $item->get_product_id(), 'variation_id' => $item->get_variation_id(), 'name' => $item->get_name(), 'quantity' => $item->get_quantity(), 'subtotal' => $item->get_subtotal(), 'total' => $item->get_total() );
        return array(
            'id' => $order->get_id(), 'status' => $order->get_status(), 'currency' => $order->get_currency(), 'total' => $order->get_total(), 'customer_id' => $order->get_customer_id(),
            'billing_name' => trim( $order->get_billing_first_name() . ' ' . $order->get_billing_last_name() ), 'billing_company' => $order->get_billing_company(), 'billing_country' => $order->get_billing_country(),
            'payment_method' => $order->get_payment_method_title(), 'customer_note' => $order->get_customer_note(), 'items' => $items,
            'created_gmt' => $order->get_date_created() ? gmdate( 'Y-m-d H:i:s', $order->get_date_created()->getTimestamp() ) : null,
            'modified_gmt' => $order->get_date_modified() ? gmdate( 'Y-m-d H:i:s', $order->get_date_modified()->getTimestamp() ) : null,
        );
    }

    private static function can_manage_orders() { return current_user_can( 'edit_shop_orders' ) || current_user_can( 'manage_woocommerce' ); }

    private static function list_orders( array $args ) {
        if ( ! self::available() ) return new WP_Error( 'woocommerce_unavailable', 'WooCommerce is not active.' );
        if ( ! self::can_manage_orders() ) return new WP_Error( 'forbidden', 'You cannot inspect WooCommerce orders.' );
        $query = array( 'limit' => self::per_page( $args ), 'page' => self::page( $args ), 'paginate' => true, 'orderby' => 'date', 'order' => 'DESC' );
        if ( ! empty( $args['status'] ) ) $query['status'] = sanitize_key( $args['status'] );
        if ( ! empty( $args['customer_id'] ) ) $query['customer_id'] = absint( $args['customer_id'] );
        $result = wc_get_orders( $query );
        $items = array();
        foreach ( $result->orders as $order ) $items[] = self::order_record( $order );
        return array( 'items' => $items, 'page' => self::page( $args ), 'per_page' => self::per_page( $args ), 'total' => (int) $result->total, 'total_pages' => (int) $result->max_num_pages );
    }

    private static function get_order( array $args ) {
        if ( ! self::available() ) return new WP_Error( 'woocommerce_unavailable', 'WooCommerce is not active.' );
        if ( ! self::can_manage_orders() ) return new WP_Error( 'forbidden', 'You cannot inspect WooCommerce orders.' );
        $order = wc_get_order( isset( $args['order_id'] ) ? absint( $args['order_id'] ) : 0 );
        return $order ? self::order_record( $order ) : new WP_Error( 'not_found', 'WooCommerce order not found.' );
    }

    private static function update_order_status( array $args ) {
        if ( ! self::available() ) return new WP_Error( 'woocommerce_unavailable', 'WooCommerce is not active.' );
        if ( ! self::can_manage_orders() ) return new WP_Error( 'forbidden', 'You cannot update WooCommerce orders.' );
        $order = wc_get_order( isset( $args['order_id'] ) ? absint( $args['order_id'] ) : 0 );
        if ( ! $order ) return new WP_Error( 'not_found', 'WooCommerce order not found.' );
        $status = isset( $args['status'] ) ? sanitize_key( preg_replace( '/^wc-/', '', $args['status'] ) ) : '';
        $allowed = array_map( static function ( $value ) { return preg_replace( '/^wc-/', '', $value ); }, array_keys( wc_get_order_statuses() ) );
        if ( ! in_array( $status, $allowed, true ) ) return new WP_Error( 'invalid_status', 'Unsupported WooCommerce order status.' );
        try {
            $order->update_status( $status, isset( $args['note'] ) ? sanitize_textarea_field( $args['note'] ) : '', ! empty( $args['manual'] ) );
            return self::order_record( wc_get_order( $order->get_id() ) );
        } catch ( Exception $e ) {
            return new WP_Error( 'order_update_failed', $e->getMessage() );
        }
    }

    private static function customer_record( $customer ) {
        return array( 'id' => $customer->get_id(), 'username' => $customer->get_username(), 'display_name' => trim( $customer->get_first_name() . ' ' . $customer->get_last_name() ), 'company' => $customer->get_billing_company(), 'country' => $customer->get_billing_country(), 'order_count' => $customer->get_order_count(), 'total_spent' => $customer->get_total_spent(), 'created_gmt' => $customer->get_date_created() ? gmdate( 'Y-m-d H:i:s', $customer->get_date_created()->getTimestamp() ) : null );
    }

    private static function list_customers( array $args ) {
        if ( ! class_exists( 'WC_Customer' ) ) return new WP_Error( 'woocommerce_unavailable', 'WooCommerce is not active.' );
        if ( ! current_user_can( 'list_users' ) ) return new WP_Error( 'forbidden', 'You cannot inspect WooCommerce customers.' );
        $per_page = self::per_page( $args );
        $page = self::page( $args );
        $query = array( 'number' => $per_page, 'offset' => ( $page - 1 ) * $per_page, 'role__in' => array( 'customer', 'subscriber' ), 'orderby' => 'registered', 'order' => 'DESC', 'count_total' => true );
        if ( ! empty( $args['search'] ) ) $query['search'] = '*' . sanitize_text_field( $args['search'] ) . '*';
        $users = new WP_User_Query( $query );
        $items = array();
        foreach ( $users->get_results() as $user ) $items[] = self::customer_record( new WC_Customer( $user->ID ) );
        $total = (int) $users->get_total();
        return array( 'items' => $items, 'page' => $page, 'per_page' => $per_page, 'total' => $total, 'total_pages' => (int) ceil( $total / $per_page ) );
    }

    private static function get_customer( array $args ) {
        if ( ! class_exists( 'WC_Customer' ) ) return new WP_Error( 'woocommerce_unavailable', 'WooCommerce is not active.' );
        if ( ! current_user_can( 'list_users' ) ) return new WP_Error( 'forbidden', 'You cannot inspect WooCommerce customers.' );
        $id = isset( $args['customer_id'] ) ? absint( $args['customer_id'] ) : 0;
        if ( ! get_user_by( 'id', $id ) ) return new WP_Error( 'not_found', 'WooCommerce customer not found.' );
        try {
            return self::customer_record( new WC_Customer( $id ) );
        } catch ( Exception $e ) {
            return new WP_Error( 'not_found', 'WooCommerce customer not found.' );
        }
    }
}
