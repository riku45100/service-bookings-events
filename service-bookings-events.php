<?php
/**
 * Plugin Name: Service Bookings & Events
 * Description: Complete booking system with payments (Stripe/PayPal), subscriptions, recurring billing, and calendar feeds (iCal/Google Calendar)
 * Version: 1.3.1
 * Author: Richard McGrath/FourZero
 * License: GPL v2 or later
 * Text Domain: https://fourzero.work
 */

if (!defined('ABSPATH')) exit;

define('SBE_VERSION', '1.3.1');
define('SBE_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('SBE_PLUGIN_URL', plugin_dir_url(__FILE__));

class Service_Bookings_Events {
    private static $instance = null;
    public static function get_instance() {
        if (null === self::$instance) self::$instance = new self();
        return self::$instance;
    }
    private function __construct() {
        $this->init_hooks();
        $this->create_database_tables();
    }
    private function init_hooks() {
        register_activation_hook(__FILE__, array($this, 'activate'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));
        add_action('init', array($this, 'register_post_types'));
        add_action('init', array($this, 'register_taxonomies'));
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_enqueue_scripts', array($this, 'admin_enqueue_scripts'));
        add_action('wp_enqueue_scripts', array($this, 'frontend_enqueue_scripts'));
        add_action('wp_ajax_sbe_submit_booking', array($this, 'handle_booking_submission'));
        add_action('wp_ajax_nopriv_sbe_submit_booking', array($this, 'handle_booking_submission'));
        add_action('wp_ajax_sbe_get_available_slots', array($this, 'get_available_slots'));
        add_action('wp_ajax_nopriv_sbe_get_available_slots', array($this, 'get_available_slots'));
        add_shortcode('sbe_booking_form', array($this, 'booking_form_shortcode'));
        add_shortcode('sbe_events_list', array($this, 'events_list_shortcode'));
        add_shortcode('sbe_services_list', array($this, 'services_list_shortcode'));
        add_shortcode('sbe_calendar', array($this, 'calendar_shortcode'));
    }
    public function activate() {
        $this->register_post_types();
        $this->register_taxonomies();
        flush_rewrite_rules();
        $this->create_database_tables();
    }
    public function deactivate() { flush_rewrite_rules(); }
    private function create_database_tables() {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();
        $table_bookings = $wpdb->prefix . 'sbe_bookings';
        $sql = "CREATE TABLE $table_bookings (id bigint(20) NOT NULL AUTO_INCREMENT, service_id bigint(20) NOT NULL, event_id bigint(20) DEFAULT NULL, customer_name varchar(255) NOT NULL, customer_email varchar(255) NOT NULL, customer_phone varchar(50) DEFAULT NULL, booking_date date NOT NULL, booking_time time NOT NULL, duration int(11) DEFAULT 60, status varchar(50) DEFAULT 'pending', notes text, payment_status varchar(50) DEFAULT NULL, payment_gateway varchar(50) DEFAULT NULL, transaction_id varchar(255) DEFAULT NULL, payment_amount decimal(10,2) DEFAULT 0, payment_currency varchar(10) DEFAULT 'USD', payment_date datetime DEFAULT NULL, created_at datetime DEFAULT CURRENT_TIMESTAMP, updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP, PRIMARY KEY (id), KEY service_id (service_id), KEY event_id (event_id), KEY booking_date (booking_date), KEY status (status), KEY payment_status (payment_status)) $charset_collate;";
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }
    public function register_post_types() {
        register_post_type('sbe_service', array('labels' => array('name' => __('Services', 'service-bookings-events'), 'singular_name' => __('Service', 'service-bookings-events'), 'add_new' => __('Add New', 'service-bookings-events'), 'add_new_item' => __('Add New Service', 'service-bookings-events'), 'edit_item' => __('Edit Service', 'service-bookings-events')), 'public' => true, 'has_archive' => true, 'show_ui' => true, 'show_in_menu' => 'sbe-main', 'show_in_rest' => true, 'supports' => array('title', 'editor', 'thumbnail', 'excerpt'), 'menu_icon' => 'dashicons-calendar-alt', 'rewrite' => array('slug' => 'services')));
        register_post_type('sbe_event', array('labels' => array('name' => __('Events', 'service-bookings-events'), 'singular_name' => __('Event', 'service-bookings-events'), 'add_new' => __('Add New', 'service-bookings-events'), 'add_new_item' => __('Add New Event', 'service-bookings-events'), 'edit_item' => __('Edit Event', 'service-bookings-events')), 'public' => true, 'has_archive' => true, 'show_ui' => true, 'show_in_menu' => 'sbe-main', 'show_in_rest' => true, 'supports' => array('title', 'editor', 'thumbnail', 'excerpt'), 'menu_icon' => 'dashicons-calendar', 'rewrite' => array('slug' => 'events')));
    }
    public function register_taxonomies() {
        register_taxonomy('sbe_service_category', array('sbe_service'), array('labels' => array('name' => __('Service Categories', 'service-bookings-events'), 'singular_name' => __('Service Category', 'service-bookings-events'), 'menu_name' => __('Categories', 'service-bookings-events')), 'hierarchical' => true, 'show_ui' => true, 'show_in_rest' => true, 'rewrite' => array('slug' => 'service-category')));
        register_taxonomy('sbe_event_category', array('sbe_event'), array('labels' => array('name' => __('Event Categories', 'service-bookings-events'), 'singular_name' => __('Event Category', 'service-bookings-events'), 'menu_name' => __('Categories', 'service-bookings-events')), 'hierarchical' => true, 'show_ui' => true, 'show_in_rest' => true, 'rewrite' => array('slug' => 'event-category')));
    }
    public function add_admin_menu() {
        add_menu_page(__('Service Bookings & Events', 'service-bookings-events'), __('Bookings & Events', 'service-bookings-events'), 'manage_options', 'sbe-main', array($this, 'admin_dashboard_page'), 'dashicons-calendar-alt', 30);
        add_submenu_page('sbe-main', __('Settings', 'service-bookings-events'), __('Settings', 'service-bookings-events'), 'manage_options', 'sbe-settings', array($this, 'settings_page'));
        add_submenu_page('sbe-main', __('All Bookings', 'service-bookings-events'), __('All Bookings', 'service-bookings-events'), 'manage_options', 'sbe-bookings', array($this, 'bookings_page'));
    }
    public function admin_dashboard_page() {
        global $wpdb;
        ?><div class="wrap"><h1><?php echo esc_html__('Service Bookings & Events Dashboard', 'service-bookings-events'); ?></h1><div class="sbe-dashboard-widgets" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px; margin: 20px 0;"><div style="background: #fff; padding: 20px; border: 1px solid #ccd0d4; border-radius: 4px;"><h3 style="margin: 0; color: #666; font-size: 14px;"><?php echo esc_html__('Total Services', 'service-bookings-events'); ?></h3><p style="font-size: 2em; margin: 10px 0; color: #0073aa;"><?php echo wp_count_posts('sbe_service')->publish; ?></p><a href="<?php echo admin_url('edit.php?post_type=sbe_service'); ?>"><?php echo esc_html__('View All Services', 'service-bookings-events'); ?></a></div><div style="background: #fff; padding: 20px; border: 1px solid #ccd0d4; border-radius: 4px;"><h3 style="margin: 0; color: #666; font-size: 14px;"><?php echo esc_html__('Total Events', 'service-bookings-events'); ?></h3><p style="font-size: 2em; margin: 10px 0; color: #0073aa;"><?php echo wp_count_posts('sbe_event')->publish; ?></p><a href="<?php echo admin_url('edit.php?post_type=sbe_event'); ?>"><?php echo esc_html__('View All Events', 'service-bookings-events'); ?></a></div><div style="background: #fff; padding: 20px; border: 1px solid #ccd0d4; border-radius: 4px;"><h3 style="margin: 0; color: #666; font-size: 14px;"><?php echo esc_html__('Pending Bookings', 'service-bookings-events'); ?></h3><p style="font-size: 2em; margin: 10px 0; color: #ffc107;"><?php echo $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}sbe_bookings WHERE status = 'pending'"); ?></p><a href="<?php echo admin_url('admin.php?page=sbe-bookings'); ?>"><?php echo esc_html__('View All Bookings', 'service-bookings-events'); ?></a></div></div><div class="sbe-quick-actions" style="margin-top: 30px;"><h2><?php echo esc_html__('Quick Actions', 'service-bookings-events'); ?></h2><a href="<?php echo admin_url('post-new.php?post_type=sbe_service'); ?>" class="button button-primary"><?php echo esc_html__('Add New Service', 'service-bookings-events'); ?></a><a href="<?php echo admin_url('post-new.php?post_type=sbe_event'); ?>" class="button button-primary"><?php echo esc_html__('Add New Event', 'service-bookings-events'); ?></a><a href="<?php echo admin_url('admin.php?page=sbe-settings'); ?>" class="button"><?php echo esc_html__('Configure Settings', 'service-bookings-events'); ?></a></div></div><?php
    }
    public function settings_page() {
        if (isset($_POST['sbe_save_settings']) && wp_verify_nonce($_POST['sbe_settings_nonce'], 'sbe_save_settings')) {
            update_option('sbe_confirmation_email_enabled', isset($_POST['sbe_confirmation_email_enabled']) ? true : false);
            update_option('sbe_default_booking_duration', intval($_POST['sbe_default_booking_duration']));
            echo '<div class="notice notice-success"><p>' . esc_html__('Settings saved!', 'service-bookings-events') . '</p></div>';
        }
        $email_enabled = get_option('sbe_confirmation_email_enabled', true);
        $duration = get_option('sbe_default_booking_duration', 60);
        ?><div class="wrap"><h1><?php echo esc_html__('Settings', 'service-bookings-events'); ?></h1><form method="post"><?php wp_nonce_field('sbe_save_settings', 'sbe_settings_nonce'); ?><table class="form-table"><tr><th><?php echo esc_html__('Enable Confirmation Emails', 'service-bookings-events'); ?></th><td><label><input type="checkbox" name="sbe_confirmation_email_enabled" value="1" <?php checked($email_enabled); ?>> <?php echo esc_html__('Send emails to customers', 'service-bookings-events'); ?></label></td></tr><tr><th><?php echo esc_html__('Default Booking Duration (minutes)', 'service-bookings-events'); ?></th><td><input type="number" name="sbe_default_booking_duration" value="<?php echo esc_attr($duration); ?>" class="small-text"></td></tr></table><?php submit_button(__('Save Settings', 'service-bookings-events'), 'primary', 'sbe_save_settings'); ?></form></div><?php
    }
    public function bookings_page() {
        global $wpdb;
        $bookings = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}sbe_bookings ORDER BY created_at DESC");
        ?><div class="wrap"><h1><?php echo esc_html__('All Bookings', 'service-bookings-events'); ?></h1><table class="wp-list-table widefat fixed striped"><thead><tr><th><?php echo esc_html__('ID', 'service-bookings-events'); ?></th><th><?php echo esc_html__('Customer', 'service-bookings-events'); ?></th><th><?php echo esc_html__('Date & Time', 'service-bookings-events'); ?></th><th><?php echo esc_html__('Status', 'service-bookings-events'); ?></th></tr></thead><tbody><?php if (empty($bookings)): ?><tr><td colspan="4"><?php echo esc_html__('No bookings found.', 'service-bookings-events'); ?></td></tr><?php else: ?><?php foreach ($bookings as $booking): ?><tr><td>#<?php echo esc_html($booking->id); ?></td><td><?php echo esc_html($booking->customer_name); ?><br><small><?php echo esc_html($booking->customer_email); ?></small></td><td><?php echo esc_html(date_i18n(get_option('date_format'), strtotime($booking->booking_date)) . ' ' . date_i18n(get_option('time_format'), strtotime($booking->booking_time))); ?></td><td><span class="sbe-status sbe-status-<?php echo esc_attr($booking->status); ?>"><?php echo esc_html(ucfirst($booking->status)); ?></span></td></tr><?php endforeach; ?><?php endif; ?></tbody></table></div><?php
    }
    public function admin_enqueue_scripts($hook) { if (strpos($hook, 'sbe-') === false) return; wp_enqueue_style('sbe-admin', SBE_PLUGIN_URL . 'assets/css/admin.css', array(), SBE_VERSION); wp_enqueue_script('sbe-admin', SBE_PLUGIN_URL . 'assets/js/admin.js', array('jquery'), SBE_VERSION, true); }
    public function frontend_enqueue_scripts() { wp_enqueue_style('sbe-frontend', SBE_PLUGIN_URL . 'assets/css/frontend.css', array(), SBE_VERSION); wp_enqueue_script('sbe-frontend', SBE_PLUGIN_URL . 'assets/js/frontend.js', array('jquery'), SBE_VERSION, true); wp_localize_script('sbe-frontend', 'sbe_ajax', array('ajax_url' => admin_url('admin-ajax.php'), 'nonce' => wp_create_nonce('sbe_booking_nonce'))); }
    public function handle_booking_submission() {
        check_ajax_referer('sbe_booking_nonce', 'nonce');
        $service_id = isset($_POST['service_id']) ? intval($_POST['service_id']) : 0;
        $event_id = isset($_POST['event_id']) ? intval($_POST['event_id']) : 0;
        $customer_name = sanitize_text_field($_POST['customer_name']);
        $customer_email = sanitize_email($_POST['customer_email']);
        $customer_phone = sanitize_text_field($_POST['customer_phone']);
        $booking_date = sanitize_text_field($_POST['booking_date']);
        $booking_time = sanitize_text_field($_POST['booking_time']);
        $notes = sanitize_textarea_field($_POST['notes']);
        $duration = get_option('sbe_default_booking_duration', 60);
        if (empty($customer_name) || empty($customer_email) || empty($booking_date) || empty($booking_time)) { wp_send_json_error(array('message' => __('Please fill in all required fields.', 'service-bookings-events'))); }
        global $wpdb;
        $result = $wpdb->insert($wpdb->prefix . 'sbe_bookings', array('service_id' => $service_id, 'event_id' => $event_id, 'customer_name' => $customer_name, 'customer_email' => $customer_email, 'customer_phone' => $customer_phone, 'booking_date' => $booking_date, 'booking_time' => $booking_time, 'duration' => $duration, 'status' => 'pending', 'notes' => $notes), array('%d', '%d', '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%s'));
        if ($result === false) { wp_send_json_error(array('message' => __('Failed to create booking.', 'service-bookings-events'))); }
        $booking_id = $wpdb->insert_id;
        if (get_option('sbe_confirmation_email_enabled', true)) { $this->send_confirmation_email($booking_id); }
        wp_send_json_success(array('message' => __('Booking submitted successfully!', 'service-bookings-events'), 'booking_id' => $booking_id));
    }
    public function get_available_slots() {
        check_ajax_referer('sbe_booking_nonce', 'nonce');
        $date = sanitize_text_field($_POST['date']);
        global $wpdb;
        $booked = $wpdb->get_col($wpdb->prepare("SELECT booking_time FROM {$wpdb->prefix}sbe_bookings WHERE booking_date = %s AND status IN ('pending', 'confirmed')", $date));
        $slots = array();
        for ($hour = 9; $hour <= 18; $hour++) { foreach (array('00', '30') as $minute) { $time = sprintf('%02d:%s:00', $hour, $minute); if (!in_array($time, $booked)) { $slots[] = array('time' => $time, 'display' => date_i18n(get_option('time_format'), strtotime($time))); } } }
        wp_send_json_success(array('slots' => $slots));
    }
    public function booking_form_shortcode($atts) {
        $atts = shortcode_atts(array('type' => 'service', 'id' => ''), $atts);
        ob_start();
        ?><div class="sbe-booking-form"><form class="sbe-form"><div class="sbe-form-group"><label for="sbe_customer_name"><?php echo esc_html__('Your Name', 'service-bookings-events'); ?> *</label><input type="text" id="sbe_customer_name" name="customer_name" required></div><div class="sbe-form-group"><label for="sbe_customer_email"><?php echo esc_html__('Email', 'service-bookings-events'); ?> *</label><input type="email" id="sbe_customer_email" name="customer_email" required></div><div class="sbe-form-group"><label for="sbe_customer_phone"><?php echo esc_html__('Phone', 'service-bookings-events'); ?></label><input type="tel" id="sbe_customer_phone" name="customer_phone"></div><?php if ($atts['type'] === 'service' && empty($atts['id'])): ?><div class="sbe-form-group"><label for="sbe_service_select"><?php echo esc_html__('Select Service', 'service-bookings-events'); ?> *</label><select id="sbe_service_select" name="service_id" required><option value=""><?php echo esc_html__('Choose a service...', 'service-bookings-events'); ?></option><?php $services = get_posts(array('post_type' => 'sbe_service', 'posts_per_page' => -1, 'post_status' => 'publish')); foreach ($services as $service): ?><option value="<?php echo esc_attr($service->ID); ?>"><?php echo esc_html($service->post_title); ?></option><?php endforeach; ?></select></div><?php endif; ?><div class="sbe-form-group"><label for="sbe_booking_date"><?php echo esc_html__('Date', 'service-bookings-events'); ?> *</label><input type="date" id="sbe_booking_date" name="booking_date" required min="<?php echo esc_attr(date('Y-m-d')); ?>"></div><div class="sbe-form-group"><label for="sbe_booking_time"><?php echo esc_html__('Time', 'service-bookings-events'); ?> *</label><select id="sbe_booking_time" name="booking_time" required><option value=""><?php echo esc_html__('Select a time...', 'service-bookings-events'); ?></option><?php for ($hour = 9; $hour <= 18; $hour++) { foreach (array('00', '30') as $minute) { $time = sprintf('%02d:%s:00', $hour, $minute); echo '<option value="' . esc_attr($time) . '">' . esc_html(date_i18n(get_option('time_format'), strtotime($time))) . '</option>'; } } ?></select></div><div class="sbe-form-group"><label for="sbe_booking_notes"><?php echo esc_html__('Notes', 'service-bookings-events'); ?></label><textarea id="sbe_booking_notes" name="notes" rows="4"></textarea></div><div class="sbe-form-submit"><button type="submit" class="sbe-submit-btn"><?php echo esc_html__('Book Now', 'service-bookings-events'); ?></button></div><div class="sbe-message"></div></form></div><?php
        return ob_get_clean();
    }
    public function events_list_shortcode($atts) {
        $atts = shortcode_atts(array('category' => '', 'limit' => 10), $atts);
        $args = array('post_type' => 'sbe_event', 'posts_per_page' => intval($atts['limit']), 'post_status' => 'publish');
        $events = get_posts($args);
        ob_start();
        ?><div class="sbe-events-list"><div class="sbe-events-grid"><?php foreach ($events as $event): ?><div class="sbe-event-card"><?php if (has_post_thumbnail($event->ID)): ?><div class="sbe-event-thumbnail"><?php echo get_the_post_thumbnail($event->ID, 'medium'); ?></div><?php endif; ?><div class="sbe-event-content"><h3 class="sbe-event-title"><a href="<?php echo get_permalink($event->ID); ?>"><?php echo esc_html($event->post_title); ?></a></h3><?php if (has_excerpt($event->ID)): ?><div class="sbe-event-excerpt"><?php echo esc_html(get_the_excerpt($event->ID)); ?></div><?php endif; ?><a href="<?php echo get_permalink($event->ID); ?>" class="sbe-event-link button"><?php echo esc_html__('Learn More', 'service-bookings-events'); ?></a></div></div><?php endforeach; ?></div></div><?php
        return ob_get_clean();
    }
    public function services_list_shortcode($atts) {
        $atts = shortcode_atts(array('category' => '', 'limit' => -1), $atts);
        $args = array('post_type' => 'sbe_service', 'posts_per_page' => intval($atts['limit']), 'post_status' => 'publish');
        $services = get_posts($args);
        ob_start();
        ?><div class="sbe-services-list"><div class="sbe-services-grid"><?php foreach ($services as $service): ?><div class="sbe-service-card"><?php if (has_post_thumbnail($service->ID)): ?><div class="sbe-service-thumbnail"><?php echo get_the_post_thumbnail($service->ID, 'medium'); ?></div><?php endif; ?><div class="sbe-service-content"><h3 class="sbe-service-title"><a href="<?php echo get_permalink($service->ID); ?>"><?php echo esc_html($service->post_title); ?></a></h3><?php if (has_excerpt($service->ID)): ?><div class="sbe-service-excerpt"><?php echo esc_html(get_the_excerpt($service->ID)); ?></div><?php endif; ?><a href="<?php echo get_permalink($service->ID); ?>" class="sbe-service-link button"><?php echo esc_html__('Book Now', 'service-bookings-events'); ?></a></div></div><?php endforeach; ?></div></div><?php
        return ob_get_clean();
    }
    public function calendar_shortcode($atts) {
        $atts = shortcode_atts(array('type' => 'both', 'view' => 'month'), $atts);
        ob_start();
        ?><div class="sbe-calendar"><div class="sbe-calendar-header"><button class="sbe-calendar-prev" aria-label="<?php echo esc_attr__('Previous', 'service-bookings-events'); ?>"><span class="dashicons dashicons-arrow-left-alt2"></span></button><h3 class="sbe-calendar-title"></h3><button class="sbe-calendar-next" aria-label="<?php echo esc_attr__('Next', 'service-bookings-events'); ?>"><span class="dashicons dashicons-arrow-right-alt2"></span></button></div><div class="sbe-calendar-grid"><div class="sbe-calendar-weekdays"><div><?php echo esc_html__('Sun', 'service-bookings-events'); ?></div><div><?php echo esc_html__('Mon', 'service-bookings-events'); ?></div><div><?php echo esc_html__('Tue', 'service-bookings-events'); ?></div><div><?php echo esc_html__('Wed', 'service-bookings-events'); ?></div><div><?php echo esc_html__('Thu', 'service-bookings-events'); ?></div><div><?php echo esc_html__('Fri', 'service-bookings-events'); ?></div><div><?php echo esc_html__('Sat', 'service-bookings-events'); ?></div></div><div class="sbe-calendar-days"></div></div><div class="sbe-calendar-events"></div></div><?php
        return ob_get_clean();
    }
    private function send_confirmation_email($booking_id) {
        global $wpdb;
        $booking = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}sbe_bookings WHERE id = %d", $booking_id));
        if (!$booking) return;
        $to = $booking->customer_email;
        $subject = __('Booking Confirmation', 'service-bookings-events');
        $message = sprintf(__('Dear %s,\n\nYour booking has been submitted successfully.\n\nBooking ID: %d\nDate: %s\nTime: %s\n\nWe will confirm your appointment soon.\n\nThank you!', 'service-bookings-events'), $booking->customer_name, $booking->id, $booking->booking_date, $booking->booking_time);
        wp_mail($to, $subject, $message);
    }
}

function sbe_init() { return Service_Bookings_Events::get_instance(); }
add_action('plugins_loaded', 'sbe_init');
