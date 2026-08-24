/* Service Bookings & Events - Frontend JavaScript */
jQuery(document).ready(function($) {
    // Booking form handling
    $('.sbe-booking-form form').on('submit', function(e) {
        e.preventDefault();
        var $form = $(this);
        var $submitBtn = $form.find('.sbe-submit-btn');
        var $message = $form.find('.sbe-message');
        
        if (!$form[0].checkValidity()) return;
        
        $submitBtn.prop('disabled', true);
        $message.hide().removeClass('success error');
        
        var formData = {
            action: 'sbe_submit_booking',
            nonce: sbe_ajax.nonce,
            customer_name: $form.find('[name="customer_name"]').val(),
            customer_email: $form.find('[name="customer_email"]').val(),
            customer_phone: $form.find('[name="customer_phone"]').val() || '',
            service_id: $form.find('[name="service_id"]').val() || 0,
            event_id: $form.find('[name="event_id"]').val() || 0,
            booking_date: $form.find('[name="booking_date"]').val(),
            booking_time: $form.find('[name="booking_time"]').val(),
            notes: $form.find('[name="notes"]').val() || ''
        };
        
        $.ajax({
            url: sbe_ajax.ajax_url,
            type: 'POST',
            data: formData,
            success: function(response) {
                if (response.success) {
                    $message.addClass('success').html(response.data.message).show();
                    $form[0].reset();
                } else {
                    $message.addClass('error').html(response.data.message || 'Booking failed. Please try again.').show();
                }
            },
            error: function() {
                $message.addClass('error').html('An error occurred. Please try again later.').show();
            },
            complete: function() {
                $submitBtn.prop('disabled', false);
            }
        });
    });
    
    // Update available time slots when date changes
    $('[name="booking_date"]').on('change', function() {
        var $dateInput = $(this);
        var $form = $dateInput.closest('form');
        var $timeSelect = $form.find('[name="booking_time"]');
        
        if ($dateInput.val()) {
            $.ajax({
                url: sbe_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'sbe_get_available_slots',
                    nonce: sbe_ajax.nonce,
                    date: $dateInput.val()
                },
                success: function(response) {
                    if (response.success) {
                        $timeSelect.find('option:not(:first)').remove();
                        if (response.data.slots.length === 0) {
                            $timeSelect.append('<option value="">No available slots</option>');
                        } else {
                            response.data.slots.forEach(function(slot) {
                                $timeSelect.append('<option value="' + slot.time + '">' + slot.display + '</option>');
                            });
                        }
                    }
                }
            });
        }
    });
});
