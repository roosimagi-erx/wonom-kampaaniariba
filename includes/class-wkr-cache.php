<?php
/**
 * WP-Croni pulss ja üleminek vanalt vahemälulahenduselt.
 *
 * Varem kirjutati riba lehe HTML-i sisse ja kampaania muutumisel tuli lehtede
 * vahemälu tühjendada. Nüüd tuleb riba andmefailist (vaata WKR_Data) ja plugin
 * ei tühjenda enam ühtegi vahemälu. Siia on jäänud ainult:
 *
 * - tunnine pulss, millest on näha, kas WP-Cron päriselt käivitub (cronist
 *   sõltub broneeritud sooduskoodi täpne üleandmine ja andmefaili korrashoid);
 * - ühekordne koristus versioonivahetusel: vanad ajastatud tühjendused maha.
 *
 * @package Wonom_Kampaaniariba
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Croni pulss ja versioonivahetuse koristus.
 */
class WKR_Cache {

	/**
	 * Pulsi haak. Selle abil saame teada, kas WP-Cron päriselt käivitub.
	 */
	const HEARTBEAT_HOOK = 'wkr_cron_heartbeat';

	/**
	 * Haagid.
	 */
	public static function init() {
		add_action( self::HEARTBEAT_HOOK, array( __CLASS__, 'heartbeat' ) );
		add_action( 'admin_init', array( __CLASS__, 'ensure_heartbeat' ) );
		add_action( 'admin_init', array( __CLASS__, 'maybe_migrate' ) );
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
	 * Versioonivahetuse järel: koristab vanad sündmused, ajastab broneeritud
	 * koodide üleandmised uuesti ja kirjutab andmefaili.
	 *
	 * Jookseb administraatori lehel, mitte külastaja päringus. Plugina välja- ja
	 * sisselülitamine kustutab ajastatud sündmused, nii et see paneb need tagasi.
	 */
	public static function maybe_migrate() {
		if ( WKR_VERSION === get_option( 'wkr_version' ) || ! current_user_can( 'edit_posts' ) ) {
			return;
		}

		update_option( 'wkr_version', WKR_VERSION, true );

		// Vanade versioonide ajastatud tühjendused. Uus versioon neid ei kasuta.
		wp_unschedule_hook( 'wkr_boundary_purge' );
		wp_unschedule_hook( 'wkr_url_purge' );
		delete_option( 'wkr_purge_due' );
		delete_option( 'wkr_purge_log' );
		delete_option( 'wkr_purge_urls' );
		delete_option( 'wkr_last_full_purge' );
		delete_option( 'wkr_auto_purge' );

		foreach ( WKR_Coupon::claimed_campaigns() as $id ) {
			WKR_Coupon::schedule_claim( $id );
		}

		WKR_Coupon::refresh_next();

		// Stiilide ja skripti aadress andmefailis kannab versiooninumbrit.
		WKR_Data::rebuild( true );
	}
}
