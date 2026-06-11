<?php
/**
 * Smart Screens v2 payment payload builder and redirect form.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'PNP_SSV2_ACTION_URL', 'https://pay1.plugnpay.com/pay/' );

/**
 * Allowed hidden field names sent to SSv2.
 *
 * @return array
 */
function pnp_get_ssv2_field_whitelist() {
	$fields = array(
		'pt_gateway_account',
		'pt_transaction_amount',
		'pt_account_code_1',
		'pt_account_code_2',
		'pt_transaction_hash',
		'pt_transaction_time',
		'pb_cards_allowed',
		'pb_post_auth',
		'pt_currency',
		'pt_order_classifier',
		'pb_transition_type',
	);

	if ( 'callback' === get_option( 'pnp_response_type', 'receipt' ) ) {
		$fields[] = 'pb_success_url';
	} else {
		$fields = array_merge(
			$fields,
			array(
				'pb_receipt_type',
				'pb_receipt_company',
				'pb_receipt_name',
				'pb_receipt_address_1',
				'pb_receipt_address_2',
				'pb_receipt_city',
				'pb_receipt_state',
				'pb_receipt_province',
				'pb_receipt_postal_code',
				'pb_receipt_country',
				'pb_receipt_email_address',
				'pb_receipt_phone',
				'pb_receipt_fax',
				'pb_receipt_company_url',
				'pb_receipt_transaction_url',
			)
		);
	}

	return $fields;
}

/**
 * Build SSv2 payment data array from validated customer input.
 *
 * @param array $payload Validated payment payload.
 * @return array
 */
function pnp_build_payment_data( $payload ) {
	$pt_gateway_account  = sanitize_text_field( get_option( 'pnp_pt_gateway_account', '' ) );
	$pt_gateway_account    = strtolower( preg_replace( '/[^a-zA-Z0-9]/', '', $pt_gateway_account ) );
	$pt_transaction_time   = gmdate( 'YmdHis', time() );
	$pt_transaction_amount = $payload['pt_transaction_amount'];
	$pt_account_code_1     = $payload['pt_account_code_1'];
	$pt_account_code_2     = $payload['pt_account_code_2'];

	$payment_data = array(
		'pt_gateway_account'    => $pt_gateway_account,
		'pt_transaction_amount' => $pt_transaction_amount,
		'pt_account_code_1'     => $pt_account_code_1,
		'pt_account_code_2'     => $pt_account_code_2,
		'pt_transaction_hash'   => '',
		'pt_transaction_time'   => $pt_transaction_time,
	);

	$option_fields = array(
		'pnp_pb_cards_allowed',
		'pnp_pb_post_auth',
		'pnp_pt_currency',
		'pnp_pt_order_classifier',
	);

	if ( 'callback' === get_option( 'pnp_response_type', 'receipt' ) ) {
		$option_fields[] = 'pnp_pb_success_url';
		$option_fields[] = 'pnp_pb_transition_type';
	} else {
		$option_fields = array_merge(
			$option_fields,
			array(
				'pnp_pb_transition_type',
				'pnp_pb_receipt_type',
				'pnp_pb_receipt_company',
				'pnp_pb_receipt_name',
				'pnp_pb_receipt_address_1',
				'pnp_pb_receipt_address_2',
				'pnp_pb_receipt_city',
				'pnp_pb_receipt_state',
				'pnp_pb_receipt_province',
				'pnp_pb_receipt_postal_code',
				'pnp_pb_receipt_country',
				'pnp_pb_receipt_email_address',
				'pnp_pb_receipt_phone',
				'pnp_pb_receipt_fax',
				'pnp_pb_receipt_company_url',
				'pnp_pb_receipt_transaction_url',
			)
		);
	}

	foreach ( $option_fields as $option_name ) {
		$value      = sanitize_text_field( get_option( $option_name, '' ) );
		$field_name = preg_replace( '/^pnp_/', '', $option_name );
		$payment_data[ $field_name ] = $value;
	}

	$authhash_key    = get_option( 'pnp_pt_transaction_hash_key', '' );
	$authhash_fields = array();
	$hash_mode       = get_option( 'pnp_pt_transaction_hash_fields', 'acct_code,card-amount,publisher-name' );

	if ( 'publisher-name' === $hash_mode ) {
		$authhash_fields[] = $pt_gateway_account;
	} elseif ( 'card-amount,publisher-name' === $hash_mode ) {
		$authhash_fields[] = $pt_transaction_amount;
		$authhash_fields[] = $pt_gateway_account;
	} else {
		$authhash_fields[] = $pt_account_code_1;
		$authhash_fields[] = $pt_transaction_amount;
		$authhash_fields[] = $pt_gateway_account;
	}

	$authhash_string = $authhash_key . $pt_transaction_time;
	foreach ( $authhash_fields as $part ) {
		if ( '' !== $part ) {
			$authhash_string .= $part;
		}
	}

	$payment_data['pt_transaction_hash'] = md5( $authhash_string );

	return $payment_data;
}

/**
 * Build auto-submitting SSv2 redirect HTML.
 *
 * @param array $payload Validated payment payload.
 * @return string
 */
function pnp_build_ssv2_redirect( $payload ) {
	$pt_gateway_account = sanitize_text_field( get_option( 'pnp_pt_gateway_account', '' ) );
	$pt_gateway_account = strtolower( preg_replace( '/[^a-zA-Z0-9]/', '', $pt_gateway_account ) );
	if ( '' === $pt_gateway_account ) {
		return pnp_reject_form();
	}

	$payment_data = pnp_build_payment_data( $payload );
	$whitelist    = pnp_get_ssv2_field_whitelist();

	ob_start();
	?>
	<div class="pnp-billpay-wrap pnp-billpay-redirect">
		<div class="pnp-billpay-card">
			<div class="pnp-billpay-body">
				<p><?php echo esc_html__( 'Redirecting to secure payment...', 'plugnpay-billpay-lite' ); ?></p>
				<form method="post" id="pnp-auto-submit-form" name="PnpForm1" action="<?php echo esc_url( PNP_SSV2_ACTION_URL ); ?>" accept-charset="utf-8">
					<?php foreach ( $payment_data as $key => $value ) : ?>
						<?php if ( in_array( $key, $whitelist, true ) ) : ?>
							<input type="hidden" name="<?php echo esc_attr( $key ); ?>" value="<?php echo esc_attr( $value ); ?>" />
						<?php endif; ?>
					<?php endforeach; ?>
					<noscript>
						<p><input type="submit" value="<?php echo esc_attr__( 'Continue to Payment', 'plugnpay-billpay-lite' ); ?>" /></p>
					</noscript>
				</form>
			</div>
		</div>
	</div>
	<script>
	document.getElementById('pnp-auto-submit-form').submit();
	</script>
	<?php
	return ob_get_clean();
}
