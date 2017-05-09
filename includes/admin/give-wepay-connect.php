<?php
/**
 * Give WePay Gateway Connect
 *
 * @package     Give
 * @copyright   Copyright (c) 2017, WordImpress
 * @license     https://opensource.org/licenses/gpl-license GNU Public License
 * @since       1.4
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}


/**
 * Conditional to check if WePay is connected.
 *
 * @return bool
 */
function give_is_wepay_connected() {

	return false;

}

/**
 * Dismiss connect banner temporarily.
 *
 * Sets transient via AJAX callback.
 */
function give_wepay_connect_dismiss_banner() {

	$user_id = get_current_user_id();
	set_transient( "give_hide_wepay_connect_notice_{$user_id}", '1', DAY_IN_SECONDS );

	return true;

}

add_action( 'give_wepay_connect_dismiss', 'give_wepay_connect_dismiss_banner' );

/**
 * Check if notice dismissed by admin user or not.
 *
 * @since  1.4
 *
 * @return bool
 */
function give_is_connect_notice_dismissed() {

	$current_user        = wp_get_current_user();
	$is_notice_dismissed = false;

	if ( get_transient( "give_hide_wepay_connect_notice_{$current_user->ID}" ) ) {
		$is_notice_dismissed = true;
	}

	return $is_notice_dismissed;
}

/**
 * WePay Connect Button.
 *
 * @return string
 */
function give_wepay_connect_button() {

	$connected = give_get_option( 'give_wepay_connected' );

	ob_start();
	?>
    <a href="#" id="give-wepay-connect"><span>Connect with WePay</span></a>

    <script src="https://static.wepay.com/min/js/wepay.v2.js" type="text/javascript"></script>
    <script type="text/javascript">

        WePay.set_endpoint("stage"); // stage or production

        WePay.OAuth2.button_init(document.getElementById('give-wepay-connect'), {
            "client_id": "199254",
            "scope": ["manage_accounts", "collect_payments", "view_user", "send_money", "preapprove_payments"],
            "user_name": "test user",
            "user_email": "test@example.com",
            "redirect_uri": "<?php echo give_get_current_page_url(); ?>",
            "top": 100, // control the positioning of the popup with the top and left params
            "left": 100,
            "state": "robot", // this is an optional parameter that lets you persist some state value through the flow
            "callback": function (data) {
                console.log(data);
                /** This callback gets fired after the user clicks "grant access" in the popup and the popup closes. The data object will include the code which you can pass to your server to make the /oauth2/token call **/
                if (data.code.length !== 0) {
                    // send the data to the server
                } else {
                    // an error has occurred and will be in data.error
                }
            }
        });

    </script>


	<?php return apply_filters( 'give_wepay_connect_button', ob_get_clean() );
}
