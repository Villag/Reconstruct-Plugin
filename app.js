jQuery(document).ready(function($) {"use strict";

	$('#new-project').submit(function() {
		new_project();

		return false;
	});

	$('.fork-project').click(function() {
		console.log( $(this).data('postid') );
		fork_project( $(this).data('postid'));

		return false;
	});

});

function fork_project( post_id ) { "use strict";

    // This does the ajax request
    jQuery.ajax({
        url: Reconstruct.ajaxurl,
        type: 'POST',
        data: {
        	action: 'fork',
        	post_id: post_id
        },
		async: false,
		dataType: 'json'
	}).done(function(response) {
		// Success!
		if( response.status == 'success' ) {

			jQuery('.hfeed').prepend('<img src="' + response.data.reconstruct_original_image_url + '">');
			jQuery('.hfeed').prepend('<img src="' + response.data.reconstruct_revised_image_url + '">');

		} else

		// Something failed
		if( response.status == 'error' ) {
			//jQuery('#feedback-response').addClass('alert-error');
		}

	}).fail(function(response) {

	}).always(function(response) {
		console.log(response);
	});

}

function new_project() { "use strict";

	var formData = new FormData(jQuery('#new-project')[0]);
	formData.append("action", "new-project");

    // This does the ajax request
    jQuery.ajax({
        url: Reconstruct.ajaxurl,
        type: 'POST',
        data: formData,
		async: false,
		cache: false,
		contentType: false,
		processData: false,
		dataType: 'json'
	}).done(function(response) {
		// Success!
		if( response.status == 'success' ) {

			jQuery('.hfeed').prepend('<img src="' + response.data.reconstruct_original_image_url + '">');
			jQuery('.hfeed').prepend('<img src="' + response.data.reconstruct_revised_image_url + '">');

			//jQuery('#feedback-form-body').fadeOut(300);
			//jQuery('#feedback-form .submit').attr('disabled', 'disabled').text('Feedback sent!');
			//jQuery('#feedback-response').removeClass('alert-error').addClass('alert-success');

			// Create GA tracking event
			//if( typeof _gaq == 'function' ) { _gaq.push(['_trackEvent', 'Feedback', 'Feedback Submission', 'Feedback Form',, false]); }
		} else

		// Something failed
		if( response.status == 'error' ) {
			//jQuery('#feedback-response').addClass('alert-error');
		}

	}).fail(function(response) {
		console.log(response);
		//jQuery('#feedback-response').addClass('alert-error');
	}).always(function(response) {
		console.log(response);
		//jQuery('#feedback-response').show().text(response.data.message);
		//jQuery('#feedback-form .modal-inlay').fadeOut('fast');
	});

}