<?php
/**
 * Automaatsed uuendused ilma WordPress.org-ita.
 *
 * Allikaks võib olla GitHubi väljalase (release) või oma serveris olev JSON-fail.
 * WordPress küsib uuendusi samamoodi nagu iga teise plugina puhul — uuendusteade
 * ilmub Pluginad-lehele ja „Uuenda kohe” töötab.
 *
 * @package Wonom_Kampaaniariba
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Uuenduste kontroll ja paigaldus.
 */
class WKR_Updater {

	/**
	 * Vahemälu võti.
	 */
	const TRANSIENT = 'wkr_update_info';

	/**
	 * Kui kaua tulemust hoitakse (sekundites).
	 */
	const TTL = 6 * HOUR_IN_SECONDS;

	/**
	 * Haagid.
	 */
	public static function init() {
		add_filter( 'pre_set_site_transient_update_plugins', array( __CLASS__, 'inject' ) );
		add_filter( 'plugins_api', array( __CLASS__, 'details' ), 20, 3 );
		add_filter( 'upgrader_source_selection', array( __CLASS__, 'fix_folder' ), 10, 4 );
		add_filter( 'http_request_args', array( __CLASS__, 'auth_header' ), 10, 2 );
		add_action( 'upgrader_process_complete', array( __CLASS__, 'flush' ), 10, 2 );
		add_action( 'admin_post_wkr_check_update', array( __CLASS__, 'manual_check' ) );
	}

	/**
	 * Plugina fail WordPressi silmis.
	 *
	 * @return string
	 */
	public static function basename() {
		return plugin_basename( WKR_FILE );
	}

	/**
	 * Kausta nimi, mis peab paigalduse järel alles jääma.
	 *
	 * @return string
	 */
	public static function slug() {
		return dirname( self::basename() );
	}

	/**
	 * Valitud allikas.
	 *
	 * @return string off | github | json
	 */
	public static function source() {
		$source = (string) get_option( 'wkr_update_source', 'off' );
		return in_array( $source, array( 'github', 'json' ), true ) ? $source : 'off';
	}

	/**
	 * GitHubi isiklik võti, kui hoidla on privaatne.
	 *
	 * @return string
	 */
	private static function token() {
		return trim( (string) get_option( 'wkr_update_token', '' ) );
	}

	/**
	 * Küsib kaugallikast uusima versiooni info.
	 *
	 * @param bool $force Jäta vahemälu vahele.
	 * @return array|null
	 */
	public static function remote( $force = false ) {
		if ( 'off' === self::source() ) {
			return null;
		}

		if ( ! $force ) {
			$cached = get_transient( self::TRANSIENT );
			if ( is_array( $cached ) ) {
				return $cached;
			}
			if ( 'none' === $cached ) {
				return null;
			}
		}

		$info = 'github' === self::source() ? self::from_github() : self::from_json();

		if ( ! $info || empty( $info['version'] ) || empty( $info['package'] ) ) {
			// Ka ebaõnnestumist hoiame hetke, et katkine URL ei koormaks iga admin-lehte.
			set_transient( self::TRANSIENT, 'none', HOUR_IN_SECONDS );
			return null;
		}

		set_transient( self::TRANSIENT, $info, self::TTL );

		return $info;
	}

