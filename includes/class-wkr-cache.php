<?php
/**
 * Vahemälu tühjendamine.
 *
 * Riba renderdatakse PHP-s otse lehe HTML-i sisse, nii et vahemällu salvestatud
 * leht hoiab riba sellisena, nagu see salvestamise hetkel oli.
 *
 * Kogu saidi vahemälu tühjendamine on poele kallis: pärast seda tuleb iga leht
 * uuesti kokku panna. Seetõttu:
 *
 * - kampaania salvestamisel, prügikasti viimisel ja taastamisel tühjendatakse
 *   ainult seadetes loetletud aadressid, üks kord ja viitega;
 * - kampaania lõpp ei vaja tühjendust — riba peidab brauser lõpuaja järgi;
 * - plugina uuendamine ei tühjenda midagi, sest stiilide ja skriptide aadressis
 *   on versiooninumber;
 * - kampaania algus tühjendab ajutiselt veel kõik lehed. See kaob, kui riba
 *   andmed liiguvad eraldi faili ja lehti ei ole enam vaja puutuda.
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
	 * Kampaania alguse tühjenduse haak.
	 */
	const CRON_HOOK = 'wkr_boundary_purge';

	/**
	 * Viitega aadressipõhise tühjenduse haak.
	 */
	const URL_HOOK = 'wkr_url_purge';

	/**
	 * Pulsi haak. Selle abil saame teada, kas WP-Cron päriselt käivitub.
	 */
	const HEARTBEAT_HOOK = 'wkr_cron_heartbeat';

	/**
	 * Valik: millal ootel olev aadressipõhine tühjendus tehakse.
	 */
	const DUE_OPTION = 'wkr_purge_due';

	/**
	 * Valik: viimaste tühjenduste logi.
	 */
	const LOG_OPTION = 'wkr_purge_log';

	/**
	 * Valik: millal viimati kõik lehed tühjendati.
	 */
	const FULL_OPTION = 'wkr_last_full_purge';

	/**
	 * Mitu sekundit pärast viimast muudatust tühjendus tehakse.
	 */
	const DELAY = 45;

	/**
	 * Mitu aadressi korraga kõige rohkem tühjendatakse.
	 */
	const MAX_URLS = 30;

	/**
	 * Kahe täistühjenduse vahele jääv vähim aeg sekundites. Kui ühel ja
	 * samal hetkel algab mitu kampaaniat, tehakse üks tühjendus.
	 */
	const FULL_GAP = 600;

	/**
	 * Haagid.
	 */
	public static function init() {
		add_action( self::CRON_HOOK, array( __CLASS__, 'on_boundary' ), 10, 2 );
		add_action( self::URL_HOOK, array( __CLASS__, 'run_if_due' ) );

		add_action( self::HEARTBEAT_HOOK, array( __CLASS__, 'heartbeat' ) );
		add_action( 'admin_init', array( __CLASS__, 'ensure_heartbeat' ) );
		add_action( 'admin_init', array( __CLASS__, 'maybe_migrate' ) );
		add_action( 'admin_init', array( __CLASS__, 'catch_up' ), 20 );

		add_action( 'trashed_post', array( __CLASS__, 'on_post_change' ) );
		add_action( 'untrashed_post', array( __CLASS__, 'on_post_change' ) );
		add_action( 'deleted_post', array( __CLASS__, 'on_post_change' ) );

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

		if ( ! get_option( 'wkr_cron_watch_since' ) ) {
			update_option( 'wkr_cron_watch_since', time(), false );
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

		$since = (int) get_option( 'wkr_cron_watch_since', 0 );

		// Puls on tunnine. Kaks tundi annab hilinemisele ruumi.
		if ( $last && ( time() - $last ) < 2 * HOUR_IN_SECONDS ) {
			$state = 'ok';
		} elseif ( $last ) {
			$state = 'stale';
		} elseif ( $since && ( time() - $since ) < 20 * MINUTE_IN_SECONDS ) {
			// Värskelt paigaldatud: esimest pulssi tuleb niikuinii oodata,
			// pole mõtet enne seda hoiatada.
			$state = 'waiting';
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
	 * Kas selles päringus on kõik lehed juba tühjendatud.
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

	/*
	 * ---------------------------------------------------------------------
	 * Aadressipõhine tühjendus: kampaania salvestamine, prügikast, taastamine.
	 * ---------------------------------------------------------------------
	 */

	/**
	 * Vaikimisi tühjendatavad aadressid. Seadetes saab nimekirja muuta.
	 *
	 * @return string[]
	 */
	public static function default_paths() {
		return array(
			'/',
			'/en/',
			'/epood/',
			'/en/shop/',
			'/tootekategooria/naised/',
			'/tootekategooria/naised/naiste-kudumid/',
			'/tootekategooria/naised/naiste-kleidid/',
			'/tootekategooria/naised/naiste-kleidid/linased-kleidid/',
			'/tootekategooria/naised/naiste-mantlid/',
			'/tootekategooria/naised/naiste-puksid/',
			'/tootekategooria/naised/naiste-jakid-ja-pintsakud/',
			'/tootekategooria/naised/naiste-pluusid/',
			'/tootekategooria/lapsed/',
			'/en/product-category-en/women/womens-jackets-blazers/',
			'/en/product-category-en/women/womens-blouses-sweatshirts/',
			'/en/product-category-en/lapsed-en/boys-lapsed-en/boys-suits/',
		);
	}

	/**
	 * Seadetes olev aadresside nimekiri tekstina, üks rida aadressi kohta.
	 *
	 * @return string
	 */
	public static function paths_text() {
		$saved = get_option( 'wkr_purge_urls', null );

		if ( ! is_string( $saved ) || '' === trim( $saved ) ) {
			return implode( "\n", self::default_paths() );
		}

		return $saved;
	}

	/**
	 * Üks rida seadetest: kas rada sellel saidil või selle saidi täisaadress.
	 *
	 * @param string $line Rida.
	 * @return string Täisaadress või tühi, kui rida ei sobi.
	 */
	public static function normalize_url( $line ) {
		$line = trim( (string) $line );

		if ( '' === $line ) {
			return '';
		}

		if ( '/' === $line[0] ) {
			$line = home_url( $line );
		}

		$url  = esc_url_raw( $line );
		$host = wp_parse_url( home_url(), PHP_URL_HOST );

		return ( $url && wp_parse_url( $url, PHP_URL_HOST ) === $host ) ? $url : '';
	}

	/**
	 * Aadressid, mis kampaania muutmisel tühjendatakse.
	 *
	 * @return string[]
	 */
	public static function urls() {
		$urls = array();

		foreach ( preg_split( '/\R/', self::paths_text() ) as $line ) {
			$url = self::normalize_url( $line );
			if ( $url ) {
				$urls[] = $url;
			}
		}

		/**
		 * Aadressid, mille vahemälu kampaania muutmisel tühjendatakse.
		 *
		 * @param string[] $urls Täisaadressid.
		 */
		$urls = (array) apply_filters( 'wkr_purge_urls', array_values( array_unique( $urls ) ) );

		return array_slice( array_values( array_unique( array_filter( $urls ) ) ), 0, self::MAX_URLS );
	}

	/**
	 * Tellib viitega tühjenduse.
	 *
	 * Iga muudatus lükkab tühjenduse DELAY sekundi kaugusele. Mitu muudatust
	 * järjest annavad seetõttu ühe tühjenduse, mitte iga muudatuse kohta oma.
	 */
	public static function request_purge() {
		if ( ! self::enabled() ) {
			return;
		}

		$at = time() + self::DELAY;

		wp_clear_scheduled_hook( self::URL_HOOK );
		wp_schedule_single_event( $at, self::URL_HOOK );
		update_option( self::DUE_OPTION, $at, false );
	}

	/**
	 * Teeb ootel tühjenduse ära, kui selle aeg on käes.
	 */
	public static function run_if_due() {
		$due = (int) get_option( self::DUE_OPTION, 0 );

		if ( ! $due ) {
			return;
		}

		if ( $due > time() ) {
			// Vahepeal tuli uus muudatus ja aeg lükkus edasi.
			if ( ! wp_next_scheduled( self::URL_HOOK ) ) {
				wp_schedule_single_event( $due, self::URL_HOOK );
			}
			return;
		}

		// Kustutamine õnnestub ainult ühel päringul — nii ei tee kaks
		// samaaegset päringut sama tühjendust kaks korda.
		if ( ! delete_option( self::DUE_OPTION ) ) {
			return;
		}

		wp_clear_scheduled_hook( self::URL_HOOK );

		self::purge_urls( 'change' );
	}

	/**
	 * Varuvariant: kui cron ei käi (näiteks testsaidil), tehakse ootel
	 * tühjendus ära järgmisel administraatori lehel. Külastaja päringus
	 * tühjendust ei tehta kunagi.
	 */
	public static function catch_up() {
		// admin-ajax.php käivitab admin_init ka poe külastajale — tema päringus ei tühjendata.
		if ( ! current_user_can( 'edit_posts' ) ) {
			return;
		}

		$due = (int) get_option( self::DUE_OPTION, 0 );

		if ( $due && $due <= time() ) {
			self::run_if_due();
		}
	}

	/**
	 * Tühjendab loetletud aadressid kõigis tuntud vahemäludes ja laeb need
	 * uuesti ette. Vahemälu, millel aadressipõhist tühjendust ei ole, jäetakse
	 * puutumata.
	 *
	 * @param string $reason Miks tühjendati (logi jaoks).
	 * @return string[] Nimed, mille vahemälu tühjendati.
	 */
	public static function purge_urls( $reason = 'change' ) {
		self::$errors = array();

		$urls = self::urls();
		$done = array();

		if ( ! $urls ) {
			return $done;
		}

		if ( self::flyingpress_urls( $urls ) ) {
			$done[] = 'FlyingPress';
		}

		if ( function_exists( 'rocket_clean_files' ) ) {
			rocket_clean_files( $urls );
			$done[] = 'WP Rocket';
		}

		if ( has_action( 'litespeed_purge_url' ) ) {
			foreach ( $urls as $url ) {
				do_action( 'litespeed_purge_url', $url );
			}
			$done[] = 'LiteSpeed Cache';
		}

		if ( function_exists( 'w3tc_flush_url' ) ) {
			foreach ( $urls as $url ) {
				w3tc_flush_url( $url );
			}
			$done[] = 'W3 Total Cache';
		}

		if ( function_exists( 'wpsc_delete_url_cache' ) ) {
			foreach ( $urls as $url ) {
				wpsc_delete_url_cache( $url );
			}
			$done[] = 'WP Super Cache';
		}

		if ( has_action( 'cache_enabler_clear_page_cache_by_url' ) ) {
			foreach ( $urls as $url ) {
				do_action( 'cache_enabler_clear_page_cache_by_url', $url );
			}
			$done[] = 'Cache Enabler';
		}

		if ( self::cloudflare( array( 'files' => $urls ) ) ) {
			$done[] = 'Cloudflare';
		}

		self::log( 'urls', $reason, count( $urls ), $done );

		/**
		 * Oma vahemälukihi aadressipõhine tühjendus.
		 *
		 * @param string[] $urls Tühjendatud aadressid.
		 * @param string[] $done Juba tühjendatud vahemälud.
		 */
		do_action( 'wkr_purge_cache_urls', $urls, $done );

		return $done;
	}

	/**
	 * FlyingPress: tühjendab ja eellaeb ainult antud aadressid.
	 *
	 * @param string[] $urls Aadressid.
	 * @return bool Kas midagi tehti.
	 */
	private static function flyingpress_urls( $urls ) {
		$purge = 'FlyingPress\Purge';

		if ( ! class_exists( $purge ) ) {
			return false;
		}

		$did = false;

		try {
			if ( method_exists( $purge, 'purge_urls' ) ) {
				$purge::purge_urls( $urls );
				$did = true;
			} elseif ( method_exists( $purge, 'purge_url' ) ) {
				foreach ( $urls as $url ) {
					$purge::purge_url( $url );
				}
				$did = true;
			}

			// Soojendame ainult need lehed — mitte kunagi kogu saiti.
			$preload = 'FlyingPress\Preload';

			if ( $did && class_exists( $preload ) ) {
				if ( method_exists( $preload, 'preload_urls' ) ) {
					$preload::preload_urls( $urls );
				} elseif ( method_exists( $preload, 'preload_url' ) ) {
					foreach ( $urls as $url ) {
						$preload::preload_url( $url );
					}
				}
			}
		} catch ( \Throwable $e ) {
			self::$errors[] = 'FlyingPress: ' . $e->getMessage();
		}

		return $did;
	}

	/*
	 * ---------------------------------------------------------------------
	 * Kõigi lehtede tühjendus: ainult kampaania algus ja käsitsi nupp.
	 * ---------------------------------------------------------------------
	 */

	/**
	 * Tühjendab kõigi lehtede vahemälu tuntud pluginates ja Cloudflare'is.
	 *
	 * See on ajutine: kuni riba on lehe HTML-i sees, ei jõua uus kampaania
	 * vahemälus olevatele lehtedele teisiti. Objektivahemälu ei puututa.
	 *
	 * @param string $reason Miks tühjendati (logi jaoks).
	 * @param bool   $force  Tühjenda ka siis, kui just äsja tühjendati.
	 * @return string[] Nimed, mille vahemälu tühjendati.
	 */
	public static function purge_all( $reason = 'start', $force = false ) {
		if ( ! $force ) {
			if ( self::$purged ) {
				return array();
			}

			// Mitu samal hetkel algavat kampaaniat annavad ühe tühjenduse.
			$last = (int) get_option( self::FULL_OPTION, 0 );
			if ( $last && ( time() - $last ) < self::FULL_GAP ) {
				return array();
			}
		}

		self::$purged = true;
		self::$errors = array();

		update_option( self::FULL_OPTION, time(), false );

		$done = array();

		// FlyingPress — purge_pages jätab fondid ja pildid alles.
		if ( class_exists( '\FlyingPress\Purge' ) ) {
			try {
				if ( method_exists( '\FlyingPress\Purge', 'purge_pages' ) ) {
					\FlyingPress\Purge::purge_pages();
					$done[] = 'FlyingPress';
				} elseif ( method_exists( '\FlyingPress\Purge', 'purge_everything' ) ) {
					\FlyingPress\Purge::purge_everything();
					$done[] = 'FlyingPress';
				}
			} catch ( \Throwable $e ) {
				self::$errors[] = 'FlyingPress: ' . $e->getMessage();
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

		// Cloudflare hoiab lehe koopiat servas. Serveripoolne tühjendus sinna ei ulatu.
		if ( self::cloudflare( array( 'purge_everything' => true ) ) ) {
			$done[] = 'Cloudflare';
		}

		self::log( 'all', $reason, 0, $done );

		/**
		 * Oma vahemälu tühjendamiseks, näiteks serveri tasemel.
		 *
		 * @param string[] $done Juba tühjendatud vahemälud.
		 */
		do_action( 'wkr_purge_cache', $done );

		return $done;
	}

	/**
	 * Kampaania algus: uus riba peab jõudma ka vahemälus olevatele lehtedele.
	 *
	 * Kampaania lõpp tühjendust ei vaja — riba peidab brauser. Varasemad
	 * versioonid ajastasid ka lõpule sündmuse; kui mõni selline veel järjekorras
	 * on, ei tee see enam midagi.
	 *
	 * @param int    $post_id Kampaania ID.
	 * @param string $which   start | end.
	 */
	public static function on_boundary( $post_id = 0, $which = 'start' ) {
		if ( 'start' !== $which || ! self::enabled() ) {
			return;
		}

		// Testsait jagab serverit päris poega ja tema vahemälu ei näe ükski klient.
		if ( wkr_is_test_host() ) {
			return;
		}

		// Välja lülitatud või vahepeal ümber ajastatud kampaania ei muuda lehtedel midagi.
		if ( $post_id && 'live' !== wkr_status( (int) $post_id ) ) {
			return;
		}

		self::purge_all( 'start' );
	}

	/**
	 * Cloudflare'i serva vahemälu tühjendus.
	 *
	 * Testsaidilt päringut ei tehta, isegi kui võti on sisestatud: test ja
	 * päris pood võivad olla samas Cloudflare'i tsoonis ja testi andmebaas on
	 * tihti päris poe koopia koos tema võtmetega.
	 *
	 * @param array $payload Kas array( 'files' => aadressid ) või array( 'purge_everything' => true ).
	 * @return bool Kas tühjendamine õnnestus.
	 */
	private static function cloudflare( $payload ) {
		$zone  = trim( (string) get_option( 'wkr_cf_zone', '' ) );
		$token = trim( (string) get_option( 'wkr_cf_token', '' ) );

		if ( '' === $zone || '' === $token || wkr_is_test_host() ) {
			return false;
		}

		// Aadresse võetakse ühe päringuga vastu kuni 30.
		$bodies = isset( $payload['files'] )
			? array_map(
				function ( $chunk ) {
					return array( 'files' => $chunk );
				},
				array_chunk( array_values( (array) $payload['files'] ), 30 )
			)
			: array( $payload );

		$ok = (bool) $bodies;

		foreach ( $bodies as $body ) {
			$response = wp_remote_post(
				'https://api.cloudflare.com/client/v4/zones/' . rawurlencode( $zone ) . '/purge_cache',
				array(
					'timeout' => 15,
					'headers' => array(
						'Authorization' => 'Bearer ' . $token,
						'Content-Type'  => 'application/json',
					),
					'body'    => wp_json_encode( $body ),
				)
			);

			if ( is_wp_error( $response ) ) {
				self::$errors[] = 'Cloudflare: ' . $response->get_error_message();
				$ok             = false;
				continue;
			}

			$answer = json_decode( wp_remote_retrieve_body( $response ), true );

			if ( empty( $answer['success'] ) ) {
				$message = __( 'tundmatu viga', 'wonom-kampaaniariba' );
				if ( ! empty( $answer['errors'][0]['message'] ) ) {
					$message = $answer['errors'][0]['message'];
				}

				self::$errors[] = 'Cloudflare: ' . $message;
				$ok             = false;
			}
		}

		return $ok;
	}

	/*
	 * ---------------------------------------------------------------------
	 * Logi, ajastus, koristus.
	 * ---------------------------------------------------------------------
	 */

	/**
	 * Jätab tühjenduse meelde, et seadete lehel oleks näha, mida ja millal tehti.
	 *
	 * @param string   $kind   urls | all.
	 * @param string   $reason change | start | manual.
	 * @param int      $count  Aadresside arv.
	 * @param string[] $done   Tühjendatud vahemälud.
	 */
	private static function log( $kind, $reason, $count, $done ) {
		$log = get_option( self::LOG_OPTION, array() );
		$log = is_array( $log ) ? $log : array();

		array_unshift(
			$log,
			array(
				'time'   => time(),
				'kind'   => $kind,
				'reason' => $reason,
				'count'  => (int) $count,
				'done'   => array_values( (array) $done ),
				'errors' => self::$errors,
			)
		);

		update_option( self::LOG_OPTION, array_slice( $log, 0, 8 ), false );
	}

	/**
	 * Viimased tühjendused, uuem ees.
	 *
	 * @return array[]
	 */
	public static function recent() {
		$log = get_option( self::LOG_OPTION, array() );
		return is_array( $log ) ? $log : array();
	}

	/**
	 * Millal ootel olev tühjendus tehakse (0, kui midagi ei oota).
	 *
	 * @return int
	 */
	public static function pending() {
		return (int) get_option( self::DUE_OPTION, 0 );
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

		// Taastatud ja kohe avaldatud kampaania saab oma alguse tühjenduse tagasi.
		if ( 'publish' === get_post_status( $post_id ) ) {
			self::schedule( $post_id );
		}

		self::request_purge();
	}

	/**
	 * Paneb kampaania algusele ajastatud tühjenduse.
	 *
	 * @param int $post_id Kampaania ID.
	 */
	public static function schedule( $post_id ) {
		self::unschedule( $post_id );

		if ( ! self::enabled() ) {
			return;
		}

		$start = (int) get_post_meta( $post_id, '_wkr_start_utc', true );

		if ( $start > time() ) {
			// Paar sekundit hiljem, et alguse hetkel oleks olek juba uus.
			wp_schedule_single_event( $start + 5, self::CRON_HOOK, array( (int) $post_id, 'start' ) );
		}
	}

	/**
	 * Eemaldab kampaania ajastatud tühjendused.
	 *
	 * @param int $post_id Kampaania ID.
	 */
	public static function unschedule( $post_id ) {
		foreach ( array( 'start', 'end' ) as $which ) {
			$args      = array( (int) $post_id, $which );
			$timestamp = wp_next_scheduled( self::CRON_HOOK, $args );

			while ( $timestamp ) {
				wp_unschedule_event( $timestamp, self::CRON_HOOK, $args );
				$timestamp = wp_next_scheduled( self::CRON_HOOK, $args );
			}
		}
	}

	/**
	 * Versioonivahetuse järel: koristab vanad sündmused ja ajastab tulevased
	 * kampaaniad uuesti.
	 *
	 * Jookseb administraatori lehel, mitte külastaja päringus. Plugina välja- ja
	 * sisselülitamine kustutab ajastatud sündmused, nii et see paneb need tagasi.
	 */
	public static function maybe_migrate() {
		if ( WKR_VERSION === get_option( 'wkr_version' ) || ! current_user_can( 'edit_posts' ) ) {
			return;
		}

		update_option( 'wkr_version', WKR_VERSION, true );

		// Kõik vanad piirisündmused maha, olenemata kampaaniast — lõpule
		// neid enam ei panda ja algused ajastatakse kohe uuesti.
		wp_unschedule_hook( self::CRON_HOOK );

		foreach ( self::future_campaigns() as $id ) {
			self::schedule( $id );

			if ( WKR_Coupon::has_claim( $id ) ) {
				WKR_Coupon::schedule_claim( $id );
			}
		}

		WKR_Coupon::refresh_next();
	}

	/**
	 * Avaldatud kampaaniad, mis ei ole veel lõppenud.
	 *
	 * @return int[]
	 */
	private static function future_campaigns() {
		return get_posts(
			array(
				'post_type'        => WKR_CPT,
				'post_status'      => 'publish',
				'numberposts'      => 100,
				'fields'           => 'ids',
				'suppress_filters' => true,
				// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
				'meta_query'       => array(
					array(
						'key'     => '_wkr_end_utc',
						'value'   => time(),
						'compare' => '>',
						'type'    => 'NUMERIC',
					),
				),
			)
		);
	}

	/**
	 * Nupp „Tühjenda kogu vahemälu kohe”.
	 */
	public static function handle_manual() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Puuduvad õigused.', 'wonom-kampaaniariba' ) );
		}

		check_admin_referer( 'wkr_purge' );

		$done = self::purge_all( 'manual', true );

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
