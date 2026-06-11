<?php
/**
 * Security helpers: input whitelist, sanitization, validation, and output wrappers.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'PNP_PAYMENT_NONCE_ACTION', 'pnp_payment_action' );
define( 'PNP_PAYMENT_NONCE_FIELD', 'pnp_payment_nonce' );
define( 'PNP_SESSION_TRANSIENT_PREFIX', 'pnp_pay_session_' );
define( 'PNP_RATE_LIMIT_PREFIX', 'pnp_pay_rl_' );
define( 'PNP_RATE_LIMIT_MAX', 10 );
define( 'PNP_RATE_LIMIT_WINDOW', 900 );
define( 'PNP_SESSION_TTL', 900 );

define( 'PNP_BLOCKED_FIELD_NAMES', 'card_number,cvv,cvc,pan,cardnum,card_num,credit_card,cc_number' );
define( 'PNP_PAYMENT_AJAX_ACTION', 'pnp_payment' );

/**
 * Canonical payment field keys accepted by the plugin.
 *
 * @return array
 */
function pnp_get_allowed_param_keys() {
	return array(
		'pt_transaction_amount',
		'pt_account_code_1',
		'pt_account_code_2',
	);
}

/**
 * Map of alias => canonical field name.
 *
 * @return array
 */
function pnp_get_param_aliases() {
	return array(
		'amt'  => 'pt_transaction_amount',
		'id1'  => 'pt_account_code_1',
		'id2'  => 'pt_account_code_2',
	);
}

/**
 * Normalize GET/POST input to canonical payment fields.
 *
 * @param array|null $source Raw input array; defaults to merged GET+POST.
 * @return array
 */
function pnp_normalize_payment_input( $source = null ) {
	if ( null === $source ) {
		$source = array_merge( wp_unslash( $_GET ), wp_unslash( $_POST ) );
	}

	$aliases   = pnp_get_param_aliases();
	$canonical = pnp_get_allowed_param_keys();
	$normalized = array(
		'pt_transaction_amount' => '',
		'pt_account_code_1'     => '',
		'pt_account_code_2'     => '',
	);

	foreach ( $canonical as $field ) {
		if ( isset( $source[ $field ] ) && '' !== (string) $source[ $field ] ) {
			$normalized[ $field ] = (string) $source[ $field ];
		}
	}

	foreach ( $aliases as $alias => $field ) {
		if ( '' !== $normalized[ $field ] ) {
			continue;
		}
		if ( isset( $source[ $alias ] ) && '' !== (string) $source[ $alias ] ) {
			$normalized[ $field ] = (string) $source[ $alias ];
		}
	}

	if ( 'yes' !== get_option( 'pnp_layout_identifer1_enabled', 'yes' ) ) {
		$normalized['pt_account_code_1'] = '';
	}

	if ( 'yes' !== get_option( 'pnp_layout_identifer2_enabled', 'no' ) ) {
		$normalized['pt_account_code_2'] = '';
	}

	return $normalized;
}

/**
 * Reject requests that include blocked cardholder-data field names.
 *
 * @param array $source Input array.
 * @return bool True when blocked fields detected.
 */
function pnp_has_blocked_fields( $source ) {
	$blocked = array_map( 'strtolower', explode( ',', PNP_BLOCKED_FIELD_NAMES ) );
	foreach ( array_keys( $source ) as $key ) {
		if ( in_array( strtolower( (string) $key ), $blocked, true ) ) {
			return true;
		}
	}
	return false;
}

/**
 * Sanitize and format a payment amount.
 *
 * @param string $raw Raw amount.
 * @return string|false Formatted amount or false when invalid.
 */
function pnp_sanitize_amount( $raw ) {
	$raw = sanitize_text_field( (string) $raw );
	$raw = preg_replace( '/[^0-9.]/', '', $raw );

	if ( '' === $raw || substr_count( $raw, '.' ) > 1 ) {
		return false;
	}

	$amount = number_format( (float) $raw, 2, '.', '' );

	if ( ! preg_match( '/^\d{1,8}(\.\d{2})$/', $amount ) ) {
		return false;
	}

	return $amount;
}

/**
 * Sanitize an account code field.
 *
 * @param string $raw Raw account code.
 * @return string
 */
function pnp_sanitize_account_code( $raw ) {
	$raw = sanitize_text_field( (string) $raw );
	$raw = substr( $raw, 0, 64 );
	$raw = preg_replace( '/[^a-zA-Z0-9,\-\_ ]/', '', $raw );
	return $raw;
}

/**
 * Validate normalized payment input against admin rules.
 *
 * @param array $data Normalized payment data.
 * @return true|WP_Error
 */
