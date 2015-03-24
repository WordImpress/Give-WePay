<?php
/**
 * Plugin Name: Give - WePay Gateway
 * Description: Process Give donations via the WePay Payment Gateway
 * Author: WordImpress
 * Author URI: https://wordimpress.com
 * Text Domain: give_wepay
 * Domain Path: languages
 * Version: 1.0
 */

define( 'GIVE_WEPAY_VERSION', '1.0' );

class Give_WePay_Gateway {

	private $client_id;
	private $client_secret;
	private $access_token;
	private $account_id;

	function __construct() {

		//Give dependents
		add_action( 'plugins_loaded', array( $this, 'wepay_init' ) );
		add_action( 'plugins_loaded', array( $this, 'give_add_wepay_licensing' ) );

	}


	function wepay_init() {

		// Filters
		add_filter( 'give_payment_gateways', array( $this, 'register_gateway' ) );
		add_filter( 'give_settings_gateways', array( $this, 'register_settings' ) );
		add_filter( 'give_payments_table_column', array( $this, 'payment_column_data' ), 10, 3 );
		add_filter( 'give_payment_statuses', array( $this, 'payment_status_labels' ) );
		add_filter( 'give_payments_table_columns', array( $this, 'payments_column' ) );
		add_filter( 'give_payments_table_views', array( $this, 'payment_status_filters' ) );

		// Actions
		add_action( 'give_gateway_wepay', array( $this, 'process_payment' ) );

		if ( ! $this->onsite_payments() ) {
			add_action( 'give_wepay_cc_form', '__return_false' );
		}
		add_action( 'init', array( $this, 'confirm_payment' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'scripts' ) );
		add_action( 'give_after_cc_fields', array( $this, 'errors_div' ) );
		add_action( 'admin_notices', array( $this, 'admin_messages' ) );
		add_action( 'init', array( $this, 'register_post_statuses' ), 110 );
		add_action( 'give_charge_wepay_preapproval', array( $this, 'process_preapproved_charge' ) );
		add_action( 'give_cancel_wepay_preapproval', array( $this, 'process_preapproved_cancel' ) );

	}

	/**
	 * Licensing
	 */
	function give_add_wepay_licensing() {
		if ( class_exists( 'Give_License' ) && is_admin() ) {
			$give_wepay_license = new Give_License( __FILE__, 'WePay Gateway', GIVE_WEPAY_VERSION, 'Devin Walker', 'wepay_license_key' );
		}
	}


	/**
	 * Get API Credentials
	 *
	 * @param int $payment_id
	 *
	 * @return mixed|void
	 */
	public function get_api_credentials( $payment_id = 0 ) {
		global $give_options;

		$creds                  = array();
		$creds['client_id']     = trim( $give_options['wepay_client_id'] );
		$creds['client_secret'] = trim( $give_options['wepay_client_secret'] );
		$creds['access_token']  = trim( $give_options['wepay_access_token'] );
		$creds['account_id']    = trim( $give_options['wepay_account_id'] );

		return apply_filters( 'give_wepay_get_api_creds', $creds, $payment_id );

	}

	/**
	 * Register WePay Gateway
	 *
	 * @param $gateways
	 *
	 * @return mixed
	 */
	public function register_gateway( $gateways ) {
		if ( $this->onsite_payments() ) {
			$checkout_label = __( 'Credit Card', 'give_wepay' );
		} else {
			$checkout_label = __( 'Credit Card or Bank Account', 'give_wepay' );
		}
		$gateways['wepay'] = array( 'admin_label' => 'WePay', 'checkout_label' => $checkout_label );

		return $gateways;
	}