	/**
	 * GitHubi viimane väljalase.
	 *
	 * @return array|null
	 */
	private static function from_github() {
		$repo = trim( (string) get_option( 'wkr_update_repo', '' ), " \t\n\r/" );
		if ( ! preg_match( '#^[\w.-]+/[\w.-]+$#', $repo ) ) {
			return null;
		}

		$args = array(
			'timeout' => 15,
			'headers' => array(
				'Accept'     => 'application/vnd.github+json',
				'User-Agent' => 'wonom-kampaaniariba',
			),
		);

		$token = self::token();
		if ( $token ) {
			$args['headers']['Authorization'] = 'Bearer ' . $token;
		}

		$response = wp_remote_get( 'https://api.github.com/repos/' . $repo . '/releases/latest', $args );

		if ( is_wp_error( $response ) || 200 !== (int) wp_remote_retrieve_response_code( $response ) ) {
			return null;
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( ! is_array( $body ) || empty( $body['tag_name'] ) ) {
			return null;
		}

		// Eelistame väljalaskele lisatud ZIP-i: seal on kaust juba õige nimega.
		$package = '';
		if ( ! empty( $body['assets'] ) && is_array( $body['assets'] ) ) {
			foreach ( $body['assets'] as $asset ) {
				if ( ! empty( $asset['browser_download_url'] ) && substr( $asset['browser_download_url'], -4 ) === '.zip' ) {
					$package = $token && ! empty( $asset['url'] ) ? $asset['url'] : $asset['browser_download_url'];
					break;
				}
			}
		}

		if ( ! $package && ! empty( $body['zipball_url'] ) ) {
			$package = $body['zipball_url'];
		}

		return array(
			'version'      => ltrim( (string) $body['tag_name'], 'vV' ),
			'package'      => $package,
			'url'          => ! empty( $body['html_url'] ) ? $body['html_url'] : 'https://github.com/' . $repo,
			'changelog'    => ! empty( $body['body'] ) ? wp_kses_post( $body['body'] ) : '',
			'last_updated' => ! empty( $body['published_at'] ) ? gmdate( 'Y-m-d H:i:s', strtotime( $body['published_at'] ) ) : '',
			'tested'       => '',
			'requires'     => '',
			'requires_php' => '',
		);
	}

	/**
	 * Oma serveris olev JSON-manifest.
	 *
	 * @return array|null
	 */
	private static function from_json() {
		$url = trim( (string) get_option( 'wkr_update_json', '' ) );
		if ( ! $url || ! wp_http_validate_url( $url ) ) {
			return null;
		}

		$response = wp_remote_get(
			$url,
			array(
				'timeout' => 15,
				'headers' => array( 'Accept' => 'application/json' ),
			)
		);

		if ( is_wp_error( $response ) || 200 !== (int) wp_remote_retrieve_response_code( $response ) ) {
			return null;
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( ! is_array( $body ) || empty( $body['version'] ) ) {
			return null;
		}

		return array(
			'version'      => (string) $body['version'],
			'package'      => ! empty( $body['download_url'] ) ? esc_url_raw( $body['download_url'] ) : '',
			'url'          => ! empty( $body['homepage'] ) ? esc_url_raw( $body['homepage'] ) : '',
			'changelog'    => isset( $body['sections']['changelog'] ) ? wp_kses_post( $body['sections']['changelog'] ) : '',
			'last_updated' => ! empty( $body['last_updated'] ) ? sanitize_text_field( $body['last_updated'] ) : '',
			'tested'       => ! empty( $body['tested'] ) ? sanitize_text_field( $body['tested'] ) : '',
			'requires'     => ! empty( $body['requires'] ) ? sanitize_text_field( $body['requires'] ) : '',
			'requires_php' => ! empty( $body['requires_php'] ) ? sanitize_text_field( $body['requires_php'] ) : '',
		);
	}

	/**
	 * Paneb uuenduse WordPressi uuenduste nimekirja.
	 *
	 * @param mixed $transient Uuenduste transient.
	 * @return mixed
	 */
	public static function inject( $transient ) {
		if ( ! is_object( $transient ) ) {
			return $transient;
		}

		$info = self::remote();
		if ( ! $info ) {
			return $transient;
		}

		$file = self::basename();

		$item = (object) array(
			'id'           => $file,
			'slug'         => self::slug(),
			'plugin'       => $file,
			'new_version'  => $info['version'],
			'url'          => $info['url'],
			'package'      => $info['package'],
			'tested'       => $info['tested'],
			'requires_php' => $info['requires_php'],
			'icons'        => array(),
			'banners'      => array(),
			'compatibility' => new stdClass(),
		);

		if ( version_compare( $info['version'], WKR_VERSION, '>' ) ) {
			$transient->response[ $file ] = $item;
			unset( $transient->no_update[ $file ] );
		} else {
			$transient->no_update[ $file ] = $item;
		}

		return $transient;
	}

	/**
	 * „Vaata üksikasju” aken.
	 *
	 * @param mixed  $result Senine tulemus.
	 * @param string $action Toiming.
	 * @param object $args   Argumendid.
	 * @return mixed
	 */
	public static function details( $result, $action, $args ) {
		if ( 'plugin_information' !== $action || empty( $args->slug ) || self::slug() !== $args->slug ) {
			return $result;
		}

		$info = self::remote();
		if ( ! $info ) {
			return $result;
		}

		$data = get_plugin_data( WKR_FILE, false, false );

		return (object) array(
			'name'          => $data['Name'],
			'slug'          => self::slug(),
			'version'       => $info['version'],
			'author'        => $data['Author'],
			'homepage'      => $info['url'],
			'requires'      => $info['requires'],
			'tested'        => $info['tested'],
			'requires_php'  => $info['requires_php'],
			'last_updated'  => $info['last_updated'],
			'download_link' => $info['package'],
			'sections'      => array(
				'description' => wpautop( $data['Description'] ),
				'changelog'   => $info['changelog'] ? wpautop( $info['changelog'] ) : esc_html__( 'Muudatuste nimekirja ei ole antud.', 'wonom-kampaaniariba' ),
			),
		);
	}

	/**
	 * GitHubi ZIP pakib kausta nimega repo-tag. WordPress ootab õiget kaustanime,
	 * muidu tekib plugin teise nime alla ja vana jääb alles.
	 *
	 * @param string      $source        Lahtipakitud kaust.
	 * @param string      $remote_source Ajutine ülemkaust.
	 * @param WP_Upgrader $upgrader      Paigaldaja.
	 * @param array       $args          Argumendid.
	 * @return string|WP_Error
	 */
	public static function fix_folder( $source, $remote_source, $upgrader, $args = array() ) {
		global $wp_filesystem;

		if ( empty( $args['plugin'] ) || self::basename() !== $args['plugin'] ) {
			return $source;
		}

		if ( ! $wp_filesystem ) {
			return $source;
		}

		$desired = trailingslashit( $remote_source ) . self::slug();

		if ( untrailingslashit( $source ) === untrailingslashit( $desired ) ) {
			return $source;
		}

		if ( $wp_filesystem->move( $source, $desired, true ) ) {
			return trailingslashit( $desired );
		}

		return new WP_Error(
			'wkr_rename_failed',
			__( 'Uuenduse kausta ümbernimetamine ebaõnnestus.', 'wonom-kampaaniariba' )
		);
	}

	/**
	 * Privaatse hoidla puhul lisab allalaadimisele autentimise.
	 *
	 * @param array  $args Päringu argumendid.
	 * @param string $url  URL.
	 * @return array
	 */
	public static function auth_header( $args, $url ) {
		$token = self::token();

		if ( ! $token || 'github' !== self::source() ) {
			return $args;
		}

		if ( strpos( $url, 'api.github.com' ) === false ) {
			return $args;
		}

		$args['headers'] = isset( $args['headers'] ) && is_array( $args['headers'] ) ? $args['headers'] : array();
		$args['headers']['Authorization'] = 'Bearer ' . $token;
		$args['headers']['User-Agent']    = 'wonom-kampaaniariba';

		// Väljalaske faili päring vajab binaarvastust, mitte JSON-i.
		if ( preg_match( '#/releases/assets/\d+#', $url ) ) {
			$args['headers']['Accept'] = 'application/octet-stream';
		}

		return $args;
	}

	/**
	 * Pärast uuendust unustame vahemälu.
	 *
	 * @param WP_Upgrader $upgrader Paigaldaja.
	 * @param array       $data     Andmed.
	 */
	public static function flush( $upgrader, $data ) {
		if ( ! is_array( $data ) || empty( $data['type'] ) || 'plugin' !== $data['type'] ) {
			return;
		}

		delete_transient( self::TRANSIENT );
	}

	/**
	 * Nupp „Kontrolli uuendusi kohe”.
	 */
	public static function manual_check() {
		if ( ! current_user_can( 'update_plugins' ) ) {
			wp_die( esc_html__( 'Puuduvad õigused.', 'wonom-kampaaniariba' ) );
		}

		check_admin_referer( 'wkr_check_update' );

		delete_transient( self::TRANSIENT );
		delete_site_transient( 'update_plugins' );

		$info    = self::remote( true );
		$version = $info ? $info['version'] : '';

		wp_safe_redirect(
			add_query_arg(
				array(
					'page'          => 'wkr-settings',
					'post_type'     => WKR_CPT,
					'wkr_checked'   => 1,
					'wkr_remote_ver' => rawurlencode( $version ),
				),
				admin_url( 'edit.php' )
			)
		);
		exit;
	}
}
