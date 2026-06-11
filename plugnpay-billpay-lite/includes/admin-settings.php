<?php
/**
 * Admin settings page and option sanitization.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function pnp_sanitize_yes_no( $value ) {
	return 'yes' === $value ? 'yes' : 'no';
}

function pnp_sanitize_layout_type( $value ) {
	return 'simple' === $value ? 'simple' : 'simple';
}

function pnp_sanitize_amount_option( $value ) {
	$sanitized = pnp_sanitize_amount( $value );
	return false !== $sanitized ? $sanitized : '0.01';
}

function pnp_sanitize_text_label( $value ) {
	return sanitize_text_field( substr( (string) $value, 0, 100 ) );
}

function pnp_sanitize_gateway_account( $value ) {
	$value = strtolower( sanitize_text_field( (string) $value ) );
	return preg_replace( '/[^a-z0-9]/', '', $value );
}

function pnp_sanitize_currency( $value ) {
	$value = strtoupper( sanitize_text_field( (string) $value ) );
	if ( ! preg_match( '/^[A-Z]{3}$/', $value ) ) {
		return 'USD';
	}
	return $value;
}

function pnp_sanitize_hash_fields( $value ) {
	$allowed = array(
		'acct_code,card-amount,publisher-name',
		'card-amount,publisher-name',
		'publisher-name',
	);
	return in_array( $value, $allowed, true ) ? $value : 'acct_code,card-amount,publisher-name';
}

function pnp_sanitize_response_type( $value ) {
	if ( 'callback' !== $value ) {
		return 'receipt';
	}

	$url = '';
	if ( isset( $_POST['pnp_pb_success_url'] ) ) {
		$url = esc_url_raw( wp_unslash( $_POST['pnp_pb_success_url'] ) );
	}
	if ( ! $url ) {
		$url = get_option( 'pnp_pb_success_url', '' );
	}

	if ( $url ) {
		return 'callback';
	}

	add_settings_error(
		'pnp_settings_group',
		'pnp_missing_success_url',
		__( 'Callback URL is required when Response Type is Callback.', 'plugnpay-billpay-lite' ),
		'error'
	);

	return get_option( 'pnp_response_type', 'receipt' );
}

function pnp_sanitize_transition_type( $value ) {
	$allowed = array( 'hidden', 'post', 'get' );
	return in_array( $value, $allowed, true ) ? $value : 'hidden';
}

function pnp_sanitize_url_option( $value ) {
	$url = esc_url_raw( (string) $value );
	return $url ? $url : '';
}

/**
 * Sanitize callback success URL; required when response type is callback.
 *
 * @param string $value Submitted URL.
 * @return string
 */
function pnp_sanitize_success_url_option( $value ) {
	$url = esc_url_raw( (string) $value );
	if ( $url ) {
		return $url;
	}

	$response_type = isset( $_POST['pnp_response_type'] )
		? pnp_sanitize_response_type( wp_unslash( $_POST['pnp_response_type'] ) )
		: get_option( 'pnp_response_type', 'receipt' );

	if ( 'callback' !== $response_type ) {
		return '';
	}

	$existing = get_option( 'pnp_pb_success_url', '' );
	if ( $existing ) {
		return $existing;
	}

	add_settings_error(
		'pnp_settings_group',
		'pnp_missing_success_url',
		__( 'Callback URL is required when Response Type is Callback.', 'plugnpay-billpay-lite' ),
		'error'
	);

	return '';
}

/**
 * Ensure min amount does not exceed max amount after settings save.
 *
 * @param string $option    Option name.
 * @param mixed  $old_value Previous value.
 * @param mixed  $value     New value.
 */