function pnp_validate_payment_input( $data ) {
	$amount = pnp_sanitize_amount( $data['pt_transaction_amount'] ?? '' );
	if ( false === $amount ) {
		return new WP_Error( 'pnp_invalid_amount', 'Invalid amount.' );
	}

	$min = pnp_sanitize_amount( get_option( 'pnp_layout_amount_min', '0.01' ) );
	$max = pnp_sanitize_amount( get_option( 'pnp_layout_amount_max', '99999.99' ) );
	if ( false === $min ) {
		$min = '0.01';
	}
	if ( false === $max ) {
		$max = '99999.99';
	}

	if ( (float) $amount < (float) $min || (float) $amount > (float) $max ) {
		return new WP_Error( 'pnp_amount_range', 'Amount out of range.' );
	}

	$data['pt_transaction_amount'] = $amount;
	$data['pt_account_code_1']     = pnp_sanitize_account_code( $data['pt_account_code_1'] ?? '' );
	$data['pt_account_code_2']     = pnp_sanitize_account_code( $data['pt_account_code_2'] ?? '' );

	if ( 'yes' === get_option( 'pnp_layout_identifer1_enabled', 'yes' ) && '' === $data['pt_account_code_1'] ) {
		return new WP_Error( 'pnp_missing_id1', 'Missing first identifier.' );
	}

	if ( 'yes' === get_option( 'pnp_layout_identifer2_enabled', 'no' ) && '' === $data['pt_account_code_2'] ) {
		return new WP_Error( 'pnp_missing_id2', 'Missing second identifier.' );
	}

	return $data;
}

/**
 * Build sanitized payment payload ready for SSv2.
 *
 * @param array $data Validated payment data.
 * @return array
 */
function pnp_prepare_payment_payload( $data ) {
	return array(
		'pt_transaction_amount' => $data['pt_transaction_amount'],
		'pt_account_code_1'     => $data['pt_account_code_1'],
		'pt_account_code_2'     => $data['pt_account_code_2'],
	);
}

/**
 * Client IP for rate limiting (best effort).
 *
 * @return string
 */
function pnp_get_client_ip() {
	if ( ! empty( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) {
		$parts = explode( ',', sanitize_text_field( wp_unslash( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) );
		return trim( $parts[0] );
	}
	if ( ! empty( $_SERVER['REMOTE_ADDR'] ) ) {
		return sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) );
	}
	return '0.0.0.0';
}

/**
 * Check whether the client is rate limited.
 *
 * @return bool
 */
function pnp_is_rate_limited() {
	$key   = PNP_RATE_LIMIT_PREFIX . md5( pnp_get_client_ip() );
	$count = (int) get_transient( $key );
	return $count >= PNP_RATE_LIMIT_MAX;
}

/**
 * Record a failed payment attempt for rate limiting.
 */
function pnp_record_failure() {
	$key   = PNP_RATE_LIMIT_PREFIX . md5( pnp_get_client_ip() );
	$count = (int) get_transient( $key );
	set_transient( $key, $count + 1, PNP_RATE_LIMIT_WINDOW );
}

/**
 * Create a short-lived payment session transient.
 *
 * @param array $payload Payment payload.
 * @return string Session token.
 */
function pnp_create_payment_session( $payload ) {
	$token = wp_generate_password( 32, false, false );
	set_transient( PNP_SESSION_TRANSIENT_PREFIX . $token, $payload, PNP_SESSION_TTL );
	return $token;
}

/**
 * Load and optionally delete a payment session.
 *
 * @param string $token   Session token.
 * @param bool   $delete  Delete transient after read.
 * @return array|false
 */
function pnp_get_payment_session( $token, $delete = false ) {
	$token   = sanitize_text_field( $token );
	$payload = get_transient( PNP_SESSION_TRANSIENT_PREFIX . $token );
	if ( false === $payload || ! is_array( $payload ) ) {
		return false;
	}
	if ( $delete ) {
		delete_transient( PNP_SESSION_TRANSIENT_PREFIX . $token );
	}
	return $payload;
}

/**
 * Whether SSL is required and the current request is insecure.
 *
 * @return bool
 */
function pnp_ssl_required_and_missing() {
	return 'yes' === get_option( 'pnp_require_ssl', 'yes' ) && ! is_ssl();
}

/**
 * Admin-ajax payment URL (primary — works on every WordPress install).
 *
 * Uses wp-admin/admin-ajax.php which WordPress core always routes correctly,
 * including subdirectory installs (/woocommerce/), without rewrite rules.
 *
 * @return string
 */
