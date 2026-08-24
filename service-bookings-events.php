<?php
/**
 * Plugin Name: Service Bookings & Events
 * Description: Complete booking system with payments (Stripe/PayPal), subscriptions, recurring billing, and calendar feeds (iCal/Google Calendar)
 * Version: 1.3.0
 * Author: Your Name
 * License: GPL v2 or later
 * Text Domain: service-bookings-events
 */

if (!defined('ABSPATH')) exit;

define('SBE_VERSION', '1.3.0');
define('SBE_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('SBE_PLUGIN_URL', plugin_dir_url(__FILE__));

// Include all plugin files
require_once SBE_PLUGIN_DIR . 'includes/class-sbe-payment-gateway.php';
require_once SBE_PLUGIN_DIR . 'includes/class-sbe-payment-settings.php';
require_once SBE_PLUGIN_DIR . 'includes/class-sbe-subscriptions.php';
require_once SBE_PLUGIN_DIR . 'includes/class-sbe-subscription-settings.php';
require_once SBE_PLUGIN_DIR . 'includes/class-sbe-calendar-feed.php';

// Activation hook
function sbe_activate() {
    flush_rewrite_rules();
}
register_activation_hook(__FILE__, 'sbe_activate');

// Deactivation hook
function sbe_deactivate() {
    flush_rewrite_rules();
}
register_deactivation_hook(__FILE__, 'sbe_deactivate');

// Initialize plugin
function sbe_init() {
    // All components auto-initialize via their constructors
}
add_action('plugins_loaded', 'sbe_init');