	/**
	 * Process WePay Payment Gateway
	 *
	 * @param $purchase_data
	 */
	public function process_payment( $purchase_data ) {

		global $give_options;

		if ( ! class_exists( 'WePay' ) ) {
			require dirname( __FILE__ ) . '/vendor/wepay.php';
		}

		$creds = $this->get_api_credentials();

		if ( give_is_test_mode() ) {
			Wepay::useStaging( $creds['client_id'], $creds['client_secret'] );
		} else {
			Wepay::useProduction( $creds['client_id'], $creds['client_secret'] );
		}

		$wepay = new WePay( $creds['access_token'] );

		// Purchase summary
		$summary = give_get_purchase_summary( $purchase_data, false );

		$prefill_info        = new stdClass;
		$prefill_info->name  = $purchase_data['user_info']['first_name'] . ' ' . $purchase_data['user_info']['last_name'];
		$prefill_info->email = $purchase_data['user_email'];

		// Collect payment data
		$payment_data = array(
			'price'           => $purchase_data['price'],
			'give_form_title' => $purchase_data['post_data']['give-form-title'],
			'give_form_id'    => intval( $purchase_data['post_data']['give-form-id'] ),
			'date'            => $purchase_data['date'],
			'user_email'      => $purchase_data['user_email'],
			'purchase_key'    => $purchase_data['purchase_key'],
			'currency'        => give_get_currency(),
			'user_info'       => $purchase_data['user_info'],
			'status'          => 'pending'
		);

		// Record the pending payment
		$payment = give_insert_payment( $payment_data );

		$endpoint = isset( $give_options['wepay_preapprove_only'] ) ? 'preapproval' : 'checkout';

		$args = array(
			'account_id'        => $creds['account_id'],
			'amount'            => $purchase_data['price'],
			'fee_payer'         => $this->fee_payer(),
			'short_description' => stripslashes_deep( html_entity_decode( wp_strip_all_tags( $summary ), ENT_COMPAT, 'UTF-8' ) ),
			'prefill_info'      => $prefill_info,
			'reference_id'      => $purchase_data['purchase_key'],
			'fallback_uri'      => give_get_failed_transaction_uri(),
			'redirect_uri'      => add_query_arg( 'payment-confirmation', 'wepay', get_permalink( $give_options['success_page'] ) )
		);

		if ( isset( $give_options['wepay_preapprove_only'] ) ) {
			$args['period'] = 'once';
		} else {
			$args['type'] = $this->payment_type();
		}

		if ( $this->onsite_payments() && ! empty( $_POST['give_wepay_card'] ) ) {

			// Use a tokenized card
			$args['payment_method_id']   = $_POST['give_wepay_card'];
			$args['payment_method_type'] = 'credit_card';
		}

		// Let other plugins modify the data that goes to WePay
		$args = apply_filters( 'give_wepay_checkout_args', $args );

		//echo '<pre>'; print_r( $args ); echo '</pre>'; exit;

		// create the checkout
		try {

			$response = $wepay->request( $endpoint . '/create', $args );

			if ( $this->onsite_payments() ) {

				if ( ! empty( $response->error ) ) {
					// if errors are present, send the user back to the purchase page so they can be corrected
					give_set_error( $response->error, $response->error_description . '. Error code: ' . $response->error_code );
					give_send_back_to_checkout( '?payment-mode=wepay' );

				}

				if ( get_option( 'permalink_structure' ) ) {
					$query_str = '?payment-confirmation=wepay&';
				} else {
					$query_str = '&payment-confirmation=wepay&';
				}

				if ( isset( $give_options['wepay_preapprove_only'] ) ) {
					$query_str .= 'preapproval_id=' . $response->preapproval_id;
				} else {
					$query_str .= 'checkout_id=' . $response->checkout_id;
				}

				give_send_to_success_page( $query_str );

			} else {

				// Send to WePay terminal
				if ( isset( $give_options['wepay_preapprove_only'] ) ) {
					wp_redirect( $response->preapproval_uri );
					exit;
				} else {
					wp_redirect( $response->checkout_uri );
					exit;
				}

			}
		}
		catch ( WePayException $e ) {
			give_set_error( 'give_wepay_exception', $e->getMessage() );
			give_send_back_to_checkout( '?payment-mode=wepay' );
		}
	}

