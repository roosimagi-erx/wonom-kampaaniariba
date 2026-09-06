<?php
/**
 * Vahemälu tühjendamine.
 *
 * Riba renderdatakse PHP-s otse lehe HTML-i sisse. See on kiire ja
 * vahemälusõbralik, aga tähendab, et vahemällu salvestatud leht hoiab riba
 * sellisena, nagu see salvestamise hetkel oli. Seetõttu tuleb vahemälu
 * tühjendada kolmel hetkel: kampaania muutmisel, kampaania algus- ja lõpuajal
 * ning plugina uuendamisel.
 *
 * @package Wonom_Kampaaniariba
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Räägib poes olevate vahemälupluginatega.
 */
class WKR_Cache {

	/**
	 * Ajastatud tühjenduse haak.
	 */
	const CRON_HOOK = 'wkr_boundary_purge';

	/**
	 * Haagid.
	 */
	public static function init() {
		add_action( self::CRON_HOOK, array( __CLASS__, 'purge' ) );

		add_action( 'trashed_post', array( __CLASS__, 'on_post_change' ) );
		add_action( 'untrashed_post', array( __CLASS__, 'on_post_change' ) );
		add_action( 'deleted_post', array( __CLASS__, 'on_post_change' ) );

		add_action( 'upgrader_process_complete', array( __CLASS__, 'on_upgrade' ), 20, 2 );
		add_action( 'admin_post_wkr_purge', array( __CLASS__, 'handle_manual' ) );
	}

	/**
	 * Kas automaatne tühjendamine on lubatud.
	 *
	 * @return bool
	 */
	public static function enabled() {
		return (bool) get_option( 'wkr_auto_purge', 1 );
	}

	/**
	 * Tühjendab lehtede vahemälu kõigis tuntud pluginates.
	 *
	 * Iga kutse on olemasolu kontrolli taga, nii et puuduv plugin ei tee midagi.
	 *
	 * @return string[] Nimed, mille vahemälu tühjendati.
	 */
	public static function purge() {
		$done = array();

		// FlyingPress — purge_pages jätab fondid ja pildid alles.
		if ( class_exists( '\FlyingPress\Purge' ) ) {
			if ( method_exists( '\FlyingPress\Purge', 'purge_pages' ) ) {
				\FlyingPress\Purge::purge_pages();
				$done[] = 'FlyingPress';
			} elseif ( method_exists( '\FlyingPress\Purge', 'purge_everything' ) ) {
				\FlyingPress\Purge::purge_everything();
				$done[] = 'FlyingPress';
			}
		}

		if ( function_exists( 'rocket_clean_domain' ) ) {
			rocket_clean_domain();
			$done[] = 'WP Rocket';
		}

		if ( has_action( 'litespeed_purge_all' ) ) {
			do_action( 'litespeed_purge_all' );
			$done[] = 'LiteSpeed Cache';
		}

		if ( function_exists( 'w3tc_flush_posts' ) ) {
			w3tc_flush_posts();
			$done[] = 'W3 Total Cache';
		}

		if ( function_exists( 'wp_cache_clear_cache' ) ) {
			wp_cache_clear_cache();
			$done[] = 'WP Super Cache';
		}

		if ( function_exists( 'wpfc_clear_all_cache' ) ) {
			wpfc_clear_all_cache( true );
			$done[] = 'WP Fastest Cache';
		}

		if ( has_action( 'cache_enabler_clear_complete_cache' ) ) {
			do_action( 'cache_enabler_clear_complete_cache' );
			$done[] = 'Cache Enabler';
		}

		if ( function_exists( 'sg_cachepress_purge_cache' ) ) {
			sg_cachepress_purge_cache();
			$done[] = 'SiteGround Optimizer';
		}

		if ( function_exists( 'wp_cache_flush' ) ) {
			wp_cache_flush();
		}

		/**
		 * Oma vahemälu tühjendamiseks, näiteks Cloudflare või serveri tasemel.
		 *
		 * @param string[] $done Juba tühjendatud vahemälud.
		 */
		do_action( 'wkr_purge_cache', $done );

		return $done;
	}

	/**
	 * Tühjendab ainult siis, kui automaatika on lubatud.
	 */
	public static function auto_purge() {
		if ( self::enabled() ) {
			self::purge();
		}
	}

	/**
	 * Kampaania prügikasti, taastamine või kustutamine.
	 *
	 * @param int $post_id Postituse ID.
	 */
	public static function on_post_change( $post_id ) {
		if ( WKR_CPT !== get_post_type( $post_id ) ) {
			return;
		}

		self::unschedule( $post_id );
		self::auto_purge();
	}

	/**
	 * Plugina uuendamise järel — markup või stiilid võivad olla muutunud.
	 *
	 * @param WP_Upgrader $upgrader Paigaldaja.
	 * @param array       $data     Andmed.
	 */
	public static function on_upgrade( $upgrader, $data ) {
		if ( ! is_array( $data ) || empty( $data['type'] ) || 'plugin' !== $data['type'] ) {
			return;
		}

		$ours    = plugin_basename( WKR_FILE );
		$plugins = array();

		if ( ! empty( $data['plugins'] ) && is_array( $data['plugins'] ) ) {
			$plugins = $data['plugins'];
		} elseif ( ! empty( $data['plugin'] ) ) {
			$plugins = array( $data['plugin'] );
		}

		if ( in_array( $ours, $plugins, true ) ) {
			self::purge();
		}
	}

	/**
	 * Paneb kampaania algusele ja lõpule ajastatud tühjenduse.
	 *
	 * Nii vahetub riba ka vahemällu salvestatud lehtedel siis, kui kampaania
	 * päriselt algab või lõpeb, mitte alles järgmisel salvestamisel.
	 *
	 * @param int $post_id Kampaania ID.
	 */
	public static function schedule( $post_id ) {
		self::unschedule( $post_id );

		if ( ! self::enabled() ) {
			return;
		}

		$now   = time();
		$times = array(
			'start' => (int) get_post_meta( $post_id, '_wkr_start_utc', true ),
			'end'   => (int) get_post_meta( $post_id, '_wkr_end_utc', true ),
		);

		foreach ( $times as $which => $timestamp ) {
			if ( $timestamp > $now ) {
				// Paar sekundit hiljem, et piiri hetkel oleks olek juba uus.
				wp_schedule_single_event( $timestamp + 5, self::CRON_HOOK, array( $post_id, $which ) );
			}
		}
	}

	/**
	 * Eemaldab kampaania ajastatud tühjendused.
	 *
	 * @param int $post_id Kampaania ID.
	 */
	public static function unschedule( $post_id ) {
		foreach ( array( 'start', 'end' ) as $which ) {
			$timestamp = wp_next_scheduled( self::CRON_HOOK, array( $post_id, $which ) );
			while ( $timestamp ) {
				wp_unschedule_event( $timestamp, self::CRON_HOOK, array( $post_id, $which ) );
				$timestamp = wp_next_scheduled( self::CRON_HOOK, array( $post_id, $which ) );
			}
		}
	}

	/**
	 * Nupp „Tühjenda vahemälu kohe”.
	 */
	public static function handle_manual() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Puuduvad õigused.', 'wonom-kampaaniariba' ) );
		}

		check_admin_referer( 'wkr_purge' );

		$done = self::purge();

		wp_safe_redirect(
			add_query_arg(
				array(
					'post_type'  => WKR_CPT,
					'page'       => 'wkr-settings',
					'wkr_purged' => rawurlencode( implode( ', ', $done ) ),
				),
				admin_url( 'edit.php' )
			)
		);
		exit;
	}
}
