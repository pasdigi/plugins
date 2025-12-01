<?php
if ( ! defined( 'ABSPATH' ) ) exit;

use \Elementor\Widget_Base;
use \Elementor\Controls_Manager;

class PC_Elementor_Product_Grid extends Widget_Base {

    public function get_name() {
        return 'pc_product_grid';
    }

    public function get_title() {
        return __( 'Product Grid', 'pascommerce-ui' );
    }

    public function get_icon() {
        return 'eicon-products';
    }

    public function get_categories() {
        return [ 'pascommerce' ];
    }

    protected function register_controls() {
        
        // --- Content Section ---
        $this->start_controls_section(
            'section_content',
            [
                'label' => __( 'Layout & Query', 'pascommerce-ui' ),
            ]
        );

        $this->add_control(
            'posts_per_page',
            [
                'label'   => __( 'Product Limit', 'pascommerce-ui' ),
                'type'    => Controls_Manager::NUMBER,
                'min'     => 1,
                'max'     => 50,
                'default' => 8,
            ]
        );

        $this->add_control(
            'columns',
            [
                'label'   => __( 'Columns', 'pascommerce-ui' ),
                'type'    => Controls_Manager::SELECT,
                'default' => '4',
                'options' => [
                    '2' => '2 Columns',
                    '3' => '3 Columns',
                    '4' => '4 Columns',
                    '5' => '5 Columns',
                ],
            ]
        );

        $this->end_controls_section();

        // --- Style Section ---
        $this->start_controls_section(
            'section_style',
            [
                'label' => __( 'Style', 'pascommerce-ui' ),
                'tab'   => Controls_Manager::TAB_STYLE,
            ]
        );

        $this->add_control(
            'price_color',
            [
                'label'     => __( 'Price Color', 'pascommerce-ui' ),
                'type'      => Controls_Manager::COLOR,
                'default'   => '#16a34a',
                'selectors' => [
                    '{{WRAPPER}} .pc-price' => 'color: {{VALUE}}',
                ],
            ]
        );

        $this->add_control(
            'btn_color',
            [
                'label'     => __( 'Button Color', 'pascommerce-ui' ),
                'type'      => Controls_Manager::COLOR,
                'default'   => '#2563eb',
                'selectors' => [
                    '{{WRAPPER}} .pc-btn-add' => 'background-color: {{VALUE}}; border-color: {{VALUE}}; color: #fff;',
                ],
            ]
        );

        $this->end_controls_section();
    }

    protected function render() {
        $settings = $this->get_settings_for_display();

        $args = array(
            'post_type'      => 'pc_product',
            'posts_per_page' => $settings['posts_per_page'],
            'post_status'    => 'publish',
        );

        $query = new \WP_Query( $args );

        if ( $query->have_posts() ) {
            echo '<div class="pc-grid" style="grid-template-columns: repeat('.esc_attr($settings['columns']).', 1fr);">';
            
            while ( $query->have_posts() ) {
                $query->the_post();
                $pid = get_the_ID();
                $price = get_post_meta( $pid, '_pc_price', true );
                $img = get_the_post_thumbnail_url( $pid, 'medium_large' ) ?: 'https://via.placeholder.com/300';
                
                // Mata Uang Helper (Optional, pakai 'Rp' dulu untuk simple)
                $price_fmt = 'Rp ' . number_format((float)$price, 0, ',', '.');

                ?>
                <div class="pc-card">
                    <div class="pc-card-img">
                        <a href="<?php the_permalink(); ?>">
                            <img src="<?php echo esc_url($img); ?>" alt="<?php the_title_attribute(); ?>">
                        </a>
                    </div>
                    <div class="pc-card-body">
                        <h3 style="font-size:16px; margin:0 0 5px;"><a href="<?php the_permalink(); ?>" style="text-decoration:none; color:inherit;"><?php the_title(); ?></a></h3>
                        <div class="pc-price"><?php echo $price_fmt; ?></div>
                        <button class="pc-btn pc-btn-add" data-id="<?php echo $pid; ?>">Add to Cart</button>
                    </div>
                </div>
                <?php
            }
            echo '</div>';
            wp_reset_postdata();
        } else {
            echo '<p>No products found.</p>';
        }
    }
}