	/**
	 * Confirm Payment
	 */
	public function confirm_payment() {

		global $give_options;

		//Checks
		if ( empty( $_GET['payment-confirmation'] ) ) {
			return;
		}

		if ( empty( $_GET['checkout_id'] ) && empty( $_GET['preapproval_id'] ) ) {
			return;
		}

		if (  $_GET['payment-confirmation'] != 'wepay' ) {
			return;
		}

		if ( ! class_exists( 'WePay' ) ) {
			require dirname( __FILE__ ) . '/vendor/wepay.php';
		}

		$creds = $this->get_api_credentials();

		if ( give_is_test_mode() ) {
			Wepay::useStaging( $creds['client_id'], $creds['client_secret'] );
		} else {
			Wepay::useProduction( $creds['client_id'], $creds['client_secret'] );
		}

		$wepay = new WePay( $creds['access_token'] );

		try {

			if ( isset( $give_options['wepay_preapprove_only'] ) ) {

				$preapproval_id = urldecode( $_GET['preapproval_id'] );
				$response       = $wepay->request( 'preapproval', array(
					'preapproval_id' => $preapproval_id
				) );

				if ( $response->account_id != $creds['account_id'] ) {
					wp_die( __( 'The store ID does not match those set in the site settings.', 'give_wepay' ), __( 'Error', 'give_wepay' ) );
				}

				if ( $response->state != 'captured' && $response->state != 'approved' ) {
					wp_die( __( 'Your payment is still processing. Please refresh the page to see your purchase receipt.', 'give_wepay' ), __( 'Error', 'give_wepay' ) );
				}

				$payment_id = give_get_purchase_id_by_key( $response->reference_id );

				give_insert_payment_note( $payment_id, sprintf( __( 'WePay Preapproval ID: %s', 'give' ), $response->preapproval_id ) );
				give_update_payment_status( $payment_id, 'preapproval' );

			} else {

				$checkout_id = urldecode( $_GET['checkout_id'] );
				$response    = $wepay->request( 'checkout', array(
					'checkout_id' => $checkout_id
				) );

				if ( $response->account_id != $creds['account_id'] ) {
					wp_die( __( 'The store ID does not match those set in the site settings.', 'give_wepay' ), __( 'Error', 'give_wepay' ) );
				}

				if ( $response->state != 'captured' && $response->state != 'authorized' ) {
					wp_die( __( 'Your payment is still processing. Please refresh the page to see your purchase receipt.', 'give_wepay' ), __( 'Error', 'give_wepay' ) );
				}

				$payment_id = give_get_purchase_id_by_key( $response->reference_id );

				give_insert_payment_note( $payment_id, sprintf( __( 'WePay Checkout ID: %s', 'give' ), $response->checkout_id ) );
				give_update_payment_status( $payment_id, 'publish' );
			}

		}
		catch ( Exception $e ) {

			// Show a message if there was an error of some kind

		}

	}


	/**
	 * Plugin Scripts
	 */
	public function scripts() {

		if ( ! $this->onsite_payments() ) {
			return;
		}

		//Is this user in test mode?
		if ( give_is_test_mode() ) {
			$script_url = 'https://stage.wepay.com/min/js/tokenization.v2.js';
		} else {
			$script_url = 'https://www.wepay.com/min/js/tokenization.v2.js';
		}

		$creds = $this->get_api_credentials();

		wp_enqueue_script( 'give-wepay-tokenization', $script_url );
		wp_enqueue_script( 'give-wepay-gateway', plugin_dir_url( __FILE__ ) . 'wepay.js', array(
			'give-wepay-tokenization',
			'jquery'
		) );
		wp_localize_script( 'give-wepay-gateway', 'give_wepay_js', array(
			'is_test_mode' => give_is_test_mode() ? '1' : '0',
			'client_id'    => $creds['client_id']
		) );
	}

	/**
	 * Add an errors div
	 *
	 * @access      public
	 * @since       1.0
	 * @return      void
	 */
	function errors_div() {
		echo '<div id="give-wepay-payment-errors"></div>';
	}


	/**
	 * Show admin notices
	 *
	 * @access      public
	 * @since       1.0
	 * @return      void
	 */

	public function admin_messages() {

		if ( isset( $_GET['give-message'] ) && 'preapproval-charged' == $_GET['give-message'] ) {
			add_settings_error( 'give-wepay-notices', 'give-wepay-preapproval-charged', __( 'The preapproved payment was successfully charged.', 'give_wepay' ), 'updated' );
		}
		if ( isset( $_GET['give-message'] ) && 'preapproval-failed' == $_GET['give-message'] ) {
			add_settings_error( 'give-wepay-notices', 'give-wepay-preapproval-charged', __( 'The preapproved payment failed to be charged.', 'give_wepay' ), 'error' );
		}
		if ( isset( $_GET['give-message'] ) && 'preapproval-cancelled' == $_GET['give-message'] ) {
			add_settings_error( 'give-wepay-notices', 'give-wepay-preapproval-cancelled', __( 'The preapproved payment was successfully cancelled.', 'give_wepay' ), 'updated' );
		}

		settings_errors( 'give-wepay-notices' );
	}

