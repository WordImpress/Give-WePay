/**
 * Give WePay Gateway
 *
 * @description The scripts that provides
 *
 */
var give_global_vars, give_wepay_js;
jQuery( document ).ready( function ( $ ) {
alert('here');
	$( 'body' ).on( 'submit', '.give-form', function ( e ) {

		var $form = $( this );

		if ( $form.find( 'input[name="give-gateway"]' ).val() == 'wepay' ) {

			e.preventDefault();

			give_wepay_process_card( $form );

		}

	} );
} );

/**
 *
 * WePay Process CC
 *
 * @param  $form obj
 * @returns {boolean}
 */
function give_wepay_process_card( $form ) {

	var $form_submit_btn = $form.find( '#give-purchase-button' );

	//testing or live?
	if ( give_wepay_js.is_test_mode == 1 ) {
		WePay.set_endpoint( 'stage' );
	} else {
		WePay.set_endpoint( 'production' );
	}

	// disable the submit button to prevent repeated clicks
	$form_submit_btn.attr( 'disabled', 'disabled' );

	if ( typeof $form.find( '#card_state_us' ).val() != 'undefined' ) {

		if ( $form.find( '.billing-country' ).val() == 'US' ) {
			var state = $form.find( '#card_state_us' ).val();
		} else if ( $form.find( '.billing-country' ).val() == 'CA' ) {
			var state = $form.find( '#card_state_ca' ).val();
		} else {
			var state = $form.find( '#card_state_other' ).val();
		}

	} else {
		var state = $form.find( '.card_state' ).val();
	}

	var response = WePay.credit_card.create( {
		"client_id"       : give_wepay_js.client_id,
		"user_name"       : $form.find( '.card-name' ).val(),
		"email"           : $form.find( '#give-email' ).val(),
		"cc_number"       : $form.find( '.card-number' ).val(),
		"cvv"             : $form.find( '.card-cvc' ).val(),
		"expiration_month": $form.find( '.card-expiry-month' ).val(),
		"expiration_year" : $form.find( '.card-expiry-year' ).val(),
		"address"         : {
			"address1": $form.find( '.card-address' ).val(),
			"address2": $form.find( '.card-address-2' ).val(),
			"city"    : $form.find( '.card-city' ).val(),
			"state"   : state,
			"country" : $form.find( '#billing_country' ).val(),
			"zip"     : $form.find( '.card-zip' ).val()
		}
	}, function ( data ) {
		if ( data.error ) {
			// handle error responses
			$form.find( '.give-loading-animation' ).hide();
			// re-add original submit button text
			if ( give_global_vars.complete_purchase ) {
				$form_submit_btn.val( give_global_vars.complete_purchase );
			} else {
				$form_submit_btn.val( 'Donate Now' );
			}
			$form_submit_btn.attr( "disabled", false );
			var error = '<div class="give_errors"><p class="give_error">' + data.error_description + '</p></div>';
			// show the errors on the form
			$form.find( '#give-wepay-payment-errors' ).html( error );
		} else {
			// handle success (probably you will submit the form with the credit_card_id)
			$form.find( '#give_cc_fields input[type="text"]' ).each( function () {
				$form.find( this ).removeAttr( 'name' );
			} );

			// insert the token into the form so it gets submitted to the server
			$form.append( "<input type='hidden' name='give_wepay_card' value='" + data.credit_card_id + "' />" );

			// and submit the donation form
			$form.get( 0 ).submit();

		}

	} );

	//Handle error responses
	if ( response.error ) {
		// handle missing data errors
		$form.find( '.give-loading-animation' ).hide();
		$form_submit_btn.attr( "disabled", false );
		var error = '<div class="give_errors"><p class="give_error">' + response.error_description + '</p></div>';
		// re-add original submit button text
		if ( give_global_vars.complete_purchase ) {
			$form_submit_btn.val( give_global_vars.complete_purchase );
		} else {
			$form_submit_btn.val( 'Donate Now' );
		}

		// show the errors on the form
		$form.find( '#give-wepay-payment-errors' ).html( error );

	}

	return false; // submit from callback
}