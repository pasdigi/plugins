<?php
defined( 'ABSPATH' ) || exit;

class PC_Gateway_PayPal extends PC_Payment_Module {

    public function __construct() {
        $this->method_id = 'paypal';
        $this->title     = 'PayPal Standard';
        
        parent::__construct();
        
        // Settings Hook
        add_filter( 'pc_settings_tabs', array($this, 'add_settings_tab') );
        add_action( 'pc_settings_content_paypal', array($this, 'render_settings') );
        add_action( 'admin_init', array($this, 'register_settings') );
    }

    // --- LOGIC PEMBAYARAN ---
    public function process_payment( $order_id, $amount, $customer_data ) {
        $sandbox = get_option('pc_paypal_sandbox');
        $email   = get_option('pc_paypal_email');
        
        if(!$email) return; 

        $url = $sandbox ? 'https://www.sandbox.paypal.com/cgi-bin/webscr' : 'https://www.paypal.com/cgi-bin/webscr';
        
        $return_url = home_url('/?order_received=' . $order_id); 
        $notify_url = home_url('/?pc_listener=paypal_ipn');

        // Parameter PayPal Standard
        $query = array(
            'cmd'           => '_xclick',
            'business'      => $email,
            'item_name'     => get_bloginfo('name') . ' - Order #' . $order_id,
            'amount'        => $amount,
            'currency_code' => get_option('pc_currency', 'USD'), // Mengikuti setting toko
            'return'        => $return_url,
            'notify_url'    => $notify_url,
            'custom'        => $order_id,
            'no_shipping'   => 1, // Kita handle shipping sendiri
            'charset'       => 'utf-8'
        );

        $redirect_url = $url . '?' . http_build_query($query);
        
        update_post_meta($order_id, '_pc_payment_redirect', $redirect_url);
    }

    // --- SETTINGS ADMIN ---
    public function add_settings_tab($tabs) {
        $tabs['paypal'] = 'PayPal';
        return $tabs;
    }

    public function register_settings() {
        register_setting( 'pc_settings_group_paypal', 'pc_paypal_email' );
        register_setting( 'pc_settings_group_paypal', 'pc_paypal_sandbox' );
    }

    public function render_settings() {
        $ipn_url = home_url('/?pc_listener=paypal_ipn');
        ?>
        <h3>PayPal Configuration</h3>
        <p>Accept payments via PayPal Standard (Redirection).</p>
        
        <table class="form-table">
            <tr>
                <th>PayPal Business Email</th>
                <td>
                    <input type="email" name="pc_paypal_email" value="<?php echo esc_attr(get_option('pc_paypal_email')); ?>" class="regular-text" placeholder="merchant@example.com">
                    <p class="description">Enter your PayPal Business Email (or Sandbox Business Email for testing).</p>
                </td>
            </tr>
            
            <tr>
                <th>Notification URL (IPN)</th>
                <td>
                    <input type="text" value="<?php echo esc_url($ipn_url); ?>" class="large-text code" readonly onclick="this.select();">
                    <p class="description">
                        <strong>Instruction:</strong><br>
                        1. Log in to your PayPal Business Dashboard.<br>
                        2. Go to <em>Account Settings &gt; Website payments &gt; Instant payment notifications</em>.<br>
                        3. Click <strong>Update</strong> and choose <strong>Choose IPN Settings</strong>.<br>
                        4. Paste the URL above into the <strong>Notification URL</strong> field.<br>
                        5. Select <strong>Receive IPN messages (Enabled)</strong> and save.
                    </p>
                </td>
            </tr>

            <tr>
                <th>Sandbox Mode</th>
                <td>
                    <label>
                        <input type="checkbox" name="pc_paypal_sandbox" value="1" <?php checked(1, get_option('pc_paypal_sandbox')); ?>> 
                        Enable Sandbox (Test Mode)
                    </label>
                    <p class="description">Enable this to test payments using <a href="https://developer.paypal.com" target="_blank">PayPal Sandbox</a> credentials.</p>
                </td>
            </tr>
        </table>
        <?php
    }
}

new PC_Gateway_PayPal();