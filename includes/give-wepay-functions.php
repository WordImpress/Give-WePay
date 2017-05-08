<?php
/**
 * Get payment method label.
 *
 * @since 1.0
 * @return string
 */
function give_wepay_get_payment_method_label() {
	return ( give_get_option( 'wepay_payment_method_label', false ) ?  give_get_option( 'wepay_payment_method_label', '' ) : __( 'WePay', 'give-wepay' ) );
}

