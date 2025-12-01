<?php
/**
 * Pascommerce UI - Shortcodes and Frontend Logic
 * This file handles all frontend rendering logic, AJAX interactions, and general UI display functions.
 *
 * @package Pascommerce_UI
 * @license GPL-2.0+
 */
defined( 'ABSPATH' ) || exit;

class PC_Shortcodes {

    public function __construct() {
        // Shortcodes: Registered directly in __construct as the parent class is loaded early (on pc_loaded action).
        add_shortcode( 'pc_shop', array( $this, 'render_shop' ) );
        add_shortcode( 'pc_cart', array( $this, 'render_cart' ) );
        add_shortcode( 'pc_checkout', array( $this, 'render_checkout' ) );
        add_shortcode( 'pc_account', array( $this, 'render_account' ) );

        // AJAX Handlers
        add_action( 'wp_ajax_pc_add_to_cart', array( $this, 'ajax_add_to_cart' ) );
        add_action( 'wp_ajax_nopriv_pc_add_to_cart', array( $this, 'ajax_add_to_cart' ) );
        
        add_action( 'wp_ajax_pc_process_checkout', array( $this, 'ajax_process_checkout' ) );
        add_action( 'wp_ajax_nopriv_pc_process_checkout', array( $this, 'ajax_process_checkout' ) );

        // Auto-inject Single Product Template (Old function name 'single_product_template' replaced)
        add_filter( 'the_content', array( $this, 'single_product_content_injection' ) );

        // Form Handlers (Profile & Address Save)
        add_action( 'init', array( $this, 'handle_account_forms' ) );
        
        // Avatar Filter
        add_filter( 'get_avatar', array( $this, 'custom_avatar' ), 10, 5 );
    }
    
    /**
     * Custom Avatar Logic.
     */
    public function custom_avatar( $avatar, $id_or_email, $size, $default, $alt ) {
        $user = false;
        if ( is_numeric( $id_or_email ) ) {
            $user = get_user_by( 'id', $id_or_email );
        } elseif ( is_object( $id_or_email ) ) {
            if ( ! empty( $id_or_email->user_id ) ) {
                $user = get_user_by( 'id', $id_or_email->user_id );
            }
        } else {
            $user = get_user_by( 'email', $id_or_email );
        }

        if ( $user && is_object( $user ) ) {
            $custom_avatar_id = get_user_meta( $user->ID, 'pc_custom_avatar', true );
            if ( $custom_avatar_id ) {
                $image = wp_get_attachment_image_src( $custom_avatar_id, 'thumbnail' );
                if ( $image ) {
                    return "<img alt='{$alt}' src='{$image[0]}' class='avatar avatar-{$size} photo' height='{$size}' width='{$size}' />";
                }
            }
        }
        return $avatar;
    }

    /**
     * HELPER: Generate Badges HTML (Placeholder only).
     */
    private function get_badges_html( $pid ) {
        // Badge logic is now modular and should be injected via Addons.
        return '';
    }

