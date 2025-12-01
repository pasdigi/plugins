<?php
defined( 'ABSPATH' ) || exit;

class PC_CPT {

    public static function register_post_types() {
        
        // --- 0. REGISTER TAXONOMY (KATEGORI) ---
        $labels_cat = array(
            'name'              => _x( 'Categories', 'taxonomy general name', 'pascommerce' ),
            'singular_name'     => _x( 'Category', 'taxonomy singular name', 'pascommerce' ),
            'menu_name'         => __( 'Categories', 'pascommerce' ),
        );

        register_taxonomy( 'pc_category', 'pc_product', array(
            'hierarchical'      => true,
            'labels'            => $labels_cat,
            'show_ui'           => true,
            'show_admin_column' => true,
            'query_var'         => true,
            'rewrite'           => array( 'slug' => 'product-category' ),
            'show_in_rest'      => true,
        ));


        // --- 1. PRODUCT POST TYPE ---
        $slug = get_option( 'pc_product_slug', 'product' );
        
        $product_labels = array( 'name' => _x( 'Products', 'Post Type General Name', 'pascommerce' ), 'singular_name' => _x( 'Product', 'Post Type Singular Name', 'pascommerce' ), 'menu_name' => __( 'Products', 'pascommerce' ), 'add_new_item' => __( 'Add New Product', 'pascommerce' ));

        register_post_type( 'pc_product', array(
            'labels'                => $product_labels,
            'public'                => true,
            'has_archive'           => true,
            'supports'              => array( 'title', 'editor', 'thumbnail', 'excerpt', 'page-attributes' ),
            'rewrite'               => array( 'slug' => $slug ),
            'show_in_menu'          => 'pascommerce',
            'show_ui'               => true,
            'show_in_rest'          => true,
            'taxonomies'            => array( 'pc_category' ), 
            'hierarchical'          => true, 
        ));

        // --- 2. ORDER POST TYPE ---
        $order_labels = array( 'name' => _x( 'Orders', 'Post Type General Name', 'pascommerce' ), 'singular_name' => _x( 'Order', 'Post Type Singular Name', 'pascommerce' ), 'menu_name' => __( 'Orders', 'pascommerce' ));

        register_post_type( 'pc_order', array(
            'labels'                => $order_labels,
            'public'                => false, 
            'show_ui'               => true, 
            'show_in_menu'          => 'pascommerce',
            'supports'              => array( 'title' ),
            'capabilities'          => array( 'create_posts' => false ),
            'map_meta_cap'          => true,
        ));

        // Hooks for Meta Boxes (Product & Order)
        add_action( 'add_meta_boxes', array( __CLASS__, 'add_meta_boxes' ) );
        add_action( 'save_post', array( __CLASS__, 'save_meta_boxes' ) );
        
        // Hooks for Order List Customization (THE FIX)
        add_filter( 'manage_pc_order_posts_columns', array( __CLASS__, 'add_order_columns' ) );
        add_action( 'manage_pc_order_posts_custom_column', array( __CLASS__, 'render_order_columns' ), 10, 2 );
    }
    
    // --- ORDER LIST CUSTOMIZATION ---
    public static function add_order_columns( $columns ) {
        // Hapus kolom Title default (karena kita pakai ID)
        unset( $columns['title'] ); 
        
        // Define kolom baru
        $new_columns = array(
            'cb'          => '<input type="checkbox" />',
            'order_id'    => __( 'Order ID', 'pascommerce' ),
            'order_status'=> __( 'Status', 'pascommerce' ),
            'order_total' => __( 'Total', 'pascommerce' ),
            'customer'    => __( 'Customer', 'pascommerce' ),
            'order_date'  => __( 'Date', 'pascommerce' ),
        );

        // Gabungkan dengan kolom Date bawaan
        return array_merge( $columns, $new_columns );
    }

