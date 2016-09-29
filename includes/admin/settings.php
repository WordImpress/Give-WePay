<?php
/**
 * Give Wepay Settings
 *
 * @package     Give
 * @copyright   Copyright (c) 2016, WordImpress
 * @license     https://opensource.org/licenses/gpl-license GNU Public License
 * @since       1.0
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Register the gateway settings
 *
 * @access      public
 * @since       1.0
 * @return      array
 */
function give_register_wepay_settings( $settings ) {

	$wepay_settings = apply_filters( 'give_gateway_wepay_settings', array(
		array(
			'name' => esc_html__( 'WePay Settings', 'give-wepay' ),
			'desc' => '<hr>',
			'id'   => 'give_title_wepay',
			'type' => 'give_title'
		),
		array(
			'id'   => 'wepay_client_id',
			'name' => esc_html__( 'Live Client ID', 'give-wepay' ),
			'desc' => sprintf( __( 'Enter your live WePay client ID. <a href="%s" target="_blank">Find out how to obtain your API credentials</a>.', 'give-wepay' ), 'https://givewp.com/documentation/add-ons/wepay-gateway/' ),
			'type' => 'text',
		),
		array(
			'id'   => 'wepay_client_secret',
			'name' => esc_html__( 'Live Client Secret', 'give-wepay' ),
			'desc' => esc_html__( 'Enter your live WePay client secret.', 'give-wepay' ),
			'type' => 'text',
		),
		array(
			'id'   => 'wepay_access_token',
			'name' => esc_html__( 'Live Access Token', 'give-wepay' ),
			'desc' => esc_html__( 'Enter your live WePay access token.', 'give-wepay' ),
			'type' => 'text',
		),
		array(
			'id'   => 'wepay_account_id',
			'name' => esc_html__( 'Live Account ID', 'give-wepay' ),
			'desc' => esc_html__( 'Enter your live WePay account ID.', 'give-wepay' ),
			'type' => 'text',
		),
		array(
			'id'   => 'wepay_sandbox_client_id',
			'name' => esc_html__( 'Stage Client ID', 'give-wepay' ),
			'desc' => esc_html__( 'Enter your stage account WePay client ID.', 'give-wepay' ),
			'type' => 'text',
		),
		array(
			'id'   => 'wepay_sandbox_client_secret',
			'name' => esc_html__( 'Stage Client Secret', 'give-wepay' ),
			'desc' => esc_html__( 'Enter your stage account WePay client secret.', 'give-wepay' ),
			'type' => 'text',
		),
		array(
			'id'   => 'wepay_sandbox_access_token',
			'name' => esc_html__( 'Stage Access Token', 'give-wepay' ),
			'desc' => esc_html__( 'Enter your stage account WePay access token.', 'give-wepay' ),
			'type' => 'text',
		),
		array(
			'id'   => 'wepay_sandbox_account_id',
			'name' => esc_html__( 'Stage Account ID', 'give-wepay' ),
			'desc' => esc_html__( 'Enter your stage account WePay account ID.', 'give-wepay' ),
			'type' => 'text',
		),
		array(
			'id'   => 'wepay_preapprove_only',
			'name' => esc_html__( 'Preapprove Payments?', 'give-wepay' ),
			'desc' => esc_html__( 'Check this if you would like to preapprove payments but not charge until a later date.', 'give-wepay' ),
			'type' => 'checkbox'
		),
		array(
			'id'      => 'wepay_payment_type',
			'name'    => esc_html__( 'Payment Type', 'give-wepay' ),
			'desc'    => esc_html__( 'Select the type of payment you would like to process.', 'give-wepay' ),
			'type'    => 'select',
			'default' => 'DONATION',
			'options' => array(
				'GOODS'    => esc_html__( 'Goods', 'give-wepay' ),
				'SERVICE'  => esc_html__( 'Service', 'give-wepay' ),
				'DONATION' => esc_html__( 'Donation', 'give-wepay' ),
				'EVENT'    => esc_html__( 'Event', 'give-wepay' ),
				'PERSONAL' => esc_html__( 'Personal', 'give-wepay' ),
			),

		),
		array(
			'id'      => 'wepay_fee_payer',
			'name'    => esc_html__( 'Fee Payer', 'give-wepay' ),
			'desc'    => esc_html__( 'How would you like to collect the WePay gateway fee?', 'give-wepay' ),
			'type'    => 'radio',
			'options' => array(
				'Payee' => esc_html__( 'Recipient', 'give-wepay' ),
				'Payer' => esc_html__( 'Donor', 'give-wepay' )
			),
			'default' => 'Payee'
		),
		array(
			'id'      => 'wepay_onsite_payments',
			'name'    => esc_html__( 'On Site Payments', 'give-wepay' ),
			'desc'    => esc_html__( 'Process credit cards on-site or send customers to WePay\'s terminal? Note: On-site payments require SSL.', 'give-wepay' ),
			'type'    => 'radio',
			'options' => array(
				'onsite'  => esc_html__( 'On-Site', 'give-wepay' ),
				'offsite' => esc_html__( 'Off-Site', 'give-wepay' )
			),
			'default' => 'offsite'
		)
	) );

	return array_merge( $settings, $wepay_settings );
}

add_filter( 'give_settings_gateways', 'give_register_wepay_settings' );
