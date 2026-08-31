<?php
/**
 * Plugin Name: Service Bookings & Events
 * Description: Complete booking system
 * Version: 2.0.3
 * Author: Your Name
 * License: GPL v2 or later
 * Text Domain: service-bookings-events
 */

if (!defined('ABSPATH')) {
    exit;
}

define('SBE_VERSION', '2.0.3');
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
        add_action('init', array($this, 'register_post_types'));
        add_action('admin_menu', array($this, 'add_admin_menu'));
    }
    
    public function register_post_types() {
        register_post_type('sbe_service', array(
            'labels' => array(
                'name' => __('Services', 'service-bookings-events'),
                'singular_name' => __('Service', 'service-bookings-events')
            ),
            'public' => true,
            'show_ui' => true,
            'show_in_menu' => 'sbe-main',
            'supports' => array('title', 'editor', 'thumbnail')
        ));
        
        register_post_type('sbe_event', array(
            'labels' => array(
                'name' => __('Events', 'service-bookings-events'),
                'singular_name' => __('Event', 'service-bookings-events')
            ),
            'public' => true,
            'show_ui' => true,
            'show_in_menu' => 'sbe-main',
            'supports' => array('title', 'editor', 'thumbnail')
        ));
        
        register_post_type('sbe_staff', array(
            'labels' => array(
                'name' => __('Staff', 'service-bookings-events'),
                'singular_name' => __('Staff Member', 'service-bookings-events')
            ),
            'public' => true,
            'show_ui' => true,
            'show_in_menu' => 'sbe-main',
            'supports' => array('title', 'editor', 'thumbnail')
        ));
    }
    
    public function add_admin_menu() {
        add_menu_page(
            __('Service Bookings & Events', 'service-bookings-events'),
            __('Bookings & Events', 'service-bookings-events'),
            'manage_options',
            'sbe-main',
            array($this, 'dashboard_page'),
            'dashicons-calendar-alt',
            30
        );
    }
    
    public function dashboard_page() {
        echo '<div class="wrap"><h1>Service Bookings & Events</h1><p>Plugin is working!</p></div>';
    }
}

function sbe_init() {
    return Service_Bookings_Events::get_instance();
}
add_action('plugins_loaded', 'sbe_init');
