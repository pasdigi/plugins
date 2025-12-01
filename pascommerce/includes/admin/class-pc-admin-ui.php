<?php
defined( 'ABSPATH' ) || exit;

class PC_Admin_UI {

    public function __construct() {
        // 1. Daftarkan Menu Sidebar
        add_action( 'admin_menu', array( $this, 'register_menu' ) );
        
        // 2. Urutkan Menu (Prioritas 999 agar dijalankan paling akhir)
        add_action( 'admin_menu', array( $this, 'reorder_menu' ), 999 );

        // 3. Fix Menu Parent saat di Taxonomy
        add_filter( 'parent_file', array( $this, 'highlight_taxonomy_parent' ) );
        
        // 4. Tambahkan Link 'Visit Store' di Admin Bar (Toolbar Atas)
        add_action( 'admin_bar_menu', array( $this, 'add_toolbar_link' ), 35 );

        // 5. Hook untuk Redirection Categories (DIPERBAIKI: Menggunakan admin_init)
        add_action( 'admin_init', array( $this, 'redirect_to_categories' ) );

        PC_Settings::init(); 
    }

    /**
     * FUNGSI REDIRECTION (Diperbaiki: Menggunakan admin_init untuk redirect aman)
     */
    public function redirect_to_categories() {
        // Cek: Apakah kita di admin, dan apakah page slug-nya adalah slug fiksi kita?
        if ( is_admin() && isset( $_GET['page'] ) && $_GET['page'] === 'pc_categories_redirect' ) {
            // Pastikan pengguna memiliki izin sebelum redirect
            if ( current_user_can( 'manage_categories' ) ) {
                // Arahkan ke URL Taxonomy yang benar
                $redirect_url = admin_url( 'edit-tags.php?taxonomy=pc_category&post_type=pc_product' );
                wp_redirect( $redirect_url );
                exit;
            } else {
                 // Tampilkan pesan error jika izin tidak ada (mencegah WSOD)
                 wp_die( __('Sorry, you are not allowed to access this page.', 'pascommerce') );
            }
        }
    }

    /**
     * Tambahkan Link 'Visit Store' ke Toolbar Admin
     */
    public function add_toolbar_link( $wp_admin_bar ) {
        if ( ! current_user_can( 'manage_options' ) ) return;

        $shop_page_id = get_option( 'pc_page_shop' );
        $store_url = $shop_page_id ? get_permalink( $shop_page_id ) : home_url();

        $wp_admin_bar->add_node( array(
            'parent' => 'site-name',
            'id'     => 'pc-visit-store',
            'title'  => __( 'Visit Store', 'pascommerce' ),
            'href'   => $store_url,
            'meta'   => array(
                'target' => '_blank',
                'title'  => __( 'Visit your store frontend', 'pascommerce' ),
            ),
        ));
    }

    public function register_menu() {
        // Menu UTAMA
        add_menu_page(
            __( 'Pascommerce', 'pascommerce' ), 
            __( 'Pascommerce', 'pascommerce' ), 
            'manage_options',                  
            'pascommerce',                     
            array( $this, 'render_dashboard_page' ), 
            'dashicons-store',                 
            56                                 
        );

        // Submenu Dashboard
        add_submenu_page(
            'pascommerce',
            __( 'Dashboard', 'pascommerce' ),
            __( 'Dashboard', 'pascommerce' ),
            'manage_options',
            'pascommerce',
            array( $this, 'render_dashboard_page' )
        );

        // Submenu Categories (MENGGUNAKAN SLUG FIKSI)
        add_submenu_page(
            'pascommerce',
            __( 'Categories', 'pascommerce' ),
            __( 'Categories', 'pascommerce' ),
            'manage_categories',
            'pc_categories_redirect', // SLUG FIKSI BARU
            '__return_null' 
        );

        // Submenu Settings
        add_submenu_page(
            'pascommerce',                     
            __( 'Settings', 'pascommerce' ),
            __( 'Settings', 'pascommerce' ),
            'manage_options',
            'pc_settings',
            array( 'PC_Settings', 'render_page' )
        );
    }
    
    // ... (Highlight, Reorder, dan Render Dashboard tetap sama)

    public function highlight_taxonomy_parent( $parent_file ) {
        global $current_screen;
        if ( isset( $current_screen->taxonomy ) && $current_screen->taxonomy === 'pc_category' ) {
            return 'pascommerce';
        }
        return $parent_file;
    }

