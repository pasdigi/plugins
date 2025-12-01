<?php
defined( 'ABSPATH' ) || exit;

class PC_Cart {
    
    public static function init() {
        if ( ! session_id() ) session_start();
        if ( ! isset( $_SESSION['pc_cart'] ) ) $_SESSION['pc_cart'] = array();
    }

    public static function add( $product_id, $qty ) {
        if ( isset( $_SESSION['pc_cart'][$product_id] ) ) {
            $_SESSION['pc_cart'][$product_id] += $qty;
        } else {
            $_SESSION['pc_cart'][$product_id] = $qty;
        }
    }

    public static function get_data() {
        return $_SESSION['pc_cart'];
    }

    public static function clear() {
        $_SESSION['pc_cart'] = array();
    }
}