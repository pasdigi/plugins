<?php
/**
 * Plugin Name:       Pascommerce - UI
 * Description:       Frontend interface module (Shop, Cart, Checkout) with Elementor Support.
 * Version:           1.1.0
 * Author:            Pasdigi
 * Text Domain:       pascommerce-ui
 * Requires Plugins:  pascommerce
 */

defined( 'ABSPATH' ) || exit;

// Define base paths and URLs
define( 'PC_UI_PATH', plugin_dir_path( __FILE__ ) );
define( 'PC_UI_URL', plugin_dir_url( __FILE__ ) );

class Pascommerce_UI {

    public function __construct() {
        add_action( 'pc_loaded', array( $this, 'init' ) );
        add_action( 'admin_notices', array( $this, 'check_dependency' ) );
        
        // ELEMENTOR HOOK
        add_action( 'elementor/init', array( $this, 'init_elementor' ) );
    }

    public function check_dependency() {
        if ( ! class_exists( 'Pascommerce' ) ) {
            echo '<div class="error"><p><strong>Pascommerce - UI</strong> requires <u>Pascommerce (Framework)</u> to be active.</p></div>';
        }
    }

    public function init() {
        // SOLUSI: Menggunakan logika pencarian jalur file shortcode yang aman.
        $shortcode_file_locations = [
            PC_UI_PATH . 'includes/class-pc-shortcodes.php', // Path yang paling diharapkan (modular)
            PC_UI_PATH . 'class-pc-shortcodes.php',         // Path fallback jika file berada di root plugin UI
        ];

        $shortcode_file_loaded = false;
        foreach ($shortcode_file_locations as $file_path) {
            if ( file_exists($file_path) ) {
                require_once $file_path;
                $shortcode_file_loaded = true;
                break;
            }
        }
        
        if ( ! $shortcode_file_loaded ) {
             // Tampilkan pemberitahuan jika gagal memuat file shortcode
             add_action( 'admin_notices', function() {
                 echo '<div class="error"><p><strong>Pascommerce - UI</strong> Fatal Error: Cannot find <code>class-pc-shortcodes.php</code>. Please check your plugin file structure.</p></div>';
             });
             return;
        }
        
        add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_assets' ) );
        
        if ( defined( 'ELEMENTOR_PATH' ) && is_callable( array( 'Elementor\\Plugin', 'instance' ) ) ) {
            $this->init_elementor_includes();
        }
    }

    public function init_elementor() {
        // Elementor runs later, just load the manager class
        if ( ! class_exists( 'PC_Elementor_Manager' ) ) {
             require_once PC_UI_PATH . 'includes/elementor/class-pc-elementor-manager.php';
        }
    }

    private function init_elementor_includes() {
        require_once PC_UI_PATH . 'includes/elementor/class-pc-elementor-manager.php';
    }

    public function enqueue_assets() {
        wp_enqueue_style( 'pc-ui-style', PC_UI_URL . 'assets/style.css', array(), '1.1.0' );
        wp_enqueue_script( 'pc-ui-script', PC_UI_URL . 'assets/script.js', array('jquery'), '1.1.0', true );
        
        // Ambil Symbol Mata Uang secara aman dari Framework Data
        $code = get_option('pc_currency', 'USD');
        $symbol = class_exists('PC_Data') ? PC_Data::get_currency_symbol($code) : '$';

        // Kirim Data Lengkap ke JS (Termasuk Pengaturan Currency)
        wp_localize_script( 'pc-ui-script', 'pc_vars', array(
            'ajax_url'     => admin_url( 'admin-ajax.php' ),
            'nonce'        => wp_create_nonce( 'pc_ui_nonce' ),
            'cart_url'     => get_permalink( get_option('pc_page_cart') ),
            'checkout_url' => get_permalink( get_option('pc_page_checkout') ),
            // DATA MATA UANG DARI SETTINGS
            'currency'     => array(
                'symbol'       => $symbol,
                'position'     => get_option('pc_currency_pos', 'left'),
                'thousand_sep' => get_option('pc_thousand_sep', '.'),
                'decimal_sep'  => get_option('pc_decimal_sep', ','),
                'decimals'     => get_option('pc_num_decimals', 0),
            )
        ) );
    }
}

new Pascommerce_UI();