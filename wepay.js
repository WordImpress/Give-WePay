var edd_global_vars;
jQuery(document).ready(function($) {

	// non ajaxed
	$('body').on('submit', '#edd_purchase_form', function(event) {

		if( $('input[name="give-gateway"]').val() == 'wepay' ) {

			event.preventDefault();

			edd_wepay_process_card();

		}

	});
});


function edd_wepay_process_card() {

	if( 1 == wepay_js.is_test_mode ) {
		WePay.set_endpoint("stage");
	} else {
		WePay.set_endpoint("production");
	}

	// disable the submit button to prevent repeated clicks
	jQuery('#edd_purchase_form #give-purchase-button').attr('disabled', 'disabled');

	if( typeof jQuery('#card_state_us').val() != 'undefined' ) {

		if( jQuery('.billing-country').val() ==  'US' ) {
			var state = jQuery('#card_state_us').val();
		} else if( jQuery('.billing-country').val() ==  'CA' ) {
			var state = jQuery('#card_state_ca').val();
		} else {
			var state = jQuery('#card_state_other').val();
		}

	} else {
		var state = jQuery('.card_state').val();
	}

	var response = WePay.credit_card.create( {
		"client_id"        : wepay_js.client_id,
		"user_name"        : jQuery('.card-name').val(),
		"email"            : jQuery('#give-email').val(),
		"cc_number"        : jQuery('.card-number').val(),
		"cvv"              : jQuery('.card-cvc').val(),
		"expiration_month" : jQuery('.card-expiry-month').val(),
		"expiration_year"  : jQuery('.card-expiry-year').val(),
		"address"          :
		{
			"address1" : jQuery('.card-address').val(),
			"address2" : jQuery('.card-address-2').val(),
			"city"     : jQuery('.card-city').val(),
			"state"    : state,
			"country"  : jQuery('#billing_country').val(),
			"zip"      : jQuery('.card-zip').val()
		}
	}, function(data) {
		if (data.error) {
			// handle error responses
			jQuery('.give-cart-ajax').hide();
			jQuery('#edd_purchase_form #give-purchase-button').attr("disabled", false);
			var error = '<div class="edd_errors"><p class="edd_error">' + data.error_description + '</p></div>';
			// show the errors on the form
			jQuery('#give-wepay-payment-errors').html(error);
		} else {
			// handle success (probably you will submit the form with the credit_card_id)
			jQuery('.give-cart-ajax').hide();

			var form$ = jQuery("#edd_purchase_form");

			jQuery('#edd_purchase_form #edd_cc_fields input[type="text"]').each(function() {
				jQuery(this).removeAttr('name');
			});

			// insert the token into the form so it gets submitted to the server
			form$.append("<input type='hidden' name='edd_wepay_card' value='" + data.credit_card_id + "' />");

			// and submit
			form$.get(0).submit();

		}

	} );

	if (response.error) {
		// handle missing data errors
		jQuery('.give-cart-ajax').hide();
		jQuery('#edd_purchase_form #give-purchase-button').attr("disabled", false);
		var error = '<div class="edd_errors"><p class="edd_error">' + response.error_description + '</p></div>';
		// show the errors on the form
		jQuery('#give-wepay-payment-errors').html(error);
	}

	return false; // submit from callback
}