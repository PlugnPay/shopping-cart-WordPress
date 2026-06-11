<?php
/**
 * Payment endpoint: direct URL entry, captcha step, SSv2 handoff.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'PNP_PAY_SESSION_FIELD', 'pnp_pay_session' );
define( 'PNP_PAY_STEP_FIELD', 'pnp_pay_step' );
define( 'PNP_FLUSH_REWRITE_OPTION', 'pnp_flush_rewrite_rules' );

/**
 * Sanitized payment page slug from settings.
 *
 * @return string
 */
function pnp_get_payment_page_slug() {
	$slug = get_option( 'pnp_payment_page_slug', 'pnp-pay' );
	$slug = sanitize_title( $slug );
	if ( '' === $slug ) {
		$slug = 'pnp-pay';
	}
	return $slug;
}

/**
 * Whether the site uses pretty permalinks (rewrite rules can map /slug/ URLs).
 *
 * @return bool
 */
function pnp_pretty_permalinks_available() {
	global $wp_rewrite;

	if ( $wp_rewrite instanceof WP_Rewrite ) {
		return $wp_rewrite->using_permalinks();
	}

	return '' !== (string) get_option( 'permalink_structure' );
}

/**
 * Register rewrite rules for payment endpoint.
 */
function pnp_register_payment_rewrite_rules() {
	$slug = pnp_get_payment_page_slug();
	add_rewrite_rule( '^' . preg_quote( $slug, '/' ) . '/?$', 'index.php?pnp_pay=1', 'top' );
}
add_action( 'init', 'pnp_register_payment_rewrite_rules' );

/**
 * Register query var.
 *
 * @param array $vars Query vars.
 * @return array
 */
function pnp_register_query_vars( $vars ) {
	$vars[] = 'pnp_pay';
	return $vars;
}
add_filter( 'query_vars', 'pnp_register_query_vars' );

/**
 * Flush rewrite rules on activation/deactivation.
 */
function pnp_activate_plugin() {
	pnp_register_payment_rewrite_rules();
	flush_rewrite_rules( false );
	update_option( PNP_FLUSH_REWRITE_OPTION, '1', false );
}

function pnp_deactivate_plugin() {
	flush_rewrite_rules( false );
	delete_option( PNP_FLUSH_REWRITE_OPTION );
}

/**
 * Deferred flush on init ensures rules persist after activation and subdirectory changes.
 */
function pnp_maybe_flush_rewrite_rules() {
	if ( ! get_option( PNP_FLUSH_REWRITE_OPTION ) ) {
		return;
	}

	pnp_register_payment_rewrite_rules();
	flush_rewrite_rules( false );
	delete_option( PNP_FLUSH_REWRITE_OPTION );
}
add_action( 'init', 'pnp_maybe_flush_rewrite_rules', 20 );

/**
 * Whether the pnp-pay rewrite rule is present in stored rules.
 *
 * @return bool
 */
function pnp_rewrite_rules_need_flush() {
	if ( ! pnp_pretty_permalinks_available() ) {
		return false;
	}

	$rules = get_option( 'rewrite_rules' );
	if ( ! is_array( $rules ) || empty( $rules ) ) {
		return true;
	}

	foreach ( pnp_get_payment_rewrite_rule_patterns() as $pattern ) {
		if ( isset( $rules[ $pattern ] ) ) {
			return false;
		}
	}

	return true;
}

/**
 * Rewrite rule match patterns for the payment slug (plain and subdirectory-prefixed).
 *
 * @return string[]
 */
function pnp_get_payment_rewrite_rule_patterns() {
	$slug      = pnp_get_payment_page_slug();
	$patterns  = array( '^' . preg_quote( $slug, '/' ) . '/?$' );
	$home_path = trim( (string) parse_url( home_url(), PHP_URL_PATH ), '/' );

	if ( '' !== $home_path ) {
		$patterns[] = '^' . preg_quote( $home_path . '/' . $slug, '/' ) . '/?$';
	}

	$site_path = trim( (string) parse_url( site_url(), PHP_URL_PATH ), '/' );
	if ( '' !== $site_path && $site_path !== $home_path ) {
		$patterns[] = '^' . preg_quote( $site_path . '/' . $slug, '/' ) . '/?$';
	}

	return $patterns;
}

/**
 * Request path relative to the WordPress home URL (handles subdirectory installs).
 *
 * Strips both home_url and site_url path prefixes when they differ (common on
 * subdirectory installs where only one includes the install folder).
 *
 * @return string
 */
