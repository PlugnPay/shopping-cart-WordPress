<?php
/**
 * hCaptcha widget and server-side verification.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Render hCaptcha widget markup.
 *
 * @return string
 */
function pnp_hcaptcha() {
	$site_key = get_option( 'pnp_hcaptcha_site_key', '' );
	return '<div class="h-captcha" data-sitekey="' . esc_attr( $site_key ) . '"></div>';
}

/**
 * Validate hCaptcha response token.
 *
 * @param string $token hCaptcha response token.
 * @return bool
 */
function pnp_validate_hcaptcha( $token ) {
	$token = sanitize_text_field( $token );
	if ( '' === $token ) {
		return false;
	}

	$secret_key = get_option( 'pnp_hcaptcha_secret_key', '' );
	if ( '' === $secret_key ) {
		return false;
	}

	$remote_ip = '';
	if ( ! empty( $_SERVER['REMOTE_ADDR'] ) ) {
		$remote_ip = sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) );
	}

	$response = wp_remote_post(
		'https://hcaptcha.com/siteverify',
		array(
			'timeout' => 15,
			'body'    => array(
				'secret'   => $secret_key,
				'response' => $token,
				'remoteip' => $remote_ip,
			),
		)
	);

	if ( is_wp_error( $response ) ) {
		return false;
	}

	$body = wp_remote_retrieve_body( $response );
	$data = json_decode( $body );

	return isset( $data->success ) && true === $data->success;
}

/**
 * Enqueue hCaptcha script on demand.
 */
function pnp_enqueue_hcaptcha_script() {
	wp_enqueue_script( 'pnp-hcaptcha', 'https://js.hcaptcha.com/1/api.js', array(), null, true );
}