function pnp_normalize_layout_amount_bounds( $option, $old_value, $value ) {
	unset( $old_value, $value );

	if ( ! in_array( $option, array( 'pnp_layout_amount_min', 'pnp_layout_amount_max' ), true ) ) {
		return;
	}

	static $normalizing = false;
	if ( $normalizing ) {
		return;
	}

	$min = get_option( 'pnp_layout_amount_min', '0.01' );
	$max = get_option( 'pnp_layout_amount_max', '99999.99' );

	if ( (float) $min <= (float) $max ) {
		return;
	}

	$normalizing = true;
	update_option( 'pnp_layout_amount_min', $max, false );
	update_option( 'pnp_layout_amount_max', $min, false );
	$normalizing = false;

	add_settings_error(
		'pnp_settings_group',
		'pnp_amount_range_swapped',
		__( 'Min and max amounts were swapped because min was greater than max.', 'plugnpay-billpay-lite' ),
		'updated'
	);
}
add_action( 'updated_option', 'pnp_normalize_layout_amount_bounds', 10, 3 );

function pnp_sanitize_email_option( $value ) {
	return sanitize_email( (string) $value );
}

function pnp_sanitize_phone_option( $value ) {
	return sanitize_text_field( substr( (string) $value, 0, 25 ) );
}

function pnp_sanitize_secret_option( $value ) {
	return sanitize_text_field( substr( (string) $value, 0, 200 ) );
}

/**
 * Preserve stored hash key when the password field is left blank on save.
 *
 * @param string $value Submitted value.
 * @return string
 */
function pnp_sanitize_transaction_hash_key( $value ) {
	$value = pnp_sanitize_secret_option( $value );
	if ( '' === $value ) {
		return get_option( 'pnp_pt_transaction_hash_key', '' );
	}
	return $value;
}

/**
 * Preserve stored hCaptcha secret when the password field is left blank on save.
 *
 * @param string $value Submitted value.
 * @return string
 */
function pnp_sanitize_hcaptcha_secret_key( $value ) {
	$value = pnp_sanitize_secret_option( $value );
	if ( '' === $value ) {
		return get_option( 'pnp_hcaptcha_secret_key', '' );
	}
	return $value;
}

function pnp_sanitize_payment_slug( $value ) {
	$slug = sanitize_title( (string) $value );
	if ( '' === $slug ) {
		return 'pnp-pay';
	}
	$reserved = array( 'wp-admin', 'wp-content', 'wp-includes', 'admin', 'login' );
	if ( in_array( $slug, $reserved, true ) ) {
		return 'pnp-pay';
	}
	return $slug;
}

function pnp_add_settings_page() {
	add_menu_page(
		__( 'PlugnPay BillPay Lite Settings', 'plugnpay-billpay-lite' ),
		__( 'PlugnPay BillPay Lite', 'plugnpay-billpay-lite' ),
		'manage_options',
		'pnp-settings',
		'pnp_settings_page',
		'dashicons-cart',
		80
	);
}
add_action( 'admin_menu', 'pnp_add_settings_page' );

