# Service Bookings & Events

Complete WordPress plugin for service bookings and events with Stripe/PayPal payments, subscriptions, recurring billing, and iCal/Google Calendar integration.

## Features

### Core Features
- ✅ Service bookings management
- ✅ Events management with custom post types
- ✅ Custom taxonomies (categories, locations)
- ✅ Admin dashboard with statistics
- ✅ Frontend booking forms via shortcodes

### Payment Integration
- ✅ Stripe payment processing
- ✅ PayPal payment integration
- ✅ Test/Live mode support
- ✅ Webhook handling
- ✅ Payment confirmation emails
- ✅ Transaction logging

### Subscriptions & Recurring Billing
- ✅ Unlimited subscription plans
- ✅ Stripe Billing integration
- ✅ PayPal Subscriptions support
- ✅ Free trials and setup fees
- ✅ Customer self-service portal
- ✅ Automated renewal emails

### Calendar Integration
- ✅ iCal (.ics) feed generation
- ✅ Google Calendar subscription
- ✅ Apple/Outlook calendar support
- ✅ User-specific calendar feeds
- ✅ Single booking calendar links

## Installation

1. Upload `service-bookings-events` folder to `/wp-content/plugins/`
2. Activate via WordPress Admin → Plugins
3. Configure settings under Bookings & Events menu

## Requirements

- WordPress 5.0+
- PHP 7.4+
- Stripe PHP SDK: `composer require stripe/stripe-php`
- SSL certificate (for live payments)

## Shortcodes

- `[sbe_booking_form]` - Booking form
- `[sbe_events_list]` - Events list
- `[sbe_services_list]` - Services list
- `[sbe_calendar]` - Calendar view
- `[sbe_subscription_form]` - Subscription signup
- `[sbe_manage_subscriptions]` - Manage subscriptions
- `[sbe_pricing_table]` - Pricing plans
- `[sbe_calendar_subscribe]` - Calendar subscription buttons
- `[sbe_add_to_calendar]` - Add to calendar button

## Documentation

See individual guide files:
- PAYMENT-INTEGRATION-GUIDE.md
- SUBSCRIPTION-GUIDE.md
- CALENDAR-SUBSCRIPTION-GUIDE.md

## License

GPL v2 or later