function pnp_get_request_path_relative_to_home() {
	$request_uri = isset( $_SERVER['REQUEST_URI'] ) ? wp_unslash( $_SERVER['REQUEST_URI'] ) : '';
	$path        = (string) parse_url( $request_uri, PHP_URL_PATH );
	$path        = trim( $path, '/' );

	$base_paths = array();
	foreach ( array( home_url(), site_url() ) as $url ) {
		$base = trim( (string) parse_url( $url, PHP_URL_PATH ), '/' );
		if ( '' !== $base ) {
			$base_paths[] = $base;
		}
	}
	$base_paths = array_unique( $base_paths );

	foreach ( $base_paths as $base_path ) {
		if ( $path === $base_path ) {
			return '';
		}
		$prefix = $base_path . '/';
		if ( 0 === strpos( $path, $prefix ) ) {
			$path = substr( $path, strlen( $prefix ) );
		}
	}

	return trim( $path, '/' );
}

/**
 * Detect payment endpoint requests via query var or path fallback.
 *
 * @return bool
 */
function pnp_is_payment_endpoint_request() {
	if ( isset( $_GET['pnp_pay'] ) && '1' === (string) wp_unslash( $_GET['pnp_pay'] ) ) {
		return true;
	}

	if ( function_exists( 'get_query_var' ) ) {
		$query_var = get_query_var( 'pnp_pay', false );
		if ( false !== $query_var && '1' === (string) $query_var ) {
			return true;
		}
	}

	return pnp_get_payment_page_slug() === pnp_get_request_path_relative_to_home();
}

/**
 * Inject pnp_pay query var during parse_request so pretty URLs do not 404.
 *
 * @param WP $wp WordPress environment instance.
 */
function pnp_parse_payment_endpoint_request( $wp ) {
	if ( ! empty( $wp->query_vars['pnp_pay'] ) ) {
		return;
	}

	if ( pnp_get_payment_page_slug() !== pnp_get_request_path_relative_to_home() ) {
		return;
	}

	$wp->query_vars['pnp_pay'] = '1';
}
add_action( 'parse_request', 'pnp_parse_payment_endpoint_request', 0 );

/**
 * Prevent WordPress from treating payment endpoint requests as 404.
 *
 * @param bool     $preempt Whether to short-circuit 404 handling.
 * @param WP_Query $query   Main query instance.
 * @return bool
 */
function pnp_pre_handle_payment_endpoint_404( $preempt, $query ) {
	if ( pnp_is_payment_endpoint_request() ) {
		return true;
	}

	return $preempt;
}
add_filter( 'pre_handle_404', 'pnp_pre_handle_payment_endpoint_404', 10, 2 );

/**
 * Register admin-ajax handlers (primary endpoint — no rewrite rules required).
 */
function pnp_register_payment_ajax_handlers() {
	add_action( 'wp_ajax_nopriv_' . PNP_PAYMENT_AJAX_ACTION, 'pnp_ajax_payment_handler' );
	add_action( 'wp_ajax_' . PNP_PAYMENT_AJAX_ACTION, 'pnp_ajax_payment_handler' );
}
add_action( 'init', 'pnp_register_payment_ajax_handlers' );

/**
 * Admin-ajax entry point for payment requests (GET and POST).
 */
function pnp_ajax_payment_handler() {
	pnp_process_payment_endpoint();
}

/**
 * Core payment endpoint logic shared by admin-ajax and legacy routes.
 */
function pnp_process_payment_endpoint() {
	pnp_enqueue_frontend_assets();
	pnp_enqueue_hcaptcha_script();

	if ( pnp_ssl_required_and_missing() ) {
		pnp_output_endpoint_response( pnp_reject_form() );
		return;
	}

	if ( pnp_is_rate_limited() ) {
		pnp_output_endpoint_response( pnp_reject_form() );
		return;
	}

	$source = array_merge( wp_unslash( $_GET ), wp_unslash( $_POST ) );
	if ( pnp_has_blocked_fields( $source ) ) {
		pnp_record_failure();
		pnp_output_endpoint_response( pnp_reject_form() );
		return;
	}

	$step = isset( $_POST[ PNP_PAY_STEP_FIELD ] ) ? sanitize_text_field( wp_unslash( $_POST[ PNP_PAY_STEP_FIELD ] ) ) : 'init';

	if ( 'captcha' === $step ) {
		pnp_handle_captcha_submission();
		return;
	}

	pnp_handle_initial_submission();
}

/**
 * Handle payment endpoint requests (legacy: index.php?pnp_pay=1 and pretty slug).
 */
