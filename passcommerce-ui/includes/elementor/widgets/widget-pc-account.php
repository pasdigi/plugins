<?php
if ( ! defined( 'ABSPATH' ) ) exit;

use \Elementor\Widget_Base;
use \Elementor\Controls_Manager;

class PC_Elementor_Account extends Widget_Base {

    public function get_name() {
        return 'pc_account';
    }

    public function get_title() {
        return __( 'My Account', 'pascommerce-ui' );
    }

    public function get_icon() {
        return 'eicon-user-circle-o';
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
        $this->end_controls_section();
    }

    protected function render() {
        // Panggil logic account (menggunakan shortcode yang telah diubah namanya)
        echo do_shortcode('[pc_account]');
    }
}