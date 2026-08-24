# Subscription & Recurring Billing Guide

## Creating Subscription Plans

### Step 1: Create Plan
1. Go to Bookings & Events → Subscription Plans → Add New
2. Enter plan name (e.g., "Premium Monthly")
3. Add description in editor

### Step 2: Configure Plan Details
- **Amount**: 29.99
- **Currency**: USD
- **Billing Interval**: Month
- **Interval Count**: 1 (every 1 month)
- **Free Trial**: 7 (days, optional)
- **Setup Fee**: 0 (optional one-time fee)

### Step 3: Add Features
- Unlimited bookings
- Priority support
- Advanced analytics
- etc.

### Step 4: Publish
Click Publish to make plan live

## Managing Subscriptions

### Admin Dashboard
1. Go to Bookings & Events → Subscriptions
2. View stats: Active, Trial, Past Due, Canceled
3. Manage individual subscriptions

### Customer Self-Service
Use shortcode: `[sbe_manage_subscriptions]`

Customers can:
- View active subscriptions
- See next billing date
- Cancel subscriptions
- View payment history

## Shortcodes

### Subscription Form
```
[sbe_subscription_form plan_id="123" show_trial="true"]
```

### Pricing Table
```
[sbe_pricing_table category="monthly"]
```

### Manage Subscriptions
```
[sbe_manage_subscriptions]
```

## Email Notifications

Automatically sent:
- Renewal success
- Renewal failed
- Expiring soon (7 days before)

## Testing

### Test Mode
1. Enable Test Mode in Payment settings
2. Use Stripe test cards
3. Create test subscription

### Test Scenarios
1. New subscription signup
2. Trial period
3. Recurring payment
4. Payment failure
5. Cancellation

## Stripe Billing Integration

### Webhook Events
- `customer.subscription.created`
- `customer.subscription.updated`
- `customer.subscription.deleted`
- `invoice.payment_succeeded`
- `invoice.payment_failed`

### Webhook URL
```
https://yoursite.com/wp-admin/admin-ajax.php?action=sbe_stripe_subscription_webhook
```

## PayPal Subscriptions

### Webhook Events
- `BILLING.SUBSCRIPTION.ACTIVATED`
- `BILLING.SUBSCRIPTION.CANCELLED`
- `PAYMENT.SALE.COMPLETED`
- `PAYMENT.SALE.DENIED`

### Webhook URL
```
https://yoursite.com/wp-admin/admin-ajax.php?action=sbe_paypal_subscription_webhook
```

## Best Practices

### Pricing
- Offer annual plans with discount
- Include free trials
- Clear feature differentiation
- Display savings for annual vs monthly

### Customer Experience
- Send renewal reminders
- Easy cancellation process
- Grace period for failed payments
- Clear billing dates

### Technical
- Verify webhook signatures
- Log all subscription events
- Test all scenarios before going live
- Monitor failed payment rates

## Troubleshooting

### Subscription not created
- Check API keys
- Verify webhook configured
- Ensure plan has valid amount/interval

### Webhooks not received
- Check webhook URL
- Verify webhook secret
- Check SSL certificate

### Status not updating
- Check webhook events selected
- Verify webhook secret
- Check Stripe/PayPal dashboard logs

## Support

- Stripe Billing: [stripe.com/docs/billing](https://stripe.com/docs/billing)
- PayPal Subscriptions: [developer.paypal.com/docs/subscriptions](https://developer.paypal.com/docs/subscriptions)
