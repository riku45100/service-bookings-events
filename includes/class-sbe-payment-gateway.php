<?php
/**
 * Payment Gateway Integration for Service Bookings & Events
 * Supports Stripe and PayPal payment processing
 */

if (!defined('ABSPATH')) exit;

class SBE_Payment_Gateway {
    
    private static $instance = null;
    private $stripe_api_key;
    private $stripe_publishable_key;
    private $stripe_webhook_secret;
    private $paypal_client_id;
    private $paypal_client_secret;
    private $paypal_webhook_id;
    private $test_mode;
    private $currency;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        $this->init_settings();
        $this->init_hooks();
    }
    
    private function init_settings() {
        $this->test_mode = get_option('sbe_payment_test_mode', true);
        $this->currency = get_option('sbe_payment_currency', 'USD');
        
        if ($this->test_mode) {
            $this->stripe_api_key = get_option('sbe_stripe_test_secret_key', '');
            $this->stripe_publishable_key = get_option('sbe_stripe_test_publishable_key', '');
            $this->stripe_webhook_secret = get_option('sbe_stripe_test_webhook_secret', '');
            $this->paypal_client_id = get_option('sbe_paypal_test_client_id', '');
            $this->paypal_client_secret = get_option('sbe_paypal_test_client_secret', '');
        } else {
            $this->stripe_api_key = get_option('sbe_stripe_live_secret_key', '');
            $this->stripe_publishable_key = get_option('sbe_stripe_live_publishable_key', '');
            $this->stripe_webhook_secret = get_option('sbe_stripe_live_webhook_secret', '');
            $this->paypal_client_id = get_option('sbe_paypal_live_client_id', '');
            $this->paypal_client_secret = get_option('sbe_paypal_live_client_secret', '');
        }
        $this->paypal_webhook_id = get_option('sbe_paypal_webhook_id', '');
    }
    
    private function init_hooks() {
        add_action('wp_ajax_sbe_create_payment_intent', array($this, 'create_stripe_payment_intent'));
        add_action('wp_ajax_nopriv_sbe_create_payment_intent', array($this, 'create_stripe_payment_intent'));
        add_action('wp_ajax_sbe_create_paypal_order', array($this, 'create_paypal_order'));
        add_action('wp_ajax_nopriv_sbe_create_paypal_order', array($this, 'create_paypal_order'));
        add_action('wp_ajax_sbe_capture_paypal_order', array($this, 'capture_paypal_order'));
        add_action('wp_ajax_nopriv_sbe_capture_paypal_order', array($this, 'capture_paypal_order'));
        add_action('wp_ajax_sbe_confirm_payment', array($this, 'confirm_payment'));
        add_action('wp_ajax_nopriv_sbe_confirm_payment', array($this, 'confirm_payment'));
        add_action('wp_ajax_nopriv_sbe_stripe_webhook', array($this, 'handle_stripe_webhook'));
        add_action('wp_ajax_nopriv_sbe_paypal_webhook', array($this, 'handle_paypal_webhook'));
    }
    
    public function create_stripe_payment_intent() {
        check_ajax_referer('sbe_booking_nonce', 'nonce');
        $amount = isset($_POST['amount']) ? intval($_POST['amount']) : 0;
        $booking_id = isset($_POST['booking_id']) ? intval($_POST['booking_id']) : 0;
        
        if ($amount <= 0 || empty($this->stripe_api_key)) {
            wp_send_json_error(array('message' => __('Invalid payment amount or Stripe not configured.', 'service-bookings-events')));
        }
        
        if (!class_exists('\Stripe\Stripe')) {
            wp_send_json_error(array('message' => __('Stripe SDK not loaded.', 'service-bookings-events')));
        }
        
        try {
            \Stripe\Stripe::setApiKey($this->stripe_api_key);
            
            $payment_intent = \Stripe\PaymentIntent::create(array(
                'amount' => $amount * 100,
                'currency' => strtolower($this->currency),
                'metadata' => array('booking_id' => $booking_id, 'site_url' => get_site_url()),
                'automatic_payment_methods' => array('enabled' => true)
            ));
            
            wp_send_json_success(array(
                'client_secret' => $payment_intent->client_secret,
                'payment_intent_id' => $payment_intent->id
            ));
        } catch (\Exception $e) {
            wp_send_json_error(array('message' => $e->getMessage()));
        }
    }
    
    public function create_paypal_order() {
        check_ajax_referer('sbe_booking_nonce', 'nonce');
        $amount = isset($_POST['amount']) ? floatval($_POST['amount']) : 0;
        $booking_id = isset($_POST['booking_id']) ? intval($_POST['booking_id']) : 0;
        
        if ($amount <= 0 || empty($this->paypal_client_id)) {
            wp_send_json_error(array('message' => __('Invalid payment amount or PayPal not configured.', 'service-bookings-events')));
        }
        
        try {
            $access_token = $this->get_paypal_access_token();
            
            $order_data = array(
                'intent' => 'CAPTURE',
                'purchase_units' => array(
                    array(
                        'amount' => array(
                            'currency_code' => $this->currency,
                            'value' => number_format($amount, 2, '.', '')
                        ),
                        'description' => sprintf(__('Booking #%d', 'service-bookings-events'), $booking_id),
                        'custom_id' => (string)$booking_id
                    )
                )
            );
            
            $response = wp_remote_post('https://' . ($this->test_mode ? 'api-m.sandbox.' : 'api.') . 'paypal.com/v2/checkout/orders', array(
                'headers' => array('Content-Type' => 'application/json', 'Authorization' => 'Bearer ' . $access_token),
                'body' => json_encode($order_data),
                'timeout' => 30
            ));
            
            if (is_wp_error($response)) {
                throw new Exception($response->get_error_message());
            }
            
            $body = json_decode(wp_remote_retrieve_body($response), true);
            
            if (isset($body['id']) && isset($body['status'])) {
                wp_send_json_success(array(
                    'order_id' => $body['id'],
                    'status' => $body['status'],
                    'approve_link' => $this->find_paypal_link($body, 'approve')
                ));
            } else {
                throw new Exception(__('Failed to create PayPal order.', 'service-bookings-events'));
            }
        } catch (\Exception $e) {
            wp_send_json_error(array('message' => $e->getMessage()));
        }
    }
    
    public function capture_paypal_order() {
        check_ajax_referer('sbe_booking_nonce', 'nonce');
        $order_id = isset($_POST['order_id']) ? sanitize_text_field($_POST['order_id']) : '';
        $booking_id = isset($_POST['booking_id']) ? intval($_POST['booking_id']) : 0;
        
        if (empty($order_id) || empty($this->paypal_client_id)) {
            wp_send_json_error(array('message' => __('Invalid order ID or PayPal not configured.', 'service-bookings-events')));
        }
        
        try {
            $access_token = $this->get_paypal_access_token();
            
            $response = wp_remote_post('https://' . ($this->test_mode ? 'api-m.sandbox.' : 'api.') . 'paypal.com/v2/checkout/orders/' . $order_id . '/capture', array(
                'headers' => array('Content-Type' => 'application/json', 'Authorization' => 'Bearer ' . $access_token, 'Prefer' => 'return=representation'),
                'timeout' => 30
            ));
            
            if (is_wp_error($response)) {
                throw new Exception($response->get_error_message());
            }
            
            $body = json_decode(wp_remote_retrieve_body($response), true);
            
            if (isset($body['status']) && $body['status'] === 'COMPLETED') {
                $this->update_booking_payment_status($booking_id, 'paid', array(
                    'gateway' => 'paypal',
                    'transaction_id' => $order_id,
                    'amount' => isset($body['purchase_units'][0]['payments']['captures'][0]['amount']['value']) ? floatval($body['purchase_units'][0]['payments']['captures'][0]['amount']['value']) : 0,
                    'currency' => $this->currency,
                    'payment_date' => current_time('mysql')
                ));
                
                wp_send_json_success(array('status' => 'completed', 'transaction_id' => $order_id));
            } else {
                throw new Exception(__('Payment capture failed.', 'service-bookings-events'));
            }
        } catch (\Exception $e) {
            wp_send_json_error(array('message' => $e->getMessage()));
        }
    }
    
    public function confirm_payment() {
        check_ajax_referer('sbe_booking_nonce', 'nonce');
        $booking_id = isset($_POST['booking_id']) ? intval($_POST['booking_id']) : 0;
        $payment_intent_id = isset($_POST['payment_intent_id']) ? sanitize_text_field($_POST['payment_intent_id']) : '';
        $gateway = isset($_POST['gateway']) ? sanitize_text_field($_POST['gateway']) : 'stripe';
        
        if (empty($booking_id) || empty($payment_intent_id)) {
            wp_send_json_error(array('message' => __('Invalid booking or payment information.', 'service-bookings-events')));
        }
        
        try {
            if ($gateway === 'stripe' && !empty($this->stripe_api_key)) {
                \Stripe\Stripe::setApiKey($this->stripe_api_key);
                $payment_intent = \Stripe\PaymentIntent::retrieve($payment_intent_id);
                
                if ($payment_intent->status === 'succeeded') {
                    $this->update_booking_payment_status($booking_id, 'paid', array(
                        'gateway' => 'stripe',
                        'transaction_id' => $payment_intent_id,
                        'amount' => $payment_intent->amount / 100,
                        'currency' => $payment_intent->currency,
                        'payment_date' => current_time('mysql')
                    ));
                    wp_send_json_success(array('message' => __('Payment confirmed successfully!', 'service-bookings-events')));
                } else {
                    wp_send_json_error(array('message' => __('Payment not completed. Status: ', 'service-bookings-events') . $payment_intent->status));
                }
            } else {
                wp_send_json_error(array('message' => __('Payment gateway not configured.', 'service-bookings-events')));
            }
        } catch (\Exception $e) {
            wp_send_json_error(array('message' => $e->getMessage()));
        }
    }
    
    public function handle_stripe_webhook() {
        $payload = file_get_contents('php://input');
        $sig_header = isset($_SERVER['HTTP_STRIPE_SIGNATURE']) ? $_SERVER['HTTP_STRIPE_SIGNATURE'] : '';
        
        if (empty($sig_header) || empty($this->stripe_webhook_secret)) {
            http_response_code(400);
            exit;
        }
        
        try {
            $event = \Stripe\Webhook::constructEvent($payload, $sig_header, $this->stripe_webhook_secret);
            $data = $event->data->object;
            
            switch ($event->type) {
                case 'payment_intent.succeeded':
                    $booking_id = isset($data->metadata->booking_id) ? intval($data->metadata->booking_id) : 0;
                    if ($booking_id > 0) {
                        $this->update_booking_payment_status($booking_id, 'paid', array(
                            'gateway' => 'stripe',
                            'transaction_id' => $data->id,
                            'amount' => $data->amount / 100,
                            'currency' => $data->currency,
                            'payment_date' => current_time('mysql')
                        ));
                    }
                    break;
                case 'payment_intent.payment_failed':
                    $booking_id = isset($data->metadata->booking_id) ? intval($data->metadata->booking_id) : 0;
                    if ($booking_id > 0) {
                        $this->update_booking_payment_status($booking_id, 'failed', array(
                            'gateway' => 'stripe',
                            'transaction_id' => $data->id
                        ));
                    }
                    break;
            }
            
            http_response_code(200);
            echo json_encode(array('received' => true));
        } catch (\Exception $e) {
            http_response_code(400);
            echo json_encode(array('error' => $e->getMessage()));
        }
        exit;
    }
    
    public function handle_paypal_webhook() {
        $payload = file_get_contents('php://input');
        $headers = getallheaders();
        
        $verification_id = isset($headers['paypal-transmission-id']) ? $headers['paypal-transmission-id'] : '';
        
        if (empty($verification_id) || empty($this->paypal_client_id)) {
            http_response_code(400);
            exit;
        }
        
        try {
            $access_token = $this->get_paypal_access_token();
            $event = json_decode($payload, true);
            $event_type = isset($event['event_type']) ? $event['event_type'] : '';
            $resource = isset($event['resource']) ? $event['resource'] : array();
            
            switch ($event_type) {
                case 'PAYMENT.CAPTURE.COMPLETED':
                    $booking_id = isset($resource['custom_id']) ? intval($resource['custom_id']) : 0;
                    if ($booking_id > 0) {
                        $this->update_booking_payment_status($booking_id, 'paid', array(
                            'gateway' => 'paypal',
                            'transaction_id' => isset($resource['id']) ? $resource['id'] : '',
                            'amount' => isset($resource['amount']['value']) ? floatval($resource['amount']['value']) : 0,
                            'currency' => isset($resource['amount']['currency_code']) ? $resource['amount']['currency_code'] : $this->currency,
                            'payment_date' => current_time('mysql')
                        ));
                    }
                    break;
            }
            
            http_response_code(200);
            echo json_encode(array('received' => true));
        } catch (\Exception $e) {
            http_response_code(400);
            echo json_encode(array('error' => $e->getMessage()));
        }
        exit;
    }
    
    private function update_booking_payment_status($booking_id, $status, $payment_data = array()) {
        global $wpdb;
        
        $update_data = array(
            'payment_status' => $status,
            'payment_gateway' => isset($payment_data['gateway']) ? $payment_data['gateway'] : '',
            'transaction_id' => isset($payment_data['transaction_id']) ? $payment_data['transaction_id'] : '',
            'payment_amount' => isset($payment_data['amount']) ? $payment_data['amount'] : 0,
            'payment_currency' => isset($payment_data['currency']) ? $payment_data['currency'] : $this->currency,
            'payment_date' => isset($payment_data['payment_date']) ? $payment_data['payment_date'] : current_time('mysql')
        );
        
        $wpdb->update(
            $wpdb->prefix . 'sbe_bookings',
            $update_data,
            array('id' => $booking_id),
            array('%s', '%s', '%s', '%f', '%s', '%s'),
            array('%d')
        );
        
        if ($status === 'paid') {
            $wpdb->update(
                $wpdb->prefix . 'sbe_bookings',
                array('status' => 'confirmed'),
                array('id' => $booking_id),
                array('%s'),
                array('%d')
            );
        }
        
        do_action('sbe_payment_status_updated', $booking_id, $status, $payment_data);
    }
    
    private function get_paypal_access_token() {
        static $cached_token = null;
        static $token_expires = 0;
        
        if ($cached_token && time() < $token_expires) {
            return $cached_token;
        }
        
        $credentials = base64_encode($this->paypal_client_id . ':' . $this->paypal_client_secret);
        
        $response = wp_remote_post('https://' . ($this->test_mode ? 'api-m.sandbox.' : 'api.') . 'paypal.com/v1/oauth2/token', array(
            'headers' => array('Authorization' => 'Basic ' . $credentials, 'Content-Type' => 'application/x-www-form-urlencoded'),
            'body' => 'grant_type=client_credentials',
            'timeout' => 30
        ));
        
        if (is_wp_error($response)) {
            throw new Exception($response->get_error_message());
        }
        
        $body = json_decode(wp_remote_retrieve_body($response), true);
        
        if (isset($body['access_token'])) {
            $cached_token = $body['access_token'];
            $token_expires = time() + ($body['expires_in'] - 300);
            return $cached_token;
        }
        
        throw new Exception(__('Failed to get PayPal access token.', 'service-bookings-events'));
    }
    
    private function find_paypal_link($response, $rel) {
        if (isset($response['links'])) {
            foreach ($response['links'] as $link) {
                if (isset($link['rel']) && $link['rel'] === $rel) {
                    return $link['href'];
                }
            }
        }
        return '';
    }
}

function sbe_init_payment_gateway() {
    return SBE_Payment_Gateway::get_instance();
}
add_action('plugins_loaded', 'sbe_init_payment_gateway', 20);

if (!function_exists('getallheaders')) {
    function getallheaders() {
        $headers = array();
        foreach ($_SERVER as $name => $value) {
            if (substr($name, 0, 5) == 'HTTP_') {
                $headers[str_replace(' ', '-', ucwords(strtolower(str_replace('_', ' ', substr($name, 5)))))] = $value;
            }
        }
        return $headers;
    }
}
