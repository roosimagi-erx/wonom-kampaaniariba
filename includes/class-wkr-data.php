<?php
/**
 * Riba andmefail.
 *
 * Riba ei kirjutata enam lehe HTML-i sisse. Kõik praegused ja tulevased
 * kampaaniad on ühes staatilises failis (üks fail keele kohta) ja lehel olev
 * väike skript loeb riba sealt. Kui kampaania muutub, kirjutatakse üle ainult
 * see fail — ühtegi lehte ei ole vaja vahemälust tühjendada, ükskõik kui kaua
 * leht vahemälus seisab.
 *
 * Failis on iga kampaania kohta algus- ja lõpuaeg ning valmis HTML. Millist
 * kampaaniat parajasti näidata, otsustab brauser kellaaja järgi, nii et riba
 * vahetub täpselt õigel sekundil ka siis, kui ükski serveri töö sel hetkel
 * ei käivitu.
 *
 * Faili laiend on .js, kuigi sisu on puhas JSON: staatiliste failide
 * vahemälud (CDN) tunnevad .js laiendit, .json laiendit tihti mitte.
 *
 * @package Wonom_Kampaaniariba
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Koostab ja kirjutab riba andmefaili.
 */
class WKR_Data {

	/**
	 * Kaust uploads-kausta all.
	 */
	const DIR = 'wkr';

	/**
	 * Andmestruktuuri versioon. Tõsta ainult siis, kui vana brauseriskript
	 * uut faili enam lugeda ei oskaks.
	 */
	const SCHEMA = 1;

	/**
	 * Valik: viimati kirjutatud failide sisu räsid keele kaupa.
	 */
	const HASH_OPTION = 'wkr_data_hash';

	/**
	 * Valik: viimase kirjutamise info seadete lehe jaoks.
	 */
	const INFO_OPTION = 'wkr_data_info';

	/**
	 * Kui kaugele ette kampaaniaid faili pannakse (sekundites).
	 */
	const HORIZON = 60 * DAY_IN_SECONDS;

	/**
	 * Mitu kampaaniat kõige rohkem faili läheb.
	 */
	const LIMIT = 20;

	/**
	 * Koht, kuhu HTML-is läheb sooduskood. Koodi ennast HTML-i ei kirjutata:
	 * see on failis eraldi ja kodeeritult, et tulevase kampaania kood ei
	 * oleks lihtsa tekstiotsinguga leitav.
	 */
	const CODE_TOKEN = 'WKRCODE7X';

	/**
	 * Haagid.
	 */
	public static function init() {
		// Pärast plugina enda salvestust (prioriteet 10), et meta oleks juba uus.
		add_action( 'save_post_' . WKR_CPT, array( __CLASS__, 'rebuild' ), 99, 0 );
		add_action( 'trashed_post', array( __CLASS__, 'on_post_change' ) );
		add_action( 'untrashed_post', array( __CLASS__, 'on_post_change' ) );
		add_action( 'after_delete_post', array( __CLASS__, 'on_post_delete' ), 10, 2 );

		add_action( 'update_option_wkr_body_padding', array( __CLASS__, 'rebuild' ), 10, 0 );
		add_action( 'add_option_wkr_body_padding', array( __CLASS__, 'rebuild' ), 10, 0 );

		// Kord tunnis: lõppenud kampaaniad kukuvad failist välja ja kadunud
		// fail tekib uuesti. Kerge töö — üks päring ja paar faili.
		add_action( WKR_Cache::HEARTBEAT_HOOK, array( __CLASS__, 'rebuild' ), 20, 0 );

		add_action( 'admin_init', array( __CLASS__, 'ensure' ), 30 );
		add_action( 'admin_post_wkr_rebuild', array( __CLASS__, 'handle_manual' ) );
	}

	/**
	 * Kampaania prügikasti või tagasi.
	 *
	 * @param int $post_id Postituse ID.
	 */
	public static function on_post_change( $post_id ) {
		if ( WKR_CPT === get_post_type( $post_id ) ) {
			self::rebuild();
		}
	}

	/**
	 * Kampaania kustutati jäädavalt.
	 *
	 * @param int     $post_id Postituse ID.
	 * @param WP_Post $post    Kustutatud postitus.
	 */
	public static function on_post_delete( $post_id, $post = null ) {
		if ( $post && WKR_CPT === $post->post_type ) {
			self::rebuild();
		}
	}

