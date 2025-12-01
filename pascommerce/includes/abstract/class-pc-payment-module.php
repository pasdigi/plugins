<?php
defined( 'ABSPATH' ) || exit;

/**
 * Abstract class for Payment Modules.
 * Third-party addons must extend this class.
 */
abstract class PC_Payment_Module {
    
    public $method_id;
    public $title;

    public function __construct() {
        add_filter( 'pc_available_payment_methods', array( $this, 'register_method' ) );
        add_action( 'pc_process_payment_' . $this->method_id, array( $this, 'process_payment' ), 10, 3 );
    }

    public function register_method( $methods ) {
        $methods[$this->method_id] = array(
            'label' => $this->title,
            'class' => $this
        );
        return $methods;
    }

    /**
     * Process the payment.
     * @param int $order_id
     * @param int $amount
     * @param array $customer_data
     */
    abstract public function process_payment( $order_id, $amount, $customer_data );
}