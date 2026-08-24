<?php
/**
 * Subscriptions and Recurring Billing
 * Supports Stripe Billing and PayPal Subscriptions
 */

if (!defined('ABSPATH')) exit;

class SBE_Subscriptions {
    
    private static $instance = null;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        $this->init_hooks();
        $this->create_subscription_tables();
    }
    
    private function init_hooks() {
        add_action('init', array($this, 'register_subscription_post_type'));
        add_action('admin_menu', array($this, 'add_subscription_menu'));
        add_action('wp_ajax_sbe_create_subscription', array($this, 'create_subscription'));
        add_action('wp_ajax_nopriv_sbe_create_subscription', array($this, 'create_subscription'));
        add_shortcode('sbe_subscription_form', array($this, 'subscription_form_shortcode'));
        add_shortcode('sbe_manage_subscriptions', array($this, 'manage_subscriptions_shortcode'));
        add_shortcode('sbe_pricing_table', array($this, 'pricing_table_shortcode'));
    }
    
    private function create_subscription_tables() {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();
        
        $table_subscriptions = $wpdb->prefix . 'sbe_subscriptions';
        $table_logs = $wpdb->prefix . 'sbe_subscription_logs';
        
        $sql_subscriptions = "CREATE TABLE $table_subscriptions (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            user_id bigint(20) NOT NULL,
            customer_email varchar(255) NOT NULL,
            subscription_id varchar(255) NOT NULL,
            plan_id bigint(20) DEFAULT NULL,
            plan_name varchar(255) NOT NULL,
            gateway varchar(50) NOT NULL,
            status varchar(50) DEFAULT 'active',
            current_period_start datetime DEFAULT NULL,
            current_period_end datetime DEFAULT NULL,
            trial_start datetime DEFAULT NULL,
            trial_end datetime DEFAULT NULL,
            amount decimal(10,2) NOT NULL,
            currency varchar(10) DEFAULT 'USD',
            interval varchar(20) NOT NULL,
            interval_count int(11) DEFAULT 1,
            cancel_at_period_end tinyint(1) DEFAULT 0,
            canceled_at datetime DEFAULT NULL,
            ended_at datetime DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            metadata longtext,
            PRIMARY KEY (id),
            KEY user_id (user_id),
            KEY subscription_id (subscription_id),
            KEY status (status)
        ) $charset_collate;";
        
        $sql_logs = "CREATE TABLE $table_logs (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            subscription_id bigint(20) NOT NULL,
            event_type varchar(100) NOT NULL,
            event_data longtext,
            amount decimal(10,2) DEFAULT 0,
            status varchar(50) DEFAULT 'pending',
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY subscription_id (subscription_id),
            KEY event_type (event_type)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql_subscriptions);
        dbDelta($sql_logs);
    }
    
    public function register_subscription_post_type() {
        register_post_type('sbe_subscription_plan', array(
            'labels' => array(
                'name' => __('Subscription Plans', 'service-bookings-events'),
                'singular_name' => __('Subscription Plan', 'service-bookings-events'),
                'add_new' => __('Add New Plan', 'service-bookings-events'),
                'add_new_item' => __('Add New Subscription Plan', 'service-bookings-events'),
                'edit_item' => __('Edit Subscription Plan', 'service-bookings-events'),
            ),
            'public' => true,
            'has_archive' => true,
            'show_ui' => true,
            'show_in_menu' => 'sbe-main',
            'show_in_rest' => true,
            'supports' => array('title', 'editor', 'thumbnail', 'excerpt'),
            'menu_icon' => 'dashicons-clipboard',
            'rewrite' => array('slug' => 'subscription-plans'),
        ));
    }
    
    public function add_subscription_menu() {
        add_submenu_page('sbe-main', __('Subscriptions', 'service-bookings-events'), __('Subscriptions', 'service-bookings-events'), 'manage_options', 'sbe-subscriptions', array($this, 'subscriptions_page'));
    }
    
    public function subscriptions_page() {
        global $wpdb;
        $subscriptions = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}sbe_subscriptions ORDER BY created_at DESC LIMIT 100");
        ?>
        <div class="wrap">
            <h1><?php echo esc_html__('Subscriptions', 'service-bookings-events'); ?></h1>
            <div class="sbe-subscription-stats" style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 20px; margin: 20px 0;">
                <div style="background: #fff; padding: 20px; border: 1px solid #ccd0d4;">
                    <h3 style="margin: 0; color: #666; font-size: 14px;"><?php echo esc_html__('Active', 'service-bookings-events'); ?></h3>
                    <p style="font-size: 2em; margin: 10px 0; color: #28a745;"><?php echo $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}sbe_subscriptions WHERE status = 'active'"); ?></p>
                </div>
                <div style="background: #fff; padding: 20px; border: 1px solid #ccd0d4;">
                    <h3 style="margin: 0; color: #666; font-size: 14px;"><?php echo esc_html__('Trial', 'service-bookings-events'); ?></h3>
                    <p style="font-size: 2em; margin: 10px 0; color: #17a2b8;"><?php echo $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}sbe_subscriptions WHERE status = 'trialing'"); ?></p>
                </div>
                <div style="background: #fff; padding: 20px; border: 1px solid #ccd0d4;">
                    <h3 style="margin: 0; color: #666; font-size: 14px;"><?php echo esc_html__('Past Due', 'service-bookings-events'); ?></h3>
                    <p style="font-size: 2em; margin: 10px 0; color: #ffc107;"><?php echo $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}sbe_subscriptions WHERE status = 'past_due'"); ?></p>
                </div>
                <div style="background: #fff; padding: 20px; border: 1px solid #ccd0d4;">
                    <h3 style="margin: 0; color: #666; font-size: 14px;"><?php echo esc_html__('Canceled', 'service-bookings-events'); ?></h3>
                    <p style="font-size: 2em; margin: 10px 0; color: #dc3545;"><?php echo $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}sbe_subscriptions WHERE status = 'canceled'"); ?></p>
                </div>
            </div>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th><?php echo esc_html__('Subscription', 'service-bookings-events'); ?></th>
                        <th><?php echo esc_html__('Customer', 'service-bookings-events'); ?></th>
                        <th><?php echo esc_html__('Plan', 'service-bookings-events'); ?></th>
                        <th><?php echo esc_html__('Amount', 'service-bookings-events'); ?></th>
                        <th><?php echo esc_html__('Status', 'service-bookings-events'); ?></th>
                        <th><?php echo esc_html__('Gateway', 'service-bookings-events'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($subscriptions)): ?>
                    <tr><td colspan="6"><?php echo esc_html__('No subscriptions found.', 'service-bookings-events'); ?></td></tr>
                    <?php else: ?>
                    <?php foreach ($subscriptions as $sub): ?>
                    <tr>
                        <td><strong>#<?php echo esc_html($sub->id); ?></strong></td>
                        <td><?php echo esc_html($sub->customer_email); ?></td>
                        <td><?php echo esc_html($sub->plan_name); ?></td>
                        <td><?php echo esc_html($sub->currency . ' ' . number_format($sub->amount, 2)); ?></td>
                        <td><span class="sbe-sub-status sbe-status-<?php echo esc_attr($sub->status); ?>"><?php echo esc_html(ucfirst($sub->status)); ?></span></td>
                        <td><?php echo esc_html(ucfirst($sub->gateway)); ?></td>
                    </tr>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php
    }
    
    public function create_subscription() {
        check_ajax_referer('sbe_booking_nonce', 'nonce');
        $user_id = isset($_POST['user_id']) ? intval($_POST['user_id']) : get_current_user_id();
        $customer_email = isset($_POST['customer_email']) ? sanitize_email($_POST['customer_email']) : '';
        $plan_id = isset($_POST['plan_id']) ? intval($_POST['plan_id']) : 0;
        $gateway = isset($_POST['gateway']) ? sanitize_text_field($_POST['gateway']) : 'stripe';
        
        if (empty($customer_email) || !$plan_id) {
            wp_send_json_error(array('message' => __('Missing required fields.', 'service-bookings-events')));
        }
        
        $plan = get_post($plan_id);
        if (!$plan) {
            wp_send_json_error(array('message' => __('Invalid plan.', 'service-bookings-events')));
        }
        
        $amount = get_post_meta($plan_id, 'sbe_plan_amount', true);
        $interval = get_post_meta($plan_id, 'sbe_plan_interval', true);
        $interval_count = get_post_meta($plan_id, 'sbe_plan_interval_count', true);
        $currency = get_post_meta($plan_id, 'sbe_plan_currency', true);
        
        try {
            if ($gateway === 'stripe' && class_exists('\Stripe\Stripe')) {
                $subscription_data = $this->create_stripe_subscription(array(
                    'user_id' => $user_id,
                    'customer_email' => $customer_email,
                    'plan_id' => $plan_id,
                    'amount' => $amount,
                    'interval' => $interval,
                    'interval_count' => $interval_count,
                    'currency' => $currency
                ));
                wp_send_json_success($subscription_data);
            } else {
                throw new Exception(__('Stripe SDK not loaded or invalid gateway.', 'service-bookings-events'));
            }
        } catch (Exception $e) {
            wp_send_json_error(array('message' => $e->getMessage()));
        }
    }
    
    private function create_stripe_subscription($data) {
        if (!class_exists('\Stripe\Stripe')) {
            throw new Exception(__('Stripe SDK not loaded.', 'service-bookings-events'));
        }
        
        $stripe_api_key = get_option('sbe_stripe_' . (get_option('sbe_payment_test_mode') ? 'test' : 'live') . '_secret_key');
        \Stripe\Stripe::setApiKey($stripe_api_key);
        
        $customer = \Stripe\Customer::create(array(
            'email' => $data['customer_email'],
            'metadata' => array('user_id' => $data['user_id'], 'site_url' => get_site_url())
        ));
        
        $subscription = \Stripe\Subscription::create(array(
            'customer' => $customer->id,
            'items' => array(array(
                'price_data' => array(
                    'currency' => $data['currency'],
                    'unit_amount' => intval($data['amount'] * 100),
                    'recurring' => array('interval' => $data['interval'], 'interval_count' => $data['interval_count']),
                    'product_data' => array('name' => get_the_title($data['plan_id']))
                )
            )),
            'expand' => array('latest_invoice.payment_intent'),
            'metadata' => array('user_id' => $data['user_id'], 'plan_id' => $data['plan_id'], 'site_url' => get_site_url())
        ));
        
        global $wpdb;
        $wpdb->insert($wpdb->prefix . 'sbe_subscriptions', array(
            'user_id' => $data['user_id'],
            'customer_email' => $data['customer_email'],
            'subscription_id' => $subscription->id,
            'plan_id' => $data['plan_id'],
            'plan_name' => get_the_title($data['plan_id']),
            'gateway' => 'stripe',
            'status' => $subscription->status,
            'current_period_start' => date('Y-m-d H:i:s', $subscription->current_period_start),
            'current_period_end' => date('Y-m-d H:i:s', $subscription->current_period_end),
            'amount' => $data['amount'],
            'currency' => $data['currency'],
            'interval' => $data['interval'],
            'interval_count' => $data['interval_count'],
            'metadata' => json_encode(array('stripe_customer_id' => $customer->id))
        ));
        
        return array(
            'subscription_id' => $wpdb->insert_id,
            'stripe_subscription_id' => $subscription->id,
            'client_secret' => isset($subscription->latest_invoice->payment_intent->client_secret) ? $subscription->latest_invoice->payment_intent->client_secret : null,
            'status' => $subscription->status
        );
    }
    
    public function subscription_form_shortcode($atts) {
        $atts = shortcode_atts(array('plan_id' => 0, 'show_trial' => true), $atts);
        ob_start();
        ?>
        <div class="sbe-subscription-form">
            <form id="sbe-subscription-form">
                <div class="sbe-form-group">
                    <label for="sbe_sub_email"><?php echo esc_html__('Email Address', 'service-bookings-events'); ?> *</label>
                    <input type="email" id="sbe_sub_email" name="customer_email" required>
                </div>
                <div class="sbe-payment-methods">
                    <label><input type="radio" name="gateway" value="stripe" checked> <?php echo esc_html__('Credit Card (Stripe)', 'service-bookings-events'); ?></label>
                </div>
                <button type="submit" class="sbe-subscribe-btn"><?php echo esc_html__('Subscribe Now', 'service-bookings-events'); ?></button>
                <div class="sbe-subscription-message"></div>
            </form>
        </div>
        <?php
        return ob_get_clean();
    }
    
    public function manage_subscriptions_shortcode($atts) {
        if (!is_user_logged_in()) {
            return '<p>' . esc_html__('Please log in to manage your subscriptions.', 'service-bookings-events') . '</p>';
        }
        
        global $wpdb;
        $user_id = get_current_user_id();
        $subscriptions = $wpdb->get_results($wpdb->prepare("SELECT * FROM {$wpdb->prefix}sbe_subscriptions WHERE user_id = %d ORDER BY created_at DESC", $user_id));
        
        ob_start();
        ?>
        <div class="sbe-manage-subscriptions">
            <h2><?php echo esc_html__('My Subscriptions', 'service-bookings-events'); ?></h2>
            <?php if (empty($subscriptions)): ?>
            <p><?php echo esc_html__('You have no active subscriptions.', 'service-bookings-events'); ?></p>
            <?php else: ?>
            <table class="sbe-subscriptions-table">
                <thead>
                    <tr>
                        <th><?php echo esc_html__('Plan', 'service-bookings-events'); ?></th>
                        <th><?php echo esc_html__('Status', 'service-bookings-events'); ?></th>
                        <th><?php echo esc_html__('Amount', 'service-bookings-events'); ?></th>
                        <th><?php echo esc_html__('Next Billing', 'service-bookings-events'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($subscriptions as $sub): ?>
                    <tr>
                        <td><?php echo esc_html($sub->plan_name); ?></td>
                        <td><span class="sbe-sub-status sbe-status-<?php echo esc_attr($sub->status); ?>"><?php echo esc_html(ucfirst($sub->status)); ?></span></td>
                        <td><?php echo esc_html($sub->currency . ' ' . number_format($sub->amount, 2)); ?></td>
                        <td><?php echo esc_html(date_i18n(get_option('date_format'), strtotime($sub->current_period_end))); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>
        </div>
        <?php
        return ob_get_clean();
    }
    
    public function pricing_table_shortcode($atts) {
        $atts = shortcode_atts(array('category' => ''), $atts);
        $args = array('post_type' => 'sbe_subscription_plan', 'posts_per_page' => -1, 'post_status' => 'publish');
        $plans = get_posts($args);
        
        ob_start();
        ?>
        <div class="sbe-pricing-table">
            <div class="sbe-plans-grid">
                <?php foreach ($plans as $plan): 
                $amount = get_post_meta($plan->ID, 'sbe_plan_amount', true);
                $interval = get_post_meta($plan->ID, 'sbe_plan_interval', true);
                ?>
                <div class="sbe-plan-card">
                    <h3><?php echo esc_html($plan->post_title); ?></h3>
                    <div class="sbe-plan-price">
                        <span class="sbe-plan-amount"><?php echo esc_html(number_format($amount, 2)); ?></span>
                        <span class="sbe-plan-currency"><?php echo esc_html(get_post_meta($plan->ID, 'sbe_plan_currency', true)); ?></span>
                        <span class="sbe-plan-interval">/<?php echo esc_html($interval); ?></span>
                    </div>
                    <div class="sbe-plan-features"><?php echo wp_kses_post($plan->post_content); ?></div>
                    <a href="<?php echo get_permalink($plan->ID); ?>" class="sbe-plan-select-btn"><?php echo esc_html__('Select Plan', 'service-bookings-events'); ?></a>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }
}

function sbe_init_subscriptions() {
    return SBE_Subscriptions::get_instance();
}
add_action('plugins_loaded', 'sbe_init_subscriptions', 30);
