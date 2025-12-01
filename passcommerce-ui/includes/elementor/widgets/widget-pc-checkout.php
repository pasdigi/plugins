<?php
if ( ! defined( 'ABSPATH' ) ) exit;

use \Elementor\Widget_Base;
use \Elementor\Controls_Manager;

class PC_Elementor_Checkout extends Widget_Base {

    public function get_name() {
        return 'pc_checkout';
    }

    public function get_title() {
        return __( 'Checkout Form', 'pascommerce-ui' );
    }

    public function get_icon() {
        return 'eicon-checkout';
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
                'label'     => __( 'Place Order Button Color', 'pascommerce-ui' ),
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
        if ( is_admin() ) {
            echo '<div class="pc-alert">Checkout Form will appear here.</div>';
        } else {
            echo do_shortcode('[pc_checkout]');
        }
    }
}