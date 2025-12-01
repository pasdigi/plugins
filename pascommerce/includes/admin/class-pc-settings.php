<?php
defined( 'ABSPATH' ) || exit;

class PC_Settings {

    public static function init() {
        add_action( 'admin_init', array( __CLASS__, 'register_settings' ) );
    }

    public static function get_tabs() {
        $tabs = array(
            'general'  => __( 'General', 'pascommerce' ),
            'display'  => __( 'Display', 'pascommerce' ),
            'shipping' => __( 'Shipping', 'pascommerce' ),
            'payment'  => __( 'Payment', 'pascommerce' ),
            'addons'   => __( 'Add-ons', 'pascommerce' ),
        );
        return apply_filters( 'pc_settings_tabs', $tabs );
    }

    public static function render_page() {
        $active_tab = isset( $_GET['tab'] ) ? sanitize_key( $_GET['tab'] ) : 'general';
        $gateway_id = isset( $_GET['gateway'] ) ? sanitize_key( $_GET['gateway'] ) : ''; 
        
        $base_url = admin_url( 'admin.php?page=pc_settings' );

        // 1. NESTED GATEWAY SETTINGS (Halaman Manage Gateway Spesifik)
        if ($active_tab === 'payment' && !empty($gateway_id)) {
            $tabs = self::get_tabs();
            
            $gateway_list = apply_filters('pc_setting_gateways_list', []);
            $gateway_title = $gateway_list[$gateway_id]['title'] ?? $gateway_id;

            echo '<div class="wrap"><h1>' . esc_html($tabs[$active_tab]) . ' &rarr; ' . esc_html($gateway_title) . ' Settings</h1>';
            
            echo '<form method="post" action="options.php">';
            // Hook ini akan dipanggil oleh add-on (Midtrans, PayPal) atau oleh fungsi di bawah (Manual Transfer)
            do_action( 'pc_gateway_settings_' . $gateway_id ); 
            echo '</form>';
            
            echo '<p><a href="' . esc_url(add_query_arg('tab', 'payment', remove_query_arg('gateway', $base_url))) . '">&larr; ' . __('Back to Payments List', 'pascommerce') . '</a></p>';
            echo '</div>';
            return;
        }

        ?>
        <div class="wrap">
            <h1><?php _e( 'Pascommerce Settings', 'pascommerce' ); ?></h1>
            <h2 class="nav-tab-wrapper">
                <?php foreach ( self::get_tabs() as $key => $label ) : ?>
                    <a href="<?php echo esc_url( add_query_arg( 'tab', $key, $base_url ) ); ?>" class="nav-tab <?php echo $active_tab == $key ? 'nav-tab-active' : ''; ?>">
                        <?php echo $label; ?>
                    </a>
                <?php endforeach; ?>
            </h2>

            <form method="post" action="options.php">
                <?php
                if ( $active_tab == 'addons' ) {
                    self::render_addons_tab();
                } elseif ( $active_tab == 'payment' ) {
                    // 2. RENDER TAB PAYMENT CONTAINER (Daftar Gateway)
                    self::render_payment_tab($base_url);
                } elseif ( has_action( 'pc_settings_content_' . $active_tab ) ) {
                    // TABS CUSTOM LAINNYA
                    settings_fields( 'pc_settings_group_' . $active_tab );
                    do_action( 'pc_settings_content_' . $active_tab );
                    submit_button();
                } else {
                    // TABS DEFAULT (General, Shipping, Display)
                    settings_fields( 'pc_settings_group_' . $active_tab );
                    do_settings_sections( 'pc_settings_' . $active_tab );
                    submit_button();
                }
                ?>
            </form>
        </div>
        <?php
    }

