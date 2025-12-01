<?php
if ( ! defined( 'ABSPATH' ) ) exit;

use \Elementor\Widget_Base;
use \Elementor\Controls_Manager;

class PC_Elementor_Cart extends Widget_Base {

    public function get_name() {
        return 'pc_cart';
    }

    public function get_title() {
        return __( 'Cart Page', 'pascommerce-ui' );
    }

    public function get_icon() {
        return 'eicon-cart';
    }

    public function get_categories() {
        return [ 'pascommerce' ];
    }

    protected function register_controls() {
        $this->start_controls_section(
            'section_style',
            [
                'label' => __( 'Style', 'pascommerce-ui' ),
                'tab'   => Controls_Manager::TAB_STYLE,
            ]
        );

        $this->add_control(
            'btn_color',
            [
                'label'     => __( 'Checkout Button Color', 'pascommerce-ui' ),
                'type'      => Controls_Manager::COLOR,
                'default'   => '#2563eb',
                'selectors' => [
                    '{{WRAPPER}} .pc-btn-primary' => 'background-color: {{VALUE}}; border-color: {{VALUE}};',
                ],
            ]
        );

        $this->end_controls_section();
    }

    protected function render() {
        // Panggil Shortcode logic yang sudah kita buat sebelumnya
        echo do_shortcode('[pc_cart]');
    }
}