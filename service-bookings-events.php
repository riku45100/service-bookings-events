<?php
/**
 * Plugin Name: Service Bookings & Events
 * Description: Complete booking system
 * Version: 2.1.0
 * Author: Your Name
 * License: GPL v2 or later
 * Text Domain: service-bookings-events
 */

if (!defined('ABSPATH')) exit;

define('SBE_VERSION', '2.1.0');

class Service_Bookings_Events {
    private static $instance = null;
    public static function get_instance() { if (null === self::$instance) self::$instance = new self(); return self::$instance; }
    private function __construct() {
        register_activation_hook(__FILE__, array($this, 'activate'));
        add_action('init', array($this, 'register_post_types'));
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_init', array($this, 'register_settings'));
        add_shortcode('sbe_booking_form', array($this, 'booking_form_shortcode'));
        add_shortcode('sbe_services_list', array($this, 'services_list_shortcode'));
        add_shortcode('sbe_events_list', array($this, 'events_list_shortcode'));
        add_shortcode('sbe_staff_list', array($this, 'staff_list_shortcode'));
        add_action('wp_ajax_sbe_submit_booking', array($this, 'handle_booking_submission'));
        add_action('wp_ajax_nopriv_sbe_submit_booking', array($this, 'handle_booking_submission'));
    }
    public function activate() { $this->register_post_types(); $this->create_database_tables(); flush_rewrite_rules(); }
    private function create_database_tables() {
        global $wpdb;
        $table = $wpdb->prefix . 'sbe_bookings';
        $sql = "CREATE TABLE IF NOT EXISTS $table (id bigint(20) NOT NULL AUTO_INCREMENT, service_id bigint(20) NOT NULL, customer_name varchar(255) NOT NULL, customer_email varchar(255) NOT NULL, customer_phone varchar(50) DEFAULT NULL, booking_date date NOT NULL, booking_time time NOT NULL, duration int(11) DEFAULT 60, status varchar(50) DEFAULT 'pending', notes text, created_at datetime DEFAULT CURRENT_TIMESTAMP, PRIMARY KEY (id)) " . $wpdb->get_charset_collate();
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql);
    }
    public function register_post_types() {
        register_post_type('sbe_service', array('labels' => array('name' => __('Services', 'service-bookings-events'), 'singular_name' => __('Service', 'service-bookings-events')), 'public' => true, 'show_ui' => true, 'show_in_menu' => 'sbe-main', 'supports' => array('title', 'editor', 'thumbnail')));
        register_post_type('sbe_event', array('labels' => array('name' => __('Events', 'service-bookings-events'), 'singular_name' => __('Event', 'service-bookings-events')), 'public' => true, 'show_ui' => true, 'show_in_menu' => 'sbe-main', 'supports' => array('title', 'editor', 'thumbnail')));
        register_post_type('sbe_staff', array('labels' => array('name' => __('Staff', 'service-bookings-events'), 'singular_name' => __('Staff Member', 'service-bookings-events')), 'public' => true, 'show_ui' => true, 'show_in_menu' => 'sbe-main', 'supports' => array('title', 'editor', 'thumbnail')));
    }
    public function add_admin_menu() {
        add_menu_page(__('Service Bookings & Events', 'service-bookings-events'), __('Bookings & Events', 'service-bookings-events'), 'manage_options', 'sbe-main', array($this, 'dashboard_page'), 'dashicons-calendar-alt', 30);
        add_submenu_page('sbe-main', __('All Bookings', 'service-bookings-events'), __('All Bookings', 'service-bookings-events'), 'manage_options', 'sbe-bookings', array($this, 'bookings_page'));
        add_submenu_page('sbe-main', __('Settings', 'service-bookings-events'), __('Settings', 'service-bookings-events'), 'manage_options', 'sbe-settings', array($this, 'settings_page'));
    }
    public function register_settings() { register_setting('sbe_settings_group', 'sbe_booking_page_id'); register_setting('sbe_settings_group', 'sbe_default_booking_duration'); }
    public function dashboard_page() { global $wpdb; $pending = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}sbe_bookings WHERE status = 'pending'"); echo '<div class="wrap"><h1>Service Bookings & Events</h1><p>Services: ' . esc_html(wp_count_posts('sbe_service')->publish) . '</p><p>Events: ' . esc_html(wp_count_posts('sbe_event')->publish) . '</p><p>Staff: ' . esc_html(wp_count_posts('sbe_staff')->publish) . '</p><p>Pending bookings: ' . esc_html($pending) . '</p></div>'; }
    public function bookings_page() { global $wpdb; $bookings = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}sbe_bookings ORDER BY created_at DESC"); echo '<div class="wrap"><h1>All Bookings</h1><table class="wp-list-table widefat striped"><thead><tr><th>ID</th><th>Customer</th><th>Service</th><th>Date &amp; Time</th><th>Status</th></tr></thead><tbody>';
        if (empty($bookings)) { echo '<tr><td colspan="5">No bookings found.</td></tr>'; }
        else { foreach ($bookings as $booking) { $service = get_post($booking->service_id); echo '<tr><td>' . esc_html($booking->id) . '</td><td>' . esc_html($booking->customer_name) . '<br><small>' . esc_html($booking->customer_email) . '</small></td><td>' . ($service ? esc_html($service->post_title) : '-') . '</td><td>' . esc_html($booking->booking_date . ' ' . $booking->booking_time) . '</td><td>' . esc_html($booking->status) . '</td></tr>'; } }
        echo '</tbody></table></div>';
    }
    public function settings_page() {
        if (isset($_POST['sbe_save_settings']) && check_admin_referer('sbe_save_settings', 'sbe_settings_nonce')) {
            update_option('sbe_booking_page_id', isset($_POST['sbe_booking_page_id']) ? absint($_POST['sbe_booking_page_id']) : 0);
            update_option('sbe_default_booking_duration', isset($_POST['sbe_default_booking_duration']) ? absint($_POST['sbe_default_booking_duration']) : 60);
            echo '<div class="notice notice-success"><p>Settings saved.</p></div>';
        }
        $booking_page_id = absint(get_option('sbe_booking_page_id', 0));
        $duration = absint(get_option('sbe_default_booking_duration', 60));
        echo '<div class="wrap"><h1>Settings</h1><form method="post">';
        wp_nonce_field('sbe_save_settings', 'sbe_settings_nonce');
        echo '<table class="form-table"><tr><th>Booking Page</th><td>'; wp_dropdown_pages(array('name' => 'sbe_booking_page_id', 'selected' => $booking_page_id, 'show_option_none' => __('— Select —'))); echo '</td></tr>';
        echo '<tr><th>Default Duration</th><td><input type="number" name="sbe_default_booking_duration" value="' . esc_attr($duration) . '" min="15" step="15"> minutes</td></tr></table>';
        submit_button('Save Settings', 'primary', 'sbe_save_settings');
        echo '</form></div>';
    }
    public function handle_booking_submission() {
        check_ajax_referer('sbe_booking_nonce', 'nonce');
        $service_id = isset($_POST['service_id']) ? absint($_POST['service_id']) : 0;
        $name = isset($_POST['customer_name']) ? sanitize_text_field(wp_unslash($_POST['customer_name'])) : '';
        $email = isset($_POST['customer_email']) ? sanitize_email(wp_unslash($_POST['customer_email'])) : '';
        $phone = isset($_POST['customer_phone']) ? sanitize_text_field(wp_unslash($_POST['customer_phone'])) : '';
        $date = isset($_POST['booking_date']) ? sanitize_text_field(wp_unslash($_POST['booking_date'])) : '';
        $time = isset($_POST['booking_time']) ? sanitize_text_field(wp_unslash($_POST['booking_time'])) : '';
        $notes = isset($_POST['notes']) ? sanitize_textarea_field(wp_unslash($_POST['notes'])) : '';
        if (!$service_id || !$name || !is_email($email) || !$date || !$time) { wp_send_json_error(array('message' => 'Please complete all required fields.')); }
        global $wpdb;
        $saved = $wpdb->insert($wpdb->prefix . 'sbe_bookings', array('service_id' => $service_id, 'customer_name' => $name, 'customer_email' => $email, 'customer_phone' => $phone, 'booking_date' => $date, 'booking_time' => $time, 'duration' => absint(get_option('sbe_default_booking_duration', 60)), 'status' => 'pending', 'notes' => $notes), array('%d', '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%s'));
        if (false === $saved) { wp_send_json_error(array('message' => 'Could not save the booking.')); }
        wp_send_json_success(array('message' => 'Booking submitted successfully.'));
    }
    public function booking_form_shortcode() {
        $selected = isset($_GET['service']) ? absint($_GET['service']) : 0;
        $services = get_posts(array('post_type' => 'sbe_service', 'posts_per_page' => -1, 'post_status' => 'publish'));
        ob_start(); ?>
        <form class="sbe-form">
            <p><label>Name *<br><input type="text" name="customer_name" required></label></p>
            <p><label>Email *<br><input type="email" name="customer_email" required></label></p>
            <p><label>Phone<br><input type="tel" name="customer_phone"></label></p>
            <p><label>Service *<br><select name="service_id" required><option value="">Choose a service</option><?php foreach ($services as $service) : ?><option value="<?php echo esc_attr($service->ID); ?>" <?php selected($selected, $service->ID); ?>><?php echo esc_html($service->post_title); ?></option><?php endforeach; ?></select></label></p>
            <p><label>Date *<br><input type="date" name="booking_date" required min="<?php echo esc_attr(wp_date('Y-m-d')); ?>"></label></p>
            <p><label>Time *<br><input type="time" name="booking_time" required></label></p>
            <p><label>Notes<br><textarea name="notes"></textarea></label></p>
            <p><button type="submit">Book Now</button></p>
            <div class="sbe-message" aria-live="polite"></div>
        </form>
        <script>jQuery(function($){$('.sbe-form').on('submit',function(e){e.preventDefault();var f=$(this),m=f.find('.sbe-message'),d=f.serializeArray();d.push({name:'action',value:'sbe_submit_booking'},{name:'nonce',value:'<?php echo esc_js(wp_create_nonce('sbe_booking_nonce')); ?>'});$.post('<?php echo admin_url('admin-ajax.php'); ?>',$.param(d),function(r){m.text(r.data.message);if(r.success){f[0].reset();}});});});</script>
        <?php return ob_get_clean();
    }
    private function render_posts($type) {
        $posts = get_posts(array('post_type' => $type, 'post_status' => 'publish', 'posts_per_page' => -1));
        ob_start(); echo '<div class="sbe-list">';
        foreach ($posts as $post) {
            echo '<article class="sbe-card">';
            if (has_post_thumbnail($post->ID)) echo get_the_post_thumbnail($post->ID, 'medium');
            echo '<h3><a href="' . esc_url(get_permalink($post->ID)) . '">' . esc_html($post->post_title) . '</a></h3>';
            echo '<div>' . esc_html(get_the_excerpt($post->ID)) . '</div>';
            echo '</article>';
        }
        echo '</div>';
        return ob_get_clean();
    }
    public function services_list_shortcode() { return $this->render_posts('sbe_service'); }
    public function events_list_shortcode() { return $this->render_posts('sbe_event'); }
    public function staff_list_shortcode() { return $this->render_posts('sbe_staff'); }
}

function sbe_init() { return Service_Bookings_Events::get_instance(); }
add_action('plugins_loaded', 'sbe_init');
