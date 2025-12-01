<?php
defined( 'ABSPATH' ) || exit;

class PC_Order {

    public static function create( $customer_data, $items, $payment_slug, $fees = array() ) {
        
        if ( empty( $items ) ) {
            return new WP_Error( 'empty_cart', __( 'Cart is empty.', 'pascommerce' ) );
        }

        $subtotal = 0;
        $order_items = array();

        foreach ( $items as $item ) {
            $pid   = intval( $item['product_id'] );
            $qty   = intval( $item['qty'] );
            $price = (int) get_post_meta( $pid, '_pc_price', true );
            
            $product_title = get_the_title( $pid );
            if ( ! $product_title ) $product_title = sprintf( __( 'Product #%d (Deleted)', 'pascommerce' ), $pid );

            $line_total = $price * $qty;
            $subtotal  += $line_total;

            $order_items[] = array(
                'product_id' => $pid,
                'name'       => $product_title,
                'price'      => $price,
                'qty'        => $qty,
                'subtotal'   => $line_total
            );
        }

        $total_fees = 0;
        if( !empty($fees) ) {
            foreach($fees as $fee) { 
                $total_fees += intval($fee['amount']); 
            }
        }

        $grand_total = $subtotal + $total_fees;

        // Insert Order
        $order_title_format = __( 'Order #%s - %s', 'pascommerce' );
        $order_title = sprintf( $order_title_format, time(), sanitize_text_field( $customer_data['name'] ) );
        
        $order_id = wp_insert_post(array(
            'post_type'   => 'pc_order',
            'post_title'  => $order_title,
            'post_status' => 'publish' 
        ));

        if ( is_wp_error( $order_id ) ) {
            return new WP_Error( 'create_failed', __( 'Failed to create order.', 'pascommerce' ) );
        }

        // Save Meta
        update_post_meta( $order_id, '_pc_customer', $customer_data );
        update_post_meta( $order_id, '_pc_items', $order_items );
        update_post_meta( $order_id, '_pc_fees', $fees );
        update_post_meta( $order_id, '_pc_total', $grand_total );
        update_post_meta( $order_id, '_pc_payment_method', $payment_slug );
        update_post_meta( $order_id, '_pc_status', 'pending' );

        // Trigger Payment
        do_action( 'pc_process_payment_' . $payment_slug, $order_id, $grand_total, $customer_data );

        return $order_id;
    }

    public static function update_status( $order_id, $status, $note = '' ) {
        update_post_meta( $order_id, '_pc_status', $status );
        if ( $note ) {
            $history = get_post_meta( $order_id, '_pc_history', true ) ?: array();
            $history[] = sprintf( '%s - %s - %s', date('Y-m-d H:i:s'), $status, $note );
            update_post_meta( $order_id, '_pc_history', $history );
        }
    }
}