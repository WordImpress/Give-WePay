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

	// Pass off link to the
	$link = add_query_arg(
		array(
			'wepay_env'            => 'staging',
			'wepay_action'         => 'connect',
			'return_url'           => rawurlencode( admin_url( 'edit.php?post_type=give_forms&page=give-settings&tab=gateways&section=wepay-settings' ) ),
			'website_url'          => rawurlencode(get_bloginfo( 'url' )),
		),
		'https://connect.givewp.com/wepay/'
	);

	return apply_filters( 'give_wepay_connect_button', sprintf( '<a href="%s" id="give-wepay-connect"><span>Connect with WePay</span></a>', esc_url( $link ) ) );
}



/**
 * Once the user returns from connecting, save the options.
 */
function give_stripe_connect_save_options() {

	// If we don't have values here, bounce.
	http://givetest.dev/wp-admin/edit.php?post_type=give_forms&page=give-settings&tab=gateways&section=wepay-settings&wepay_access_token=STAGE_e124d4c884dd52b4fe8e3541789bb05d169237275a39dcce1da8ca71da611ce8&wepay_account_id=1936032468&connected=1
	if (
		! isset( $_GET['wepay_access_token'] )
		|| ! isset( $_GET['wepay_account_id'] )
	) {
		return false;
	}

	// Update keys
	give_update_option( 'wepay_client_id', $_GET['connected'] );
	give_update_option( 'wepay_sandbox_client_id', $_GET['connected'] );


	// Delete option for user API key.
	give_delete_option( 'wepay_user_api_keys' );

}

add_action( 'admin_init', 'give_stripe_connect_save_options' );