function pnp_register_settings() {
	$settings = array(
		'pnp_layout_type'                 => 'pnp_sanitize_layout_type',
		'pnp_layout_amount_min'           => 'pnp_sanitize_amount_option',
		'pnp_layout_amount_max'           => 'pnp_sanitize_amount_option',
		'pnp_layout_amount_title'         => 'pnp_sanitize_text_label',
		'pnp_layout_identifer1_enabled'   => 'pnp_sanitize_yes_no',
		'pnp_layout_identifer1_title'     => 'pnp_sanitize_text_label',
		'pnp_layout_identifer2_enabled'   => 'pnp_sanitize_yes_no',
		'pnp_layout_identifer2_title'     => 'pnp_sanitize_text_label',
		'pnp_pt_gateway_account'          => 'pnp_sanitize_gateway_account',
		'pnp_pb_cards_allowed'            => 'pnp_sanitize_cards_allowed',
		'pnp_pb_post_auth'                => 'pnp_sanitize_yes_no',
		'pnp_pt_currency'                 => 'pnp_sanitize_currency',
		'pnp_pt_order_classifier'         => 'pnp_sanitize_text_label',
		'pnp_pt_transaction_hash_key'     => 'pnp_sanitize_transaction_hash_key',
		'pnp_pt_transaction_hash_fields'    => 'pnp_sanitize_hash_fields',
		'pnp_hcaptcha_secret_key'         => 'pnp_sanitize_hcaptcha_secret_key',
		'pnp_hcaptcha_site_key'           => 'pnp_sanitize_secret_option',
		'pnp_contact_email'               => 'pnp_sanitize_email_option',
		'pnp_contact_phone'               => 'pnp_sanitize_phone_option',
		'pnp_response_type'               => 'pnp_sanitize_response_type',
		'pnp_pb_success_url'              => 'pnp_sanitize_success_url_option',
		'pnp_pb_transition_type'          => 'pnp_sanitize_transition_type',
		'pnp_pb_receipt_type'             => 'pnp_sanitize_text_label',
		'pnp_pb_receipt_company'          => 'pnp_sanitize_text_label',
		'pnp_pb_receipt_name'             => 'pnp_sanitize_text_label',
		'pnp_pb_receipt_address_1'        => 'pnp_sanitize_text_label',
		'pnp_pb_receipt_address_2'        => 'pnp_sanitize_text_label',
		'pnp_pb_receipt_city'             => 'pnp_sanitize_text_label',
		'pnp_pb_receipt_state'            => 'pnp_sanitize_text_label',
		'pnp_pb_receipt_province'         => 'pnp_sanitize_text_label',
		'pnp_pb_receipt_postal_code'      => 'pnp_sanitize_text_label',
		'pnp_pb_receipt_country'          => 'pnp_sanitize_text_label',
		'pnp_pb_receipt_email_address'    => 'pnp_sanitize_email_option',
		'pnp_pb_receipt_phone'            => 'pnp_sanitize_phone_option',
		'pnp_pb_receipt_fax'              => 'pnp_sanitize_phone_option',
		'pnp_pb_receipt_company_url'      => 'pnp_sanitize_url_option',
		'pnp_pb_receipt_transaction_url'  => 'pnp_sanitize_url_option',
		'pnp_payment_page_slug'           => 'pnp_sanitize_payment_slug',
		'pnp_require_ssl'                 => 'pnp_sanitize_yes_no',
	);

	foreach ( $settings as $option_name => $callback ) {
		register_setting(
			'pnp_settings_group',
			$option_name,
			array(
				'type'              => 'string',
				'sanitize_callback' => $callback,
			)
		);
	}
}
add_action( 'admin_init', 'pnp_register_settings' );

/**
 * Refresh rewrite rules when payment slug changes.
 *
 * @param mixed  $old_value Old option value.
 * @param mixed  $value     New option value.
 * @param string $option    Option name.
 */
function pnp_maybe_flush_rewrite_on_slug_change( $old_value, $value, $option ) {
	if ( 'pnp_payment_page_slug' !== $option || $old_value === $value ) {
		return;
	}
	pnp_register_payment_rewrite_rules();
	flush_rewrite_rules( false );
	update_option( PNP_FLUSH_REWRITE_OPTION, '1', false );
}
add_action( 'update_option_pnp_payment_page_slug', 'pnp_maybe_flush_rewrite_on_slug_change', 10, 3 );

