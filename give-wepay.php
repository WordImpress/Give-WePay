<?php
/**
 * Plugin Name: Give - WePay Gateway
 * Description: Process Give donations via the WePay Payment Gateway
 * Author: WordImpress
 * Author URI: https://wordimpress.com
 * Text Domain: give-wepay
 * Domain Path: languages
 * Version: 1.2
 * GitHub Plugin URI: https://github.com/WordImpress/Give-WePay
 */


//Plugin version.
if ( ! defined( 'GIVE_WEPAY_VERSION' ) ) {
	define( 'GIVE_WEPAY_VERSION', '1.2' );
}

// Plugin Folder Path.
if ( ! defined( 'GIVE_WEPAY_DIR' ) ) {
	define( 'GIVE_WEPAY_DIR', plugin_dir_path( __FILE__ ) );
}

//Plugin Folder URL.
if ( ! defined( 'GIVE_WEPAY_URL' ) ) {
	define( 'GIVE_WEPAY_URL', plugin_dir_url( __FILE__ ) );
}

// WePay API Version that Give uses.
if ( ! defined( 'GIVE_WEPAY_API_VERSION' ) ) {
	define( 'GIVE_WEPAY_API_VERSION', apply_filters( 'give_wepay_api_version', '' ) );
}

/**
 * Class Give_WePay_Gateway
 */
final class Give_WePay_Gateway {

	/** Singleton *************************************************************/

	/**
	 * @var Give_WePay_Gateway The one true Give_WePay_Gateway
	 */
	private static $instance;
	private $account_id;


	/**
	 * Main WePay Instance
	 *
	 * @since     v1.0
	 * @static var array $instance
	 * @return    Give_WePay_Gateway()
	 */
	public static function instance() {
		if ( ! isset( self::$instance ) ) {
			self::$instance = new Give_WePay_Gateway();
			self::$instance->wepay_init();
		}

		return self::$instance;
	}


	function wepay_init() {

		// Filters
		add_filter( 'give_payment_gateways', array( $this, 'register_gateway' ) );
		add_filter( 'give_payments_table_column', array( $this, 'payment_column_data' ), 10, 3 );
		add_filter( 'give_payment_statuses', array( $this, 'payment_status_labels' ) );
		add_filter( 'give_payments_table_columns', array( $this, 'payments_column' ) );
		add_filter( 'give_payments_table_views', array( $this, 'payment_status_filters' ) );

		// Actions
		add_action( 'give_gateway_wepay', array( $this, 'process_wepay_payment' ) );
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
		add_action( 'init', array( $this, 'give_add_wepay_licensing' ) );

		//Includes
		require_once GIVE_WEPAY_DIR . 'includes/admin/settings.php';

	}

	/**
	 * Licensing
	 */
	function give_add_wepay_licensing() {
		if ( class_exists( 'Give_License' ) ) {
			new Give_License( __FILE__, 'WePay Gateway', GIVE_WEPAY_VERSION, 'Devin Walker', 'wepay_license_key' );
		}
	}


	/**
	 * Get API Credentials
	 *
	 * @return mixed|void
	 */
	public function get_api_credentials() {

		$creds = array();

		if ( give_is_test_mode() ) {
			$creds['client_id']     = ! empty( give_get_option( 'wepay_sandbox_client_id' ) ) ? trim( give_get_option( 'wepay_sandbox_client_id' ) ) : '';
			$creds['client_secret'] = ! empty( give_get_option( 'wepay_sandbox_client_secret' ) ) ? trim( give_get_option( 'wepay_sandbox_client_secret' ) ) : '';
			$creds['access_token']  = ! empty( give_get_option( 'wepay_sandbox_access_token' ) ) ? trim( give_get_option( 'wepay_sandbox_access_token' ) ) : '';
			$creds['account_id']    = ! empty( give_get_option( 'wepay_sandbox_account_id' ) ) ? trim( give_get_option( 'wepay_sandbox_account_id' ) ) : '';
		} else {
			$creds['client_id']     = ! empty( give_get_option( 'wepay_client_id' ) ) ? trim( give_get_option( 'wepay_client_id' ) ) : '';
			$creds['client_secret'] = ! empty( give_get_option( 'wepay_client_secret' ) ) ? trim( give_get_option( 'wepay_client_secret' ) ) : '';
			$creds['access_token']  = ! empty( give_get_option( 'wepay_access_token' ) ) ? trim( give_get_option( 'wepay_access_token' ) ) : '';
			$creds['account_id']    = ! empty( give_get_option( 'wepay_account_id' ) ) ? trim( give_get_option( 'wepay_account_id' ) ) : '';
		}


		return apply_filters( 'give_wepay_get_api_creds', $creds );

	}

