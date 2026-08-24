<?php
/**
 * Calendar Feed and Subscription Links
 * Generates iCal (.ics) feeds and Google Calendar links
 */

if (!defined('ABSPATH')) exit;

class SBE_Calendar_Feed {
    
    private static $instance = null;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        $this->init_hooks();
    }
    
    private function init_hooks() {
        add_action('init', array($this, 'add_calendar_feed_endpoints'));
        add_action('template_redirect', array($this, 'handle_calendar_feed_request'));
        add_action('sbe_booking_email_template', array($this, 'add_calendar_subscribe_to_email'), 10, 2);
        add_shortcode('sbe_calendar_subscribe', array($this, 'calendar_subscribe_shortcode'));
        add_shortcode('sbe_add_to_calendar', array($this, 'add_to_calendar_shortcode'));
    }
    
    public function add_calendar_feed_endpoints() {
        add_rewrite_rule('^feed/sbe-bookings\.ics$', 'index.php?sbe_calendar_feed=bookings', 'top');
        add_rewrite_rule('^feed/sbe-events\.ics$', 'index.php?sbe_calendar_feed=events', 'top');
        add_rewrite_rule('^feed/sbe-user-([^/]+)\.ics$', 'index.php?sbe_calendar_feed=user&sbe_user_id=$matches[1]', 'top');
        add_rewrite_rule('^feed/sbe-booking-([^/]+)\.ics$', 'index.php?sbe_calendar_feed=booking&sbe_booking_id=$matches[1]', 'top');
    }
    
    public function handle_calendar_feed_request() {
        $feed_type = get_query_var('sbe_calendar_feed');
        if (!$feed_type) return;
        
        $enabled = get_option('sbe_calendar_enabled', true);
        if (!$enabled) return;
        
        switch ($feed_type) {
            case 'bookings':
                $this->output_bookings_calendar();
                break;
            case 'events':
                $this->output_events_calendar();
                break;
            case 'user':
                $user_id = get_query_var('sbe_user_id');
                $this->output_user_calendar(intval($user_id));
                break;
            case 'booking':
                $booking_id = get_query_var('sbe_booking_id');
                $this->output_single_booking_calendar(intval($booking_id));
                break;
        }
        exit;
    }
    
    private function output_bookings_calendar() {
        global $wpdb;
        $timezone = get_option('sbe_calendar_timezone', 'UTC');
        
        $bookings = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}sbe_bookings WHERE status IN ('pending', 'confirmed', 'completed') AND booking_date >= CURDATE() - INTERVAL 30 DAY ORDER BY booking_date, booking_time");
        $ics_content = $this->generate_ics_content($bookings, 'bookings', $timezone);
        $this->send_ics_response($ics_content, 'sbe-bookings.ics');
    }
    
    private function output_events_calendar() {
        $timezone = get_option('sbe_calendar_timezone', 'UTC');
        $events = get_posts(array('post_type' => 'sbe_event', 'posts_per_page' => -1, 'post_status' => 'publish'));
        $ics_content = $this->generate_ics_content($events, 'events', $timezone);
        $this->send_ics_response($ics_content, 'sbe-events.ics');
    }
    
    private function output_user_calendar($user_id) {
        global $wpdb;
        if (!$user_id) return;
        
        $timezone = get_option('sbe_calendar_timezone', 'UTC');
        $user = get_user_by('id', $user_id);
        if (!$user) return;
        
        $bookings = $wpdb->get_results($wpdb->prepare("SELECT * FROM {$wpdb->prefix}sbe_bookings WHERE customer_email = %s AND booking_date >= CURDATE() - INTERVAL 30 DAY ORDER BY booking_date, booking_time", $user->user_email));
        $ics_content = $this->generate_ics_content($bookings, 'user', $timezone, array('user_id' => $user_id));
        $this->send_ics_response($ics_content, 'sbe-user-' . $user_id . '.ics');
    }
    
    private function output_single_booking_calendar($booking_id) {
        global $wpdb;
        if (!$booking_id) return;
        
        $timezone = get_option('sbe_calendar_timezone', 'UTC');
        $booking = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}sbe_bookings WHERE id = %d", $booking_id));
        if (!$booking) return;
        
        $ics_content = $this->generate_ics_content(array($booking), 'booking', $timezone);
        $this->send_ics_response($ics_content, 'sbe-booking-' . $booking_id . '.ics');
    }
    
    private function generate_ics_content($items, $type = 'bookings', $timezone = 'UTC', $extra = array()) {
        $calendar_name = get_bloginfo('name');
        $calendar_desc = get_bloginfo('description');
        
        $ics = array();
        $ics[] = 'BEGIN:VCALENDAR';
        $ics[] = 'VERSION:2.0';
        $ics[] = 'PRODID:-//' . $calendar_name . '//Service Bookings & Events//EN';
        $ics[] = 'CALSCALE:GREGORIAN';
        $ics[] = 'METHOD:PUBLISH';
        $ics[] = 'X-WR-CALNAME:' . $this->fold_ical_text($calendar_name);
        $ics[] = 'X-WR-CALDESC:' . $this->fold_ical_text($calendar_desc);
        $ics[] = 'X-WR-TIMEZONE:' . $timezone;
        
        if ($type === 'bookings' || $type === 'user' || $type === 'booking') {
            foreach ($items as $booking) {
                $event = $this->booking_to_ical_event($booking, $timezone);
                if ($event) $ics = array_merge($ics, $event);
            }
        } elseif ($type === 'events') {
            foreach ($items as $event_post) {
                $event = $this->event_post_to_ical_event($event_post, $timezone);
                if ($event) $ics = array_merge($ics, $event);
            }
        }
        
        $ics[] = 'END:VCALENDAR';
        return implode("\r\n", $ics);
    }
    
    private function booking_to_ical_event($booking, $timezone = 'UTC') {
        $title_format = get_option('sbe_calendar_event_title_format', '{service} - {customer}');
        $service_name = $booking->service_id ? get_the_title($booking->service_id) : '';
        $title = str_replace(array('{service}', '{customer}', '{date}', '{time}'), array($service_name, $booking->customer_name, date_i18n(get_option('date_format'), strtotime($booking->booking_date)), date_i18n(get_option('time_format'), strtotime($booking->booking_time))), $title_format);
        
        $start_datetime = $booking->booking_date . ' ' . $booking->booking_time;
        $start_timestamp = strtotime($start_datetime);
        $end_timestamp = $start_timestamp + ($booking->duration * 60);
        
        $start_dt = new DateTime($start_datetime, new DateTimeZone($timezone));
        $end_dt = new DateTime(date('Y-m-d H:i:s', $end_timestamp), new DateTimeZone($timezone));
        
        $event = array();
        $event[] = 'BEGIN:VEVENT';
        $event[] = 'UID:booking-' . $booking->id . '@' . preg_replace('#^https?://#', '', home_url());
        $event[] = 'DTSTAMP:' . gmdate('Ymd\THis\Z');
        $event[] = 'DTSTART;TZID=' . $timezone . ':' . $start_dt->format('Ymd\THis');
        $event[] = 'DTEND;TZID=' . $timezone . ':' . $end_dt->format('Ymd\THis');
        $event[] = 'SUMMARY:' . $this->fold_ical_text($title);
        $event[] = 'DESCRIPTION:' . $this->fold_ical_text(sprintf(__('Booking with %s', 'service-bookings-events'), $booking->customer_name) . '\n' . __('Service', 'service-bookings-events') . ': ' . $service_name);
        $event[] = 'LOCATION:' . $this->fold_ical_text(home_url());
        $event[] = 'STATUS:CONFIRMED';
        $event[] = 'SEQUENCE:0';
        $event[] = 'END:VEVENT';
        return $event;
    }
    
    private function event_post_to_ical_event($event_post, $timezone = 'UTC') {
        $event_date = get_post_meta($event_post->ID, 'sbe_event_date', true);
        $event_time = get_post_meta($event_post->ID, 'sbe_event_time', true);
        if (!$event_date) return null;
        
        $start_datetime = $event_date . ' ' . ($event_time ? $event_time : '09:00:00');
        $start_dt = new DateTime($start_datetime, new DateTimeZone($timezone));
        $end_dt = new DateTime(date('Y-m-d H:i:s', strtotime($start_datetime) + 3600), new DateTimeZone($timezone));
        
        $event = array();
        $event[] = 'BEGIN:VEVENT';
        $event[] = 'UID:event-' . $event_post->ID . '@' . preg_replace('#^https?://#', '', home_url());
        $event[] = 'DTSTAMP:' . gmdate('Ymd\THis\Z');
        $event[] = 'DTSTART;TZID=' . $timezone . ':' . $start_dt->format('Ymd\THis');
        $event[] = 'DTEND;TZID=' . $timezone . ':' . $end_dt->format('Ymd\THis');
        $event[] = 'SUMMARY:' . $this->fold_ical_text($event_post->post_title);
        $event[] = 'DESCRIPTION:' . $this->fold_ical_text(wp_trim_words($event_post->post_content, 50));
        $event[] = 'STATUS:CONFIRMED';
        $event[] = 'SEQUENCE:0';
        $event[] = 'END:VEVENT';
        return $event;
    }
    
    private function fold_ical_text($text) {
        $text = str_replace(array("\r\n", "\n", "\r"), '\n', $text);
        $text = str_replace(',', '\,', $text);
        $text = str_replace(';', '\;', $text);
        if (strlen($text) > 75) $text = rtrim(chunk_split($text, 75, "\r\n "));
        return $text;
    }
    
    private function send_ics_response($content, $filename) {
        header('Content-Type: text/calendar; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . strlen($content));
        echo $content;
    }
    
    public function add_calendar_subscribe_to_email($booking_id, $email_type) {
        $enabled = get_option('sbe_calendar_enabled', true);
        if (!$enabled) return;
        
        $booking_url = home_url('/feed/sbe-booking-' . $booking_id . '.ics');
        $google_url = 'https://calendar.google.com/calendar/render?cid=' . urlencode($booking_url);
        ?>
        <div style="margin-top: 20px; padding-top: 20px; border-top: 1px solid #e0e0e0;">
            <p style="margin: 0 0 10px; font-size: 14px; color: #666;"><?php echo esc_html__('Add to your calendar:', 'service-bookings-events'); ?></p>
            <div style="display: flex; gap: 10px; flex-wrap: wrap;">
                <a href="<?php echo esc_url($booking_url); ?>" style="display: inline-block; padding: 8px 16px; background: #0073aa; color: #fff; text-decoration: none; border-radius: 4px; font-size: 13px;"><?php echo esc_html__('Download .ics', 'service-bookings-events'); ?></a>
                <a href="<?php echo esc_url($google_url); ?>" style="display: inline-block; padding: 8px 16px; background: #4285F4; color: #fff; text-decoration: none; border-radius: 4px; font-size: 13px;"><?php echo esc_html__('Google Calendar', 'service-bookings-events'); ?></a>
            </div>
        </div>
        <?php
    }
    
    public function calendar_subscribe_shortcode($atts) {
        $enabled = get_option('sbe_calendar_enabled', true);
        if (!$enabled) return '';
        
        $atts = shortcode_atts(array('type' => 'all', 'user_id' => get_current_user_id()), $atts);
        $feed_url = '';
        
        switch ($atts['type']) {
            case 'bookings': $feed_url = home_url('/feed/sbe-bookings.ics'); break;
            case 'events': $feed_url = home_url('/feed/sbe-events.ics'); break;
            case 'user': $feed_url = home_url('/feed/sbe-user-' . intval($atts['user_id']) . '.ics'); break;
            default: $feed_url = home_url('/feed/sbe-bookings.ics');
        }
        
        $google_url = 'https://calendar.google.com/calendar/render?cid=' . urlencode($feed_url);
        $apple_url = str_replace('http://', 'webcal://', str_replace('https://', 'http://', $feed_url));
        
        ob_start();
        ?>
        <div class="sbe-calendar-subscribe" style="margin: 30px 0; padding: 20px; background: #f9f9f9; border-radius: 8px;">
            <h3 style="margin: 0 0 15px; font-size: 1.2em;"><?php echo esc_html__('Subscribe to Calendar', 'service-bookings-events'); ?></h3>
            <p style="margin: 0 0 15px; color: #666;"><?php echo esc_html__('Stay updated with your bookings. Subscribe to our calendar feed:', 'service-bookings-events'); ?></p>
            <div style="display: flex; gap: 10px; flex-wrap: wrap;">
                <a href="<?php echo esc_url($feed_url); ?>" style="display: inline-block; padding: 10px 20px; background: #0073aa; color: #fff; text-decoration: none; border-radius: 4px; font-size: 14px;">📅 <?php echo esc_html__('iCal Feed (.ics)', 'service-bookings-events'); ?></a>
                <a href="<?php echo esc_url($google_url); ?>" style="display: inline-block; padding: 10px 20px; background: #4285F4; color: #fff; text-decoration: none; border-radius: 4px; font-size: 14px;">🔵 <?php echo esc_html__('Google Calendar', 'service-bookings-events'); ?></a>
                <a href="<?php echo esc_url($apple_url); ?>" style="display: inline-block; padding: 10px 20px; background: #666; color: #fff; text-decoration: none; border-radius: 4px; font-size: 14px;">🍎 <?php echo esc_html__('Apple/Outlook', 'service-bookings-events'); ?></a>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }
    
    public function add_to_calendar_shortcode($atts) {
        $enabled = get_option('sbe_calendar_enabled', true);
        if (!$enabled) return '';
        
        $atts = shortcode_atts(array('booking_id' => 0, 'event_id' => 0, 'style' => 'button'), $atts);
        $feed_url = '';
        
        if ($atts['booking_id']) $feed_url = home_url('/feed/sbe-booking-' . intval($atts['booking_id']) . '.ics');
        elseif ($atts['event_id']) $feed_url = home_url('/feed/sbe-event-' . intval($atts['event_id']) . '.ics');
        
        if (!$feed_url) return '';
        
        $google_url = 'https://calendar.google.com/calendar/render?cid=' . urlencode($feed_url);
        
        if ($atts['style'] === 'link') {
            return '<a href="' . esc_url($google_url) . '" target="_blank" rel="noopener" style="color: #4285F4; text-decoration: underline;">' . esc_html__('Add to Calendar', 'service-bookings-events') . '</a>';
        }
        
        return '<a href="' . esc_url($google_url) . '" target="_blank" rel="noopener" class="sbe-add-to-calendar-btn" style="display: inline-block; padding: 10px 20px; background: #4285F4; color: #fff; text-decoration: none; border-radius: 4px; font-size: 14px; font-weight: 600;">📅 ' . esc_html__('Add to Calendar', 'service-bookings-events') . '</a>';
    }
}

function sbe_init_calendar_feed() {
    return SBE_Calendar_Feed::get_instance();
}
add_action('plugins_loaded', 'sbe_init_calendar_feed', 40);

function sbe_calendar_flush_rewrites() {
    SBE_Calendar_Feed::get_instance()->add_calendar_feed_endpoints();
    flush_rewrite_rules();
}
add_action('sbe_activate', 'sbe_calendar_flush_rewrites');