function pnp_settings_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}

	$endpoint       = pnp_get_payment_endpoint_url();
	$ajax_url       = pnp_get_payment_ajax_url();
	$pretty_url     = pnp_get_payment_pretty_url();
	$legacy_url     = pnp_get_payment_endpoint_fallback_url();
	$needs_flush    = pnp_rewrite_rules_need_flush();
	$short_url = add_query_arg(
		array(
			'amt' => '25.00',
			'id1' => 'INV-123',
			'id2' => 'CUST-456',
		),
		$ajax_url
	);
	?>
	<div class="wrap pnp-admin-wrap">
		<h1><?php echo esc_html__( 'PlugnPay BillPay Lite Settings', 'plugnpay-billpay-lite' ); ?></h1>

		<?php if ( $needs_flush ) : ?>
			<div class="notice notice-warning inline">
				<p>
					<strong><?php echo esc_html__( 'Permalinks need to be refreshed.', 'plugnpay-billpay-lite' ); ?></strong>
					<?php echo esc_html__( 'Visit Settings → Permalinks and click Save Changes so the pretty payment URL works. Forms use admin-ajax.php and do not depend on permalinks.', 'plugnpay-billpay-lite' ); ?>
					<a href="<?php echo esc_url( admin_url( 'options-permalink.php' ) ); ?>"><?php echo esc_html__( 'Open Permalink Settings', 'plugnpay-billpay-lite' ); ?></a>
				</p>
			</div>
		<?php endif; ?>

		<div class="pnp-admin-notice">
			<p><strong><?php echo esc_html__( 'Shortcode:', 'plugnpay-billpay-lite' ); ?></strong> <code>[pnp_payment_form]</code></p>
			<p><strong><?php echo esc_html__( 'Form POST URL (always used):', 'plugnpay-billpay-lite' ); ?></strong> <code><?php echo esc_html( $endpoint ); ?></code></p>
			<p><strong><?php echo esc_html__( 'Admin-ajax URL:', 'plugnpay-billpay-lite' ); ?></strong> <code><?php echo esc_html( $ajax_url ); ?></code></p>
			<p><strong><?php echo esc_html__( 'Pretty URL (direct GET links):', 'plugnpay-billpay-lite' ); ?></strong> <code><?php echo esc_html( $pretty_url ); ?></code></p>
			<p><strong><?php echo esc_html__( 'Legacy URL (index.php):', 'plugnpay-billpay-lite' ); ?></strong> <code><?php echo esc_html( $legacy_url ); ?></code></p>
			<p><?php echo esc_html__( 'Direct URL short params:', 'plugnpay-billpay-lite' ); ?> <code>amt</code>, <code>id1</code>, <code>id2</code></p>
			<p><?php echo esc_html__( 'Direct URL full params:', 'plugnpay-billpay-lite' ); ?> <code>pt_transaction_amount</code>, <code>pt_account_code_1</code>, <code>pt_account_code_2</code></p>
			<p><strong><?php echo esc_html__( 'Example:', 'plugnpay-billpay-lite' ); ?></strong> <code><?php echo esc_html( $short_url ); ?></code></p>
			<p><?php echo esc_html__( 'Required fields are marked with *', 'plugnpay-billpay-lite' ); ?></p>
		</div>

		<form method="post" action="options.php">
			<?php settings_fields( 'pnp_settings_group' ); ?>

			<details open>
				<summary><strong><?php echo esc_html__( 'Page Layout', 'plugnpay-billpay-lite' ); ?></strong></summary>
				<table class="form-table pnp-form-subtable">
					<tr>
						<th scope="row"><?php echo esc_html__( 'Amount *', 'plugnpay-billpay-lite' ); ?></th>
						<td>
							<?php echo esc_html__( 'Allow amounts between', 'plugnpay-billpay-lite' ); ?>
							<label><?php echo esc_html__( 'Min', 'plugnpay-billpay-lite' ); ?> <input type="number" name="pnp_layout_amount_min" min="0.01" max="99999.99" step="0.01" value="<?php echo esc_attr( get_option( 'pnp_layout_amount_min', '0.01' ) ); ?>" required /></label>
							<label><?php echo esc_html__( 'Max', 'plugnpay-billpay-lite' ); ?> <input type="number" name="pnp_layout_amount_max" min="0.02" max="99999.99" step="0.01" value="<?php echo esc_attr( get_option( 'pnp_layout_amount_max', '99999.99' ) ); ?>" required /></label>
							<br />
							<label><?php echo esc_html__( 'Title', 'plugnpay-billpay-lite' ); ?> <input type="text" name="pnp_layout_amount_title" value="<?php echo esc_attr( get_option( 'pnp_layout_amount_title', 'Amount' ) ); ?>" size="30" required /></label>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php echo esc_html__( '1st Payment Identifier', 'plugnpay-billpay-lite' ); ?></th>
						<td>
							<select name="pnp_layout_identifer1_enabled">
								<option value="yes" <?php selected( get_option( 'pnp_layout_identifer1_enabled', 'yes' ), 'yes' ); ?>><?php echo esc_html__( 'Yes', 'plugnpay-billpay-lite' ); ?></option>
								<option value="no" <?php selected( get_option( 'pnp_layout_identifer1_enabled', 'yes' ), 'no' ); ?>><?php echo esc_html__( 'No', 'plugnpay-billpay-lite' ); ?></option>
							</select>
							<label><?php echo esc_html__( 'Title', 'plugnpay-billpay-lite' ); ?> <input type="text" name="pnp_layout_identifer1_title" value="<?php echo esc_attr( get_option( 'pnp_layout_identifer1_title', 'Invoice Number' ) ); ?>" size="30" /></label>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php echo esc_html__( '2nd Payment Identifier', 'plugnpay-billpay-lite' ); ?></th>
						<td>
							<select name="pnp_layout_identifer2_enabled">
								<option value="yes" <?php selected( get_option( 'pnp_layout_identifer2_enabled', 'no' ), 'yes' ); ?>><?php echo esc_html__( 'Yes', 'plugnpay-billpay-lite' ); ?></option>
								<option value="no" <?php selected( get_option( 'pnp_layout_identifer2_enabled', 'no' ), 'no' ); ?>><?php echo esc_html__( 'No', 'plugnpay-billpay-lite' ); ?></option>
							</select>
							<label><?php echo esc_html__( 'Title', 'plugnpay-billpay-lite' ); ?> <input type="text" name="pnp_layout_identifer2_title" value="<?php echo esc_attr( get_option( 'pnp_layout_identifer2_title', 'Customer ID' ) ); ?>" size="30" /></label>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php echo esc_html__( 'Payment Page Slug (optional for forms)', 'plugnpay-billpay-lite' ); ?></th>
						<td>
							<input type="text" name="pnp_payment_page_slug" value="<?php echo esc_attr( get_option( 'pnp_payment_page_slug', 'pnp-pay' ) ); ?>" size="20" />
							<p class="description"><?php echo esc_html__( 'Optional URL path for direct GET payment links (relative to your WordPress home URL, including any subdirectory). Leave blank to use the default slug. Form submissions and the [pnp_payment_form] shortcode always POST to admin-ajax.php and do not depend on this slug or rewrite rules.', 'plugnpay-billpay-lite' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php echo esc_html__( 'Require HTTPS *', 'plugnpay-billpay-lite' ); ?></th>
						<td>
							<select name="pnp_require_ssl">
								<option value="yes" <?php selected( get_option( 'pnp_require_ssl', 'yes' ), 'yes' ); ?>><?php echo esc_html__( 'Yes', 'plugnpay-billpay-lite' ); ?></option>
								<option value="no" <?php selected( get_option( 'pnp_require_ssl', 'yes' ), 'no' ); ?>><?php echo esc_html__( 'No', 'plugnpay-billpay-lite' ); ?></option>
							</select>
						</td>
					</tr>
				</table>
			</details>

			<details>
				<summary><strong><?php echo esc_html__( 'Gateway Settings', 'plugnpay-billpay-lite' ); ?></strong></summary>
				<table class="form-table pnp-form-subtable">
					<tr>
						<th scope="row"><?php echo esc_html__( 'Gateway Account *', 'plugnpay-billpay-lite' ); ?></th>
						<td><input type="text" name="pnp_pt_gateway_account" value="<?php echo esc_attr( get_option( 'pnp_pt_gateway_account', '' ) ); ?>" size="20" required /><p class="description"><?php echo esc_html__( 'PlugnPay gateway username.', 'plugnpay-billpay-lite' ); ?></p></td>
					</tr>
					<tr>
						<th scope="row"><?php echo esc_html__( 'Card Types Allowed *', 'plugnpay-billpay-lite' ); ?></th>
						<td>
							<?php
							$cards_allowed_value = get_option( 'pnp_pb_cards_allowed', 'Visa,Mastercard' );
							$cards_selected      = pnp_parse_cards_allowed( $cards_allowed_value );
							?>
							<input type="hidden" name="pnp_pb_cards_allowed" id="pnp_pb_cards_allowed" value="<?php echo esc_attr( $cards_allowed_value ); ?>" />
							<div class="pnp-card-type-grid">
								<?php foreach ( pnp_get_card_type_catalog() as $entry ) : ?>
									<label class="pnp-card-type-option">
										<input type="checkbox" class="pnp-card-type-checkbox" value="<?php echo esc_attr( $entry['name'] ); ?>" <?php checked( in_array( $entry['name'], $cards_selected, true ) ); ?> />
										<span><?php echo esc_html( $entry['name'] ); ?></span>
									</label>
								<?php endforeach; ?>
							</div>
							<p class="description">
								<?php echo esc_html__( 'Select the card types your PlugnPay account accepts. Values are sent to Smart Screens v2 exactly as shown. Types without icons display a text label on the payment form.', 'plugnpay-billpay-lite' ); ?>
							</p>
							<script>
							(function () {
								function syncCardTypesAllowed() {
									var values = [];
									document.querySelectorAll('.pnp-card-type-checkbox:checked').forEach(function (cb) {
										values.push(cb.value);
									});
									document.getElementById('pnp_pb_cards_allowed').value = values.join(',');
								}
								document.querySelectorAll('.pnp-card-type-checkbox').forEach(function (cb) {
									cb.addEventListener('change', syncCardTypesAllowed);
								});
								syncCardTypesAllowed();
							})();
							</script>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php echo esc_html__( 'Transaction Settlement', 'plugnpay-billpay-lite' ); ?></th>
						<td>
							<select name="pnp_pb_post_auth">
								<option value="yes" <?php selected( get_option( 'pnp_pb_post_auth', 'no' ), 'yes' ); ?>><?php echo esc_html__( 'Yes', 'plugnpay-billpay-lite' ); ?></option>
								<option value="no" <?php selected( get_option( 'pnp_pb_post_auth', 'no' ), 'no' ); ?>><?php echo esc_html__( 'No', 'plugnpay-billpay-lite' ); ?></option>
							</select>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php echo esc_html__( 'Currency *', 'plugnpay-billpay-lite' ); ?></th>
						<td><input type="text" name="pnp_pt_currency" value="<?php echo esc_attr( get_option( 'pnp_pt_currency', 'USD' ) ); ?>" size="3" maxlength="3" required /></td>
					</tr>
					<tr>
						<th scope="row"><?php echo esc_html__( 'Order Classifier', 'plugnpay-billpay-lite' ); ?></th>
						<td><input type="text" name="pnp_pt_order_classifier" value="<?php echo esc_attr( get_option( 'pnp_pt_order_classifier', 'Online Payment' ) ); ?>" size="30" /></td>
					</tr>
					<tr>
						<th scope="row"><?php echo esc_html__( 'Authorization Hash Key *', 'plugnpay-billpay-lite' ); ?></th>
						<td><input type="password" name="pnp_pt_transaction_hash_key" value="<?php echo esc_attr( get_option( 'pnp_pt_transaction_hash_key', '' ) ); ?>" size="30" autocomplete="new-password" required /></td>
					</tr>
					<tr>
						<th scope="row"><?php echo esc_html__( 'Authorization Hash Fields *', 'plugnpay-billpay-lite' ); ?></th>
						<td>
							<select name="pnp_pt_transaction_hash_fields" required>
								<?php
								$current_hash = get_option( 'pnp_pt_transaction_hash_fields', 'acct_code,card-amount,publisher-name' );
								$hash_options = array(
									'acct_code,card-amount,publisher-name',
									'card-amount,publisher-name',
									'publisher-name',
								);
								foreach ( $hash_options as $option ) :
									?>
									<option value="<?php echo esc_attr( $option ); ?>" <?php selected( $current_hash, $option ); ?>><?php echo esc_html( $option ); ?></option>
								<?php endforeach; ?>
							</select>
						</td>
					</tr>
				</table>
			</details>

			<details>
				<summary><strong><?php echo esc_html__( 'hCaptcha Settings', 'plugnpay-billpay-lite' ); ?></strong></summary>
				<table class="form-table pnp-form-subtable">
					<tr>
						<th scope="row"><?php echo esc_html__( 'Secret Key *', 'plugnpay-billpay-lite' ); ?></th>
						<td><input type="password" name="pnp_hcaptcha_secret_key" value="<?php echo esc_attr( get_option( 'pnp_hcaptcha_secret_key', '' ) ); ?>" size="40" autocomplete="new-password" required /></td>
					</tr>
					<tr>
						<th scope="row"><?php echo esc_html__( 'Site Key *', 'plugnpay-billpay-lite' ); ?></th>
						<td><input type="text" name="pnp_hcaptcha_site_key" value="<?php echo esc_attr( get_option( 'pnp_hcaptcha_site_key', '' ) ); ?>" size="40" required /></td>
					</tr>
				</table>
			</details>

			<details>
				<summary><strong><?php echo esc_html__( 'Rejection Message Contacts', 'plugnpay-billpay-lite' ); ?></strong></summary>
				<table class="form-table pnp-form-subtable">
					<tr>
						<th scope="row"><?php echo esc_html__( 'Contact Email *', 'plugnpay-billpay-lite' ); ?></th>
						<td><input type="email" name="pnp_contact_email" value="<?php echo esc_attr( get_option( 'pnp_contact_email', '' ) ); ?>" size="35" required /></td>
					</tr>
					<tr>
						<th scope="row"><?php echo esc_html__( 'Contact Phone *', 'plugnpay-billpay-lite' ); ?></th>
						<td><input type="tel" name="pnp_contact_phone" value="<?php echo esc_attr( get_option( 'pnp_contact_phone', '' ) ); ?>" size="20" required /></td>
					</tr>
				</table>
			</details>

			<details>
				<summary><strong><?php echo esc_html__( 'Success Response Settings', 'plugnpay-billpay-lite' ); ?></strong></summary>
				<table class="form-table pnp-form-subtable">
					<tr>
						<th scope="row"><?php echo esc_html__( 'Response Type *', 'plugnpay-billpay-lite' ); ?></th>
						<td>
							<select name="pnp_response_type" required>
								<option value="callback" <?php selected( get_option( 'pnp_response_type', 'receipt' ), 'callback' ); ?>><?php echo esc_html__( 'Callback', 'plugnpay-billpay-lite' ); ?></option>
								<option value="receipt" <?php selected( get_option( 'pnp_response_type', 'receipt' ), 'receipt' ); ?>><?php echo esc_html__( 'Receipt', 'plugnpay-billpay-lite' ); ?></option>
							</select>
						</td>
					</tr>
				</table>

				<details>
					<summary><strong><?php echo esc_html__( 'Callback Settings', 'plugnpay-billpay-lite' ); ?></strong></summary>
					<table class="form-table pnp-form-subtable">
						<tr>
							<th scope="row"><?php echo esc_html__( 'Callback URL *', 'plugnpay-billpay-lite' ); ?></th>
							<td>
								<input type="url" name="pnp_pb_success_url" value="<?php echo esc_attr( get_option( 'pnp_pb_success_url', '' ) ); ?>" size="40" />
								<p class="description"><?php echo esc_html__( 'Required when Response Type is Callback.', 'plugnpay-billpay-lite' ); ?></p>
							</td>
						</tr>
						<tr>
							<th scope="row"><?php echo esc_html__( 'Callback Type', 'plugnpay-billpay-lite' ); ?></th>
							<td>
								<select name="pnp_pb_transition_type">
									<option value="hidden" <?php selected( get_option( 'pnp_pb_transition_type', 'hidden' ), 'hidden' ); ?>><?php echo esc_html__( 'Hidden POST', 'plugnpay-billpay-lite' ); ?></option>
									<option value="post" <?php selected( get_option( 'pnp_pb_transition_type', 'hidden' ), 'post' ); ?>><?php echo esc_html__( 'POST Transition Page', 'plugnpay-billpay-lite' ); ?></option>
									<option value="get" <?php selected( get_option( 'pnp_pb_transition_type', 'hidden' ), 'get' ); ?>><?php echo esc_html__( 'GET Transition Page', 'plugnpay-billpay-lite' ); ?></option>
								</select>
							</td>
						</tr>
					</table>
				</details>

				<details>
					<summary><strong><?php echo esc_html__( 'Receipt Settings', 'plugnpay-billpay-lite' ); ?></strong></summary>
					<table class="form-table pnp-form-subtable">
						<tr>
							<th scope="row"><?php echo esc_html__( 'Receipt Type', 'plugnpay-billpay-lite' ); ?></th>
							<td><input type="text" name="pnp_pb_receipt_type" value="<?php echo esc_attr( get_option( 'pnp_pb_receipt_type', '' ) ); ?>" size="30" /></td>
						</tr>
						<?php
						$receipt_fields = array(
							'pnp_pb_receipt_company'         => __( 'Company', 'plugnpay-billpay-lite' ),
							'pnp_pb_receipt_name'            => __( 'Contact', 'plugnpay-billpay-lite' ),
							'pnp_pb_receipt_address_1'       => __( 'Address Line 1', 'plugnpay-billpay-lite' ),
							'pnp_pb_receipt_address_2'       => __( 'Address Line 2', 'plugnpay-billpay-lite' ),
							'pnp_pb_receipt_city'            => __( 'City', 'plugnpay-billpay-lite' ),
							'pnp_pb_receipt_state'           => __( 'State', 'plugnpay-billpay-lite' ),
							'pnp_pb_receipt_province'        => __( 'Province', 'plugnpay-billpay-lite' ),
							'pnp_pb_receipt_postal_code'      => __( 'Postal Code', 'plugnpay-billpay-lite' ),
							'pnp_pb_receipt_country'         => __( 'Country', 'plugnpay-billpay-lite' ),
							'pnp_pb_receipt_email_address'   => __( 'Email', 'plugnpay-billpay-lite' ),
							'pnp_pb_receipt_phone'           => __( 'Phone', 'plugnpay-billpay-lite' ),
							'pnp_pb_receipt_fax'             => __( 'Fax', 'plugnpay-billpay-lite' ),
							'pnp_pb_receipt_company_url'     => __( 'Site URL', 'plugnpay-billpay-lite' ),
							'pnp_pb_receipt_transaction_url' => __( 'Return To Site URL', 'plugnpay-billpay-lite' ),
						);
						foreach ( $receipt_fields as $field => $label ) :
							$type = false !== strpos( $field, 'url' ) ? 'url' : ( false !== strpos( $field, 'email' ) ? 'email' : ( false !== strpos( $field, 'phone' ) || false !== strpos( $field, 'fax' ) ? 'tel' : 'text' ) );
							?>
							<tr>
								<th scope="row"><?php echo esc_html( $label ); ?></th>
								<td><input type="<?php echo esc_attr( $type ); ?>" name="<?php echo esc_attr( $field ); ?>" value="<?php echo esc_attr( get_option( $field, '' ) ); ?>" size="40" /></td>
							</tr>
						<?php endforeach; ?>
					</table>
				</details>
			</details>

			<?php submit_button(); ?>
		</form>
	</div>
	<?php
}
