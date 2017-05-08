<?php
/**
 * Give WePay Scripts
 *
 * @package     Give_WePay
 * @copyright   Copyright (c) 2017, WordImpress
 * @license     https://opensource.org/licenses/gpl-license GNU Public License
 * @since       1.4
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}


/**
 * Plugin Scripts
 */
function give_wepay_frontend_scripts() {

	// Onsite payments only.
	if ( ! Give_WePay()->onsite_payments() ) {
		return;
	}

	//Is this user in test mode?
	if ( give_is_test_mode() ) {
		$script_url = 'https://stage.wepay.com/min/js/tokenization.v2.js';
	} else {
		$script_url = 'https://www.wepay.com/min/js/tokenization.v2.js';
	}

	$creds = Give_WePay()->get_api_credentials();

	wp_register_script( 'give-wepay-tokenization', $script_url, array(), GIVE_WEPAY_VERSION );
	wp_enqueue_script( 'give-wepay-tokenization' );

	wp_register_script( 'give-wepay-gateway', GIVE_WEPAY_URL . 'assets/js/wepay.js', array(
		'give-wepay-tokenization',
		'jquery'
	), GIVE_WEPAY_VERSION );
	wp_enqueue_script( 'give-wepay-gateway' );

	wp_localize_script( 'give-wepay-gateway', 'give_wepay_js', array(
		'is_test_mode' => give_is_test_mode() ? '1' : '0',
		'client_id'    => $creds['client_id']
	) );
}


add_action( 'wp_enqueue_scripts', 'give_wepay_frontend_scripts' );


/**
 * Load admin javascript.
 *
 * @since  1.0
 *
 * @param  $hook
 *
 * @return void
 */
function give_wepay_admin_js( $hook ) {

	wp_register_style( 'give-wepay-connect-css', GIVE_WEPAY_URL . 'assets/css/give-wepay-connect.css', false, GIVE_WEPAY_VERSION );
	wp_register_script( 'give-wepay-connect-js', GIVE_WEPAY_URL . 'assets/js/give-wepay-admin-connect.js', false, GIVE_WEPAY_VERSION );

	if (
		isset( $_GET['page'] )
		&& 'give-settings' === $_GET['page']
	) {
		wp_enqueue_style( 'give-wepay-connect-css' );
		wp_enqueue_script( 'give-wepay-connect-js' );
	}

	if ( ! give_is_wepay_connected() ) {
		wp_enqueue_style( 'give-wepay-connect-css' );
		wp_enqueue_script( 'give-wepay-connect-js' );
	}

}

add_action( 'admin_enqueue_scripts', 'give_wepay_admin_js', 100 );