<?php
/**
 * Koristus plugina eemaldamisel.
 *
 * Kampaaniad kustutatakse. WooCommerce'i kuponge ei puututa, sest need on
 * seotud juba tehtud tellimustega.
 *
 * @package Wonom_Kampaaniariba
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

// Jupikaupa, et kõik kampaaniad ei oleks korraga mälus.
do {
	$wkr_posts = get_posts(
		array(
			'post_type'      => 'wonom_banner',
			'post_status'    => 'any',
			'posts_per_page' => 100,
			'fields'         => 'ids',
		)
	);

	foreach ( $wkr_posts as $wkr_post_id ) {
		wp_delete_post( $wkr_post_id, true );
	}
} while ( count( $wkr_posts ) === 100 );

$wkr_options = array(
	'wkr_default_zindex',
	'wkr_body_padding',
	'wkr_deepl_key',
	'wkr_auto_purge',
	'wkr_purge_urls',
	'wkr_purge_due',
	'wkr_purge_log',
	'wkr_last_full_purge',
	'wkr_cron_last_run',
	'wkr_cron_watch_since',
	'wkr_cf_zone',
	'wkr_cf_token',
	'wkr_always_exclude_products',
	'wkr_always_exclude_cats',
	'wkr_update_source',
	'wkr_update_repo',
	'wkr_update_token',
	'wkr_update_json',
	'wkr_next_handover',
	'wkr_version',
	'wkr_data_hash',
	'wkr_data_info',
);

foreach ( $wkr_options as $wkr_option ) {
	delete_option( $wkr_option );
}

delete_transient( 'wkr_update_info' );

wp_clear_scheduled_hook( 'wkr_cron_heartbeat' );
wp_clear_scheduled_hook( 'wkr_url_purge' );
wp_unschedule_hook( 'wkr_coupon_handover' );
wp_unschedule_hook( 'wkr_boundary_purge' );

// Riba andmefailid.
$wkr_uploads = wp_upload_dir( null, false );

if ( empty( $wkr_uploads['error'] ) && ! empty( $wkr_uploads['basedir'] ) ) {
	$wkr_dir = trailingslashit( $wkr_uploads['basedir'] ) . 'wkr';

	foreach ( (array) glob( $wkr_dir . '/campaigns-*.js*' ) as $wkr_file ) {
		if ( is_file( $wkr_file ) ) {
			wp_delete_file( $wkr_file );
		}
	}

	if ( is_dir( $wkr_dir ) ) {
		@rmdir( $wkr_dir ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_rmdir, WordPress.PHP.NoSilencedErrors.Discouraged
	}
}
