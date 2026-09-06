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

$wkr_posts = get_posts(
	array(
		'post_type'      => 'wonom_banner',
		'post_status'    => 'any',
		'posts_per_page' => -1,
		'fields'         => 'ids',
	)
);

foreach ( $wkr_posts as $wkr_post_id ) {
	wp_delete_post( $wkr_post_id, true );
}

$wkr_options = array(
	'wkr_default_zindex',
	'wkr_body_padding',
	'wkr_deepl_key',
	'wkr_auto_purge',
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
);

foreach ( $wkr_options as $wkr_option ) {
	delete_option( $wkr_option );
}

delete_transient( 'wkr_update_info' );

wp_clear_scheduled_hook( 'wkr_cron_heartbeat' );