	/**
	 * Trigger preapproved payment charge
	 *
	 * @since 1.0
	 * @return void
	 */
	public function process_preapproved_charge() {

		if ( empty( $_GET['nonce'] ) ) {
			return;
		}

		if ( ! wp_verify_nonce( $_GET['nonce'], 'give-wepay-process-preapproval' ) ) {
			return;
		}

		$payment_id = absint( $_GET['payment_id'] );
		$charge     = $this->charge_preapproved( $payment_id );

		if ( $charge ) {
			wp_redirect( add_query_arg( array( 'give-message' => 'preapproval-charged' ), admin_url( 'edit.php?post_type=give_forms&page=give-payment-history' ) ) );
			exit;
		} else {
			wp_redirect( add_query_arg( array( 'give-message' => 'preapproval-failed' ), admin_url( 'edit.php?post_type=give_forms&page=give-payment-history' ) ) );
			exit;
		}

	}


	/**
	 * Cancel a preapproved payment
	 *
	 * @since 1.0
	 * @return void
	 */
	public function process_preapproved_cancel() {
		global $give_options;

		if ( empty( $_GET['nonce'] ) ) {
			return;
		}

		if ( ! wp_verify_nonce( $_GET['nonce'], 'give-wepay-process-preapproval' ) ) {
			return;
		}

		if ( ! class_exists( 'WePay' ) ) {
			require dirname( __FILE__ ) . '/vendor/wepay.php';
		}

		$payment_id = absint( $_GET['payment_id'] );

		if ( empty( $payment_id ) ) {
			return;
		}

		if ( 'preapproval' !== get_post_status( $payment_id ) ) {
			return;
		}

		$creds = $this->get_api_credentials();

		if ( give_is_test_mode() ) {
			Wepay::useStaging( $creds['client_id'], $creds['client_secret'] );
		} else {
			Wepay::useProduction( $creds['client_id'], $creds['client_secret'] );
		}

		$wepay = new WePay( $creds['access_token'] );

		$response = $wepay->request( 'preapproval/find', array(
			'reference_id' => give_get_payment_key( $payment_id ),
			'account_id'   => $creds['account_id']
		) );

		foreach ( $response as $preapproval ) {

			$cancel = $wepay->request( 'preapproval/cancel', array(
				'preapproval_id' => $preapproval->preapproval_id
			) );

			give_insert_payment_note( $payment_id, __( 'Preapproval cancelled', 'give_wepay' ) );
			give_update_payment_status( $payment_id, 'cancelled' );

		}

		wp_redirect( add_query_arg( array( 'give-message' => 'preapproval-cancelled' ), admin_url( 'edit.php?post_type=give_forms&page=give-payment-history' ) ) );
		exit;
	}

	/**
	 * Charge a preapproved payment
	 *
	 * @since 1.0
	 * @return bool
	 */
	function charge_preapproved( $payment_id = 0 ) {

		global $give_options;

		if ( empty( $payment_id ) ) {
			return false;
		}

		if ( ! class_exists( 'WePay' ) ) {
			require dirname( __FILE__ ) . '/vendor/wepay.php';
		}

		$creds = $this->get_api_credentials( $payment_id );

		try {
			if ( give_is_test_mode() ) {
				Wepay::useStaging( $creds['client_id'], $creds['client_secret'] );
			} else {
				Wepay::useProduction( $creds['client_id'], $creds['client_secret'] );
			}
		}
		catch ( RuntimeException $e ) {
			// already been setup
		}

		$wepay = new WePay( $creds['access_token'] );

		$response = $wepay->request( 'preapproval/find', array(
			'reference_id' => give_get_payment_key( $payment_id ),
			'account_id'   => $creds['account_id']
		) );

		foreach ( $response as $preapproval ) {
			try {
				$charge = $wepay->request( 'checkout/create', array(
					'account_id'        => $creds['account_id'],
					'preapproval_id'    => $preapproval->preapproval_id,
					'type'              => $this->payment_type(),
					'fee_payer'         => $this->fee_payer(),
					'amount'            => give_get_payment_amount( $payment_id ),
					'short_description' => sprintf( __( 'Charge of preapproved payment %s', 'give_wepay' ), give_get_payment_key( $payment_id ) )
				) );

				give_insert_payment_note( $payment_id, 'WePay Checkout ID: ' . $charge->checkout_id );
				give_update_payment_status( $payment_id, 'publish' );

				return true;
			}
			catch ( WePayException $e ) {
				give_insert_payment_note( $payment_id, 'WePay Checkout Error: ' . $e->getMessage() );

				do_action( 'give_wepay_charge_failed', $e );

				return false;
			}
		}
	}