	/**
	 * Get WePay API
	 *
	 * @return WePay
	 */
	public function get_wepay_api() {

		$creds = $this->get_api_credentials();

		try {
			if ( give_is_test_mode() ) {
				WePay::useStaging( $creds['client_id'], $creds['client_secret'], GIVE_WEPAY_API_VERSION );
			} else {
				WePay::useProduction( $creds['client_id'], $creds['client_secret'], GIVE_WEPAY_API_VERSION );
			}
		} catch ( RuntimeException $e ) {
			//Log with DB.
			give_record_gateway_error( __( 'WePay Error', 'give-wepay' ), sprintf( __( 'An error happened while processing a donation.<br> Details: %1$s <br><br>Code: %2$s', 'give-wepay' ), $e->getMessage(), $e->getCode() ) );

			//Display error for user.
			give_set_error( 'wepay_error', __( 'An error occurred while processing your donation. Please try again.', 'give-wepay' ) );

			//Send em' on back
			give_send_back_to_checkout( '?payment-mode=wepay' );

		}

		return new WePay( $creds['access_token'] );
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
			$checkout_label = __( 'Credit Card', 'give-wepay' );
		} else {
			$checkout_label = __( 'Credit Card or Bank Account', 'give-wepay' );
		}

		$gateways['wepay'] = array(
			'admin_label'    => __( 'WePay', 'give-wepay' ),
			'checkout_label' => $checkout_label
		);