	/**
	 * Kaust, kuhu failid kirjutatakse, ja selle aadress.
	 *
	 * @return array{dir:string,url:string}|null
	 */
	private static function location() {
		$uploads = wp_upload_dir( null, false );

		if ( ! empty( $uploads['error'] ) || empty( $uploads['basedir'] ) || empty( $uploads['baseurl'] ) ) {
			return null;
		}

		return array(
			'dir' => trailingslashit( $uploads['basedir'] ) . self::DIR,
			'url' => set_url_scheme( trailingslashit( $uploads['baseurl'] ) . self::DIR ),
		);
	}

	/**
	 * Keelekood failinime jaoks.
	 *
	 * @param string $lang Keel.
	 * @return string
	 */
	private static function slug( $lang ) {
		$lang = strtolower( preg_replace( '/[^A-Za-z0-9_-]/', '', (string) $lang ) );
		return '' === $lang ? 'et' : $lang;
	}

	/**
	 * Andmefaili asukoht kettal.
	 *
	 * @param string $lang Keel.
	 * @return string Tühi, kui uploads-kausta ei ole.
	 */
	public static function path( $lang ) {
		$where = self::location();
		return $where ? $where['dir'] . '/campaigns-' . self::slug( $lang ) . '.js' : '';
	}

	/**
	 * Andmefaili aadress.
	 *
	 * @param string $lang Keel.
	 * @return string Tühi, kui uploads-kausta ei ole.
	 */
	public static function url( $lang ) {
		$where = self::location();
		return $where ? $where['url'] . '/campaigns-' . self::slug( $lang ) . '.js' : '';
	}

	/**
	 * Kampaaniad, mis ei ole veel lõppenud: eetris olevad ja tulevased.
	 *
	 * Järjekord on sama, mille järgi riba valitakse — tähtsam ees.
	 *
	 * @return WP_Post[]
	 */
	private static function campaigns() {
		$now = time();

		return get_posts(
			array(
				'post_type'        => WKR_CPT,
				'post_status'      => 'publish',
				'numberposts'      => self::LIMIT,
				'orderby'          => array(
					'menu_order' => 'ASC',
					'date'       => 'DESC',
				),
				'suppress_filters' => true,
				// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
				'meta_query'       => array(
					'relation' => 'AND',
					array(
						'key'   => '_wkr_enabled',
						'value' => '1',
					),
					array(
						'key'     => '_wkr_end_utc',
						'value'   => $now,
						'compare' => '>',
						'type'    => 'NUMERIC',
					),
					array(
						'key'     => '_wkr_start_utc',
						'value'   => $now + self::HORIZON,
						'compare' => '<',
						'type'    => 'NUMERIC',
					),
				),
			)
		);
	}

