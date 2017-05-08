<?php
/**
 * Give Wepay Settings
 *
 * @package     Give
 * @copyright   Copyright (c) 2017, WordImpress
 * @license     https://opensource.org/licenses/gpl-license GNU Public License
 * @since       1.0
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}


/**
 * Class Give_WePay_Gateway_Settings
 */
class Give_WePay_Gateway_Settings {

	/**
	 * @since  1.0
	 * @access static
	 * @var Give_WePay_Gateway_Settings $instance
	 */
	static private $instance;

	/**
	 * @since  1.0
	 * @access private
	 * @var string $section_id
	 */
	private $section_id;

	/**
	 * @since  1.0
	 * @access private
	 * @var string $section_label
	 */
	private $section_label;

	/**
	 * Give_WePay_Settings constructor.
	 */
	public function __construct() {
	}

	/**
	 * get class object.
	 *
	 * @since 1.0
	 * @return Give_WePay_Gateway_Settings
	 */
	static function get_instance() {
		if ( null === static::$instance ) {
			static::$instance = new static();
		}

		return static::$instance;
	}

	/**
	 * Setup hooks.
	 *
	 * @since 1.0
	 */
	public function setup_hooks() {
		$this->section_id    = 'wepay-settings';
		$this->section_label = __( 'WePay Settings', 'give-payumoney' );

		// Add payment gateway to payment gateways list.
		add_filter( 'give_payment_gateways', array( $this, 'add_gateways' ) );

		if ( is_admin() ) {

			// Add section to payment gateways tab.
			add_filter( 'give_get_sections_gateways', array( $this, 'add_section' ) );

			// Add section settings.
			add_filter( 'give_get_settings_gateways', array( $this, 'add_settings' ) );
		}
	}

	/**
	 * Add payment gateways to gateways list.
	 *
	 * @since 1.0
	 *
	 * @param array $gateways array of payment gateways.
	 *
	 * @return array
	 */
	public function add_gateways( $gateways ) {
		$gateways[ $this->section_id ] = array(
			'admin_label'    => $this->section_label,
			'checkout_label' => __( 'Credit Card', 'give-wepay' ),
		);

		return $gateways;
	}

	/**
	 * Add setting section.
	 *
	 * @since 1.0
	 *
	 * @param array $sections Array of section.
	 *
	 * @return array
	 */
	public function add_section( $sections ) {
		$sections[ $this->section_id ] = $this->section_label;

		return $sections;
	}

	/**
	 * Get setting.
	 *
	 * @param $settings array
	 *
	 * @return array
	 */
	function add_settings( $settings ) {

		$current_section = give_get_current_setting_section();

		if ( $this->section_id == $current_section ) {

			$settings = array(
				array(
					'id'   => 'wepay_payments_setting',
					'type' => 'title',
				),
				array(
					'id'   => 'wepay_client_id',
					'name' => __( 'Live Client ID', 'give-wepay' ),
					'desc' => sprintf( __( 'Enter your live WePay client ID. <a href="%s" target="_blank">Find out how to obtain your API credentials</a>.', 'give-wepay' ), 'https://givewp.com/documentation/add-ons/wepay-gateway/' ),
					'type' => 'text',
				),
				array(
					'id'   => 'wepay_account_id',
					'name' => __( 'Live Account ID', 'give-wepay' ),
					'desc' => __( 'Enter your live WePay account ID.', 'give-wepay' ),
					'type' => 'text',
				),
				array(
					'id'   => 'wepay_client_secret',
					'name' => __( 'Live Client Secret', 'give-wepay' ),
					'desc' => __( 'Enter your live WePay client secret.', 'give-wepay' ),
					'type' => 'api_key',
				),
				array(
					'id'   => 'wepay_access_token',
					'name' => __( 'Live Access Token', 'give-wepay' ),
					'desc' => __( 'Enter your live WePay access token.', 'give-wepay' ),
					'type' => 'api_key',
				),
				array(
					'id'   => 'wepay_sandbox_client_id',
					'name' => __( 'Stage Client ID', 'give-wepay' ),
					'desc' => __( 'Enter your stage account WePay client ID.', 'give-wepay' ),
					'type' => 'text',
				),
				array(
					'id'   => 'wepay_sandbox_account_id',
					'name' => __( 'Stage Account ID', 'give-wepay' ),
					'desc' => __( 'Enter your stage account WePay account ID.', 'give-wepay' ),
					'type' => 'text',
				),
				array(
					'id'   => 'wepay_sandbox_client_secret',
					'name' => __( 'Stage Client Secret', 'give-wepay' ),
					'desc' => __( 'Enter your stage account WePay client secret.', 'give-wepay' ),
					'type' => 'api_key',
				),
				array(
					'id'   => 'wepay_sandbox_access_token',
					'name' => __( 'Stage Access Token', 'give-wepay' ),
					'desc' => __( 'Enter your stage account WePay access token.', 'give-wepay' ),
					'type' => 'api_key',
				),
				array(
					'id'   => 'wepay_preapprove_only',
					'name' => __( 'Preapprove Payments?', 'give-wepay' ),
					'desc' => __( 'Check this if you would like to preapprove payments but not charge until a later date.', 'give-wepay' ),
					'type' => 'checkbox'
				),
				array(
					'id'      => 'wepay_payment_type',
					'name'    => __( 'Payment Type', 'give-wepay' ),
					'desc'    => __( 'Select the type of payment you would like to process.', 'give-wepay' ),
					'type'    => 'select',
					'default' => 'DONATION',
					'options' => array(
						'GOODS'    => __( 'Goods', 'give-wepay' ),
						'SERVICE'  => __( 'Service', 'give-wepay' ),
						'DONATION' => __( 'Donation', 'give-wepay' ),
						'EVENT'    => __( 'Event', 'give-wepay' ),
						'PERSONAL' => __( 'Personal', 'give-wepay' ),
					),

				),
				array(
					'id'      => 'wepay_fee_payer',
					'name'    => __( 'Fee Payer', 'give-wepay' ),
					'desc'    => __( 'How would you like to collect the WePay gateway fee?', 'give-wepay' ),
					'type'    => 'radio',
					'options' => array(
						'Payee' => __( 'Recipient', 'give-wepay' ),
						'Payer' => __( 'Donor', 'give-wepay' )
					),
					'default' => 'Payee'
				),
				array(
					'id'      => 'wepay_onsite_payments',
					'name'    => __( 'On Site Payments', 'give-wepay' ),
					'desc'    => __( 'Process credit cards on-site or send customers to WePay\'s terminal? Note: On-site payments require SSL.', 'give-wepay' ),
					'type'    => 'radio',
					'options' => array(
						'onsite'  => __( 'On-Site', 'give-wepay' ),
						'offsite' => __( 'Off-Site', 'give-wepay' )
					),
					'default' => 'offsite'
				),
				array(
					'id'   => 'wepay_payments_setting',
					'type' => 'sectionend',
				),
			);

		}

		return $settings;
	}

}

Give_WePay_Gateway_Settings::get_instance()->setup_hooks();
