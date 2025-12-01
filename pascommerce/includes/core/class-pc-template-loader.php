<?php
defined( 'ABSPATH' ) || exit;

class PC_Template_Loader {

    public function __construct() {
        // Load Template from Plugin (Frontend)
        add_filter( 'template_include', array( $this, 'template_loader' ) );

        // Inject Template Options to Editor Dropdown (Admin)
        add_filter( 'theme_pc_product_templates', array( $this, 'add_page_templates' ), 10, 4 );
        
        // Add body class support
        add_filter( 'body_class', array( $this, 'add_body_classes' ) );
    }

    /**
     * Main logic: Chooses the correct template file path.
     * * @param string $template Current template file path.
     * @return string Modified template file path.
     */
    public function template_loader( $template ) {
        if ( is_embed() ) return $template;
        
        $post_type = get_query_var( 'post_type' );
        $taxonomy = get_query_var( 'taxonomy' );

        if ( 'pc_product' === $post_type || 'pc_category' === $taxonomy ) {
            
            $default_file = '';

            if ( is_singular( 'pc_product' ) ) {
                $default_file = 'single-pc_product.php';
            } elseif ( is_post_type_archive( 'pc_product' ) || is_tax( 'pc_category' ) ) {
                $default_file = 'archive-pc_product.php';
            }
            
            // Allow Addons to override the template path
            $located_template = apply_filters( 'pc_locate_template', null, $default_file );
            
            if ( $located_template ) {
                return $located_template;
            }

            // Fallback to Core Logic if no addon provided a template
            if ( $default_file ) {
                $theme_file = locate_template( array( 'pascommerce/' . $default_file ) );
                
                if ( $theme_file ) {
                    $template = $theme_file;
                } else {
                    $plugin_file = PC_PATH . 'templates/' . $default_file;
                    if ( file_exists( $plugin_file ) ) {
                        $template = $plugin_file;
                    }
                }
            }
        }

        return $template;
    }

    /**
     * ADMIN: Injects all Theme and Elementor templates into the Post Editor dropdown.
     */
    public function add_page_templates( $post_templates, $wp_theme, $post, $post_type ) {
        
        // 1. Fetch all page templates from the active Theme
        $theme_templates = wp_get_theme()->get_page_templates();
        
        // Merge them into the dropdown
        foreach ( $theme_templates as $file => $name ) {
            $post_templates[ $file ] = $name;
        }

        // 2. Add Elementor Templates (if active)
        if ( did_action( 'elementor/loaded' ) ) {
            $post_templates['elementor_header_footer'] = __( 'Elementor Full Width', 'pascommerce' );
            $post_templates['elementor_canvas']        = __( 'Elementor Canvas', 'pascommerce' );
        }

        return $post_templates;
    }

    public function add_body_classes( $classes ) {
        if ( is_singular( 'pc_product' ) ) {
            $classes[] = 'pascommerce-product';
        }
        return $classes;
    }
}
// Instantiate the class
new PC_Template_Loader();