    /**
     * RENDER PAYMENT TAB (Container for all gateways)
     */
    public static function render_payment_tab($base_url) {
        $gateways = array();

        // Filter untuk gateway eksternal (PayPal, Midtrans, dll)
        $gateways = apply_filters('pc_setting_gateways_list', $gateways);

        ?>
        <h3 style="margin-top:20px;"><?php _e('Payment Methods', 'pascommerce'); ?></h3>
        <p><?php _e('Click "Manage" to configure payment settings.', 'pascommerce'); ?></p>

        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th style="width: 25%;"><?php _e('Method', 'pascommerce'); ?></th>
                    <th style="width: 55%;"><?php _e('Description', 'pascommerce'); ?></th>
                    <th style="width: 20%;"><?php _e('Action', 'pascommerce'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($gateways as $id => $gateway): 
                    $manage_link = $gateway['manage_link'] ?? add_query_arg(['tab' => 'payment', 'gateway' => $id], $base_url);
                ?>
                    <tr>
                        <td><strong><?php echo esc_html($gateway['title']); ?></strong></td>
                        <td><?php echo esc_html($gateway['description']); ?></td>
                        <td>
                            <a href="<?php echo esc_url($manage_link); ?>" class="button"><?php _e('Manage', 'pascommerce'); ?></a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        
        <?php
    }
    
    // FUNGSI render_manual_transfer_logic() yang pertama telah dihapus di sini

    public static function register_settings() {
        
        // =================================================================
        // 1. GENERAL TAB
        // =================================================================
        $gen_group = 'pc_settings_group_general';
        $gen_page  = 'pc_settings_general';

        $gen_fields = [
            'pc_store_address', 'pc_store_address_2', 'pc_store_city', 
            'pc_store_postcode', 'pc_store_country', 
            'pc_currency', 'pc_currency_pos', 'pc_thousand_sep', 'pc_decimal_sep', 'pc_num_decimals',
            'pc_product_slug', 
            'pc_page_shop', 'pc_page_cart', 'pc_page_checkout', 'pc_page_account'
        ];
        foreach($gen_fields as $f) register_setting( $gen_group, $f );

        // --- SECTION 1: STORE ADDRESS ---
        add_settings_section( 'pc_gen_addr', __( 'Store Address', 'pascommerce' ), null, $gen_page );
        add_settings_field( 'pc_store_address', __( 'Address Line 1', 'pascommerce' ), function() { echo '<input type="text" name="pc_store_address" value="' . esc_attr( get_option( 'pc_store_address' ) ) . '" class="regular-text">'; }, $gen_page, 'pc_gen_addr' );
        add_settings_field( 'pc_store_address_2', __( 'Address Line 2', 'pascommerce' ), function() { echo '<input type="text" name="pc_store_address_2" value="' . esc_attr( get_option( 'pc_store_address_2' ) ) . '" class="regular-text">'; }, $gen_page, 'pc_gen_addr' );
        add_settings_field( 'pc_store_city', __( 'City', 'pascommerce' ), function() { echo '<input type="text" name="pc_store_city" value="' . esc_attr( get_option( 'pc_store_city' ) ) . '" class="regular-text">'; }, $gen_page, 'pc_gen_addr' );
        add_settings_field( 'pc_store_postcode', __( 'Postcode / ZIP', 'pascommerce' ), function() { echo '<input type="text" name="pc_store_postcode" value="' . esc_attr( get_option( 'pc_store_postcode' ) ) . '" class="regular-text">'; }, $gen_page, 'pc_gen_addr' );
        add_settings_field( 'pc_store_country', __( 'Country', 'pascommerce' ), function() { 
            $countries = class_exists('PC_Data') ? PC_Data::get_countries() : ['ID' => 'Indonesia']; 
            $current = get_option('pc_store_country', 'ID');
            echo '<select name="pc_store_country" class="regular-text">';
            foreach ($countries as $c => $n) echo '<option value="'.$c.'" '.selected($current,$c,false).'>'.$n.'</option>';
            echo '</select>';
        }, $gen_page, 'pc_gen_addr' );

        // --- SECTION 2: CURRENCY OPTIONS ---
        add_settings_section( 'pc_gen_curr', __( 'Currency Options', 'pascommerce' ), null, $gen_page );
        add_settings_field( 'pc_currency', __( 'Currency', 'pascommerce' ), function() { 
            $cur = class_exists('PC_Data') ? PC_Data::get_currencies() : ['IDR' => 'Rupiah'];
            $current = get_option('pc_currency', 'IDR');
            echo '<select name="pc_currency" class="regular-text">';
            foreach ($cur as $c => $n) echo '<option value="'.$c.'" '.selected($current,$c,false).'>'.$n.'</option>';
            echo '</select>';
        }, $gen_page, 'pc_gen_curr' );
        add_settings_field( 'pc_currency_pos', __( 'Position', 'pascommerce' ), function() {
            $val = get_option('pc_currency_pos', 'left');
            echo '<select name="pc_currency_pos"><option value="left" '.selected($val,'left',false).'>Left (Rp100)</option><option value="left_space" '.selected($val,'left_space',false).'>Left Space (Rp 100)</option><option value="right" '.selected($val,'right',false).'>Right (100Rp)</option></select>';
        }, $gen_page, 'pc_gen_curr' );
        add_settings_field( 'pc_thousand_sep', __( 'Thousand Separator', 'pascommerce' ), function() { echo '<input type="text" name="pc_thousand_sep" value="'.esc_attr(get_option('pc_thousand_sep', '.')).'" class="small-text">'; }, $gen_page, 'pc_gen_curr' );
        add_settings_field( 'pc_decimal_sep', __( 'Decimal Separator', 'pascommerce' ), function() { echo '<input type="text" name="pc_decimal_sep" value="'.esc_attr(get_option('pc_decimal_sep', ',')).'" class="small-text">'; }, $gen_page, 'pc_gen_curr' );
        add_settings_field( 'pc_num_decimals', __( 'Decimals', 'pascommerce' ), function() { echo '<input type="number" name="pc_num_decimals" value="'.esc_attr(get_option('pc_num_decimals', 0)).'" class="small-text" min="0">'; }, $gen_page, 'pc_gen_curr' );

        // Pages Section
        add_settings_section( 'pc_gen_pages', __( 'Pages & Permalinks', 'pascommerce' ), null, $gen_page );
        add_settings_field( 'pc_product_slug', __( 'Product URL Slug', 'pascommerce' ), function() { echo '<input type="text" name="pc_product_slug" value="'.esc_attr(get_option('pc_product_slug', 'product')).'" class="regular-text"><p class="description">Save Permalinks after changing.</p>'; }, $gen_page, 'pc_gen_pages' );
        $pages = ['pc_page_shop'=>'Shop', 'pc_page_cart'=>'Cart', 'pc_page_checkout'=>'Checkout', 'pc_page_account'=>'Account'];
        foreach($pages as $k=>$l) {
            add_settings_field( $k, __($l.' Page', 'pascommerce'), function() use ($k){ wp_dropdown_pages(['name'=>$k, 'selected'=>get_option($k), 'show_option_none'=>'-- Select --']); }, $gen_page, 'pc_gen_pages' );
        }

        // =================================================================
        // 2. DISPLAY TAB (GRID & LAYOUT) - Custom Badges Dihapus
        // =================================================================
        $disp_group = 'pc_settings_group_display';
        $disp_page  = 'pc_settings_display';

        $disp_fields = [
            'pc_img_size_main', 
            'pc_desc_layout', 
            'pc_grid_img_size', 
            'pc_grid_elements'
            // pc_custom_badges_config, pc_badge_pos, pc_enable_badges DIHAPUS
        ];
        foreach($disp_fields as $f) register_setting( $disp_group, $f );

        // --- Single Product Settings ---
        add_settings_section( 'pc_disp_single', __( 'Single Product Layout', 'pascommerce' ), null, $disp_page );
        add_settings_field( 'pc_img_size_main', __( 'Main Gallery Size', 'pascommerce' ), function() {
            $val = get_option('pc_img_size_main', 'medium');
            echo '<select name="pc_img_size_main">
                <option value="small" '.selected($val,'small',false).'>Small Layout</option>
                <option value="medium" '.selected($val,'medium',false).'>Medium Layout</option>
                <option value="large" '.selected($val,'large',false).'>Large Layout</option>
            </select>';
            echo '<p class="description">Controls the layout width of the gallery column on product page.</p>';
        }, $disp_page, 'pc_disp_single' );
        add_settings_field( 'pc_desc_layout', __( 'Description Layout', 'pascommerce' ), function() {
            $val = get_option('pc_desc_layout', 'side');
            echo '<select name="pc_desc_layout"><option value="side" '.selected($val,'side',false).'>Side</option><option value="bottom" '.selected($val,'bottom',false).'>Bottom</option></select>';
        }, $disp_page, 'pc_disp_single' );

        // --- Grid Settings ---
        add_settings_section( 'pc_disp_grid', __( 'Shop Grid Settings', 'pascommerce' ), null, $disp_page );
        add_settings_field( 'pc_grid_img_size', __( 'Grid Image Resolution', 'pascommerce' ), function() {
            $val = get_option('pc_grid_img_size', 'medium');
            echo '<select name="pc_grid_img_size">
                <option value="thumbnail" '.selected($val,'thumbnail',false).'>Small (150x150)</option>
                <option value="medium" '.selected($val,'medium',false).'>Medium (300x300)</option>
                <option value="large" '.selected($val,'large',false).'>Large (1024x1024)</option>
                <option value="full" '.selected($val,'full',false).'>Full (Original)</option>
            </select>';
            echo '<p class="description">Choose image source size to load. Affects page speed.</p>';
        }, $disp_page, 'pc_disp_grid' );
        add_settings_field( 'pc_grid_elements', __( 'Grid Elements', 'pascommerce' ), function() {
            $opts = get_option('pc_grid_elements', ['title', 'price', 'cart']);
            if(!is_array($opts)) $opts=['title', 'price', 'cart'];
            $items = ['title'=>'Title', 'price'=>'Price', 'desc'=>'Short Desc', 'cart'=>'Add to Cart', 'category'=>'Category'];
            foreach($items as $k=>$l) echo '<label style="margin-right:15px;"><input type="checkbox" name="pc_grid_elements[]" value="'.$k.'" '.checked(in_array($k,$opts),true,false).'> '.$l.'</label><br>';
        }, $disp_page, 'pc_disp_grid' );
        
        // Bagian Custom Badges Settings DIHAPUS

        // =================================================================
        // 3. SHIPPING TAB (DEFAULT CORE SETTINGS)
        // =================================================================
        register_setting( 'pc_settings_group_shipping', 'pc_enable_shipping' );
        register_setting( 'pc_settings_group_shipping', 'pc_shipping_flat_rate' );
        add_settings_section( 'pc_ship_main', __( 'Shipping', 'pascommerce' ), null, 'pc_settings_shipping' );
        add_settings_field( 'pc_enable_shipping', __( 'Enable', 'pascommerce' ), function() { echo '<input type="checkbox" name="pc_enable_shipping" value="1" '.checked(1, get_option('pc_enable_shipping'), false).'>'; }, 'pc_settings_shipping', 'pc_ship_main' );
        add_settings_field( 'pc_shipping_flat_rate', __( 'Flat Rate', 'pascommerce' ), function(){ echo '<input type="number" name="pc_shipping_flat_rate" value="'.esc_attr(get_option('pc_shipping_flat_rate')).'" class="regular-text">'; }, 'pc_settings_shipping', 'pc_ship_main' );

        // =================================================================
        // 4. PAYMENT TAB (MANUAL TRANSFER REGISTRATION)
        // =================================================================
        register_setting( 'pc_settings_group_payment', 'pc_bank_details' );
        
        // NESTED HOOK: Manual Transfer Settings
        // BARIS INI TETAP ADA, memanggil fungsi yang didefinisikan di bawah
        add_action('pc_gateway_settings_manual_transfer', [__CLASS__, 'render_manual_transfer_logic']);
        
        // SECTIONS FOR MANUAL TRANSFER (Dibutuhkan oleh render_manual_transfer_settings)
        add_settings_section( 'pc_pay_main', __( 'Manual Bank Transfer', 'pascommerce' ), null, 'pc_settings_payment' );
        add_settings_field( 'pc_bank_details', __( 'Bank Info', 'pascommerce' ), function(){ echo '<textarea name="pc_bank_details" rows="5" class="large-text code">'.esc_textarea(get_option('pc_bank_details')).'"</textarea>'; }, 'pc_settings_payment', 'pc_pay_main' );
    }
    
    /**
     * RENDER MANUAL TRANSFER SETTINGS PAGE (LOGIC) - DEKLARASI INI DIPERTAHANKAN
     */
    public static function render_manual_transfer_logic() {
        ?>
        <form method="post" action="options.php">
            <?php settings_fields( 'pc_settings_group_payment' ); ?>
            <h3><?php _e('Manual Bank Transfer Settings', 'pascommerce'); ?></h3>
            <p><?php _e('These details will be displayed to the customer during checkout if this method is selected.', 'pascommerce'); ?></p>
            <?php do_settings_sections( 'pc_settings_payment' ); ?>
            <?php submit_button(); ?>
        </form>
        <?php
    }

    /**
     * RENDER ADDONS TAB
     */
    public static function render_addons_tab() {
        // ... (Kode Addons) ...
        $addons = get_transient( 'pc_addons_feed' );
        if ( false === $addons ) {
            $addons = array(
                array( 'name' => 'Pascommerce - REST API', 'desc' => 'Expose store data securely via REST API.', 'type' => 'free', 'slug' => 'pascommerce-api' ),
                array( 'name' => 'Midtrans Payment Gateway', 'desc' => 'Official integration for Midtrans.', 'type' => 'free', 'slug' => 'pascommerce-midtrans' ),
                array( 'name' => 'RajaOngkir Pro', 'desc' => 'Real-time shipping calculation.', 'type' => 'premium', 'url' => 'https://pasdigi.com' )
            );
            set_transient( 'pc_addons_feed', $addons, 12 * HOUR_IN_SECONDS );
        }

        ?>
        <div class="pc-addons-grid" style="display:grid; grid-template-columns:repeat(auto-fill, minmax(280px, 1fr)); gap:20px; margin-top:20px;">
            <?php foreach ( $addons as $addon ) : ?>
                <div class="pc-addon-card" style="background:#fff; border:1px solid #ccd0d4; padding:20px; border-radius:4px;">
                    <h3 style="margin-top:0;"><?php echo esc_html( $addon['name'] ); ?></h3>
                    <p><?php echo esc_html( $addon['desc'] ); ?></p>
                    <div style="margin-top:15px; text-align:right;">
                        <?php if ( $addon['type'] == 'free' ): ?>
                              <a href="#" class="button disabled"><?php _e('Coming Soon', 'pascommerce'); ?></a>
                        <?php else: ?>
                            <a href="<?php echo esc_url( $addon['url'] ?? '#' ); ?>" target="_blank" class="button"><?php _e('Buy Premium', 'pascommerce'); ?></a>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        <?php
    }
}

// REGISTER MANUAL TRANSFER DENGAN BENAR (OUTSIDE CLASS)
add_filter('pc_setting_gateways_list', function($gateways) {
    $gateways['manual_transfer'] = array(
        'title' => __('Manual Bank Transfer', 'pascommerce'),
        'description' => __('Accept payments by checking bank statement.', 'pascommerce'),
        'manage_link' => admin_url('admin.php?page=pc_settings&tab=payment&gateway=manual_transfer')
    );
    return $gateways;
});

// HOOK UNTUK RENDER MANUAL TRANSFER
add_action('pc_gateway_settings_manual_transfer', [PC_Settings::class, 'render_manual_transfer_logic']);