function pnp_get_payment_ajax_url() {
	return add_query_arg( 'action', PNP_PAYMENT_AJAX_ACTION, admin_url( 'admin-ajax.php' ) );
}

/**
 * Legacy index.php query-var URL (kept for backward compatibility).
 *
 * @return string
 */
function pnp_get_payment_endpoint_fallback_url() {
	return add_query_arg( 'pnp_pay', '1', home_url( '/index.php' ) );
}

/**
 * Pretty payment page URL (optional — for direct GET links when rewrites work).
 *
 * @return string
 */
function pnp_get_payment_pretty_url() {
	return trailingslashit( home_url( '/' . pnp_get_payment_page_slug() ) );
}

/**
 * Payment endpoint URL for form actions and internal POST handoffs.
 *
 * Always returns admin-ajax.php so submissions bypass rewrite rules entirely.
 *
 * @return string
 */
function pnp_get_payment_endpoint_url() {
	return pnp_get_payment_ajax_url();
}

/**
 * Render wrapped message page.
 *
 * @param string $title   Page title.
 * @param string $message Message HTML (already escaped where needed).
 * @return string
 */
function pnp_render_message_page( $title, $message ) {
	$logo = PNP_PLUGIN_URL . 'images/logo.png';
	ob_start();
	?>
	<div class="pnp-billpay-wrap">
		<div class="pnp-billpay-card">
			<div class="pnp-billpay-header">
				<div class="pnp-billpay-logo-bar">
					<img src="<?php echo esc_url( $logo ); ?>" alt="<?php echo esc_attr__( 'PlugnPay', 'plugnpay-billpay-lite' ); ?>" class="pnp-billpay-logo" width="204" height="59" decoding="async" />
				</div>
				<div class="pnp-billpay-title-bar">
					<h2 class="pnp-billpay-title"><?php echo esc_html( $title ); ?></h2>
				</div>
			</div>
			<div class="pnp-billpay-body">
				<div class="pnp-billpay-message"><?php echo $message; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- caller supplies escaped fragments. ?></div>
			</div>
		</div>
	</div>
	<?php
	return ob_get_clean();
}

/**
 * Generic rejection page.
 *
 * @return string
 */
function pnp_reject_form() {
	$contact_email = sanitize_email( get_option( 'pnp_contact_email', '' ) );
	$contact_phone = sanitize_text_field( get_option( 'pnp_contact_phone', '' ) );

	$message  = '<p>' . esc_html__( 'Online payments are temporarily unavailable. Please try again later.', 'plugnpay-billpay-lite' ) . '</p>';
	$message .= '<p>' . esc_html__( 'We apologize for any inconvenience and appreciate your patience.', 'plugnpay-billpay-lite' ) . '</p>';

	if ( $contact_phone || $contact_email ) {
		$message .= '<p>' . esc_html__( 'If you need assistance, please contact us:', 'plugnpay-billpay-lite' ) . ' ';
		if ( $contact_phone ) {
			$message .= '<a href="tel:' . esc_attr( $contact_phone ) . '">' . esc_html( $contact_phone ) . '</a>';
		}
		if ( $contact_phone && $contact_email ) {
			$message .= ' | ';
		}
		if ( $contact_email ) {
			$message .= '<a href="mailto:' . esc_attr( $contact_email ) . '">' . esc_html( $contact_email ) . '</a>';
		}
		$message .= '</p>';
	}

	return pnp_render_message_page( __( 'Temporarily Unavailable', 'plugnpay-billpay-lite' ), $message );
}

/**
 * Missing required information page.
 *
 * @return string
 */
function pnp_missing_form() {
	$contact_email = sanitize_email( get_option( 'pnp_contact_email', '' ) );
	$contact_phone = sanitize_text_field( get_option( 'pnp_contact_phone', '' ) );

	$message  = '<p>' . esc_html__( 'Please try again and ensure all required information has been provided.', 'plugnpay-billpay-lite' ) . '</p>';

	if ( $contact_phone || $contact_email ) {
		$message .= '<p>' . esc_html__( 'If you need assistance, please contact us:', 'plugnpay-billpay-lite' ) . ' ';
		if ( $contact_phone ) {
			$message .= '<a href="tel:' . esc_attr( $contact_phone ) . '">' . esc_html( $contact_phone ) . '</a>';
		}
		if ( $contact_phone && $contact_email ) {
			$message .= ' | ';
		}
		if ( $contact_email ) {
			$message .= '<a href="mailto:' . esc_attr( $contact_email ) . '">' . esc_html( $contact_email ) . '</a>';
		}
		$message .= '</p>';
	}

	return pnp_render_message_page( __( 'Missing Required Information', 'plugnpay-billpay-lite' ), $message );
}
