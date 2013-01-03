//********************************************************
// separate function to fire on each log adding
//********************************************************

function app_status_dates() {
    jQuery('tr.log-update').each(function() {
        jQuery(this).find('input.time-select').will_pickdate({
            timePicker:         true,
            format:             'm-d-Y h:ia',
            inputOutputFormat:  'U',
            allowEmpty:         true
        });

    });
}

jQuery(document).ready(function($) {
//http://tazsingh.github.com/will_pickdate/
//********************************************************
// set datepicker fields
//********************************************************

    $('table#apr-meta-table input.time-select').each(function() {

        $(this).will_pickdate({
            timePicker:         true,
            format:             'm-d-Y h:ia',
            inputOutputFormat:  'U',
            allowEmpty:         true
        });

    });

//********************************************************
// handle repeating fields
//********************************************************

	$( 'input#add-status' ).on('click', function() {

		// remove any existing messages
		$('#wpbody div#message').remove();

		// clone the fields
		var newfield = $( 'tr.empty-row.screen-reader-text' ).clone(true);

		// make it visible
		newfield.removeClass( 'empty-row screen-reader-text' );

		// and now insert it
		newfield.insertAfter( 'table#apr-logs-table tr.log-update:last' );

		// add the class
		newfield.addClass('log-update');

		// and move the cursor
//		newfield.find('input.key-name').focus();

		// and fire the timepicker
		app_status_dates();

	});

	$( 'span.remove-log' ).on('click', function() {
		$(this).parents('tr.log-update').find('input [type="text"]').val('');
		$(this).parents('tr.log-update').remove();
	});


	app_status_dates();
//********************************************************
// You're still here? It's over. Go home.
//********************************************************


}); // end init
