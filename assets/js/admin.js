/* Admin JavaScript */
jQuery(document).ready(function($) {
    // Dashboard functionality
    $('.sbe-widget a').on('click', function(e) {
        // Track clicks on dashboard widgets
        console.log('Dashboard widget clicked:', $(this).attr('href'));
    });
    
    // Bulk actions for bookings
    $('#sbe-bulk-action').on('change', function() {
        var action = $(this).val();
        if (action) {
            console.log('Bulk action selected:', action);
        }
    });
    
    // Quick edit for bookings
    $('.sbe-quick-edit').on('click', function() {
        var bookingId = $(this).data('booking-id');
        console.log('Quick edit booking:', bookingId);
    });
    
    // View booking details
    $('.sbe-view-booking').on('click', function() {
        var bookingId = $(this).data('booking-id');
        console.log('View booking:', bookingId);
    });
    
    // Send reminder email
    $('.sbe-send-reminder').on('click', function() {
        var bookingId = $(this).data('booking-id');
        if (confirm('Send reminder email for booking #' + bookingId + '?')) {
            console.log('Send reminder for booking:', bookingId);
        }
    });
    
    // Date/time pickers initialization
    $('.sbe-datepicker').datepicker({
        dateFormat: 'yy-mm-dd',
        minDate: 0,
        changeMonth: true,
        changeYear: true
    });
    
    $('.sbe-timepicker').timepicker({
        timeFormat: 'H:i:s',
        interval: 30
    });
});