    public function reorder_menu() {
        global $submenu;
        $parent_slug = 'pascommerce';

        if ( ! isset( $submenu[ $parent_slug ] ) ) return;

        $current_menu = $submenu[ $parent_slug ];
        $sorted_menu = array();

        // FILTERABLE: Ubah order rules untuk menggunakan slug fiksi Categories
        $order_rules = apply_filters( 'pc_menu_order', array(
            'pascommerce',
            'edit.php?post_type=pc_product',
            'post-new.php?post_type=pc_product',
            'pc_categories_redirect', // Gunakan slug fiksi
            'edit.php?post_type=pc_order',
            'pc_settings'
        ));

        foreach ( $order_rules as $rule ) {
            foreach ( $current_menu as $index => $item ) {
                if ( $item[2] === $rule ) {
                    $sorted_menu[] = $item;
                    unset( $current_menu[$index] );
                    break;
                }
            }
        }

        if ( ! empty( $current_menu ) ) {
            foreach ( $current_menu as $item ) {
                $sorted_menu[] = $item;
            }
        }

        $submenu[ $parent_slug ] = $sorted_menu;
    }

    public function render_dashboard_page() {
        // ... (Isi render_dashboard_page() yang lengkap)
        global $wpdb;

        // 1. Filter Input
        $filter_period = isset($_GET['period']) ? sanitize_text_field($_GET['period']) : '7days';
        
        // 2. Hitung Statistik Dasar
        $today = getdate();
        
        $count_orders = function($args) {
            $q = new WP_Query(array_merge($args, ['post_type'=>'pc_order', 'post_status'=>'any', 'fields'=>'ids', 'posts_per_page'=>-1]));
            return $q->found_posts;
        };

        // Raw Stats
        $raw_today = $count_orders(['date_query' => [['year'=>$today['year'], 'month'=>$today['mon'], 'day'=>$today['mday']]]]);
        $raw_month = $count_orders(['date_query' => [['year'=>$today['year'], 'month'=>$today['mon']]]]);
        $raw_pending = $count_orders(['meta_query' => [['key'=>'_pc_status', 'value'=>'pending']]]);
        $raw_revenue = $wpdb->get_var("SELECT SUM(meta_value) FROM $wpdb->postmeta WHERE meta_key = '_pc_total'");
        $currency = class_exists('PC_Data') ? PC_Data::get_currency_symbol() : '$';

        // FILTERABLE STATS DATA: Ini kuncinya! Plugin lain bisa menambah array ini.
        $stats_cards = apply_filters( 'pc_dashboard_stats', array(
            'today' => array(
                'label' => 'Orders Today',
                'value' => number_format($raw_today),
                'color' => '#3b82f6', // Blue
            ),
            'month' => array(
                'label' => 'Orders This Month',
                'value' => number_format($raw_month),
                'color' => '#10b981', // Green
            ),
            'pending' => array(
                'label' => 'Pending Payment',
                'value' => number_format($raw_pending),
                'color' => '#f59e0b', // Orange
            ),
            'revenue' => array(
                'label' => 'Total Revenue',
                'value' => $currency . ' ' . number_format((float)$raw_revenue),
                'color' => '#6366f1', // Indigo
            ),
        ));

        // 3. Persiapkan Data Grafik (Chart)
        $chart_labels = [];
        $chart_values = [];
        $chart_title = '';
        
        // Logic grafik tetap sama, tapi kita bungkus agar rapi
        if($filter_period === 'month') {
            $chart_title = 'Orders This Month (' . date('M Y') . ')';
            $days_in_month = cal_days_in_month(CAL_GREGORIAN, $today['mon'], $today['year']);
            $sql = "SELECT DATE(post_date) as date, COUNT(*) as count FROM $wpdb->posts WHERE post_type = 'pc_order' AND post_status IN ('publish', 'draft', 'future') AND MONTH(post_date) = %d AND YEAR(post_date) = %d GROUP BY DATE(post_date)";
            $results = $wpdb->get_results($wpdb->prepare($sql, $today['mon'], $today['year']), OBJECT_K);
            for($d=1; $d<=$days_in_month; $d++) {
                $date_str = sprintf('%04d-%02d-%02d', $today['year'], $today['mon'], $d);
                $chart_labels[] = $d;
                $chart_values[] = isset($results[$date_str]) ? (int)$results[$date_str]->count : 0;
            }
        } else {
            $chart_title = 'Orders Last 7 Days';
            $sql = "SELECT DATE(post_date) as date, COUNT(*) as count FROM $wpdb->posts WHERE post_type = 'pc_order' AND post_status IN ('publish', 'draft', 'future') AND post_date >= DATE_SUB(NOW(), INTERVAL 7 DAY) GROUP BY DATE(post_date)";
            $results = $wpdb->get_results($sql, OBJECT_K);
            for ($i = 6; $i >= 0; $i--) {
                $ts = strtotime("-$i days");
                $date_str = date('Y-m-d', $ts);
                $chart_labels[] = date('d M', $ts);
                $chart_values[] = isset($results[$date_str]) ? (int)$results[$date_str]->count : 0;
            }
        }

        ?>
        <div class="wrap pc-dashboard">
            <h1 style="margin-bottom:20px;"><?php _e('Store Overview', 'pascommerce'); ?></h1>
            
            <?php 
            do_action( 'pc_dashboard_top' ); 
            ?>

            <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(220px, 1fr)); gap:20px; margin-bottom:30px;">
                <?php foreach( $stats_cards as $key => $stat ): ?>
                    <div style="background:#fff; padding:20px; border-radius:4px; border-left:4px solid <?php echo esc_attr($stat['color']); ?>; box-shadow:0 1px 3px rgba(0,0,0,0.1);">
                        <h3 style="margin:0 0 5px; color:#64748b; font-size:12px; text-transform:uppercase; letter-spacing:0.5px;"><?php echo esc_html($stat['label']); ?></h3>
                        <div style="font-size:32px; font-weight:bold; color:#0f172a;"><?php echo esc_html($stat['value']); ?></div>
                    </div>
                <?php endforeach; ?>
            </div>
            
