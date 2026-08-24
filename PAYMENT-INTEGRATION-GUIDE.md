# Payment Integration Guide

## Stripe Setup

### Step 1: Get API Keys
1. Go to [Stripe Dashboard](https://dashboard.stripe.com/apikeys)
2. Copy Test Publishable Key (pk_test_...)
3. Copy Test Secret Key (sk_test_...)

### Step 2: Configure Webhooks
1. Go to Developers → Webhooks
2. Add endpoint: `https://yoursite.com/wp-admin/admin-ajax.php?action=sbe_stripe_webhook`
3. Select events: `payment_intent.succeeded`, `payment_intent.payment_failed`
4. Copy Signing Secret (whsec_...)

### Step 3: Install Stripe SDK
```bash
composer require stripe/stripe-php
```

### Step 4: Configure in WordPress
1. Go to Bookings & Events → Payment Gateways
2. Enable Test Mode
3. Enter API keys
4. Save settings

## PayPal Setup

### Step 1: Get API Credentials
1. Go to [PayPal Developer](https://developer.paypal.com/dashboard/applications)
2. Create app
3. Copy Client ID and Client Secret

### Step 2: Configure Webhooks
1. Go to Webhooks
2. Add endpoint: `https://yoursite.com/wp-admin/admin-ajax.php?action=sbe_paypal_webhook`
3. Select events: `PAYMENT.CAPTURE.COMPLETED`, `PAYMENT.CAPTURE.DENIED`
4. Copy Webhook ID

### Step 3: Configure in WordPress
1. Go to Bookings & Events → Payment Gateways
2. Enter PayPal credentials
3. Save settings

## Testing

### Stripe Test Cards
- Success: 4242 4242 4242 4242
- Decline: 4000 0000 0000 0002
- Any future expiry date
- Any 3-digit CVC

### PayPal Sandbox
1. Use sandbox accounts from Developer Dashboard
2. Test with sandbox buyer account

## Going Live

1. Disable Test Mode
2. Enter live API keys
3. Update webhook URLs to production
4. Test with real payment
5. Launch!

## Troubleshooting

### "Stripe SDK not loaded"
Install: `composer require stripe/stripe-php`

### Webhooks not working
- Check webhook URL is accessible
- Verify webhook secret
- Check SSL certificate

### Payment succeeds but booking not updated
- Check webhook logs in Stripe/PayPal dashboard
- Verify webhook is configured correctly
- Check WordPress debug log

## Security

- Use HTTPS for all payment pages
- Never commit API keys to version control
- Rotate keys periodically
- Verify webhook signatures (already implemented)
- PCI compliance handled by Stripe/PayPal

## Support

- Stripe: [stripe.com/docs](https://stripe.com/docs)
- PayPal: [developer.paypal.com/docs](https://developer.paypal.com/docs)