	/**
	 * Sooduskood kodeeritult. Brauser pöörab selle tagasi alles riba joonistades.
	 *
	 * @param string $code Kood.
	 * @return string
	 */
	private static function encode_code( $code ) {
		$code = (string) $code;
		return '' === $code ? '' : base64_encode( strrev( rawurlencode( $code ) ) ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
	}

	/**
	 * Asendab riba HTML-is sooduskoodi kohatäitega.
	 *
	 * Kood võib HTML-is olla mitmes kohas: nupul, lingi küljes ja ka kampaania
	 * pealkirjas, kui kampaania on nimetatud koodi järgi. Asendatakse kõik
	 * kohad. Brauser paneb kohatäite asemele täpselt sama teksti tagasi, nii et
	 * riba välimus sellest ei muutu.
	 *
	 * @param string $html Riba HTML.
	 * @param string $code Sooduskood.
	 * @return string
	 */
	private static function mask( $html, $code ) {
		$code = (string) $code;

		if ( '' === $code ) {
			return $html;
		}

		return str_replace( esc_html( $code ), self::CODE_TOKEN, $html );
	}

	/**
	 * Ühe keele andmed.
	 *
	 * @param string $lang Keel.
	 * @return array
	 */
	public static function build( $lang ) {
		$previous                     = isset( $GLOBALS['wkr_lang_override'] ) ? $GLOBALS['wkr_lang_override'] : null;
		$GLOBALS['wkr_lang_override'] = $lang;

		$fonts = wkr_fonts();
		$items = array();

		foreach ( self::campaigns() as $post ) {
			$start = (int) get_post_meta( $post->ID, '_wkr_start_utc', true );
			$end   = (int) get_post_meta( $post->ID, '_wkr_end_utc', true );

			if ( ! $start || ! $end || $end <= $start ) {
				continue;
			}

			$position = wkr_get( $post->ID, 'position' );
			$places   = 'both' === $position ? array( 'top', 'bottom' ) : array( 'bottom' === $position ? 'bottom' : 'top' );

			$code   = (string) wkr_get( $post->ID, 'coupon' );
			$font   = wkr_get( $post->ID, 'font' );
			$google = isset( $fonts[ $font ] ) ? (string) $fonts[ $font ]['google'] : '';

			$item = array(
				'id'   => (int) $post->ID,
				's'    => $start,
				'e'    => $end,
				'k'    => self::encode_code( $code ),
				'font' => $google ? 'https://fonts.googleapis.com/css2?family=' . $google . '&display=swap' : '',
			);

			foreach ( $places as $place ) {
				$item[ $place ] = self::mask( WKR_Render::markup( $post, $place ), $code );
			}

			// Sisu sõrmejälg: muudetud kampaania joonistatakse lahtisel lehel uuesti.
			$item['r'] = substr( md5( wp_json_encode( $item ) ), 0, 8 );

			$items[] = $item;
		}

		$data = array(
			'v'    => self::SCHEMA,
			'ver'  => WKR_VERSION,
			'js'   => WKR_URL . 'assets/front.js?ver=' . WKR_VERSION,
			'css'  => WKR_URL . 'assets/front.css?ver=' . WKR_VERSION,
			'pad'  => (int) get_option( 'wkr_body_padding', 1 ),
			'i18n' => array(
				'copied' => 'en' === $lang ? 'Copied!' : 'Kopeeritud!',
				'ends'   => 'en' === $lang ? 'ends in' : 'lõpeb',
				'days'   => 'en' === $lang ? 'd' : 'p',
			),
			'c'    => $items,
		);

		if ( null === $previous ) {
			unset( $GLOBALS['wkr_lang_override'] );
		} else {
			$GLOBALS['wkr_lang_override'] = $previous;
		}

		/**
		 * Riba andmefaili sisu enne kirjutamist.
		 *
		 * @param array  $data Andmed.
		 * @param string $lang Keel.
		 */
		return (array) apply_filters( 'wkr_data', $data, $lang );
	}

	/**
	 * Kirjutab kõigi keelte failid uuesti, kui sisu on muutunud.
	 *
	 * @param bool $force Kirjuta ka siis, kui sisu ei muutunud.
	 * @return bool Kas kõik failid on kettal olemas ja värsked.
	 */
	public static function rebuild( $force = false ) {
		$where = self::location();

		if ( ! $where ) {
			return false;
		}

		if ( ! wp_mkdir_p( $where['dir'] ) ) {
			self::remember( false, 0 );
			return false;
		}

		$hashes = get_option( self::HASH_OPTION, array() );
		$hashes = is_array( $hashes ) ? $hashes : array();
		$ok     = true;
		$count  = 0;
		$wrote  = false;

		foreach ( array_keys( wkr_langs() ) as $lang ) {
			$data  = self::build( $lang );
			$count = count( $data['c'] );
			$hash  = md5( wp_json_encode( $data ) );
			$path  = self::path( $lang );

			if ( ! $force && isset( $hashes[ $lang ] ) && $hashes[ $lang ] === $hash && is_readable( $path ) ) {
				continue;
			}

			// Kirjutamise aeg ei kuulu räsi sisse — muidu oleks iga kord „muutus”.
			$data['t'] = time();

			if ( self::write( $path, wp_json_encode( $data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) ) ) {
				$hashes[ $lang ] = $hash;
				$wrote           = true;
			} else {
				$ok = false;
				unset( $hashes[ $lang ] );
			}
		}

		if ( $wrote || ! $ok ) {
			update_option( self::HASH_OPTION, $hashes, false );
			self::remember( $ok, $count );
		}

		return $ok;
	}

	/**
	 * Kirjutab failidesse tühja kampaaniate nimekirja.
	 *
	 * Vahemälus olevatel lehtedel on laadija alles ka siis, kui plugin on välja
	 * lülitatud. Tühi nimekiri võtab riba kõigilt lehtedelt maha; ilma selleta
	 * jääks viimane riba poodi rippuma.
	 */
	public static function clear() {
		$where = self::location();

		if ( ! $where || ! is_dir( $where['dir'] ) ) {
			return;
		}

		$json = wp_json_encode(
			array(
				'v' => self::SCHEMA,
				't' => time(),
				'c' => array(),
			)
		);

		foreach ( array_keys( wkr_langs() ) as $lang ) {
			self::write( self::path( $lang ), $json );
		}

		delete_option( self::HASH_OPTION );
		delete_option( self::INFO_OPTION );
	}

	/**
	 * Kirjutab faili nii, et lugeja ei näe kunagi poolikut sisu: kõigepealt
	 * ajutine fail, siis ümbernimetamine.
	 *
	 * @param string $path Sihtfail.
	 * @param string $json Sisu.
	 * @return bool
	 */
	private static function write( $path, $json ) {
		if ( '' === $path || ! is_string( $json ) || '' === $json ) {
			return false;
		}

		$tmp = $path . '.' . wp_generate_password( 8, false ) . '.tmp';

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
		if ( false === @file_put_contents( $tmp, $json, LOCK_EX ) ) {
			return false;
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.rename_rename
		if ( @rename( $tmp, $path ) ) {
			return true;
		}

		// Mõni failisüsteem ei luba olemasolevat faili ümbernimetamisega asendada.
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
		$done = false !== @file_put_contents( $path, $json, LOCK_EX );
		wp_delete_file( $tmp );

		return $done;
	}

	/**
	 * Jätab viimase kirjutamise tulemuse seadete lehe jaoks meelde.
	 *
	 * @param bool $ok    Kas õnnestus.
	 * @param int  $count Kampaaniate arv failis.
	 */
	private static function remember( $ok, $count ) {
		update_option(
			self::INFO_OPTION,
			array(
				'time'  => time(),
				'ok'    => (bool) $ok,
				'count' => (int) $count,
			),
			false
		);
	}

	/**
	 * Seis seadete lehe jaoks.
	 *
	 * @return array {
	 *     @type bool   $ok     Kas viimane kirjutamine õnnestus ja failid on olemas.
	 *     @type int    $time   Viimase kirjutamise aeg.
	 *     @type int    $count  Kampaaniate arv failis.
	 *     @type array  $files  Keel => aadress.
	 *     @type string $dir    Kaust kettal.
	 * }
	 */
	public static function status() {
		$info  = get_option( self::INFO_OPTION, array() );
		$info  = is_array( $info ) ? $info : array();
		$where = self::location();
		$files = array();
		$all   = (bool) $where;

		foreach ( array_keys( wkr_langs() ) as $lang ) {
			$files[ $lang ] = self::url( $lang );

			if ( ! is_readable( self::path( $lang ) ) ) {
				$all = false;
			}
		}

		return array(
			'ok'    => $all && ! empty( $info['ok'] ),
			'time'  => isset( $info['time'] ) ? (int) $info['time'] : 0,
			'count' => isset( $info['count'] ) ? (int) $info['count'] : 0,
			'files' => $files,
			'dir'   => $where ? $where['dir'] : '',
		);
	}

	/**
	 * Kui fail on kadunud (näiteks uploads-kaust taastati varukoopiast),
	 * kirjutab administraatori lehe avamine selle uuesti.
	 */
	public static function ensure() {
		if ( ! current_user_can( 'edit_posts' ) ) {
			return;
		}

		foreach ( array_keys( wkr_langs() ) as $lang ) {
			if ( ! is_readable( self::path( $lang ) ) ) {
				self::rebuild( true );
				return;
			}
		}
	}

	/**
	 * Nupp „Kirjuta andmefail uuesti”.
	 */
	public static function handle_manual() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Puuduvad õigused.', 'wonom-kampaaniariba' ) );
		}

		check_admin_referer( 'wkr_rebuild' );

		$ok = self::rebuild( true );

		wp_safe_redirect(
			add_query_arg(
				array(
					'post_type'   => WKR_CPT,
					'page'        => 'wkr-settings',
					'wkr_rebuilt' => $ok ? 1 : 0,
				),
				admin_url( 'edit.php' )
			)
		);
		exit;
	}
}
