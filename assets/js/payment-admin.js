/* Payment Admin JavaScript */
jQuery(document).ready(function($) {
    // Toggle between test and live mode sections
    $('input[name="sbe_payment_test_mode"]').on('change', function() {
        location.reload();
    });
    
    // Toggle payment amount type
    $('input[name="sbe_payment_amount_type"]').on('change', function() {
        var $fixedAmount = $('input[name="sbe_fixed_payment_amount"]');
        if ($(this).val() === 'fixed') {
            $fixedAmount.closest('div').slideDown();
        } else {
            $fixedAmount.closest('div').slideUp();
        }
    });
    
    // Validate API keys before save
    $('#sbe_save_payment_settings').on('click', function(e) {
        var isValid = validateAPIKeys();
        if (!isValid) {
            e.preventDefault();
            alert('Please fill in all required API keys for the selected payment gateway.');
        }
    });
    
    // API key visibility toggles
    $('.sbe-api-key-field').each(function() {
        var $field = $(this);
        var $input = $field.find('input[type="password"]');
        var $toggle = $('<button type="button" class="sbe-toggle-key">Show</button>');
        $field.append($toggle);
        
        $toggle.on('click', function() {
            if ($input.attr('type') === 'password') {
                $input.attr('type', 'text');
                $toggle.text('Hide');
            } else {
                $input.attr('type', 'password');
                $toggle.text('Show');
            }
        });
    });
    
    // Payment gateway selection cards
    $('.sbe-gateway-card').on('click', function() {
        var gateway = $(this).data('gateway');
        $('.sbe-gateway-card').removeClass('active');
        $(this).addClass('active');
        $('input[name="sbe_payment_gateway"]').val(gateway);
        $('.sbe-gateway-settings').hide();
        $('#sbe-' + gateway + '-settings').show();
    });
    
    // Test webhook connection
    $('#sbe-test-webhook').on('click', function() {
        var $btn = $(this);
        var gateway = $btn.data('gateway');
        $btn.prop('disabled', true).text('Testing...');
        
        $.ajax({
            url: sbe_payment_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'sbe_test_webhook',
                nonce: sbe_payment_ajax.nonce,
                gateway: gateway
            },
            success: function(response) {
                if (response.success) {
                    alert('Webhook test successful! Your endpoint is reachable.');
                } else {
                    alert('Webhook test failed: ' + (response.data.message || 'Unknown error'));
                }
            },
            error: function() {
                alert('Webhook test failed. Please check your webhook URL configuration.');
            },
            complete: function() {
                $btn.prop('disabled', false).text('Test Webhook');
            }
        });
    });
    
    // Copy webhook URL to clipboard
    $('.sbe-copy-webhook').on('click', function() {
        var webhookUrl = $(this).data('url');
        navigator.clipboard.writeText(webhookUrl).then(function() {
            alert('Webhook URL copied to clipboard!');
        }).catch(function() {
            var $tempInput = $('<input>');
            $('body').append($tempInput);
            $tempInput.val(webhookUrl).select();
            document.execCommand('copy');
            $tempInput.remove();
            alert('Webhook URL copied to clipboard!');
        });
    });
    
    // Real-time API key validation
    $('input[name*="stripe"]').on('blur', function() {
        var $input = $(this);
        var value = $input.val();
        var name = $input.attr('name');
        
        if (value) {
            if (name.includes('publishable') && !value.startsWith('pk_')) {
                $input.css('border-color', '#dc3545');
            } else if (name.includes('secret') && !name.includes('webhook') && !value.startsWith('sk_')) {
                $input.css('border-color', '#dc3545');
            } else if (name.includes('webhook') && !value.startsWith('whsec_')) {
                $input.css('border-color', '#dc3545');
            } else {
                $input.css('border-color', '#28a745');
            }
        } else {
            $input.css('border-color', '#ddd');
        }
    });
    
    // Show/hide live credentials warning
    $('input[name="sbe_payment_test_mode"]').on('change', function() {
        if (!$(this).is(':checked')) {
            var $warning = $('<div class="notice notice-warning inline" style="margin: 10px 0;"><p><strong>Warning:</strong> You are about to use live credentials. Real payments will be processed!</p></div>');
            $('.sbe-settings-section:last').prepend($warning);
        }
    });
    
    // Auto-detect test vs live keys
    $('input[name*="stripe"], input[name*="paypal"]').on('input', function() {
        var value = $(this).val();
        if (value.includes('test') || value.includes('sandbox')) {
            $('input[name="sbe_payment_test_mode"]').prop('checked', true);
        } else if (value.includes('live') || value.includes('production')) {
            $('input[name="sbe_payment_test_mode"]').prop('checked', false);
        }
    });
    
    // Payment gateway health check
    $('.sbe-check-gateway-health').on('click', function() {
        var $btn = $(this);
        var gateway = $btn.data('gateway');
        var $statusIndicator = $btn.siblings('.sbe-gateway-status');
        
        $btn.prop('disabled', true).text('Checking...');
        
        $.ajax({
            url: sbe_payment_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'sbe_check_gateway_health',
                nonce: sbe_payment_ajax.nonce,
                gateway: gateway
            },
            success: function(response) {
                if (response.success) {
                    $statusIndicator.removeClass('error').addClass('success').html('<span style="color: #28a745;">✓ Gateway is healthy</span>');
                } else {
                    $statusIndicator.removeClass('success').addClass('error').html('<span style="color: #dc3545;">✗ ' + response.data.message + '</span>');
                }
            },
            error: function() {
                $statusIndicator.removeClass('success').addClass('error').html('<span style="color: #dc3545;">✗ Connection failed</span>');
            },
            complete: function() {
                $btn.prop('disabled', false).text('Check Health');
            }
        });
    });
    
    function validateAPIKeys() {
        var testMode = $('input[name="sbe_payment_test_mode"]').is(':checked');
        var paymentEnabled = $('input[name="sbe_payment_enabled"]').is(':checked');
        
        if (!paymentEnabled) return true;
        
        if (testMode) {
            var stripeTestPK = $('input[name="sbe_stripe_test_publishable_key"]').val();
            var stripeTestSK = $('input[name="sbe_stripe_test_secret_key"]').val();
            var stripeTestWH = $('input[name="sbe_stripe_test_webhook_secret"]').val();
            var paypalTestCID = $('input[name="sbe_paypal_test_client_id"]').val();
            var paypalTestCS = $('input[name="sbe_paypal_test_client_secret"]').val();
            
            var stripeConfigured = stripeTestPK && stripeTestSK && stripeTestWH;
            var paypalConfigured = paypalTestCID && paypalTestCS;
            
            if (!stripeConfigured && !paypalConfigured) return false;
        } else {
            var stripeLivePK = $('input[name="sbe_stripe_live_publishable_key"]').val();
            var stripeLiveSK = $('input[name="sbe_stripe_live_secret_key"]').val();
            var stripeLiveWH = $('input[name="sbe_stripe_live_webhook_secret"]').val();
            var paypalLiveCID = $('input[name="sbe_paypal_live_client_id"]').val();
            var paypalLiveCS = $('input[name="sbe_paypal_live_client_secret"]').val();
            var paypalWebhook = $('input[name="sbe_paypal_webhook_id"]').val();
            
            var stripeConfigured = stripeLivePK && stripeLiveSK && stripeLiveWH;
            var paypalConfigured = paypalLiveCID && paypalLiveCS && paypalWebhook;
            
            if (!stripeConfigured && !paypalConfigured) return false;
        }
        
        return true;
    }
});
