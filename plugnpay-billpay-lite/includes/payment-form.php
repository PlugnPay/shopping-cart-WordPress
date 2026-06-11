<?php
/**
 * Shortcode payment form (fields only — captcha handled on endpoint).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Register payment form shortcode.
 */
function pnp_register_payment_form_shortcode() {
	add_shortcode( 'pnp_payment_form', 'pnp_render_payment_form_shortcode' );
}
add_action( 'init', 'pnp_register_payment_form_shortcode' );

/**
 * Render payment form shortcode output.
 *
 * @return string
 */
function pnp_render_payment_form_shortcode() {
	pnp_enqueue_frontend_assets();

	$amount_title = get_option( 'pnp_layout_amount_title', 'Amount' );
	$endpoint     = pnp_get_payment_endpoint_url();
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
					<h2 class="pnp-billpay-title"><?php echo esc_html__( 'Make a Payment', 'plugnpay-billpay-lite' ); ?></h2>
				</div>
			</div>
			<form id="pnp-payment-form" class="pnp-billpay-form" method="post" action="<?php echo esc_url( $endpoint ); ?>">
				<?php wp_nonce_field( PNP_PAYMENT_NONCE_ACTION, PNP_PAYMENT_NONCE_FIELD ); ?>
				<div class="pnp-field">
					<label for="pt_transaction_amount"><?php echo esc_html( $amount_title ); ?></label>
					<input type="text" name="pt_transaction_amount" id="pt_transaction_amount" inputmode="decimal" autocomplete="off" required />
				</div>
				<?php if ( 'yes' === get_option( 'pnp_layout_identifer1_enabled', 'yes' ) ) : ?>
					<div class="pnp-field">
						<label for="pt_account_code_1"><?php echo esc_html( get_option( 'pnp_layout_identifer1_title', 'Invoice Number' ) ); ?></label>
						<input type="text" name="pt_account_code_1" id="pt_account_code_1" maxlength="64" autocomplete="off" required />
					</div>
				<?php endif; ?>
				<?php if ( 'yes' === get_option( 'pnp_layout_identifer2_enabled', 'no' ) ) : ?>
					<div class="pnp-field">
						<label for="pt_account_code_2"><?php echo esc_html( get_option( 'pnp_layout_identifer2_title', 'Customer ID' ) ); ?></label>
						<input type="text" name="pt_account_code_2" id="pt_account_code_2" maxlength="64" autocomplete="off" required />
					</div>
				<?php endif; ?>
				<div class="pnp-field pnp-submit-field">
					<button type="submit" class="pnp-btn-primary"><?php echo esc_html__( 'Continue', 'plugnpay-billpay-lite' ); ?></button>
				</div>
			</form>
			<?php echo pnp_render_card_brands(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
		</div>
	</div>
	<?php
	return ob_get_clean();
}

/**
 * Render accepted card brand icons and text labels for types without artwork.
 *
 * @return string
 */
function pnp_render_card_brands() {
	$entries = pnp_get_selected_card_type_entries();
	if ( empty( $entries ) ) {
		return '';
	}

	ob_start();
	?>
	<div class="pnp-card-brands" aria-hidden="true">
		<?php foreach ( $entries as $entry ) : ?>
			<?php if ( ! empty( $entry['image'] ) ) : ?>
				<img src="<?php echo esc_url( PNP_PLUGIN_URL . 'images/' . $entry['image'] . '.png' ); ?>" alt="" />
			<?php else : ?>
				<span class="pnp-card-brand-label"><?php echo esc_html( $entry['name'] ); ?></span>
			<?php endif; ?>
		<?php endforeach; ?>
	</div>
	<?php
	return ob_get_clean();
}

/**
 * Enqueue frontend assets when shortcode is present.
 */
function pnp_enqueue_frontend_assets() {
	wp_enqueue_style( 'pnp-billpay-frontend', PNP_PLUGIN_URL . 'assets/css/frontend.css', array(), PNP_PLUGIN_VERSION );
	wp_enqueue_script( 'pnp-billpay-frontend', PNP_PLUGIN_URL . 'assets/js/frontend.js', array( 'jquery' ), PNP_PLUGIN_VERSION, true );
}