		return $gateways;
	}

	/**
	 * Process WePay Payment Gateway
	 *
	 * @param $purchase_data
	 *
	 * @see: https://www.wepay.com/developer/reference/checkout#create
	 */
	public function process_wepay_payment( $purchase_data ) {

		global $give_options;

		if ( ! class_exists( 'WePay' ) ) {
			require dirname( __FILE__ ) . '/vendor/wepay.php';
		}

		$creds = $this->get_api_credentials();
		$wepay = $this->get_wepay_api();

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

		//Item name - pass level name if variable priced
		$item_name = $purchase_data['post_data']['give-form-title'];

		//Append price option name if necessary
		if ( give_has_variable_prices( $purchase_data['post_data']['give-form-id'] ) && isset( $purchase_data['post_data']['give-price-id'] ) ) {
			$item_price_level_text = give_get_price_option_name( $purchase_data['post_data']['give-form-id'], $purchase_data['post_data']['give-price-id'] );

			//Is there any donation level text?
			if ( ! empty( $item_price_level_text ) ) {
				$item_name .= ' - ' . $item_price_level_text;
			}

		}

		// Record the pending payment
		$payment_id = give_insert_payment( $payment_data );
		$endpoint   = isset( $give_options['wepay_preapprove_only'] ) ? 'preapproval' : 'checkout';

		$args = array(
			'account_id'        => $creds['account_id'],
			'amount'            => $purchase_data['price'],
			'short_description' => stripslashes_deep( html_entity_decode( wp_strip_all_tags( $item_name ), ENT_COMPAT, 'UTF-8' ) ),
			'currency'          => give_get_currency(),
			'reference_id'      => $purchase_data['purchase_key'],
		);

		//Preapproval payment: Preapproval payments have a different structure
		//@see: https://www.wepay.com/developer/reference/preapproval#create
		if ( $endpoint == 'preapproval' ) {
			//Use payment_method param: use a tokenized card
			$args['period']       = 'once';
			$args['redirect_uri'] = add_query_arg( 'payment-confirmation', 'wepay', get_permalink( $give_options['success_page'] ) );
			$args['fallback_uri'] = give_get_failed_transaction_uri();
			$args['prefill_info'] = array(
				'name'  => $purchase_data['user_info']['first_name'] . ' ' . $purchase_data['user_info']['last_name'],
				'email' => $purchase_data['user_email'],
			);
			$args['fee_payer']    = $this->fee_payer();

			if ( $this->onsite_payments() && ! empty( $_POST['give_wepay_card'] ) ) {
				// Use a tokenized card
				$args['payment_method_id']   = $_POST['give_wepay_card'];
				$args['payment_method_type'] = 'credit_card';
			}

		} else {
			$args['type'] = $this->payment_type();
		}

		//This is an onsite payment AND not preapproval
		if ( $this->onsite_payments() && ! empty( $_POST['give_wepay_card'] ) && ! isset( $give_options['wepay_preapprove_only'] ) ) {

			//Use payment_method param:
			$args['payment_method'] = array(
				'type'        => 'credit_card',
				'credit_card' => array(
					'id' => $_POST['give_wepay_card'],
				)
			);

			//Fees
			$args['fee'] = array(
				'fee_payer' => $this->fee_payer(),
			);

		} elseif ( ! isset( $args['period'] ) ) {

			//Is this an offsite payment
			$args['hosted_checkout'] = array(
				'redirect_uri' => add_query_arg( 'payment-confirmation', 'wepay', get_permalink( $give_options['success_page'] ) ),
				'fallback_uri' => give_get_failed_transaction_uri(),
				'prefill_info' => array(
					'name'  => $purchase_data['user_info']['first_name'] . ' ' . $purchase_data['user_info']['last_name'],
					'email' => $purchase_data['user_email'],
				),
			);

			//Fees
			$args['fee'] = array(
				'fee_payer' => $this->fee_payer(),
			);

		}

		// Let other plugins modify the data that goes to WePay
		$args = apply_filters( 'give_wepay_checkout_args', $args );

		/**
		 * Uncomment below for testing only!
		 */
		//		echo '<pre>';
		//		print_r( $args );
		//		echo '</pre>';
		//		exit;

		// create the checkout
		try {

			$response = $wepay->request( $endpoint . '/create', $args );

			if ( $this->onsite_payments() ) {

				if ( ! empty( $response->error ) ) {
					// if errors are present, send the user back to the purchase page so they can be corrected
					give_set_error( $response->error, $response->error_description . '. Error code: ' . $response->error_code );
					//Log with DB.
					give_record_gateway_error( __( 'WePay Error', 'give-wepay' ), sprintf( __( 'An error happened while processing a donation.<br> Details: %1$s <br><br>Code: %2$s', 'give-wepay' ), $response->error, $response->error_description ) );
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
					wp_redirect( $response->hosted_checkout->checkout_uri );
					exit;
				}

			}
		} catch ( WePayException $e ) {
			give_set_error( 'give_wepay_exception', $e->getMessage() );
			give_record_gateway_error( __( 'WePay Error', 'give-wepay' ), sprintf( __( 'An error happened while processing a donation.<br> Details: %1$s <br><br>Code: %2$s', 'give-wepay' ), $e->getMessage(), $e->getCode() ) );
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

		if ( $_GET['payment-confirmation'] != 'wepay' ) {
			return;
		}

		if ( ! class_exists( 'WePay' ) ) {
			require dirname( __FILE__ ) . '/vendor/wepay.php';
		}

		$creds = $this->get_api_credentials();
		$wepay = $this->get_wepay_api();

		try {

			//Preapproval donation payments
			if ( isset( $give_options['wepay_preapprove_only'] ) ) {

				$preapproval_id = urldecode( $_GET['preapproval_id'] );
				$response       = $wepay->request( 'preapproval', array(
					'preapproval_id' => $preapproval_id
				) );

				if ( $response->account_id != $creds['account_id'] ) {
					wp_die( __( 'The store ID does not match those set in the site settings.', 'give-wepay' ), __( 'Error', 'give-wepay' ) );
				}

				if ( $response->state != 'captured' && $response->state != 'approved' ) {
					wp_die( __( 'Your payment is still processing. Please refresh the page to see your donation receipt.', 'give-wepay' ), __( 'Error', 'give-wepay' ) );
				}

				$payment_id = give_get_purchase_id_by_key( $response->reference_id );

				//Ensure this payment hasn't been completed already (so it doesn't go from completed back to preapproval)
				$payment_status = give_check_for_existing_payment( $payment_id );
				if ( $payment_status === false ) {
					give_insert_payment_note( $payment_id, sprintf( __( 'WePay Preapproval ID: %s', 'give' ), $response->preapproval_id ) );
					give_update_payment_status( $payment_id, 'preapproval' );
				}


			} //All other donation payments
			else {

				$checkout_id = isset( $_GET['checkout_id'] ) ? urldecode( $_GET['checkout_id'] ) : '';

				$response = $wepay->request( 'checkout', array(
					'checkout_id' => $checkout_id
				) );

				if ( $response->account_id != $creds['account_id'] ) {
					wp_die( __( 'The store ID does not match those set in the site settings.', 'give-wepay' ), __( 'Error', 'give-wepay' ) );
				}

				if ( $response->state != 'captured' && $response->state != 'authorized' ) {
					wp_die( __( 'Your payment is still processing. Please refresh the page to see your donation receipt.', 'give-wepay' ), __( 'Error', 'give-wepay' ) );
				}

				$payment_id = give_get_purchase_id_by_key( $response->reference_id );

				give_insert_payment_note( $payment_id, sprintf( __( 'WePay Checkout ID: %s', 'give' ), $response->checkout_id ) );
				give_update_payment_status( $payment_id, 'publish' );
			}

		} catch ( Exception $e ) {

			// Show a message if there was an error of some kind

		}

	}


	/**
	 * Plugin Scripts
	 */
	public function scripts() {

		//Onsite payments only
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

		wp_register_script( 'give-wepay-tokenization', $script_url );
		wp_enqueue_script( 'give-wepay-tokenization' );

		wp_register_script( 'give-wepay-gateway', GIVE_WEPAY_URL . 'assets/js/wepay.js', array(
			'give-wepay-tokenization',
			'jquery'
		) );
		wp_enqueue_script( 'give-wepay-gateway' );

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
			add_settings_error( 'give-wepay-notices', 'give-wepay-preapproval-charged', __( 'The preapproved payment was successfully charged.', 'give-wepay' ), 'updated' );
		}
		if ( isset( $_GET['give-message'] ) && 'preapproval-failed' == $_GET['give-message'] ) {
			add_settings_error( 'give-wepay-notices', 'give-wepay-preapproval-charged', __( 'The preapproved payment failed to be charged.', 'give-wepay' ), 'error' );
		}
		if ( isset( $_GET['give-message'] ) && 'preapproval-cancelled' == $_GET['give-message'] ) {
			add_settings_error( 'give-wepay-notices', 'give-wepay-preapproval-cancelled', __( 'The preapproved payment was successfully cancelled.', 'give-wepay' ), 'updated' );
		}

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
		$wepay = $this->get_wepay_api();

		$response = $wepay->request( 'preapproval/find', array(
			'reference_id' => give_get_payment_key( $payment_id ),
			'account_id'   => $creds['account_id']
		) );

		foreach ( $response as $preapproval ) {

			$cancel = $wepay->request( 'preapproval/cancel', array(
				'preapproval_id' => $preapproval->preapproval_id
			) );

			give_insert_payment_note( $payment_id, __( 'Preapproval cancelled', 'give-wepay' ) );
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

		$creds = $this->get_api_credentials();
		$wepay = $this->get_wepay_api();

		$response = $wepay->request( 'preapproval/find', array(
			'reference_id' => give_get_payment_key( $payment_id ),
			'account_id'   => $creds['account_id']
		) );

		foreach ( $response as $preapproval ) {
			try {
				$charge = $wepay->request( 'checkout/create', array(
					'amount'            => give_get_payment_amount( $payment_id ),
					'short_description' => sprintf( __( 'Charge of preapproved payment %s', 'give-wepay' ), give_get_payment_key( $payment_id ) ),
					'account_id'        => $creds['account_id'],
					'type'              => $this->payment_type(),
					'currency'          => give_get_currency(),
					'payment_method'    => array(
						'type'        => 'preapproval',
						'preapproval' => array(
							'id' => $preapproval->preapproval_id,
						),
					),
					'fee'               => array(
						'fee_payer' => $this->fee_payer(),
					),

				) );

				give_insert_payment_note( $payment_id, 'WePay Checkout ID: ' . $charge->checkout_id );
				give_update_payment_status( $payment_id, 'publish' );

				return true;
			} catch ( WePayException $e ) {
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
	}


	/**
	 * Register our new payment status labels for Give
	 *
	 * @since 1.0
	 * @return array
	 */
	public function payment_status_labels( $statuses ) {
		$statuses['preapproval'] = __( 'Preapproved', 'give-wepay' );

		return $statuses;
	}


	/**
	 * Display the Preapproval column label
	 *
	 * @since 1.0
	 * @return array
	 */
	public function payments_column( $columns ) {

		global $give_options;

		if ( isset( $give_options['wepay_preapprove_only'] ) && $give_options['wepay_preapprove_only'] == 'on' ) {
			$columns['preapproval'] = __( 'Preapproval', 'give-wepay' );
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
	 *
	 * @param $value
	 * @param $payment_id
	 * @param $column_name
	 *
	 * @return string
	 */
	public function payment_column_data( $value, $payment_id, $column_name ) {

		$gateway = give_get_payment_gateway( $payment_id );

		if ( $column_name == 'preapproval' && $gateway == 'wepay' ) {
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
				$value = '<a href="' . add_query_arg( $preapproval_args, admin_url( 'edit.php?post_type=give_forms&page=give-payment-history' ) ) . '" class="button-secondary button button-small" style="width: 120px; margin: 0 0 3px; text-align:center;">' . __( 'Process Payment', 'give-wepay' ) . '</a>&nbsp;';
				$value .= '<a href="' . add_query_arg( $cancel_args, admin_url( 'edit.php?post_type=give_forms&page=give-payment-history' ) ) . '" class="button-secondary button button-small" style="width: 120px; margin: 0; text-align:center;">' . __( 'Cancel Preapproval', 'give-wepay' ) . '</a>';
			}
		}

		return $value;
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

		return strtolower( $type );
	}


	/**
	 * Who pays the fee?
	 *
	 * @see         : https://www.wepay.com/developer/reference/structures#fee
	 *
	 * @access      public
	 * @since       1.0
	 * @return      string
	 */
	private function fee_payer() {

		global $give_options;

		//Payer or Payee (lowercase)
		$payer = isset( $give_options['wepay_fee_payer'] ) ? $give_options['wepay_fee_payer'] : 'Payee';

		return strtolower( $payer );

	}


	/**
	 * Process payments onsite or off?
	 *
	 * @access      public
	 * @since       1.0
	 * @return bool
	 */
	public static function onsite_payments() {

		$onsite_option = give_get_option( 'wepay_onsite_payments' );

		return isset( $onsite_option ) && $onsite_option == 'onsite';
	}

}

/**
 * The main function responsible for returning the one true Give_WePay_Gateway Instance
 * to functions everywhere.
 *
 * Use this function like you would a global variable, except without needing
 * to declare the global.
 *
 * Example: <?php $recurring = Give_WePay_Gateway(); ?>
 *
 * @since v1.0
 *
 * @return mixed one true Give_WePay_Gateway Instance
 */

function Give_WePay() {

	if ( ! class_exists( 'Give' ) ) {
		return false;
	}

	return Give_WePay_Gateway::instance();
}

add_action( 'plugins_loaded', 'Give_WePay' );