<?php
/**
 * Plugin Name:       Pascommerce
 * Plugin URI:        https://pasdigi.com/pascommerce
 * Description:       Professional, lightweight commerce framework. Native PayPal, Product Variants & Gallery support.
 * Version:           1.1.0
 * Author:            Pasdigi
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       pascommerce
 * Domain Path:       /languages
 * Requires PHP:      7.4
 */

// =========================================================
// AKTIVASI DEBUG MODE UNTUK MENDAPATKAN LOG
// =========================================================
if ( ! defined( 'WP_DEBUG' ) ) {
    define( 'WP_DEBUG', true );
}
if ( ! defined( 'WP_DEBUG_LOG' ) ) {
    // Ini akan menulis error ke wp-content/debug.log
    define( 'WP_DEBUG_LOG', true );
}
// =========================================================
defined( 'ABSPATH' ) || exit;

define( 'PC_PATH', plugin_dir_path( __FILE__ ) );
define( 'PC_URL', plugin_dir_url( __FILE__ ) );

final class Pascommerce {

    private static $instance = null;

    public static function instance() {
        if ( is_null( self::$instance ) ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function __construct() {
        $this->init_hooks();
        $this->includes();
    }

    private function init_hooks() {
        add_action( 'plugins_loaded', array( $this, 'load_textdomain' ) );
        add_action( 'init', array( 'PC_CPT', 'register_post_types' ) );
        add_action( 'init', array( 'PC_Cart', 'init' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'admin_assets' ) );
        register_activation_hook( __FILE__, array( $this, 'activate' ) );
        do_action( 'pc_loaded' );
    }

    public function load_textdomain() {
        load_plugin_textdomain( 'pascommerce', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
    }

    public function admin_assets() {
        $screen = get_current_screen();
        if ( $screen && $screen->post_type === 'pc_product' ) {
            wp_enqueue_style( 'pc-admin-css', PC_URL . 'assets/admin.css', array(), '1.1.0' );
            wp_enqueue_script( 'pc-admin-js', PC_URL . 'assets/admin.js', array('jquery'), '1.1.0', true );
            wp_enqueue_media();
        }
    }

    private function includes() {
        // Abstract
        require_once PC_PATH . 'includes/abstract/class-pc-payment-module.php';

        // Core
        require_once PC_PATH . 'includes/core/class-pc-data.php'; // <--- NEW FILE ADDED HERE
        require_once PC_PATH . 'includes/core/class-pc-cpt.php';
        require_once PC_PATH . 'includes/core/class-pc-order.php';
        require_once PC_PATH . 'includes/core/class-pc-cart.php';
		require_once PC_PATH . 'includes/core/class-pc-template-loader.php';

        // Gateways
        require_once PC_PATH . 'includes/gateways/class-pc-gateway-paypal.php';

        // Admin
        if ( is_admin() ) {
            require_once PC_PATH . 'includes/admin/class-pc-settings.php';
            require_once PC_PATH . 'includes/admin/class-pc-admin-ui.php';
        }
    }

    public function activate() {
        require_once PC_PATH . 'includes/core/class-pc-cpt.php';
        PC_CPT::register_post_types();
        flush_rewrite_rules();
    }
}

Pascommerce::instance();