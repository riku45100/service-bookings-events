<?php
/**
 * Plugin Name: Service Bookings & Events
 * Description: Complete booking system with payments, calendar feeds, and staff management
 * Version: 2.0.2
 * Author: Your Name
 * License: GPL v2 or later
 * Text Domain: service-bookings-events
 */

if (!defined('ABSPATH')) exit;

define('SBE_VERSION', '2.0.2');
define('SBE_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('SBE_PLUGIN_URL', plugin_dir_url(__FILE__));

class Service_Bookings_Events {
    private static $instance = null;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
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
        add_action('admin_init', array($this, 'register_settings'));
        add_shortcode('sbe_booking_form', array($this, 'booking_form_shortcode'));
        add_shortcode('sbe_services_list', array($this, 'services_list_shortcode'));
        add_shortcode('sbe_events_list', array($this, 'events_list_shortcode'));
        add_shortcode('sbe_staff_list', array($this, 'staff_list_shortcode'));
    }
    
    public function activate() {
        $this->register_post_types();
        $this->register_taxonomies();
        flush_rewrite_rules();
        $this->create_database_tables();
    }
    
    public function deactivate() {
        flush_rewrite_rules();
    }
    
    private function create_database_tables() {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();
        $table_bookings = $wpdb->prefix . 'sbe_bookings';
        $sql = "CREATE TABLE IF NOT EXISTS $table_bookings (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            service_id bigint(20) NOT NULL,
            event_id bigint(20) DEFAULT NULL,
            staff_id bigint(20) DEFAULT NULL,
            customer_name varchar(255) NOT NULL,
            customer_email varchar(255) NOT NULL,
            customer_phone varchar(50) DEFAULT NULL,
            booking_date date NOT NULL,
            booking_time time NOT NULL,
            duration int(11) DEFAULT 60,
            price decimal(10,2) DEFAULT 0,
            host_name varchar(255) DEFAULT NULL,
            status varchar(50) DEFAULT 'pending',
            notes text,
            payment_status varchar(50) DEFAULT NULL,
            reminder_sent tinyint(1) DEFAULT 0,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY service_id (service_id),
            KEY event_id (event_id),
            KEY staff_id (staff_id),
            KEY booking_date (booking_date)
        ) $charset_collate;";
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }
    
    public function register_post_types() {
        register_post_type('sbe_service', array(
            'labels' => array(
                'name' => __('Services', 'service-bookings-events'),
                'singular_name' => __('Service', 'service-bookings-events'),
                'add_new' => __('Add New', 'service-bookings-events'),
                'add_new_item' => __('Add New Service', 'service-bookings-events'),
                'edit_item' => __('Edit Service', 'service-bookings-events')
            ),
            'public' => true,
            'has_archive' => true,
            'show_ui' => true,
            'show_in_menu' => 'sbe-main',
            'show_in_rest' => true,
            'supports' => array('title', 'editor', 'thumbnail', 'excerpt'),
            'menu_icon' => 'dashicons-calendar-alt',
            'rewrite' => array('slug' => 'services')
        ));
        
        register_post_type('sbe_event', array(
            'labels' => array(
                'name' => __('Events', 'service-bookings-events'),
                'singular_name' => __('Event', 'service-bookings-events'),
                'add_new' => __('Add New', 'service-bookings-events'),
                'add_new_item' => __('Add New Event', 'service-bookings-events'),
                'edit_item' => __('Edit Event', 'service-bookings-events')
            ),
            'public' => true,
            'has_archive' => true,
            'show_ui' => true,
            'show_in_menu' => 'sbe-main',
            'show_in_rest' => true,
            'supports' => array('title', 'editor', 'thumbnail', 'excerpt'),
            'menu_icon' => 'dashicons-calendar',
            'rewrite' => array('slug' => 'events')
        ));
        
        register_post_type('sbe_staff', array(
            'labels' => array(
                'name' => __('Staff', 'service-bookings-events'),
                'singular_name' => __('Staff Member', 'service-bookings-events'),
                'add_new' => __('Add New', 'service-bookings-events'),
                'add_new_item' => __('Add New Staff Member', 'service-bookings-events'),
                'edit_item' => __('Edit Staff Member', 'service-bookings-events'),
                'all_items' => __('All Staff', 'service-bookings-events')
            ),
            'public' => true,
            'has_archive' => true,
            'show_ui' => true,
            'show_in_menu' => 'sbe-main',
            'show_in_rest' => true,
            'supports' => array('title', 'editor', 'thumbnail'),
            'menu_icon' => 'dashicons-businessperson',
            'rewrite' => array('slug' => 'staff')
        ));
    }
    
    public function register_taxonomies() {
        register_taxonomy('sbe_service_category', array('sbe_service'), array(
            'labels' => array(
                'name' => __('Service Categories', 'service-bookings-events'),
                'singular_name' => __('Service Category', 'service-bookings-events'),
                'menu_name' => __('Categories', 'service-bookings-events')
            ),
            'hierarchical' => true,
            'show_ui' => true,
            'show_in_rest' => true,
            'rewrite' => array('slug' => 'service-category')
        ));
        
        register_taxonomy('sbe_event_category', array('sbe_event'), array(
            'labels' => array(
                'name' => __('Event Categories', 'service-bookings-events'),
                'singular_name' => __('Event Category', 'service-bookings-events'),
                'menu_name' => __('Categories', 'service-bookings-events')
            ),
            'hierarchical' => true,
            'show_ui' => true,
            'show_in_rest' => true,
            'rewrite' => array('slug' => 'event-category')
        ));
        
        register_taxonomy('sbe_staff_category', array('sbe_staff'), array(
            'labels' => array(
                'name' => __('Staff Categories', 'service-bookings-events'),
                'singular_name' => __('Staff Category', 'service-bookings-events'),
                'menu_name' => __('Categories', 'service-bookings-events')
            ),
            'hierarchical' => true,
            'show_ui' => true,
            'show_in_rest' => true,
            'rewrite' => array('slug' => 'staff-category')
        ));
    }
    
    public function add_admin_menu() {
        add_menu_page(
            __('Service Bookings & Events', 'service-bookings-events'),
            __('Bookings & Events', 'service-bookings-events'),
            'manage_options',
            'sbe-main',
            array($this, 'admin_dashboard_page'),
            'dashicons-calendar-alt',
            30
        );
        
        add_submenu_page('sbe-main', __('All Bookings', 'service-bookings-events'), __('All Bookings', 'service-bookings-events'), 'manage_options', 'sbe-bookings', array($this, 'bookings_page'));
        add_submenu_page('sbe-main', __('Staff', 'service-bookings-events'), __('Staff', 'service-bookings-events'), 'manage_options', 'sbe-staff', array($this, 'staff_page'));
        add_submenu_page('sbe-main', __('Settings', 'service-bookings-events'), __('Settings', 'service-bookings-events'), 'manage_options', 'sbe-settings', array($this, 'settings_page'));
    }
    
    public function register_settings() {
        register_setting('sbe_settings_group', 'sbe_booking_page_id');
        register_setting('sbe_settings_group', 'sbe_confirmation_email_enabled');
        register_setting('sbe_settings_group', 'sbe_default_booking_duration');
        register_setting('sbe_settings_group', 'sbe_admin_email');
    }
    
    public function admin_dashboard_page() {
        global $wpdb;
        ?>
        <div class="wrap">
            <h1><?php echo esc_html__('Service Bookings & Events Dashboard', 'service-bookings-events'); ?></h1>
            <div class="sbe-dashboard-widgets" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px; margin: 20px 0;">
                <div style="background: #fff; padding: 20px; border: 1px solid #ccd0d4; border-radius: 4px;">
                    <h3 style="margin: 0; color: #666; font-size: 14px;"><?php echo esc_html__('Total Services', 'service-bookings-events'); ?></h3>
                    <p style="font-size: 2em; margin: 10px 0; color: #0073aa;"><?php echo wp_count_posts('sbe_service')->publish; ?></p>
                    <a href="<?php echo admin_url('edit.php?post_type=sbe_service'); ?>"><?php echo esc_html__('View All Services', 'service-bookings-events'); ?></a>
                </div>
                <div style="background: #fff; padding: 20px; border: 1px solid #ccd0d4; border-radius: 4px;">
                    <h3 style="margin: 0; color: #666; font-size: 14px;"><?php echo esc_html__('Total Events', 'service-bookings-events'); ?></h3>
                    <p style="font-size: 2em; margin: 10px 0; color: #0073aa;"><?php echo wp_count_posts('sbe_event')->publish; ?></p>
                    <a href="<?php echo admin_url('edit.php?post_type=sbe_event'); ?>"><?php echo esc_html__('View All Events', 'service-bookings-events'); ?></a>
                </div>
                <div style="background: #fff; padding: 20px; border: 1px solid #ccd0d4; border-radius: 4px;">
                    <h3 style="margin: 0; color: #666; font-size: 14px;"><?php echo esc_html__('Staff Members', 'service-bookings-events'); ?></h3>
                    <p style="font-size: 2em; margin: 10px 0; color: #0073aa;"><?php echo wp_count_posts('sbe_staff')->publish; ?></p>
                    <a href="<?php echo admin_url('edit.php?post_type=sbe_staff'); ?>"><?php echo esc_html__('Manage Staff', 'service-bookings-events'); ?></a>
                </div>
                <div style="background: #fff; padding: 20px; border: 1px solid #ccd0d4; border-radius: 4px;">
                    <h3 style="margin: 0; color: #666; font-size: 14px;"><?php echo esc_html__('Pending Bookings', 'service-bookings-events'); ?></h3>
                    <p style="font-size: 2em; margin: 10px 0; color: #ffc107;"><?php echo $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}sbe_bookings WHERE status = 'pending'"); ?></p>
                    <a href="<?php echo admin_url('admin.php?page=sbe-bookings'); ?>"><?php echo esc_html__('View All Bookings', 'service-bookings-events'); ?></a>
                </div>
            </div>
            <div class="sbe-quick-actions" style="margin-top: 30px;">
                <h2><?php echo esc_html__('Quick Actions', 'service-bookings-events'); ?></h2>
                <a href="<?php echo admin_url('post-new.php?post_type=sbe_service'); ?>" class="button button-primary"><?php echo esc_html__('Add New Service', 'service-bookings-events'); ?></a>
                <a href="<?php echo admin_url('post-new.php?post_type=sbe_event'); ?>" class="button button-primary"><?php echo esc_html__('Add New Event', 'service-bookings-events'); ?></a>
                <a href="<?php echo admin_url('post-new.php?post_type=sbe_staff'); ?>" class="button button-primary"><?php echo esc_html__('Add New Staff', 'service-bookings-events'); ?></a>
            </div>
        </div>
        <?php
    }
    
    public function staff_page() {
        global $wpdb;
        $staff_members = get_posts(array('post_type' => 'sbe_staff', 'posts_per_page' => -1, 'post_status' => 'publish'));
        ?>
        <div class="wrap">
            <h1><?php echo esc_html__('Staff Members', 'service-bookings-events'); ?></h1>
            <a href="<?php echo admin_url('post-new.php?post_type=sbe_staff'); ?>" class="button button-primary" style="margin-bottom: 20px;"><?php echo esc_html__('Add New Staff Member', 'service-bookings-events'); ?></a>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th><?php echo esc_html__('Name', 'service-bookings-events'); ?></th>
                        <th><?php echo esc_html__('Email', 'service-bookings-events'); ?></th>
                        <th><?php echo esc_html__('Phone', 'service-bookings-events'); ?></th>
                        <th><?php echo esc_html__('Profile Link', 'service-bookings-events'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($staff_members)): ?>
                    <tr><td colspan="4"><?php echo esc_html__('No staff members found.', 'service-bookings-events'); ?></td></tr>
                    <?php else: ?>
                    <?php foreach ($staff_members as $staff): 
                        $email = get_post_meta($staff->ID, '_sbe_staff_email', true);
                        $phone = get_post_meta($staff->ID, '_sbe_staff_phone', true);
                        $profile_link = get_post_meta($staff->ID, '_sbe_staff_profile_link', true);
                        if (empty($profile_link)) $profile_link = get_permalink($staff->ID);
                    ?>
                    <tr>
                        <td><strong><a href="<?php echo get_edit_post_link($staff->ID); ?>"><?php echo esc_html($staff->post_title); ?></a></strong></td>
                        <td><?php echo esc_html($email); ?></td>
                        <td><?php echo esc_html($phone); ?></td>
                        <td><a href="<?php echo esc_url($profile_link); ?>" target="_blank"><?php echo esc_html__('View Profile', 'service-bookings-events'); ?></a></td>
                    </tr>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php
    }
    
    public function settings_page() {
        if (isset($_POST['sbe_save_settings']) && wp_verify_nonce($_POST['sbe_settings_nonce'], 'sbe_save_settings')) {
            update_option('sbe_confirmation_email_enabled', isset($_POST['sbe_confirmation_email_enabled']) ? true : false);
            update_option('sbe_default_booking_duration', intval($_POST['sbe_default_booking_duration']));
            update_option('sbe_admin_email', sanitize_email($_POST['sbe_admin_email']));
            update_option('sbe_booking_page_id', intval($_POST['sbe_booking_page_id']));
            echo '<div class="notice notice-success"><p>' . esc_html__('Settings saved!', 'service-bookings-events') . '</p></div>';
        }
        $email_enabled = get_option('sbe_confirmation_email_enabled', true);
        $duration = get_option('sbe_default_booking_duration', 60);
        $admin_email = get_option('sbe_admin_email', get_option('admin_email'));
        $booking_page_id = get_option('sbe_booking_page_id', 0);
        ?>
        <div class="wrap">
            <h1><?php echo esc_html__('Settings', 'service-bookings-events'); ?></h1>
            <form method="post">
                <?php wp_nonce_field('sbe_save_settings', 'sbe_settings_nonce'); ?>
                <table class="form-table">
                    <tr>
                        <th><?php echo esc_html__('Booking Page', 'service-bookings-events'); ?></th>
                        <td>
                            <select name="sbe_booking_page_id" style="min-width: 300px;">
                                <option value="0"><?php echo esc_html__('— Select a page —', 'service-bookings-events'); ?></option>
                                <?php 
                                $pages = get_pages(array('post_type' => 'page', 'number' => -1)); 
                                foreach ($pages as $page): 
                                ?>
                                <option value="<?php echo esc_attr($page->ID); ?>" <?php selected($booking_page_id, $page->ID); ?>><?php echo esc_html($page->post_title); ?></option>
                                <?php endforeach; ?>
                            </select>
                            <p class="description"><?php echo esc_html__('Select the page where your booking form is located.', 'service-bookings-events'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th><?php echo esc_html__('Enable Confirmation Emails', 'service-bookings-events'); ?></th>
                        <td>
                            <label><input type="checkbox" name="sbe_confirmation_email_enabled" value="1" <?php checked($email_enabled); ?>> <?php echo esc_html__('Send emails to customers', 'service-bookings-events'); ?></label>
                        </td>
                    </tr>
                    <tr>
                        <th><?php echo esc_html__('Default Booking Duration (minutes)', 'service-bookings-events'); ?></th>
                        <td><input type="number" name="sbe_default_booking_duration" value="<?php echo esc_attr($duration); ?>" class="small-text"></td>
                    </tr>
                    <tr>
                        <th><?php echo esc_html__('Admin Notification Email', 'service-bookings-events'); ?></th>
                        <td><input type="email" name="sbe_admin_email" value="<?php echo esc_attr($admin_email); ?>" class="regular-text"></td>
                    </tr>
                </table>
                <?php submit_button(__('Save Settings', 'service-bookings-events'), 'primary', 'sbe_save_settings'); ?>
            </form>
        </div>
        <?php
    }
    
    public function bookings_page() {
        global $wpdb;
        $bookings = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}sbe_bookings ORDER BY created_at DESC");
        ?>
        <div class="wrap">
            <h1><?php echo esc_html__('All Bookings', 'service-bookings-events'); ?></h1>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th><?php echo esc_html__('ID', 'service-bookings-events'); ?></th>
                        <th><?php echo esc_html__('Customer', 'service-bookings-events'); ?></th>
                        <th><?php echo esc_html__('Service/Event', 'service-bookings-events'); ?></th>
                        <th><?php echo esc_html__('Staff', 'service-bookings-events'); ?></th>
                        <th><?php echo esc_html__('Date & Time', 'service-bookings-events'); ?></th>
                        <th><?php echo esc_html__('Status', 'service-bookings-events'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($bookings)): ?>
                    <tr><td colspan="6"><?php echo esc_html__('No bookings found.', 'service-bookings-events'); ?></td></tr>
                    <?php else: ?>
                    <?php foreach ($bookings as $booking): ?>
                    <tr>
                        <td>#<?php echo esc_html($booking->id); ?></td>
                        <td><?php echo esc_html($booking->customer_name); ?><br><small><?php echo esc_html($booking->customer_email); ?></small></td>
                        <td><?php $service = get_post($booking->service_id); echo $service ? esc_html($service->post_title) : 'N/A'; ?></td>
                        <td><?php if ($booking->staff_id): $staff = get_post($booking->staff_id); echo $staff ? esc_html($staff->post_title) : ''; else echo '-'; endif; ?></td>
                        <td><?php echo esc_html(date_i18n(get_option('date_format'), strtotime($booking->booking_date)) . ' ' . date_i18n(get_option('time_format'), strtotime($booking->booking_time))); ?></td>
                        <td><span class="sbe-status"><?php echo esc_html(ucfirst($booking->status)); ?></span></td>
                    </tr>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php
    }
    
    public function booking_form_shortcode($atts) {
        $atts = shortcode_atts(array('type' => 'service', 'id' => ''), $atts);
        $selected_service = isset($_GET['service']) ? intval($_GET['service']) : 0;
        if (empty($atts['id']) && $selected_service) $atts['id'] = $selected_service;
        ob_start();
        ?>
        <div class="sbe-booking-form">
            <form class="sbe-form">
                <div class="sbe-form-group">
                    <label for="sbe_customer_name"><?php echo esc_html__('Your Name', 'service-bookings-events'); ?> *</label>
                    <input type="text" id="sbe_customer_name" name="customer_name" required>
                </div>
                <div class="sbe-form-group">
                    <label for="sbe_customer_email"><?php echo esc_html__('Email', 'service-bookings-events'); ?> *</label>
                    <input type="email" id="sbe_customer_email" name="customer_email" required>
                </div>
                <div class="sbe-form-group">
                    <label for="sbe_customer_phone"><?php echo esc_html__('Phone', 'service-bookings-events'); ?></label>
                    <input type="tel" id="sbe_customer_phone" name="customer_phone">
                </div>
                <?php if ($atts['type'] === 'service' && empty($atts['id'])): ?>
                <div class="sbe-form-group">
                    <label for="sbe_service_select"><?php echo esc_html__('Select Service', 'service-bookings-events'); ?> *</label>
                    <select id="sbe_service_select" name="service_id" required>
                        <option value=""><?php echo esc_html__('Choose a service...', 'service-bookings-events'); ?></option>
                        <?php 
                        $services = get_posts(array('post_type' => 'sbe_service', 'posts_per_page' => -1, 'post_status' => 'publish')); 
                        foreach ($services as $service): 
                            $host = get_post_meta($service->ID, '_sbe_host', true);
                            $duration = get_post_meta($service->ID, '_sbe_duration', true);
                            $price = get_post_meta($service->ID, '_sbe_price', true);
                            if (!$duration) $duration = get_option('sbe_default_booking_duration', 60);
                            $label = $service->post_title . ' (';
                            if ($price > 0) $label .= number_format($price, 2) . ' | ';
                            $label .= $duration . ' ' . __('min', 'service-bookings-events');
                            if ($host) $label .= ' | ' . $host;
                            $label .= ')';
                        ?>
                        <option value="<?php echo esc_attr($service->ID); ?>" <?php selected($selected_service, $service->ID); ?>><?php echo esc_html($label); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?php endif; ?>
                <div class="sbe-form-group">
                    <label for="sbe_booking_date"><?php echo esc_html__('Date', 'service-bookings-events'); ?> *</label>
                    <input type="date" id="sbe_booking_date" name="booking_date" required min="<?php echo esc_attr(date('Y-m-d')); ?>">
                </div>
                <div class="sbe-form-group">
                    <label for="sbe_booking_time"><?php echo esc_html__('Time', 'service-bookings-events'); ?> *</label>
                    <select id="sbe_booking_time" name="booking_time" required>
                        <option value=""><?php echo esc_html__('Select a time...', 'service-bookings-events'); ?></option>
                        <?php for ($hour = 9; $hour <= 18; $hour++) { foreach (array('00', '30') as $minute) { $time = sprintf('%02d:%s:00', $hour, $minute); echo '<option value="' . esc_attr($time) . '">' . esc_html(date_i18n(get_option('time_format'), strtotime($time))) . '</option>'; } } ?>
                    </select>
                </div>
                <div class="sbe-form-group">
                    <label for="sbe_booking_notes"><?php echo esc_html__('Notes', 'service-bookings-events'); ?></label>
                    <textarea id="sbe_booking_notes" name="notes" rows="4"></textarea>
                </div>
                <div class="sbe-form-submit">
                    <button type="submit" class="sbe-submit-btn"><?php echo esc_html__('Book Now', 'service-bookings-events'); ?></button>
                </div>
                <div class="sbe-message"></div>
            </form>
        </div>
        <?php
        return ob_get_clean();
    }
    
    public function services_list_shortcode($atts) {
        $atts = shortcode_atts(array('category' => '', 'limit' => -1, 'layout' => 'grid'), $atts);
        $args = array('post_type' => 'sbe_service', 'posts_per_page' => intval($atts['limit']), 'post_status' => 'publish');
        if (!empty($atts['category'])) {
            $args['tax_query'] = array(array('taxonomy' => 'sbe_service_category', 'field' => 'slug', 'terms' => $atts['category']));
        }
        $services = get_posts($args);
        $booking_page_id = get_option('sbe_booking_page_id', 0);
        $booking_url = $booking_page_id ? get_permalink($booking_page_id) : '#';
        $layout_class = $atts['layout'] === 'list' ? 'sbe-services-list' : 'sbe-services-grid';
        ob_start();
        ?>
        <div class="sbe-services <?php echo esc_attr($layout_class); ?>">
            <div class="sbe-services-inner">
                <?php foreach ($services as $service): ?>
                <div class="sbe-service-card">
                    <?php if (has_post_thumbnail($service->ID)): ?>
                    <div class="sbe-service-thumbnail"><?php echo get_the_post_thumbnail($service->ID, 'medium'); ?></div>
                    <?php endif; ?>
                    <div class="sbe-service-content">
                        <h3 class="sbe-service-title"><a href="<?php echo get_permalink($service->ID); ?>"><?php echo esc_html($service->post_title); ?></a></h3>
                        <?php 
                        $host = get_post_meta($service->ID, '_sbe_host', true); 
                        $duration = get_post_meta($service->ID, '_sbe_duration', true); 
                        $price = get_post_meta($service->ID, '_sbe_price', true); 
                        if (!$duration) $duration = get_option('sbe_default_booking_duration', 60); 
                        if ($price > 0 || $host || $duration): 
                        ?>
                        <p class="sbe-service-meta">
                            <?php if ($price > 0): ?><span class="sbe-price"><?php echo esc_html(number_format($price, 2)); ?></span><?php endif; ?>
                            <?php if ($duration): ?><span class="sbe-duration"><?php echo esc_html($duration); ?> <?php echo esc_html__('min', 'service-bookings-events'); ?></span><?php endif; ?>
                            <?php if ($host): ?><span class="sbe-host"><?php echo esc_html__('with', 'service-bookings-events'); ?> <?php echo esc_html($host); ?></span><?php endif; ?>
                        </p>
                        <?php endif; ?>
                        <?php if (has_excerpt($service->ID)): ?>
                        <div class="sbe-service-excerpt"><?php echo esc_html(get_the_excerpt($service->ID)); ?></div>
                        <?php endif; ?>
                        <?php if ($booking_page_id): ?>
                        <a href="<?php echo esc_url(add_query_arg('service', $service->ID, $booking_url)); ?>" class="sbe-service-link button"><?php echo esc_html__('Book Now', 'service-bookings-events'); ?></a>
                        <?php else: ?>
                        <a href="<?php echo get_permalink($service->ID); ?>" class="sbe-service-link button"><?php echo esc_html__('Learn More', 'service-bookings-events'); ?></a>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }
    
    public function events_list_shortcode($atts) {
        $atts = shortcode_atts(array('category' => '', 'limit' => 10, 'layout' => 'grid'), $atts);
        $args = array('post_type' => 'sbe_event', 'posts_per_page' => intval($atts['limit']), 'post_status' => 'publish');
        if (!empty($atts['category'])) {
            $args['tax_query'] = array(array('taxonomy' => 'sbe_event_category', 'field' => 'slug', 'terms' => $atts['category']));
        }
        $events = get_posts($args);
        $layout_class = $atts['layout'] === 'list' ? 'sbe-events-list' : 'sbe-events-grid';
        ob_start();
        ?>
        <div class="sbe-events <?php echo esc_attr($layout_class); ?>">
            <div class="sbe-events-inner">
                <?php foreach ($events as $event): ?>
                <div class="sbe-event-card">
                    <?php if (has_post_thumbnail($event->ID)): ?>
                    <div class="sbe-event-thumbnail"><?php echo get_the_post_thumbnail($event->ID, 'medium'); ?></div>
                    <?php endif; ?>
                    <div class="sbe-event-content">
                        <h3 class="sbe-event-title"><a href="<?php echo get_permalink($event->ID); ?>"><?php echo esc_html($event->post_title); ?></a></h3>
                        <?php $host = get_post_meta($event->ID, '_sbe_host', true); $price = get_post_meta($event->ID, '_sbe_price', true); if ($price > 0 || $host): ?>
                        <p class="sbe-event-meta">
                            <?php if ($price > 0): ?><span class="sbe-price"><?php echo esc_html(number_format($price, 2)); ?></span><?php endif; ?>
                            <?php if ($host): ?><span class="sbe-host"><?php echo esc_html__('Host:', 'service-bookings-events'); ?> <?php echo esc_html($host); ?></span><?php endif; ?>
                        </p>
                        <?php endif; ?>
                        <?php if (has_excerpt($event->ID)): ?>
                        <div class="sbe-event-excerpt"><?php echo esc_html(get_the_excerpt($event->ID)); ?></div>
                        <?php endif; ?>
                        <a href="<?php echo get_permalink($event->ID); ?>" class="sbe-event-link button"><?php echo esc_html__('Learn More', 'service-bookings-events'); ?></a>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }
    
    public function staff_list_shortcode($atts) {
        $atts = shortcode_atts(array('category' => '', 'limit' => -1, 'layout' => 'grid'), $atts);
        $args = array('post_type' => 'sbe_staff', 'posts_per_page' => intval($atts['limit']), 'post_status' => 'publish');
        if (!empty($atts['category'])) {
            $args['tax_query'] = array(array('taxonomy' => 'sbe_staff_category', 'field' => 'slug', 'terms' => $atts['category']));
        }
        $staff_members = get_posts($args);
        $layout_class = $atts['layout'] === 'list' ? 'sbe-staff-list' : 'sbe-staff-grid';
        ob_start();
        ?>
        <div class="sbe-staff <?php echo esc_attr($layout_class); ?>">
            <div class="sbe-staff-inner">
                <?php foreach ($staff_members as $staff): 
                    $email = get_post_meta($staff->ID, '_sbe_staff_email', true); 
                    $phone = get_post_meta($staff->ID, '_sbe_staff_phone', true); 
                    $bio = get_post_meta($staff->ID, '_sbe_staff_bio', true); 
                    $profile_link = get_post_meta($staff->ID, '_sbe_staff_profile_link', true); 
                    if (empty($profile_link)) $profile_link = get_permalink($staff->ID); 
                ?>
                <div class="sbe-staff-card">
                    <?php if (has_post_thumbnail($staff->ID)): ?>
                    <div class="sbe-staff-thumbnail"><?php echo get_the_post_thumbnail($staff->ID, 'medium'); ?></div>
                    <?php endif; ?>
                    <div class="sbe-staff-content">
                        <h3 class="sbe-staff-name"><a href="<?php echo esc_url($profile_link); ?>"><?php echo esc_html($staff->post_title); ?></a></h3>
                        <?php if ($bio): ?>
                        <div class="sbe-staff-bio"><?php echo esc_html($bio); ?></div>
                        <?php endif; ?>
                        <?php if ($email || $phone): ?>
                        <p class="sbe-staff-contact">
                            <?php if ($email): ?><span class="sbe-staff-email"><?php echo esc_html($email); ?></span><?php endif; ?>
                            <?php if ($phone): ?><span class="sbe-staff-phone"><?php echo esc_html($phone); ?></span><?php endif; ?>
                        </p>
                        <?php endif; ?>
                        <a href="<?php echo esc_url($profile_link); ?>" class="sbe-staff-link button"><?php echo esc_html__('View Profile', 'service-bookings-events'); ?></a>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }
}

function sbe_init() { 
    return Service_Bookings_Events::get_instance(); 
}
add_action('plugins_loaded', 'sbe_init');