function pnp_handle_payment_endpoint() {
	if ( is_admin() ) {
		return;
	}

	if ( ! pnp_is_payment_endpoint_request() ) {
		return;
	}

	pnp_process_payment_endpoint();
}
add_action( 'init', 'pnp_handle_payment_endpoint', 1 );
add_action( 'template_redirect', 'pnp_handle_payment_endpoint', 0 );

/**
 * Handle initial GET/POST with payment parameters.
 */
function pnp_handle_initial_submission() {
	$normalized = pnp_normalize_payment_input();

	if ( '' === $normalized['pt_transaction_amount'] && '' === $normalized['pt_account_code_1'] && '' === $normalized['pt_account_code_2'] ) {
		pnp_output_endpoint_response( pnp_missing_form() );
		return;
	}

	$validated = pnp_validate_payment_input( $normalized );

	if ( is_wp_error( $validated ) ) {
		pnp_record_failure();
		$code = $validated->get_error_code();
		if ( in_array( $code, array( 'pnp_missing_id1', 'pnp_missing_id2' ), true ) ) {
			pnp_output_endpoint_response( pnp_missing_form() );
			return;
		}
		pnp_output_endpoint_response( pnp_reject_form() );
		return;
	}

	if ( isset( $_SERVER['REQUEST_METHOD'] ) && 'POST' === $_SERVER['REQUEST_METHOD'] ) {
		if ( ! isset( $_POST[ PNP_PAYMENT_NONCE_FIELD ] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST[ PNP_PAYMENT_NONCE_FIELD ] ) ), PNP_PAYMENT_NONCE_ACTION ) ) {
			pnp_record_failure();
			pnp_output_endpoint_response( pnp_reject_form() );
			return;
		}
	}

	$payload = pnp_prepare_payment_payload( $validated );
	$token   = pnp_create_payment_session( $payload );

	pnp_output_endpoint_response( pnp_render_captcha_page( $payload, $token ) );
}

/**
 * Handle captcha form submission.
 */
function pnp_handle_captcha_submission() {
	if ( ! isset( $_POST[ PNP_PAYMENT_NONCE_FIELD ] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST[ PNP_PAYMENT_NONCE_FIELD ] ) ), PNP_PAYMENT_NONCE_ACTION ) ) {
		pnp_record_failure();
		pnp_output_endpoint_response( pnp_reject_form() );
		return;
	}

	$token = isset( $_POST[ PNP_PAY_SESSION_FIELD ] ) ? sanitize_text_field( wp_unslash( $_POST[ PNP_PAY_SESSION_FIELD ] ) ) : '';
	if ( '' === $token ) {
		pnp_record_failure();
		pnp_output_endpoint_response( pnp_reject_form() );
		return;
	}

	$payload = pnp_get_payment_session( $token, false );
	if ( false === $payload ) {
		pnp_record_failure();
		pnp_output_endpoint_response( pnp_reject_form() );
		return;
	}

	$validated = pnp_validate_payment_input( $payload );
	if ( is_wp_error( $validated ) ) {
		pnp_record_failure();
		delete_transient( PNP_SESSION_TRANSIENT_PREFIX . $token );
		pnp_output_endpoint_response( pnp_reject_form() );
		return;
	}

	$hcaptcha_token = isset( $_POST['h-captcha-response'] ) ? sanitize_text_field( wp_unslash( $_POST['h-captcha-response'] ) ) : '';
	if ( ! pnp_validate_hcaptcha( $hcaptcha_token ) ) {
		pnp_record_failure();
		pnp_output_endpoint_response( pnp_reject_form() );
		return;
	}

	delete_transient( PNP_SESSION_TRANSIENT_PREFIX . $token );
	$payload = pnp_prepare_payment_payload( $validated );
	pnp_output_endpoint_response( pnp_build_ssv2_redirect( $payload ) );
}

/**
 * Render captcha confirmation page.
 *
 * @param array  $payload Payment payload.
 * @param string $token   Session token.
 * @return string
 */