    public static function render_order_columns( $column, $post_id ) {
        // Ambil Data Customer & Total
        $customer_data = get_post_meta( $post_id, '_pc_customer', true );
        $total         = get_post_meta( $post_id, '_pc_total', true );
        $status        = get_post_meta( $post_id, '_pc_status', true );
        $symbol        = class_exists('PC_Data') ? PC_Data::get_currency_symbol() : '$';
        
        switch ( $column ) {
            case 'order_id':
                // Tampilkan ID order dengan link ke halaman edit
                $edit_url = get_edit_post_link( $post_id );
                echo '<a href="' . esc_url($edit_url) . '"><strong>#' . $post_id . '</strong></a>';
                break;

            case 'order_status':
                $status_label = strtoupper($status ?: 'pending');
                $color = 'orange';
                if ($status === 'processing' || $status === 'completed') $color = 'green';
                if ($status === 'failed' || $status === 'cancelled') $color = 'red';
                
                echo '<span class="pc-status-badge" style="background-color:'.$color.'; color:white; padding: 4px 8px; border-radius:4px; font-size:11px; font-weight:bold;">' . esc_html($status_label) . '</span>';
                break;

            case 'order_total':
                echo '<strong>' . $symbol . ' ' . number_format((float)$total) . '</strong>';
                break;
                
            case 'customer':
                // Tampilkan Nama dan Email
                $name = $customer_data['name'] ?? '-';
                $email = $customer_data['email'] ?? '-';
                echo '<strong>' . esc_html($name) . '</strong><br><small>' . esc_html($email) . '</small>';
                break;
                
            case 'order_date':
                // Kolom ini tidak perlu diisi karena sudah dihandle oleh WordPress (kolom 'date')
                break;
        }
    }
    
    // --- PRODUCT META BOXES (TETAP SAMA) ---
    public static function add_meta_boxes() {
        // Product Data Meta Box
        add_meta_box(
            'pc_product_data',
            __( 'Product Data', 'pascommerce' ),
            array( __CLASS__, 'render_product_meta_box' ),
            'pc_product',
            'normal', 
            'high'
        );
        
        // Order Details Meta Box (New)
        add_meta_box(
            'pc_order_details',
            __( 'Order Details', 'pascommerce' ),
            array( __CLASS__, 'render_order_details_meta_box' ),
            'pc_order',
            'normal', 
            'high'
        );
    }
    
