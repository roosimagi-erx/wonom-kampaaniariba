<?php
/**
 * Plugin Name:       Wonom Kampaaniariba
 * Description:       Ajastatud sooduspakkumiste riba poe päisesse ja jalusesse. Kampaaniad kalendris, tekstid kahes keeles, sooduskood ühe klõpsuga kopeeritav, WooCommerce'i kupong luuakse samast kohast.
 * Version:           1.5.0
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Author:            Wonom Digital
 * Update URI:        https://wonom.ee/plugins/wonom-kampaaniariba
 * Text Domain:       wonom-kampaaniariba
 * Domain Path:       /languages
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 *
 * @package Wonom_Kampaaniariba
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'WKR_VERSION', '1.5.0' );
define( 'WKR_FILE', __FILE__ );
define( 'WKR_DIR', plugin_dir_path( __FILE__ ) );
define( 'WKR_URL', plugin_dir_url( __FILE__ ) );
define( 'WKR_CPT', 'wonom_banner' );

require_once WKR_DIR . 'includes/helpers.php';
require_once WKR_DIR . 'includes/class-wkr-post-type.php';
require_once WKR_DIR . 'includes/class-wkr-coupon.php';
require_once WKR_DIR . 'includes/class-wkr-meta.php';
require_once WKR_DIR . 'includes/class-wkr-translate.php';
require_once WKR_DIR . 'includes/class-wkr-settings.php';
require_once WKR_DIR . 'includes/class-wkr-calendar.php';
require_once WKR_DIR . 'includes/class-wkr-render.php';
require_once WKR_DIR . 'includes/class-wkr-updater.php';

add_action(
	'plugins_loaded',
	function () {
		load_plugin_textdomain( 'wonom-kampaaniariba', false, dirname( plugin_basename( WKR_FILE ) ) . '/languages' );

		WKR_Post_Type::init();
		WKR_Coupon::init();
		WKR_Meta::init();
		WKR_Translate::init();
		WKR_Settings::init();
		WKR_Calendar::init();
		WKR_Render::init();
		WKR_Updater::init();
	}
);

register_activation_hook(
	WKR_FILE,
	function () {
		WKR_Post_Type::register();
		flush_rewrite_rules();
	}
);

register_deactivation_hook( WKR_FILE, 'flush_rewrite_rules' );