	/**
	 * Register payment statuses for preapproval
	 *
	 * @since 1.0
	 * @return void
	 */
	public function register_post_statuses() {
		register_post_status( 'preapproval', array(
			'label'                     => _x( 'Preapproved', 'Preapproved payment', 'give' ),
			'public'                    => true,
			'exclude_from_search'       => false,
			'show_in_admin_all_list'    => true,
			'show_in_admin_status_list' => true,
			'label_count'               => _n_noop( 'Active <span class="count">(%s)</span>', 'Active <span class="count">(%s)</span>', 'give' )
		) );
		register_post_status( 'cancelled', array(
			'label'                     => _x( 'Cancelled', 'Cancelled payment', 'give' ),
			'public'                    => true,
			'exclude_from_search'       => false,
			'show_in_admin_all_list'    => true,
			'show_in_admin_status_list' => true,
			'label_count'               => _n_noop( 'Active <span class="count">(%s)</span>', 'Active <span class="count">(%s)</span>', 'give' )
		) );
	}


	/**
	 * Register our new payment status labels for Give
	 *
	 * @since 1.0
	 * @return array
	 */
	public function payment_status_labels( $statuses ) {
		$statuses['preapproval'] = __( 'Preapproved', 'give_wepay' );
		$statuses['cancelled']   = __( 'Cancelled', 'give_wepay' );

		return $statuses;
	}


	/**
	 * Display the Preapprove column label
	 *
	 * @since 1.0
	 * @return array
	 */
	public function payments_column( $columns ) {

		global $give_options;

		if ( isset( $give_options['wepay_preapprove_only'] ) && $give_options['wepay_preapprove_only'] == 'on' ) {
			$columns['preapproval'] = __( 'Preapproval', 'give_wepay' );
		}

		return $columns;
	}


	/**
	 * Display the payment status filters
	 *
	 * @since 1.0
	 * @return array
	 */
	public function payment_status_filters( $views ) {
		$payment_count        = wp_count_posts( 'give_payment' );
		$preapproval_count    = '&nbsp;<span class="count">(' . $payment_count->preapproval . ')</span>';
		$cancelled_count      = '&nbsp;<span class="count">(' . $payment_count->cancelled . ')</span>';
		$current              = isset( $_GET['status'] ) ? $_GET['status'] : '';
		$views['preapproval'] = sprintf( '<a href="%s"%s>%s</a>', add_query_arg( 'status', 'preapproval', admin_url( 'edit.php?post_type=give_forms&page=give-payment-history' ) ), $current === 'preapproval' ? ' class="current"' : '', __( 'Preapproval Pending', 'give' ) . $preapproval_count );
		$views['cancelled']   = sprintf( '<a href="%s"%s>%s</a>', add_query_arg( 'status', 'cancelled', admin_url( 'edit.php?post_type=give_forms&page=give-payment-history' ) ), $current === 'cancelled' ? ' class="current"' : '', __( 'Cancelled', 'give' ) . $cancelled_count );

		return $views;
	}


	/**
	 * Show the Process / Cancel buttons for preapproved payments
	 *
	 * @since 1.0
	 * @return string
	 */
	public function payment_column_data( $value, $payment_id, $column_name ) {
		if ( $column_name == 'preapproval' ) {
			$status = get_post_status( $payment_id );

			$nonce = wp_create_nonce( 'give-wepay-process-preapproval' );

			$preapproval_args = array(
				'payment_id'  => $payment_id,
				'nonce'       => $nonce,
				'give-action' => 'charge_wepay_preapproval'
			);
			$cancel_args      = array(
				'payment_id'  => $payment_id,
				'nonce'       => $nonce,
				'give-action' => 'cancel_wepay_preapproval'
			);

			if ( 'preapproval' === $status ) {
				$value = '<a href="' . add_query_arg( $preapproval_args, admin_url( 'edit.php?post_type=give_forms&page=give-payment-history' ) ) . '" class="button-secondary button button-small" style="width: 120px; margin: 0 0 3px; text-align:center;">' . __( 'Process Payment', 'give_wepay' ) . '</a>&nbsp;';
				$value .= '<a href="' . add_query_arg( $cancel_args, admin_url( 'edit.php?post_type=give_forms&page=give-payment-history' ) ) . '" class="button-secondary button button-small" style="width: 120px; margin: 0; text-align:center;">' . __( 'Cancel Preapproval', 'give_wepay' ) . '</a>';
			}
		}

		return $value;
	}