    // --- ORDER DETAILS VIEW (Fix Halaman Edit Order) ---
    public static function render_order_details_meta_box( $post ) {
        $order_id = $post->ID;
        $customer_data = get_post_meta( $order_id, '_pc_customer', true );
        $items         = get_post_meta( $order_id, '_pc_items', true );
        $total         = get_post_meta( $order_id, '_pc_total', true );
        $method        = get_post_meta( $order_id, '_pc_payment_method', true );
        $status        = get_post_meta( $order_id, '_pc_status', true );
        $order_notes   = get_post_meta( $order_id, '_pc_history', true ) ?: [];
        $symbol        = class_exists('PC_Data') ? PC_Data::get_currency_symbol() : '$';
        
        $email = $customer_data['email'] ?? '-';
        $phone = $customer_data['phone'] ?? '-';
        $address = $customer_data['address'] ?? '-';
        
        ?>
        <div style="display:grid; grid-template-columns:1fr 1fr; gap:20px; margin-bottom:20px; background:#f8fafc; padding:20px; border-radius:6px;">
            <div>
                <h4><?php _e('Customer Details', 'pascommerce'); ?></h4>
                <p><strong><?php _e('Name', 'pascommerce'); ?>:</strong> <?php echo esc_html($customer_data['name'] ?? '-'); ?></p>
                <p><strong><?php _e('Email', 'pascommerce'); ?>:</strong> <?php echo esc_html($email); ?></p>
                <p><strong><?php _e('Phone', 'pascommerce'); ?>:</strong> <?php echo esc_html($phone); ?></p>
                <p><strong><?php _e('Address', 'pascommerce'); ?>:</strong> <?php echo nl2br(esc_html($address)); ?></p>
            </div>
            <div>
                <h4><?php _e('Order Summary', 'pascommerce'); ?></h4>
                <p><strong><?php _e('Status', 'pascommerce'); ?>:</strong> <span style="background:lightgray; padding: 4px 8px; border-radius:4px; font-weight:bold;"><?php echo strtoupper($status); ?></span></p>
                <p><strong><?php _e('Payment Method', 'pascommerce'); ?>:</strong> <?php echo esc_html($method); ?></p>
                <p style="font-size:1.2em;"><strong><?php _e('Total Amount', 'pascommerce'); ?>:</strong> <?php echo $symbol . ' ' . number_format((float)$total); ?></p>
                
                <form method="post" action="">
                    <input type="hidden" name="pc_order_status_nonce" value="<?php echo wp_create_nonce('pc_change_status'); ?>">
                    <select name="new_status">
                        <option value="pending" <?php selected($status, 'pending'); ?>>Pending Payment</option>
                        <option value="processing" <?php selected($status, 'processing'); ?>>Processing</option>
                        <option value="completed" <?php selected($status, 'completed'); ?>>Completed</option>
                        <option value="cancelled" <?php selected($status, 'cancelled'); ?>>Cancelled</option>
                    </select>
                    <button type="submit" class="button button-primary"><?php _e('Update Status', 'pascommerce'); ?></button>
                    <input type="hidden" name="pc_update_order_id" value="<?php echo $order_id; ?>">
                </form>
            </div>
        </div>
        
        <h4><?php _e('Items Ordered', 'pascommerce'); ?></h4>
        <table class="widefat fixed" cellspacing="0">
            <thead><tr><th><?php _e('Product', 'pascommerce'); ?></th><th style="width:100px;"><?php _e('Price', 'pascommerce'); ?></th><th style="width:50px;"><?php _e('Qty', 'pascommerce'); ?></th><th style="width:100px;"><?php _e('Subtotal', 'pascommerce'); ?></th></tr></thead>
            <tbody>
            <?php if ( is_array($items) ): foreach ( $items as $item ): ?>
                <tr>
                    <td><?php echo esc_html($item['name'] ?? 'N/A'); ?> (#<?php echo esc_html($item['product_id'] ?? 0); ?>)</td>
                    <td><?php echo $symbol . ' ' . number_format($item['price'] ?? 0); ?></td>
                    <td><?php echo esc_html($item['qty'] ?? 1); ?></td>
                    <td><?php echo $symbol . ' ' . number_format($item['subtotal'] ?? 0); ?></td>
                </tr>
            <?php endforeach; endif; ?>
            </tbody>
        </table>
        
        <h4 style="margin-top:20px;"><?php _e('Order History / Notes', 'pascommerce'); ?></h4>
        <ul style="padding-left: 20px;">
            <?php if ( !empty($order_notes) ): foreach ( $order_notes as $note ): ?>
                <li><?php echo esc_html($note); ?></li>
            <?php endforeach; else: ?>
                <li><?php _e('No history recorded.', 'pascommerce'); ?></li>
            <?php endif; ?>
        </ul>
        <?php
    }
    
    // --- PRODUCT META BOX (MODULAR HOOKS) ---
    public static function render_product_meta_box( $post ) {
        wp_nonce_field( 'pc_save_product_data', 'pc_product_nonce' );
        
        $type     = get_post_meta( $post->ID, '_pc_type', true ) ?: 'simple';
        $price    = get_post_meta( $post->ID, '_pc_price', true );
        $sku      = get_post_meta( $post->ID, '_pc_sku', true );
        $stock    = get_post_meta( $post->ID, '_pc_stock', true );
        $gallery  = get_post_meta( $post->ID, '_pc_gallery', true );
        $variants = get_post_meta( $post->ID, '_pc_variants', true );
        
        // Logika Badges telah dihapus (modular)
        
        ?>
        <div class="pc-meta-wrapper">
            <ul class="pc-meta-tabs">
                <li class="active" data-tab="general"><span class="dashicons dashicons-tag"></span> General</li>
                <li data-tab="inventory"><span class="dashicons dashicons-archive"></span> Inventory</li>
                <li data-tab="variants" id="tab-trigger-variants"><span class="dashicons dashicons-list-view"></span> Variations</li>
                <li data-tab="gallery"><span class="dashicons dashicons-images-alt2"></span> Gallery</li>
                <?php do_action( 'pc_product_meta_box_tabs' ); // Hook untuk menambahkan tab addon ?>
            </ul>

            <div class="pc-meta-content">
                <div id="pc-tab-general" class="pc-tab-pane active">
                    <div class="pc-form-group">
                        <label><?php _e('Product Type', 'pascommerce'); ?></label>
                        <select name="pc_type" id="pc_type_selector" class="widefat">
                            <option value="simple" <?php selected($type, 'simple'); ?>>Simple Product</option>
                            <option value="variable" <?php selected($type, 'variable'); ?>>Variable Product (Variants)</option>
                        </select>
                    </div>
                    <div class="pc-form-group pc-pricing-group">
                        <label><?php _e('Regular Price (Rp)', 'pascommerce'); ?></label>
                        <input type="number" name="pc_price" value="<?php echo esc_attr($price); ?>" class="widefat">
                    </div>
                </div>

                <div id="pc-tab-inventory" class="pc-tab-pane">
                    <div class="pc-form-group"><label><?php _e('SKU', 'pascommerce'); ?></label><input type="text" name="pc_sku" value="<?php echo esc_attr($sku); ?>" class="widefat"></div>
                    <div class="pc-form-group"><label><?php _e('Stock Quantity', 'pascommerce'); ?></label><input type="number" name="pc_stock" value="<?php echo esc_attr($stock); ?>" class="widefat" placeholder="Unlimited"></div>
                </div>

                <div id="pc-tab-variants" class="pc-tab-pane">
                    <div id="pc-variants-warning" style="display:<?php echo ($type === 'simple') ? 'block' : 'none'; ?>;"><p><?php _e('Enable "Variable Product" in General tab first.', 'pascommerce'); ?></p></div>
                    <div id="pc-variants-wrapper" style="display:<?php echo ($type === 'variable') ? 'block' : 'none'; ?>;">
                        <div id="pc-variants-list">
                            <?php if( !empty($variants) && is_array($variants) ): foreach($variants as $i => $var): ?>
                                <div class="pc-variant-item">
                                    <div class="pc-var-header"><strong><?php _e('Variation #', 'pascommerce'); echo $i+1; ?></strong><button type="button" class="button pc-remove-var">&times;</button></div>
                                    <div class="pc-var-body"><label><?php _e('Name', 'pascommerce'); ?></label><input type="text" name="pc_vars[<?php echo $i; ?>][name]" value="<?php echo esc_attr($var['name']); ?>" class="widefat"><label><?php _e('Price (Rp)', 'pascommerce'); ?></label><input type="number" name="pc_vars[<?php echo $i; ?>][price]" value="<?php echo esc_attr($var['price']); ?>" class="widefat"><label><?php _e('Stock', 'pascommerce'); ?></label><input type="number" name="pc_vars[<?php echo $i; ?>][stock]" value="<?php echo esc_attr($var['stock']); ?>" class="widefat"></div>
                                </div>
                            <?php endforeach; endif; ?>
                        </div>
                        <button type="button" class="button button-primary" id="pc-add-variant"><?php _e('Add Variation', 'pascommerce'); ?></button>
                    </div>
                </div>

                <div id="pc-tab-gallery" class="pc-tab-pane">
                    <div class="pc-gallery-preview">
                        <?php if( !empty($gallery) && is_array($gallery) ) {
                            foreach($gallery as $img_id) {
                                echo '<div class="pc-gal-item" data-id="'.$img_id.'"><img src="'.wp_get_attachment_thumb_url($img_id).'"><span class="pc-gal-remove">&times;</span><input type="hidden" name="pc_gallery[]" value="'.$img_id.'"></div>';
                            }
                        } ?>
                    </div>
                    <button type="button" class="button" id="pc-add-gallery"><?php _e('Add Product Images', 'pascommerce'); ?></button>
                </div>
                
                <?php do_action( 'pc_product_meta_box_content', $post ); // Hook untuk menambahkan konten tab addon ?>

            </div>
        </div>
        <?php
    }

    public static function save_meta_boxes( $post_id ) {
        if ( ! isset( $_POST['pc_product_nonce'] ) || ! wp_verify_nonce( $_POST['pc_product_nonce'], 'pc_save_product_data' ) ) {
            // Check for order status update
            if ( isset($_POST['new_status']) && isset($_POST['pc_update_order_id']) && wp_verify_nonce($_POST['pc_order_status_nonce'], 'pc_change_status') ) {
                // Handle Order Status Update (Called from Order Details Meta Box)
                $new_status = sanitize_text_field( $_POST['new_status'] );
                if ( class_exists('PC_Order') ) {
                    PC_Order::update_status( $post_id, $new_status, 'Manually updated by Admin.' );
                }
                return;
            }
            return;
        }

        if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) return;

        // Save Product Meta
        if(isset($_POST['pc_type'])) update_post_meta( $post_id, '_pc_type', sanitize_text_field( $_POST['pc_type'] ) );
        if(isset($_POST['pc_price'])) update_post_meta( $post_id, '_pc_price', sanitize_text_field( $_POST['pc_price'] ) );
        if(isset($_POST['pc_sku'])) update_post_meta( $post_id, '_pc_sku', sanitize_text_field( $_POST['pc_sku'] ) );
        if(isset($_POST['pc_stock'])) update_post_meta( $post_id, '_pc_stock', sanitize_text_field( $_POST['pc_stock'] ) );

        // Save Gallery
        if(isset($_POST['pc_gallery'])) {
            $gallery = array_map( 'intval', $_POST['pc_gallery'] );
            update_post_meta( $post_id, '_pc_gallery', $gallery );
        } else { delete_post_meta( $post_id, '_pc_gallery' ); }

        // Save Variants
        if(isset($_POST['pc_vars'])) {
            $variants = array();
            foreach($_POST['pc_vars'] as $var) {
                if(!empty($var['name'])) {
                    $variants[] = array(
                        'name' => sanitize_text_field($var['name']),
                        'price' => intval($var['price']),
                        'stock' => intval($var['stock']),
                    );
                }
            }
            update_post_meta( $post_id, '_pc_variants', $variants );
        } else { delete_post_meta( $post_id, '_pc_variants' ); }

        // Logika Save Badges Dihapus (sekarang menjadi tanggung jawab Addon untuk menggunakan hook save_post biasa)
    }
}