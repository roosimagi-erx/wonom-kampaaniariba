<?php
/**
 * Koristus plugina eemaldamisel.
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

delete_option( 'wkr_default_zindex' );
delete_option( 'wkr_body_padding' );
delete_option( 'wkr_deepl_key' );
