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
 * WePay Connect Button.
 *
 * @return string
 */
function give_wepay_connect_button() {

	$connected = give_get_option( 'give_wepay_connected' );

	// Pass off link to the
	$link = add_query_arg(
		array(
			'wepay_action'         => 'connect',
			'return_url'            => rawurlencode( admin_url( 'edit.php?post_type=give_forms&page=give-settings&tab=gateways&section=wepay-settings' ) ),
			'website_url'           => get_bloginfo( 'url' ),
			'give_wepay_connected' => ! empty( $connected ) ? '1' : '0',
		),
		'https://connect.givewp.com/wepay/connect.php'
	);

	return apply_filters( 'give_wepay_connect_button', sprintf( '<a href="%s" id="give-wepay-connect"><span>Connect with WePay</span></a>', esc_url( $link ) ) );
}
