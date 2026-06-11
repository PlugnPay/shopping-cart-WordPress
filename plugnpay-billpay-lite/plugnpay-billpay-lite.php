<?php
/**
 * Plugin Name: PlugnPay BillPay Lite
 * Description: BillPay Lite for WordPress — collect payments via a secure form or direct URL, verify with hCaptcha, and redirect to PlugnPay Smart Screens v2.
 * Version: 2.0.1
 * Author: PlugnPay Technologies Inc.
 * License: GPL2
 * Text Domain: plugnpay-billpay-lite
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'PNP_PLUGIN_VERSION', '2.0.1' );
define( 'PNP_PLUGIN_PATH', plugin_dir_path( __FILE__ ) );
define( 'PNP_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

require_once PNP_PLUGIN_PATH . 'includes/security.php';
require_once PNP_PLUGIN_PATH . 'includes/card-types.php';
require_once PNP_PLUGIN_PATH . 'includes/hcaptcha.php';
require_once PNP_PLUGIN_PATH . 'includes/payment-processor.php';
require_once PNP_PLUGIN_PATH . 'includes/payment-form.php';
require_once PNP_PLUGIN_PATH . 'includes/payment-endpoint.php';
require_once PNP_PLUGIN_PATH . 'includes/admin-settings.php';

register_activation_hook( __FILE__, 'pnp_activate_plugin' );
register_deactivation_hook( __FILE__, 'pnp_deactivate_plugin' );

/**
 * Enqueue admin assets on plugin settings page.
 *
 * @param string $hook Admin page hook.
 */
function pnp_enqueue_admin_assets( $hook ) {
	if ( 'toplevel_page_pnp-settings' !== $hook ) {
		return;
	}
	wp_enqueue_style( 'pnp-billpay-admin', PNP_PLUGIN_URL . 'assets/css/admin.css', array(), PNP_PLUGIN_VERSION );
}
add_action( 'admin_enqueue_scripts', 'pnp_enqueue_admin_assets' );