    /**
     * 1. MODERN SINGLE PRODUCT LAYOUT (Logic Template)
     * Renders the single product view by injecting content into 'the_content' filter.
     */
    public function single_product_content_injection( $content ) {
        // Rebranded 'pc_product' CPT check
        if ( ! is_singular( 'pc_product' ) || ! in_the_loop() || ! is_main_query() ) return $content;

        $pid = get_the_ID();
        // Rebranded meta keys
        $price = get_post_meta( $pid, '_pc_price', true );
        $type = get_post_meta( $pid, '_pc_type', true );
        $sku  = get_post_meta( $pid, '_pc_sku', true ) ?: 'N/A';
        $stock = get_post_meta( $pid, '_pc_stock', true );
        $gallery = get_post_meta( $pid, '_pc_gallery', true );
        $variants = get_post_meta( $pid, '_pc_variants', true );
        
        // Use PC_Data from the core framework
        $symbol = class_exists('PC_Data') ? PC_Data::get_currency_symbol() : '$';
        $price_fmt = $symbol . ' ' . number_format((float)$price, 0, ',', '.');
        
        // Rebranded taxonomy check
        $cats = get_the_terms( $pid, 'pc_category' );
        $cat_name = $cats ? $cats[0]->name : 'Uncategorized';
        $main_img_url = get_the_post_thumbnail_url( $pid, 'large' ) ?: PC_UI_URL . 'assets/placeholder.png'; // Assuming PC_UI_URL is defined
        
        // Settings from Admin (using 'pc_' prefix for options)
        $img_size = get_option('pc_img_size_main', 'medium');
        $desc_layout = get_option('pc_desc_layout', 'side');
        // Rebranded CSS classes
        $wrapper_class = 'pc-single-wrapper pc-layout-' . esc_attr($desc_layout) . ' pc-img-' . esc_attr($img_size);

        // Actual content for the "Description" tab
        $main_description = get_post_field( 'post_content', $pid );

        ob_start();
        ?>
        <div class="pc-container">
            <div class="<?php echo esc_attr($wrapper_class); ?>">
                <div class="pc-gallery-col">
                    <div class="pc-main-image-wrapper">
                        <?php echo $this->get_badges_html($pid); ?>
                        <div class="pc-main-image"><img src="<?php echo esc_url($main_img_url); ?>" id="pc-main-display" data-index="0"></div>
                        <?php if ( !empty($gallery) ): ?>
                            <button type="button" class="pc-gallery-nav pc-prev" onclick="navGallery(-1)">&lt;</button>
                            <button type="button" class="pc-gallery-nav pc-next" onclick="navGallery(1)">&gt;</button>
                        <?php endif; ?>
                    </div>
                    <?php if ( !empty($gallery) ): ?>
                    <div class="pc-thumbs-wrapper">
                        <button type="button" class="pc-thumb-nav pc-thumb-prev" onclick="scrollThumbs(-1)">&lt;</button>
                        <div class="pc-thumbs" id="pc-thumbs-container">
                            <img src="<?php echo esc_url($main_img_url); ?>" data-src="<?php echo esc_url($main_img_url); ?>" class="pc-thumb active" data-index="0" onclick="changeImage(this)">
                            <?php $idx = 1; foreach($gallery as $img_id): 
                                $thumb_url = wp_get_attachment_image_url($img_id, 'large'); ?>
                                <img src="<?php echo esc_url($thumb_url); ?>" data-src="<?php echo esc_url($thumb_url); ?>" class="pc-thumb" data-index="<?php echo $idx; ?>" onclick="changeImage(this)">
                            <?php $idx++; endforeach; ?>
                        </div>
                        <button type="button" class="pc-thumb-nav pc-thumb-next" onclick="scrollThumbs(1)">&gt;</button>
                    </div>
                    <?php endif; ?>
                </div>
                <div class="pc-detail-col">
                    <div class="pc-product-cat"><?php echo esc_html($cat_name); ?></div>
                    <h1 class="pc-product-title"><?php the_title(); ?></h1>
                    <div class="pc-product-price"><?php echo $price_fmt; ?></div>
                    <div class="pc-product-short-desc"><?php the_excerpt(); ?></div>
                    <?php if ( $type === 'variable' && !empty($variants) ): ?>
                    <div class="pc-variants-box">
                        <label><strong>Option:</strong></label>
                        <select id="pc-variant-select" class="pc-input" style="margin-top:5px;">
                            <option value="">-- Select --</option>
                            <?php foreach($variants as $idx => $var): ?>
                                <option value="<?php echo $idx; ?>" data-price="<?php echo $var['price']; ?>"><?php echo esc_html($var['name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <?php endif; ?>
                    <div class="pc-action-row">
                        <div class="pc-qty-ctrl">
                            <button class="pc-qty-btn" onclick="updateQty(-1)">-</button>
                            <input type="number" id="pc-qty" value="1" min="1" class="pc-qty-input" readonly>
                            <button class="pc-qty-btn" onclick="updateQty(1)">+</button>
                        </div>
                        <button class="pc-btn pc-btn-primary pc-btn-block pc-add-cart-single" data-id="<?php echo $pid; ?>">Add to Cart</button>
                    </div>
                    <div class="pc-product-meta">
                        <div class="pc-meta-row"><span class="pc-meta-label">SKU:</span> <span><?php echo esc_html($sku); ?></span></div>
                        <div class="pc-meta-row"><span class="pc-meta-label">Category:</span> <span><?php echo esc_html($cat_name); ?></span></div>
                        <div class="pc-meta-row"><span class="pc-meta-label">Stock:</span> <span><?php echo $stock ? $stock . ' units' : 'Available'; ?></span></div>
                    </div>
                    <?php if ( $desc_layout === 'side' ): ?>
                        <div class="pc-desc-content" style="margin-top:30px; border-top:1px solid #eee; padding-top:20px;">
                            <h3 style="font-size:1.4em; margin-top:0;">Description</h3>
                            <?php echo $main_description; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <?php if ( $desc_layout === 'bottom' || $desc_layout === 'side' ): // Only display Tabs if not Full Width Layout ?>
            <div class="pc-tabs-wrapper" style="margin-top: 50px;">
                <ul class="pc-tabs-nav">
                    <li class="active" data-tab="desc">Description</li>
                    <li data-tab="spec">Specification</li>
                    <li data-tab="reviews">Reviews (<?php echo get_comments_number($pid); ?>)</li>
                </ul>
                <div class="pc-tabs-content">
                    
                    <div id="pc-tab-desc" class="pc-tab-pane active">
                        <?php 
                        // Display full description only if 'bottom' layout is chosen
                        if ($desc_layout === 'bottom') {
                            echo $main_description; 
                        } else {
                            echo '<p>Description is displayed in the right column. This area can be used for related items or upsells.</p>';
                        }
                        ?>
                    </div>
                    
                    <div id="pc-tab-spec" class="pc-tab-pane">
                        <p>Specifications data goes here. (Placeholder)</p>
                        <ul style="list-style:disc; padding-left:20px;">
                            <li>Weight: <?php echo get_post_meta($pid, '_pc_weight', true) ?: 'N/A'; ?></li>
                            <li>Type: <?php echo strtoupper($type); ?></li>
                        </ul>
                    </div>
                    
                    <div id="pc-tab-reviews" class="pc-tab-pane">
                        <?php comments_template(); ?>
                    </div>
                </div>
            </div>
            <?php endif; ?>

        </div>
        
        <script>
        // JS for Tab Switching
        jQuery(document).ready(function($){
            $('.pc-tabs-nav li').on('click', function(){
                var target = $(this).data('tab');
                
                // Switch Nav Active Class
                $('.pc-tabs-nav li').removeClass('active');
                $(this).addClass('active');
                
                // Switch Content Visibility
                $('.pc-tabs-content .pc-tab-pane').removeClass('active').hide();
                $('#pc-tab-' + target).addClass('active').show();
            });
            // Show the first tab on load
            $('#pc-tab-desc').show();
        });
        </script>
        <?php return ob_get_clean();
    }

    /**
     * Injects product details on the single product page if the theme does not load the content.
     */
    public function single_product_content_injection( $content ) {
        if ( ! is_singular( 'pc_product' ) || ! in_the_loop() ) {
            return $content;
        }
        
        // Only inject if content is empty
        if ( empty( $content ) ) {
            return '';
        }

        return $content;
    }
    
    /**
     * Handles form submissions for profile and address updates.
     */
    public function handle_account_forms() {
        if ( ! is_user_logged_in() ) return;
        
        // Save Profile
        if ( isset( $_POST['pc_action'] ) && $_POST['pc_action'] == 'save_profile' ) {
            // Rebranded nonce field
            if ( ! wp_verify_nonce( $_POST['_wpnonce'], 'pc_save_profile' ) ) return;
            $user_id = get_current_user_id(); $d_name = sanitize_text_field( $_POST['pc_display_name'] ); $email = sanitize_email( $_POST['pc_email'] ); $pass1 = $_POST['pc_pass1']; $pass2 = $_POST['pc_pass2'];
            $args = array( 'ID' => $user_id, 'display_name' => $d_name, 'user_email' => $email );
            if ( ! empty( $pass1 ) ) { if ( $pass1 === $pass2 ) { $args['user_pass'] = $pass1; } }
            wp_update_user( $args );
            if ( ! empty( $_FILES['pc_avatar']['name'] ) ) { 
                require_once( ABSPATH . 'wp-admin/includes/image.php' ); 
                require_once( ABSPATH . 'wp-admin/includes/file.php' ); 
                require_once( ABSPATH . 'wp-admin/includes/media.php' ); 
                $attach_id = media_handle_upload( 'pc_avatar', 0 ); 
                if ( ! is_wp_error( $attach_id ) ) { 
                    // Rebranded user meta key
                    update_user_meta( $user_id, 'pc_custom_avatar', $attach_id ); 
                } 
            }
            wp_redirect( add_query_arg( 'msg', 'profile_saved', get_permalink() ) ); exit;
        }
        
        // Save Address
        if ( isset( $_POST['pc_action'] ) && $_POST['pc_action'] == 'save_address' ) {
            // Rebranded nonce field
            if ( ! wp_verify_nonce( $_POST['_wpnonce'], 'pc_save_address' ) ) return;
            $user_id = get_current_user_id(); 
            // Rebranded user meta keys
            update_user_meta( $user_id, 'pc_phone', sanitize_text_field( $_POST['pc_phone'] ) );
            update_user_meta( $user_id, 'pc_addr_country', sanitize_text_field( $_POST['pc_addr_country'] ) ); 
            update_user_meta( $user_id, 'pc_addr_1', sanitize_text_field( $_POST['pc_addr_1'] ) ); 
            update_user_meta( $user_id, 'pc_addr_2', sanitize_text_field( $_POST['pc_addr_2'] ) ); 
            update_user_meta( $user_id, 'pc_addr_city', sanitize_text_field( $_POST['pc_addr_city'] ) ); 
            update_user_meta( $user_id, 'pc_addr_state', sanitize_text_field( $_POST['pc_addr_state'] ) ); 
            update_user_meta( $user_id, 'pc_addr_postcode', sanitize_text_field( $_POST['pc_addr_postcode'] ) );
            wp_redirect( add_query_arg( array('pc_tab' => 'address', 'msg' => 'address_saved'), get_permalink() ) ); exit;
        }
    }

    /**
     * SHORTCODE: [pc_cart]
     */
    public function render_cart() { 
        // Use PC_Cart from core
        $cart = PC_Cart::get_data(); 
        // Use PC_Data from core
        $symbol = class_exists('PC_Data') ? PC_Data::get_currency_symbol() : '$';
        
        // Rebranded option keys
        if ( empty( $cart ) ) return '<div class="pc-alert">Cart is empty. <a href="'.get_permalink(get_option('pc_page_shop')).'">Go Shopping</a></div>';
        
        ob_start(); ?>
        <div class="pc-cart-wrapper">
            <table class="pc-table"><thead><tr><th>Product</th><th>Price</th><th>Qty</th><th>Subtotal</th></tr></thead>
                <tbody><?php $total = 0; foreach ( $cart as $pid => $qty ) : $price = (int) get_post_meta( $pid, '_pc_price', true ); $subtotal = $price * $qty; $total += $subtotal; ?>
                    <tr><td style="display:flex; align-items:center; gap:10px;"><img src="<?php echo get_the_post_thumbnail_url($pid, 'thumbnail'); ?>" class="pc-cart-img"><?php echo get_the_title( $pid ); ?></td><td><?php echo $symbol . ' ' . number_format($price); ?></td><td><?php echo $qty; ?></td><td><?php echo $symbol . ' ' . number_format($subtotal); ?></td></tr>
                <?php endforeach; ?>
                </tbody><tfoot><tr><td colspan="3" align="right"><strong>Total</strong></td><td><strong><?php echo $symbol . ' ' . number_format($total); ?></strong></td></tr></tfoot>
            </table>
            <div style="margin-top:20px; text-align:right;"><a href="<?php echo get_permalink(get_option('pc_page_checkout')); ?>" class="pc-btn pc-btn-primary">Proceed to Checkout</a></div>
        </div>
        <?php return ob_get_clean(); 
    }

    /**
     * SHORTCODE: [pc_checkout]
     */
    public function render_checkout() { 
        // Use PC_Cart from core
        $cart = PC_Cart::get_data(); if ( empty( $cart ) ) return '<div class="pc-alert">Cart is empty.</div>';
        $payment_methods = apply_filters( 'pc_available_payment_methods', array() ); $manual_bank = get_option('pc_bank_details');
        // Use PC_Data from core
        $countries = class_exists('PC_Data') ? PC_Data::get_countries() : ['ID' => 'Indonesia']; 
        $user = wp_get_current_user(); $name = $user->exists() ? $user->display_name : ''; $email = $user->exists() ? $user->user_email : '';
        // Rebranded option and meta keys
        $phone = $user->exists() ? get_user_meta($user->ID, 'pc_phone', true) : ''; $country = $user->exists() ? get_user_meta($user->ID, 'pc_addr_country', true) : get_option('pc_store_country');
        $addr1 = $user->exists() ? get_user_meta($user->ID, 'pc_addr_1', true) : ''; $addr2 = $user->exists() ? get_user_meta($user->ID, 'pc_addr_2', true) : '';
        $city = $user->exists() ? get_user_meta($user->ID, 'pc_addr_city', true) : ''; $state = get_user_meta($user->ID, 'pc_addr_state', true) : '';
        $postcode = $user->exists() ? get_user_meta($user->ID, 'pc_addr_postcode', true) : ''; 
        // Use PC_Data from core
        $symbol = class_exists('PC_Data') ? PC_Data::get_currency_symbol() : '$'; $total = 0;
        ob_start(); ?>
        <form id="pc-checkout-form">
            <div class="pc-checkout-layout">
                <div class="pc-checkout-form">
                    <h3>Billing Details</h3>
                    <div class="pc-row"><div class="pc-col-6"><div class="pc-form-group"><label>Name <span style="color:red">*</span></label><input type="text" name="pc_name" value="<?php echo esc_attr($name); ?>" required class="pc-input"></div></div>
                        <div class="pc-col-6"><div class="pc-form-group"><label>Phone <span style="color:red">*</span></label><input type="text" name="pc_phone" value="<?php echo esc_attr($phone); ?>" required class="pc-input"></div></div></div>
                    <div class="pc-form-group"><label>Email <span style="color:red">*</span></label><input type="email" name="pc_email" value="<?php echo esc_attr($email); ?>" required class="pc-input"></div>
                    <div class="pc-form-group"><label>Country / Region <span style="color:red">*</span></label><select name="pc_country" class="pc-input" required><?php foreach ($countries as $code => $cname) { echo '<option value="'.esc_attr($code).'" '.selected($country, $code, false).'>'.esc_html($cname).'</option>'; } ?></select></div>
                    <div class="pc-form-group"><label>Street Address <span style="color:red">*</span></label>
                        <input type="text" name="pc_address_1" value="<?php echo esc_attr($addr1); ?>" required class="pc-input" placeholder="House number and street name" style="margin-bottom:10px;">
                        <input type="text" name="pc_address_2" value="<?php echo esc_attr($addr2); ?>" class="pc-input" placeholder="Apartment, suite, unit, etc. (optional)">
                    </div>
                    <div class="pc-row"><div class="pc-col-6"><div class="pc-form-group"><label>Town / City <span style="color:red">*</span></label><input type="text" name="pc_city" value="<?php echo esc_attr($city); ?>" required class="pc-input"></div></div>
                        <div class="pc-col-6"><div class="pc-form-group"><label>State / Province</label><input type="text" name="pc_state" value="<?php echo esc_attr($state); ?>" class="pc-input"></div></div></div>
                    <div class="pc-form-group"><label>Postcode / ZIP <span style="color:red">*</span></label><input type="text" name="pc_postcode" value="<?php echo esc_attr($postcode); ?>" required class="pc-input"></div>
                </div>
                <div class="pc-checkout-sidebar">
                    <h3>Your Order</h3>
                    <div class="pc-order-summary-items" style="margin-bottom: 20px; padding-bottom: 15px; border-bottom: 1px solid #eee;">
                        <?php foreach ( $cart as $pid => $qty ) : $item_price = (int) get_post_meta( $pid, '_pc_price', true ); $item_subtotal = $item_price * $qty; $total += $item_subtotal; $img_url = get_the_post_thumbnail_url($pid, 'thumbnail') ?: PC_UI_URL . 'assets/placeholder.png'; $weight = get_post_meta($pid, '_pc_weight', true); ?>
                            <div style="display:grid; grid-template-columns:40px 1fr 80px; gap:10px; margin-bottom:15px; font-size:14px; align-items:center;">
                                <img src="<?php echo esc_url($img_url); ?>" style="width:40px; height:40px; border-radius:4px; object-fit:cover;">
                                <div><strong style="display:block;"><?php echo get_the_title( $pid ); ?></strong><span style="font-size:12px; color:#666;">Qty: <?php echo $qty; ?> | Weight: <?php echo esc_html($weight) ?: 0; ?>g</span></div>
                                <div style="font-weight:bold; text-align:right;"><?php echo $symbol . ' ' . number_format($item_subtotal); ?></div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <div style="font-size:16px; margin-bottom: 20px;">
                        <div style="display:flex; justify-content:space-between; margin-bottom:5px;"><span>Subtotal:</span> <strong><?php echo $symbol . ' ' . number_format($total); ?></strong></div>
                        <div style="display:flex; justify-content:space-between; font-weight:bold; padding-top:10px; border-top:1px dashed #ddd;"><span>TOTAL:</span> <strong style="color:var(--pc-primary);"><?php echo $symbol . ' ' . number_format($total); ?></strong></div>
                    </div>
                    <h3>Payment</h3>
                    <div class="pc-payment-methods">
                        <label class="pc-radio"><input type="radio" name="pc_payment_method" value="manual_transfer" checked> <span>Manual Bank Transfer</span></label>
                        <?php foreach($payment_methods as $slug => $data): ?>
                            <label class="pc-radio"><input type="radio" name="pc_payment_method" value="<?php echo esc_attr($slug); ?>"> <span><?php echo esc_html($data['label']); ?></span></label>
                        <?php endforeach; ?>
                    </div>
                    <?php if($manual_bank): ?><div class="pc-alert" style="margin-top:15px; font-size:13px;"><?php echo nl2br(esc_html($manual_bank)); ?></div><?php endif; ?>
                    <button type="submit" class="pc-btn pc-btn-primary pc-btn-block" id="pc-place-order" style="margin-top:20px;">Place Order</button>
                </div>
            </div>
            <div id="pc-msg" style="margin-top:20px;"></div>
        </form>
        <?php return ob_get_clean(); 
    }

    /**
     * SHORTCODE: [pc_account]
     */
    public function render_account() { 
        if ( ! is_user_logged_in() ) {
            return '<div class="pc-container" style="max-width:400px; margin:0 auto; padding:40px; border:1px solid #eee; border-radius:8px;">
                        <h3 style="text-align:center;">Login</h3>' . 
                        wp_login_form( array( 'echo' => false, 'redirect' => get_permalink() ) ) . 
                   '</div>';
        }
        $user = wp_get_current_user(); 
        // Rebranded option key
        $account_page = get_permalink( get_option('pc_page_account') ); 
        // Rebranded tab query
        $active_tab = isset($_GET['pc_tab']) ? $_GET['pc_tab'] : 'dashboard'; $msg = isset($_GET['msg']) ? $_GET['msg'] : '';
        if ( isset($_GET['view-order']) ) return $this->render_order_detail( (int)$_GET['view-order'], $account_page );
        ob_start(); ?>
        <div class="pc-account-wrapper">
            <div style="display:flex; gap:30px; flex-wrap:wrap;">
                <div style="flex:1; min-width:200px; border-right:1px solid #eee; padding-right:20px;">
                    <div style="text-align:center; margin-bottom:20px;">
                        <?php echo get_avatar($user->ID, 96); ?>
                        <h4 style="margin:10px 0 0;"><?php echo esc_html($user->display_name); ?></h4>
                    </div>
                    <nav class="pc-account-nav">
                        <a href="<?php echo esc_url($account_page); ?>" class="pc-btn pc-btn-block <?php echo $active_tab=='dashboard'?'pc-btn-primary':''; ?>" style="margin-bottom:10px; text-align:left;">Orders</a>
                        <a href="<?php echo esc_url(add_query_arg('pc_tab', 'profile', $account_page)); ?>" class="pc-btn pc-btn-block <?php echo $active_tab=='profile'?'pc-btn-primary':''; ?>" style="margin-bottom:10px; text-align:left;">Edit Profile</a>
                        <a href="<?php echo esc_url(add_query_arg('pc_tab', 'address', $account_page)); ?>" class="pc-btn pc-btn-block <?php echo $active_tab=='address'?'pc-btn-primary':''; ?>" style="margin-bottom:10px; text-align:left;">Addresses</a>
                        <a href="<?php echo wp_logout_url(get_permalink()); ?>" class="pc-btn pc-btn-block" style="margin-bottom:10px; background:#fee2e2; color:#991b1b; text-align:left;">Logout</a>
                    </nav>
                </div>
                <div style="flex:3; min-width:300px;">
                    <?php if($msg == 'profile_saved'): ?><div class="pc-alert" style="background:#dcfce7; color:#166534;">Profile updated successfully.</div><?php endif; ?>
                    <?php if($msg == 'address_saved'): ?><div class="pc-alert" style="background:#dcfce7; color:#166534;">Address updated successfully.</div><?php endif; ?>
                    <?php if($active_tab == 'dashboard'): 
                        // Use 'pc_order' post type and '_pc_customer' meta key
                        $symbol = class_exists('PC_Data') ? PC_Data::get_currency_symbol() : '$'; $args = array( 'post_type' => 'pc_order', 'posts_per_page' => 10, 'meta_query' => array( array( 'key' => '_pc_customer', 'value' => '"email";s:'.strlen($user->user_email).':"'.$user->user_email.'"', 'compare' => 'LIKE' ) ) );
                        $orders = new WP_Query($args); ?>
                        <h3>Order History</h3>
                        <?php if ( $orders->have_posts() ): ?>
                            <table class="pc-table"><thead><tr><th>Order ID</th><th>Date</th><th>Total</th><th>Status</th><th>Action</th></tr></thead>
                                <tbody><?php while ( $orders->have_posts() ): $orders->the_post(); $oid = get_the_ID(); $total = get_post_meta($oid, '_pc_total', true); $status = get_post_meta($oid, '_pc_status', true); $view_url = add_query_arg('view-order', $oid, $account_page); ?>
                                    <tr><td><a href="<?php echo esc_url($view_url); ?>">#<?php echo $oid; ?></a></td><td><?php echo get_the_date(); ?></td><td><?php echo $symbol . ' ' . number_format((int)$total,0,',','.'); ?></td><td><span style="padding:4px 8px; background:#eee; border-radius:4px; font-size:11px; font-weight:bold;"><?php echo strtoupper($status); ?></span></td><td><a href="<?php echo esc_url($view_url); ?>" class="pc-btn" style="padding:5px 10px; font-size:12px;">View</a></td></tr>
                                <?php endwhile; ?>
                                </tbody>
                            </table>
                        <?php else: ?><p>No orders found.</p><?php endif; wp_reset_postdata(); ?>
                        
                    <?php elseif($active_tab == 'profile'): ?>
                        <h3>Edit Profile</h3>
                        <form method="post" enctype="multipart/form-data">
                            <?php wp_nonce_field('pc_save_profile'); ?>
                            <input type="hidden" name="pc_action" value="save_profile">
                            <div class="pc-form-group"><label>Profile Picture</label><input type="file" name="pc_avatar" accept="image/*"></div>
                            <div class="pc-form-group"><label>Display Name</label><input type="text" name="pc_display_name" value="<?php echo esc_attr($user->display_name); ?>" class="pc-input" required></div>
                            <div class="pc-form-group"><label>Email Address</label><input type="email" name="pc_email" value="<?php echo esc_attr($user->user_email); ?>" class="pc-input" required></div>
                            <hr style="margin:20px 0; border:0; border-top:1px solid #eee;">
                            <h4>Change Password</h4>
                            <div class="pc-form-group"><label>New Password</label><input type="password" name="pc_pass1" class="pc-input"></div>
                            <div class="pc-form-group"><label>Confirm New Password</label><input type="password" name="pc_pass2" class="pc-input"></div>
                            <button type="submit" class="pc-btn pc-btn-primary">Save Changes</button>
                        </form>

                    <?php elseif($active_tab == 'address'): 
                        // Rebranded meta keys and PC_Data
                        $phone = get_user_meta($user->ID, 'pc_phone', true); $country = get_user_meta($user->ID, 'pc_addr_country', true) ?: get_option('pc_store_country');
                        $addr1 = get_user_meta($user->ID, 'pc_addr_1', true); $addr2 = get_user_meta($user->ID, 'pc_addr_2', true);
                        $city = get_user_meta($user->ID, 'pc_addr_city', true); $state = get_user_meta($user->ID, 'pc_addr_state', true);
                        $postcode = get_user_meta($user->ID, 'pc_addr_postcode', true); $countries = class_exists('PC_Data') ? PC_Data::get_countries() : ['ID' => 'Indonesia'];
                    ?>
                        <h3>Shipping Address</h3>
                        <form method="post">
                            <?php wp_nonce_field('pc_save_address'); ?>
                            <input type="hidden" name="pc_action" value="save_address">
                            <div class="pc-form-group"><label>Phone Number</label><input type="text" name="pc_phone" value="<?php echo esc_attr($phone); ?>" class="pc-input" placeholder="e.g. 08123456789"></div>
                            <div class="pc-form-group"><label>Country / Region</label>
                                <select name="pc_addr_country" class="pc-input"><?php foreach ($countries as $code => $cname) { echo '<option value="'.esc_attr($code).'" '.selected($country, $code, false).'>'.esc_html($cname).'</option>'; } ?></select>
                            </div>
                            <div class="pc-form-group"><label>Street Address</label>
                                <input type="text" name="pc_addr_1" value="<?php echo esc_attr($addr1); ?>" class="pc-input" placeholder="House number and street name" style="margin-bottom:10px;">
                                <input type="text" name="pc_addr_2" value="<?php echo esc_attr($addr2); ?>" class="pc-input" placeholder="Apartment, suite, unit, etc. (optional)">
                            </div>
                            <div class="pc-row"><div class="pc-col-6"><div class="pc-form-group"><label>Town / City</label><input type="text" name="pc_addr_city" value="<?php echo esc_attr($city); ?>" class="pc-input"></div></div>
                                <div class="pc-col-6"><div class="pc-form-group"><label>State / Province</label><input type="text" name="pc_addr_state" value="<?php echo esc_attr($state); ?>" class="pc-input"></div></div></div>
                            <div class="pc-form-group"><label>Postcode / ZIP</label><input type="text" name="pc_addr_postcode" value="<?php echo esc_attr($postcode); ?>" class="pc-input"></div>
                            <button type="submit" class="pc-btn pc-btn-primary">Save Address</button>
                        </form>
                    <?php endif; ?>

                </div>
            </div>
        </div>
        <?php return ob_get_clean(); 
    }

    /**
     * Renders order details on the My Account page.
     */
    public function render_order_detail( $order_id, $back_url ) {
        $order = get_post( $order_id ); $current_user = wp_get_current_user(); $order_customer = get_post_meta( $order_id, '_pc_customer', true );
        // Use 'pc_order' post type
        if ( !$order || $order->post_type !== 'pc_order' ) return '<div class="pc-alert">Order not found.</div>';
        if ( isset($order_customer['email']) && $order_customer['email'] !== $current_user->user_email && !current_user_can('manage_options') ) return '<div class="pc-alert">Access Denied.</div>';
        $items = get_post_meta( $order_id, '_pc_items', true ); $total = get_post_meta( $order_id, '_pc_total', true ); $status = get_post_meta( $order_id, '_pc_status', true ); $gw_redirect = get_post_meta($order_id, '_pc_payment_redirect', true); 
        // Use PC_Data from core
        $symbol = class_exists('PC_Data') ? PC_Data::get_currency_symbol() : '$';
        ob_start(); ?>
        <div class="pc-cart-wrapper">
            <a href="<?php echo esc_url($back_url); ?>" style="margin-bottom:20px; display:inline-block;">&larr; Back to Dashboard</a>
            <h3>Order #<?php echo $order_id; ?></h3>
            <div style="background:#f8fafc; padding:20px; border-radius:8px; margin-bottom:20px; border:1px solid #eee;">
                <p><strong>Status:</strong> <span style="font-weight:bold;"><?php echo strtoupper($status); ?></span></p>
                <p><strong>Total:</strong> <?php echo $symbol . ' ' . number_format((float)$total); ?></p>
                <?php if ($status === 'pending' && $gw_redirect): ?><div style="margin-top:15px;"><a href="<?php echo esc_url($gw_redirect); ?>" class="pc-btn pc-btn-primary">Pay Now</a></div><?php endif; ?>
            </div>
            <table class="pc-table">
                <thead><tr><th>Product</th><th>Qty</th><th>Price</th></tr></thead>
                <tbody><?php $subtotal = 0; foreach ( $items as $item ) : $subtotal += $item['subtotal']; ?><tr><td><?php echo esc_html($item['name'] ?? '-'); ?></td><td><?php echo esc_html($item['qty'] ?? 1); ?></td><td><?php echo $symbol . ' ' . number_format($item['price'] ?? 0); ?></td></tr><?php endforeach; ?></tbody>
                <tfoot><tr><td colspan="2" align="right">Total</td><td><?php echo $symbol . ' ' . number_format((float)$total); ?></td></tr></tfoot>
            </table>
            <h4 style="margin-top:30px;">Billing Address</h4>
            <p><?php if ( isset($order_customer['address_data']) ) { $ad = $order_customer['address_data']; $country_name = class_exists('PC_Data') ? (PC_Data::get_countries()[$ad['country']] ?? $ad['country']) : $ad['country']; echo esc_html($ad['line1']) . '<br>'; if(!empty($ad['line2'])) echo esc_html($ad['line2']) . '<br>'; echo esc_html($ad['city']) . ', ' . esc_html($ad['state']) . ' ' . esc_html($ad['postcode']) . '<br>'; echo esc_html($country_name); } else { echo nl2br(esc_html($order_customer['address'] ?? '-')); } ?></p>
        </div>
        <?php return ob_get_clean();
    }

    /**
     * AJAX Handler: Adds product to cart.
     */
    public function ajax_add_to_cart() {
        check_ajax_referer( 'pc_ui_nonce', 'nonce' ); $pid = intval($_POST['pid']); $qty = intval($_POST['qty']) ?: 1; 
        // Use PC_Cart from core
        PC_Cart::add( $pid, $qty ); wp_send_json_success( ['msg' => 'Added to cart!'] );
    }

    /**
     * AJAX Handler: Processes checkout form and creates order.
     */
    public function ajax_process_checkout() {
        check_ajax_referer( 'pc_ui_nonce', 'nonce' ); $data = array(); parse_str($_POST['form_data'], $data);
        
        // Rebranding keys and logic
        $address_data = array( 'country' => sanitize_text_field($data['pc_country']), 'line1' => sanitize_text_field($data['pc_address_1']), 'line2' => sanitize_text_field($data['pc_address_2']), 'city' => sanitize_text_field($data['pc_city']), 'state' => sanitize_text_field($data['pc_state']), 'postcode' => sanitize_text_field($data['pc_postcode']) );
        $full_address = $address_data['line1']; if($address_data['line2']) $full_address .= ', ' . $address_data['line2']; $full_address .= "\n" . $address_data['city'] . ', ' . $address_data['state'] . ' ' . $address_data['postcode']; $full_address .= "\n" . $address_data['country'];
        $customer = array( 'name' => sanitize_text_field($data['pc_name']), 'email' => sanitize_email($data['pc_email']), 'phone' => sanitize_text_field($data['pc_phone']), 'address' => $full_address, 'address_data' => $address_data );
        
        // Use PC_Cart from core
        $cart = PC_Cart::get_data(); if(empty($cart)) wp_send_json_error('Cart empty'); $items = []; foreach($cart as $pid => $qty) { $items[] = ['product_id' => $pid, 'qty' => $qty]; }
        
        // Use PC_Order from core
        $order_id = PC_Order::create( $customer, $items, $data['pc_payment_method'] ); 
        if( is_wp_error($order_id) ) wp_send_json_error( $order_id->get_error_message() ); 
        
        PC_Cart::clear();
        
        $redirect = home_url('/?order_received=' . $order_id); $gw_redirect = get_post_meta($order_id, '_pc_payment_redirect', true); if($gw_redirect) $redirect = $gw_redirect;
        
        wp_send_json_success(['order_id' => $order_id, 'redirect' => $redirect]);
    }
}

// Instantiate the class to register all shortcodes and hooks
new PC_Shortcodes();