            <?php 
            do_action( 'pc_dashboard_middle' ); 
            ?>

            <div style="background:#fff; padding:25px; border-radius:4px; box-shadow:0 1px 3px rgba(0,0,0,0.1); margin-bottom:30px;">
                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px;">
                    <h2 style="margin:0; font-size:18px; font-weight:600; color:#334155;"><?php echo $chart_title; ?></h2>
                    
                    <form method="get" style="display:inline-block;">
                        <input type="hidden" name="page" value="pascommerce">
                        <select name="period" onchange="this.form.submit()" style="font-size:13px; padding:5px 10px; border-color:#cbd5e1; border-radius:4px;">
                            <option value="7days" <?php selected($filter_period, '7days'); ?>>Last 7 Days</option>
                            <option value="month" <?php selected($filter_period, 'month'); ?>>This Month</option>
                        </select>
                    </form>
                </div>

                <div style="height:350px; width:100%; position:relative;">
                    <canvas id="pcOrderChart"></canvas>
                </div>
            </div>

            <?php 
            do_action( 'pc_dashboard_bottom' ); 
            ?>

            <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
            <script>
            document.addEventListener('DOMContentLoaded', function() {
                const ctx = document.getElementById('pcOrderChart').getContext('2d');
                const gradient = ctx.createLinearGradient(0, 0, 0, 400);
                gradient.addColorStop(0, 'rgba(59, 130, 246, 0.2)');
                gradient.addColorStop(1, 'rgba(59, 130, 246, 0)');

                new Chart(ctx, {
                    type: 'line',
                    data: {
                        labels: <?php echo json_encode($chart_labels); ?>,
                        datasets: [{
                            label: 'Orders',
                            data: <?php echo json_encode($chart_values); ?>,
                            borderColor: '#3b82f6',
                            backgroundColor: gradient,
                            borderWidth: 2,
                            pointBackgroundColor: '#ffffff',
                            pointBorderColor: '#3b82f6',
                            pointRadius: 4,
                            pointHoverRadius: 6,
                            fill: true,
                            tension: 0.4
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: { legend: { display: false }, tooltip: { backgroundColor: '#1e293b', padding: 10, cornerRadius: 4, displayColors: false } },
                        scales: { y: { beginAtZero: true, ticks: { stepSize: 1, precision: 0 }, grid: { color: '#f1f5f9' }, border: { display: false } }, x: { grid: { display: false }, border: { display: false } } }
                    }
                });
            });
            </script>
        </div>
        <?php
    }
}

new PC_Admin_UI();