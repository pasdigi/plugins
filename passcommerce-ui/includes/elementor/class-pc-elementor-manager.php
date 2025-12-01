<?php
defined( 'ABSPATH' ) || exit;

class PC_Elementor_Manager {

    public function __construct() {
        // 1. Register Category
        add_action( 'elementor/elements/categories_registered', array( $this, 'register_categories' ) );
        
        // 2. Register Widgets
        add_action( 'elementor/widgets/register', array( $this, 'register_widgets' ) );
    }

    public function register_categories( $elements_manager ) {
        $elements_manager->add_category(
            'pascommerce',
            [
                'title' => __( 'Pascommerce', 'pascommerce-ui' ),
                'icon'  => 'fa fa-shopping-bag',
            ]
        );
    }

    public function register_widgets( $widgets_manager ) {
        // Load Widget Files (HARAP UBAH NAMA FILE INI DI SISTEM FILE)
        require_once __DIR__ . '/widgets/widget-pc-product-grid.php';
        require_once __DIR__ . '/widgets/widget-pc-cart.php';
        require_once __DIR__ . '/widgets/widget-pc-checkout.php';
        require_once __DIR__ . '/widgets/widget-pc-account.php';
        
        // Register Classes
        $widgets_manager->register( new \PC_Elementor_Product_Grid() );
        $widgets_manager->register( new \PC_Elementor_Cart() );
        $widgets_manager->register( new \PC_Elementor_Checkout() );
        $widgets_manager->register( new \PC_Elementor_Account() );
    }
}

new PC_Elementor_Manager();