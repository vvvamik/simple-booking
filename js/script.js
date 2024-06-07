jQuery(document).ready(function($) {
    var bookedDates = [];

    function disableBookedDates(date) {
        var string = jQuery.datepicker.formatDate('yy-mm-dd', date);
        return [bookedDates.indexOf(string) == -1];
    }

    function initializeDatePickers() {
        $('#arrival_date').datepicker({
            dateFormat: 'yy-mm-dd',
            beforeShowDay: disableBookedDates,
            onSelect: function(selectedDate) {
                var minDate = $(this).datepicker('getDate');
                minDate.setDate(minDate.getDate() + 1);
                $('#departure_date').datepicker('option', 'minDate', minDate);
            }
        });

        $('#departure_date').datepicker({
            dateFormat: 'yy-mm-dd',
            beforeShowDay: disableBookedDates,
            onSelect: function(selectedDate) {
                var maxDate = $(this).datepicker('getDate');
                maxDate.setDate(maxDate.getDate() - 1);
                $('#arrival_date').datepicker('option', 'maxDate', maxDate);
            }
        });
    }

    $.ajax({
        url: ajax_object.ajax_url,
        data: { action: 'simple_booking_get_booked_dates' },
        method: 'POST',
        success: function(response) {
            bookedDates = JSON.parse(response);
            initializeDatePickers();
        }
    });

    $('#booking-form').submit(function(event) {
        event.preventDefault();

        var formData = {
            action: 'simple_booking',
            arrival_date: $('#arrival_date').val(),
            departure_date: $('#departure_date').val(),
            name: $('#name').val(),
            email: $('#email').val(),
            phone: $('#phone').val()
        };

        $.post(ajax_object.ajax_url, formData, function(response) {
            $('#booking-result').html(response);
            // Refresh dates in case the booking was successful
            if(response.indexOf('úspěšně vytvořena') !== -1) {
                $('#booking-form')[0].reset(); // Empty form after successful submission
                $.ajax({
                    url: ajax_object.ajax_url,
                    data: { action: 'simple_booking_get_booked_dates' },
                    method: 'POST',
                    success: function(response) {
                        bookedDates = JSON.parse(response);
                        $('#arrival_date').datepicker('refresh');
                        $('#departure_date').datepicker('refresh');
                    }
                });
            }
        });
    });
});