function pnp_render_captcha_page( $payload, $token ) {
	$amount_label = get_option( 'pnp_layout_amount_title', 'Amount' );
	$id1_label    = get_option( 'pnp_layout_identifer1_title', 'Invoice Number' );
	$id2_label    = get_option( 'pnp_layout_identifer2_title', 'Customer ID' );
	$logo         = PNP_PLUGIN_URL . 'images/logo.png';

	ob_start();
	?>
	<div class="pnp-billpay-wrap">
		<div class="pnp-billpay-card">
			<div class="pnp-billpay-header">
				<div class="pnp-billpay-logo-bar">
					<img src="<?php echo esc_url( $logo ); ?>" alt="<?php echo esc_attr__( 'PlugnPay', 'plugnpay-billpay-lite' ); ?>" class="pnp-billpay-logo" width="204" height="59" decoding="async" />
				</div>
				<div class="pnp-billpay-title-bar">
					<h2 class="pnp-billpay-title"><?php echo esc_html__( 'Verify Payment Details', 'plugnpay-billpay-lite' ); ?></h2>
				</div>
			</div>
			<div class="pnp-billpay-summary">
				<dl>
					<dt><?php echo esc_html( $amount_label ); ?></dt>
					<dd><?php echo esc_html( $payload['pt_transaction_amount'] ); ?></dd>
					<?php if ( '' !== $payload['pt_account_code_1'] ) : ?>
						<dt><?php echo esc_html( $id1_label ); ?></dt>
						<dd><?php echo esc_html( $payload['pt_account_code_1'] ); ?></dd>
					<?php endif; ?>
					<?php if ( '' !== $payload['pt_account_code_2'] ) : ?>
						<dt><?php echo esc_html( $id2_label ); ?></dt>
						<dd><?php echo esc_html( $payload['pt_account_code_2'] ); ?></dd>
					<?php endif; ?>
				</dl>
			</div>
			<form class="pnp-billpay-form" method="post" action="<?php echo esc_url( pnp_get_payment_endpoint_url() ); ?>">
				<?php wp_nonce_field( PNP_PAYMENT_NONCE_ACTION, PNP_PAYMENT_NONCE_FIELD ); ?>
				<input type="hidden" name="<?php echo esc_attr( PNP_PAY_STEP_FIELD ); ?>" value="captcha" />
				<input type="hidden" name="<?php echo esc_attr( PNP_PAY_SESSION_FIELD ); ?>" value="<?php echo esc_attr( $token ); ?>" />
				<div class="pnp-field pnp-captcha-field">
					<?php echo pnp_hcaptcha(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				</div>
				<div class="pnp-field pnp-submit-field">
					<button type="submit" class="pnp-btn-primary"><?php echo esc_html__( 'Proceed to Secure Payment', 'plugnpay-billpay-lite' ); ?></button>
				</div>
			</form>
			<?php echo pnp_render_card_brands(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
		</div>
	</div>
	<?php
	return ob_get_clean();
}

/**
 * Output endpoint HTML within theme context.
 *
 * @param string $content Page content.
 */
function pnp_output_endpoint_response( $content ) {
	status_header( 200 );
	nocache_headers();
	?>
	<!DOCTYPE html>
	<html <?php language_attributes(); ?>>
	<head>
		<meta charset="<?php bloginfo( 'charset' ); ?>" />
		<meta name="viewport" content="width=device-width, initial-scale=1" />
		<title><?php echo esc_html__( 'PlugnPay BillPay Lite', 'plugnpay-billpay-lite' ); ?></title>
		<?php wp_head(); ?>
	</head>
	<body <?php body_class( 'pnp-billpay-endpoint' ); ?>>
		<?php echo $content; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
		<?php wp_footer(); ?>
	</body>
	</html>
	<?php
	exit;
}

/**
 * Admin notice when rewrite rules for the payment endpoint are missing.
 */
function pnp_admin_permalink_notice() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}

	if ( ! pnp_rewrite_rules_need_flush() ) {
		return;
	}

	$permalink_url = admin_url( 'options-permalink.php' );
	$ajax_url      = pnp_get_payment_ajax_url();
	?>
	<div class="notice notice-warning">
		<p>
			<strong><?php echo esc_html__( 'PlugnPay BillPay Lite:', 'plugnpay-billpay-lite' ); ?></strong>
			<?php echo esc_html__( 'Direct GET links using the pretty slug may 404 until permalinks are refreshed. Form submissions use admin-ajax.php and are unaffected.', 'plugnpay-billpay-lite' ); ?>
			<a href="<?php echo esc_url( $permalink_url ); ?>"><?php echo esc_html__( 'Open Permalink Settings', 'plugnpay-billpay-lite' ); ?></a>
		</p>
		<p>
			<?php echo esc_html__( 'Form POST URL (always works):', 'plugnpay-billpay-lite' ); ?>
			<code><?php echo esc_html( $ajax_url ); ?></code>
		</p>
	</div>
	<?php
}
add_action( 'admin_notices', 'pnp_admin_permalink_notice' );