	/**
	 * Register the gateway settings
	 *
	 * @access      public
	 * @since       1.0
	 * @return      array
	 */
	public function register_settings( $settings ) {

		$wepay_settings = apply_filters( 'give_gateway_wepay_settings', array(
			array(
				'name' => __( 'WePay Settings', 'give_wepay' ),
				'desc' => '<hr>',
				'id'   => 'give_title',
				'type' => 'give_title'
			),
			array(
				'id'   => 'wepay_client_id',
				'name' => __( 'Client ID', 'give_wepay' ),
				'desc' => __( 'Enter your WebPay client ID', 'give_wepay' ),
				'type' => 'text',
			),
			array(
				'id'   => 'wepay_client_secret',
				'name' => __( 'Client Secret', 'give_wepay' ),
				'desc' => __( 'Enter your Client Secret', 'give_wepay' ),
				'type' => 'text',
			),
			array(
				'id'   => 'wepay_access_token',
				'name' => __( 'Access Token', 'give_wepay' ),
				'desc' => __( 'Enter your Access Token', 'give_wepay' ),
				'type' => 'text',
			),
			array(
				'id'   => 'wepay_account_id',
				'name' => __( 'Account ID', 'give_wepay' ),
				'desc' => __( 'Enter your Account ID', 'give_wepay' ),
				'type' => 'text',
			),
			array(
				'id'   => 'wepay_preapprove_only',
				'name' => __( 'Preapprove Payments?', 'give_wepay' ),
				'desc' => __( 'Check this if you would like to preapprove payments but not charge until a later date.', 'give_wepay' ),
				'type' => 'checkbox'
			),
			array(
				'id'      => 'wepay_payment_type',
				'name'    => __( 'Payment Type', 'give_wepay' ),
				'desc'    => __( 'Select the type of payment you would like to process.', 'give_wepay' ),
				'type'    => 'select',
				'default' => 'DONATION',
				'options' => array(
					'GOODS'    => __( 'Goods', 'give_wepay' ),
					'SERVICE'  => __( 'Service', 'give_wepay' ),
					'DONATION' => __( 'Donation', 'give_wepay' ),
					'EVENT'    => __( 'Event', 'give_wepay' ),
					'PERSONAL' => __( 'Personal', 'give_wepay' ),
				),

			),
			array(
				'id'      => 'wepay_fee_payer',
				'name'    => __( 'Fee Payer', 'give_wepay' ),
				'desc'    => __( 'How would you like to collect the WePay gateway fee?', 'give_wepay' ),
				'type'    => 'radio',
				'options' => array(
					'Payee' => __( 'Recipient', 'give_wepay' ),
					'Payer' => __( 'Donor', 'give_wepay' )
				),
				'default' => 'Payee'
			),
			array(
				'id'      => 'wepay_onsite_payments',
				'name'    => __( 'On Site Payments', 'give_wepay' ),
				'desc'    => __( 'Process credit cards on-site or send customers to WePay\'s terminal? Note: On-site payments require SSL.', 'give_wepay' ),
				'type'    => 'radio',
				'options' => array(
					'onsite'  => __( 'On-Site', 'give_wepay' ),
					'offsite' => __( 'Off-Site', 'give_wepay' )
				),
				'default' => 'offsite'
			)
		) );

		return array_merge( $settings, $wepay_settings );
	}


	/**
	 * Determine the type of payment we are processing
	 *
	 * @access      public
	 * @since       1.0
	 * @return      string
	 */

	private function payment_type() {
		global $give_options;
		$type = isset( $give_options['wepay_payment_type'] ) ? $give_options['wepay_payment_type'] : 'GOODS';

		return $type;
	}


	/**
	 * Who pays the fee?
	 *
	 * @access      public
	 * @since       1.0
	 * @return      string
	 */
	private function fee_payer() {
		global $give_options;
		$payer = isset( $give_options['wepay_fee_payer'] ) ? $give_options['wepay_fee_payer'] : 'Payee';

		return $payer;
	}


	/**
	 * Process payments onsite or off?
	 *
	 * @access      public
	 * @since       1.0
	 * @return      string
	 */

	private function onsite_payments() {

		global $give_options;

		return isset( $give_options['wepay_onsite_payments'] ) && $give_options['wepay_onsite_payments'] == 'onsite';
	}

}

$give_wepay = new Give_WePay_Gateway;