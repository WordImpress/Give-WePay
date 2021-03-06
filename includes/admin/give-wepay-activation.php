<?php
/**
 * Give WePay Gateway Activation
 *
 * @package     Give
 * @copyright   Copyright (c) 2016, WordImpress
 * @license     https://opensource.org/licenses/gpl-license GNU Public License
 * @since       1.2
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Give WePay Activation Banner
 *
 * Includes and initializes Give activation banner class.
 *
 * @since 1.2
 */
function give_wepay_activation_banner() {

	// Check for if give plugin activate or not.
	$is_give_active = defined( 'GIVE_PLUGIN_BASENAME' ) ? is_plugin_active( GIVE_PLUGIN_BASENAME ) : false;

	//Check to see if Give is activated, if it isn't deactivate and show a banner
	if ( current_user_can( 'activate_plugins' ) && ! $is_give_active ) {

		add_action( 'admin_notices', 'give_wepay_activation_notice' );

		//Don't let this plugin activate
		deactivate_plugins( GIVE_WEPAY_BASENAME );

		if ( isset( $_GET['activate'] ) ) {
			unset( $_GET['activate'] );
		}

		return false;

	}

	// Minimum Give version required for this plugin to work.
	if ( version_compare( GIVE_VERSION, GIVE_WEPAY_MIN_GIVE_VERSION, '<' ) ) {

		add_action( 'admin_notices', 'give_wepay_version_notice' );

		// Don't let this plugin activate.
		deactivate_plugins( GIVE_WEPAY_BASENAME );

		if ( isset( $_GET['activate'] ) ) {
			unset( $_GET['activate'] );
		}

		return false;

	}

	// Check for activation banner inclusion.
	if ( ! class_exists( 'Give_Addon_Activation_Banner' )
	     && file_exists( GIVE_PLUGIN_DIR . 'includes/admin/class-addon-activation-banner.php' )
	) {

		include GIVE_PLUGIN_DIR . 'includes/admin/class-addon-activation-banner.php';

	}

	// Initialize activation welcome banner.
	if ( class_exists( 'Give_Addon_Activation_Banner' ) ) {

		//Only runs on admin
		$args = array(
			'file'              => __FILE__,
			'name'              => esc_html__( 'WePay Gateway', 'give-wepay' ),
			'version'           => GIVE_WEPAY_VERSION,
			'settings_url'      => admin_url( 'edit.php?post_type=give_forms&page=give-settings&tab=gateways' ),
			'documentation_url' => 'http://docs.givewp.com/addon-wepay',
			'support_url'       => 'https://givewp.com/support/',
			'testing'           => false
		);

		new Give_Addon_Activation_Banner( $args );

	}

	return false;

}

add_action( 'admin_init', 'give_wepay_activation_banner' );

/**
 * Notice for No Core Activation
 *
 * @since 1.2
 */
function give_wepay_activation_notice() {
	echo '<div class="error"><p>' . __( '<strong>Activation Error:</strong> You must have the <a href="https://givewp.com/" target="_blank">Give</a> plugin installed and activated for the WePay Add-on to activate.', 'give-wepay' ) . '</p></div>';}


/**
 * Notice for min. version violation.
 *
 * @since 1.3.3
 */
function give_wepay_version_notice() {
	echo '<div class="error"><p>' . sprintf( __( '<strong>Activation Error:</strong> You must have <a href="%1$s" target="_blank">Give</a> minimum version %2$s for the WePay Add-on to activate.', 'give-wepay' ), 'https://givewp.com', GIVE_PAYU_MIN_GIVE_VER ) . '</p></div>';
}