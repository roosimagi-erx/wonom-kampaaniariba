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
	 * Pulsi haak. Selle abil saame teada, kas WP-Cron päriselt käivitub.
	 */
	const HEARTBEAT_HOOK = 'wkr_cron_heartbeat';

	/**
	 * Haagid.
	 */
	public static function init() {
		add_action( self::CRON_HOOK, array( __CLASS__, 'purge' ) );

		add_action( self::HEARTBEAT_HOOK, array( __CLASS__, 'heartbeat' ) );
		add_action( 'admin_init', array( __CLASS__, 'ensure_heartbeat' ) );

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
	 * Hoolitseb, et puls oleks ajastatud.
	 *
	 * DISABLE_WP_CRON konstant ütleb ainult seda, et WordPress ise lehekülastuste
	 * pealt cron'i ei käivita. See ei ütle midagi selle kohta, kas serveris on
	 * päris cron-töö, mis wp-cron.php käivitab. Ainus aus viis teada saada on
	 * ise üks korduv sündmus ajastada ja vaadata, kas see käivitub.
	 */
	public static function ensure_heartbeat() {
		if ( ! wp_next_scheduled( self::HEARTBEAT_HOOK ) ) {
			// Esimene käivitus paari minuti pärast, et administraator saaks
			// kohe kinnituse, mitte ei ootaks tund aega.
			wp_schedule_event( time() + 120, 'hourly', self::HEARTBEAT_HOOK );
		}
	}

	/**
	 * Märgib üles, millal WP-Cron viimati päriselt käivitus.
	 */
	public static function heartbeat() {
		update_option( 'wkr_cron_last_run', time(), false );
	}

	/**
	 * Kas cron päriselt käivitub ja millal viimati.
	 *
	 * @return array {
	 *     @type string $state   ok | waiting | stale | disabled
	 *     @type int    $last    Viimase käivituse ajatempel, 0 kui pole olnud.
	 *     @type bool   $wp_off  Kas DISABLE_WP_CRON on sees.
	 * }
	 */
	public static function cron_status() {
		$last   = (int) get_option( 'wkr_cron_last_run', 0 );
		$wp_off = defined( 'DISABLE_WP_CRON' ) && DISABLE_WP_CRON;

		// Puls on tunnine. Kaks tundi annab hilinemisele ruumi.
		if ( $last && ( time() - $last ) < 2 * HOUR_IN_SECONDS ) {
			$state = 'ok';
		} elseif ( $last ) {
			$state = 'stale';
		} elseif ( $wp_off ) {
			$state = 'disabled';
		} else {
			$state = 'waiting';
		}

		return array(
			'state'  => $state,
			'last'   => $last,
			'wp_off' => $wp_off,
		);
	}

	/**
	 * Kas selles päringus on juba tühjendatud.
	 *
	 * @var bool
	 */
	private static $purged = false;

	/**
	 * Viimase tühjenduse vead.
	 *
	 * @var string[]
	 */
	private static $errors = array();

	/**
	 * Viimase tühjenduse vead.
	 *
	 * @return string[]
	 */
	public static function last_errors() {
		return self::$errors;
	}

	/**
	 * Tühjendab lehtede vahemälu kõigis tuntud pluginates ja Cloudflare'is.
	 *
	 * Iga kutse on olemasolu kontrolli taga, nii et puuduv plugin ei tee midagi.
	 * Ühe päringu jooksul tühjendatakse ainult korra — mitu haaki võivad korraga
	 * käivituda ja Cloudflare'i API-l on päevalimiit.
	 *
	 * @param bool $force Tühjenda ka siis, kui selles päringus on juba tühjendatud.
	 * @return string[] Nimed, mille vahemälu tühjendati.
	 */
	public static function purge( $force = false ) {
		if ( self::$purged && ! $force ) {
			return array();
		}

		self::$purged = true;
		self::$errors = array();

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

		// Cloudflare hoiab lehe koopiat servas. Serveripoolne tühjendus sinna ei ulatu.
		if ( self::cloudflare() ) {
			$done[] = 'Cloudflare';
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
	 * Tühjendab Cloudflare'i serva vahemälu.
	 *
	 * Cloudflare'i tasuta ja Pro pakett lubab ainult kogu tsooni tühjendamist.
	 * Riba on igal lehel, seega see on siin niikuinii õige valik.
	 *
	 * @return bool Kas tühjendamine õnnestus.
	 */
	private static function cloudflare() {
		$zone  = trim( (string) get_option( 'wkr_cf_zone', '' ) );
		$token = trim( (string) get_option( 'wkr_cf_token', '' ) );

		if ( '' === $zone || '' === $token ) {
			return false;
		}

		$response = wp_remote_post(
			'https://api.cloudflare.com/client/v4/zones/' . rawurlencode( $zone ) . '/purge_cache',
			array(
				'timeout' => 20,
				'headers' => array(
					'Authorization' => 'Bearer ' . $token,
					'Content-Type'  => 'application/json',
				),
				'body'    => wp_json_encode( array( 'purge_everything' => true ) ),
			)
		);

		if ( is_wp_error( $response ) ) {
			self::$errors[] = 'Cloudflare: ' . $response->get_error_message();
			return false;
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( ! empty( $body['success'] ) ) {
			return true;
		}

		$message = __( 'tundmatu viga', 'wonom-kampaaniariba' );
		if ( ! empty( $body['errors'][0]['message'] ) ) {
			$message = $body['errors'][0]['message'];
		}

		self::$errors[] = 'Cloudflare: ' . $message;

		return false;
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

		$done = self::purge( true );

		$args = array(
			'post_type'  => WKR_CPT,
			'page'       => 'wkr-settings',
			'wkr_purged' => rawurlencode( implode( ', ', $done ) ),
		);

		if ( self::$errors ) {
			$args['wkr_purge_err'] = rawurlencode( implode( ' · ', self::$errors ) );
		}

		wp_safe_redirect( add_query_arg( $args, admin_url( 'edit.php' ) ) );
		exit;
	}
}
