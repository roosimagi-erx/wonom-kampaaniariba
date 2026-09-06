<?php
/**
 * Ühised abifunktsioonid: väljade skeem, fondid, keeled, ajaarvutus.
 *
 * @package Wonom_Kampaaniariba
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Keeled, milles kampaania tekste hoitakse.
 *
 * Lisa oma keeli filtriga: add_filter( 'wkr_langs', ... ).
 *
 * @return array<string,string>
 */
function wkr_langs() {
	return apply_filters(
		'wkr_langs',
		array(
			'et' => __( 'Eesti', 'wonom-kampaaniariba' ),
			'en' => __( 'English', 'wonom-kampaaniariba' ),
		)
	);
}

/**
 * Tekstiväljad, mis on iga keele jaoks eraldi.
 *
 * @return array<string,string>
 */
function wkr_text_fields() {
	return array(
		'l1'           => __( '1. rida — esiletõstetud', 'wonom-kampaaniariba' ),
		'l2'           => __( '2. rida', 'wonom-kampaaniariba' ),
		'l3'           => __( '3. rida — väiksem', 'wonom-kampaaniariba' ),
		'coupon_label' => __( 'Sooduskoodi silt', 'wonom-kampaaniariba' ),
		'link'         => __( 'Link klõpsamisel', 'wonom-kampaaniariba' ),
	);
}

/**
 * Valitavad fondid. 'google' on Google Fonts css2 päring, tühi = ei laadita midagi.
 *
 * @return array<string,array>
 */
function wkr_fonts() {
	return apply_filters(
		'wkr_fonts',
		array(
			'theme'    => array(
				'label'  => __( 'Teema oma font', 'wonom-kampaaniariba' ),
				'stack'  => 'inherit',
				'google' => '',
			),
			'system'   => array(
				'label'  => __( 'Süsteemi sans', 'wonom-kampaaniariba' ),
				'stack'  => '-apple-system,"Segoe UI",Roboto,Helvetica,Arial,sans-serif',
				'google' => '',
			),
			'poppins'  => array(
				'label'  => 'Poppins',
				'stack'  => '"Poppins",system-ui,sans-serif',
				'google' => 'Poppins:wght@400;600',
			),
			'mont'     => array(
				'label'  => 'Montserrat',
				'stack'  => '"Montserrat",system-ui,sans-serif',
				'google' => 'Montserrat:wght@400;700',
			),
			'oswald'   => array(
				'label'  => __( 'Oswald (kitsas)', 'wonom-kampaaniariba' ),
				'stack'  => '"Oswald",Impact,sans-serif',
				'google' => 'Oswald:wght@400;600',
			),
			'playfair' => array(
				'label'  => __( 'Playfair Display (seriif)', 'wonom-kampaaniariba' ),
				'stack'  => '"Playfair Display",Georgia,serif',
				'google' => 'Playfair+Display:wght@400;700',
			),
			'lora'     => array(
				'label'  => __( 'Lora (seriif)', 'wonom-kampaaniariba' ),
				'stack'  => '"Lora",Georgia,serif',
				'google' => 'Lora:wght@400;700',
			),
		)
	);
}

/**
 * Kampaania seaded, mis ei sõltu keelest.
 *
 * @return array<string,array>
 */
function wkr_meta_schema() {
	return array(
		'enabled'      => array(
			'type'    => 'bool',
			'default' => 1,
		),
		'coupon'       => array(
			'type'    => 'text',
			'default' => '',
		),
		'apply_coupon' => array(
			'type'    => 'bool',
			'default' => 1,
		),
		'bg'           => array(
			'type'    => 'color',
			'default' => '#6a5c53',
		),
		'fg'           => array(
			'type'    => 'color',
			'default' => '#f4efe9',
		),
		'hl'           => array(
			'type'    => 'color',
			'default' => '#ffd7c2',
		),
		'font'         => array(
			'type'    => 'key',
			'default' => 'system',
		),
		'size'         => array(
			'type'    => 'float',
			'default' => 13,
		),
		'position'     => array(
			'type'    => 'key',
			'default' => 'both',
		),
		'style'        => array(
			'type'    => 'key',
			'default' => 'full',
		),
		'zindex'       => array(
			'type'    => 'int',
			'default' => (int) get_option( 'wkr_default_zindex', 90 ),
		),
		'dismissible'  => array(
			'type'    => 'bool',
			'default' => 1,
		),
		'countdown'    => array(
			'type'    => 'bool',
			'default' => 0,
		),
		'start_local'  => array(
			'type'    => 'datetime',
			'default' => '',
		),
		'end_local'    => array(
			'type'    => 'datetime',
			'default' => '',
		),
		'tz'           => array(
			'type'    => 'text',
			'default' => '',
		),
		'en_auto'      => array(
			'type'    => 'bool',
			'default' => 0,
		),

		// WooCommerce'i kupong.
		'wc_mode'       => array(
			'type'    => 'key',
			'default' => 'none',
		),
		'wc_type'       => array(
			'type'    => 'key',
			'default' => 'percent',
		),
		'wc_amount'     => array(
			'type'    => 'float',
			'default' => 20,
		),
		'wc_min'        => array(
			'type'    => 'float',
			'default' => 0,
		),
		'wc_limit'      => array(
			'type'    => 'int',
			'default' => 0,
		),
		'wc_limit_user' => array(
			'type'    => 'int',
			'default' => 0,
		),
		'wc_individual' => array(
			'type'    => 'bool',
			'default' => 0,
		),
		'wc_free_ship'  => array(
			'type'    => 'bool',
			'default' => 0,
		),
		'wc_takeover'   => array(
			'type'    => 'bool',
			'default' => 0,
		),
	);
}

/**
 * Seade lugemine vaikeväärtusega.
 *
 * @param int    $post_id Kampaania ID.
 * @param string $key     Võti ilma _wkr_ eesliiteta.
 * @return mixed
 */
function wkr_get( $post_id, $key ) {
	$schema = wkr_meta_schema();
	$value  = get_post_meta( $post_id, '_wkr_' . $key, true );

	if ( '' === $value || null === $value ) {
		return isset( $schema[ $key ] ) ? $schema[ $key ]['default'] : '';
	}

	if ( isset( $schema[ $key ] ) ) {
		switch ( $schema[ $key ]['type'] ) {
			case 'bool':
				return (int) $value ? 1 : 0;
			case 'int':
				return (int) $value;
			case 'float':
				return (float) $value;
		}
	}

	return $value;
}

/**
 * Keelepõhine tekst. Tühi väli langeb tagasi eesti keelele, et riba ei jääks tühjaks.
 *
 * @param int         $post_id Kampaania ID.
 * @param string      $key     l1 | l2 | l3 | coupon_label | link.
 * @param string|null $lang    Keelekood.
 * @return string
 */
function wkr_text( $post_id, $key, $lang = null ) {
	$lang  = $lang ? $lang : wkr_current_lang();
	$value = (string) get_post_meta( $post_id, '_wkr_' . $lang . '_' . $key, true );

	if ( '' === $value && 'et' !== $lang ) {
		$value = (string) get_post_meta( $post_id, '_wkr_et_' . $key, true );
	}

	return $value;
}

/**
 * Jooksev keel. Tunneb ära Polylangi ja WPML-i, muidu vaatab saidi lokaati.
 *
 * @return string
 */
function wkr_current_lang() {
	$lang = '';

	if ( function_exists( 'pll_current_language' ) ) {
		$lang = (string) pll_current_language( 'slug' );
	} elseif ( defined( 'ICL_LANGUAGE_CODE' ) ) {
		$lang = (string) ICL_LANGUAGE_CODE;
	}

	if ( '' === $lang ) {
		$lang = substr( (string) get_locale(), 0, 2 );
	}

	$langs = wkr_langs();
	if ( ! isset( $langs[ $lang ] ) ) {
		$lang = 'et';
	}

	return apply_filters( 'wkr_current_lang', $lang );
}

/**
 * Saidi vaikeajavöönd IANA kujul.
 *
 * @return string
 */
function wkr_default_tz() {
	$tz = wp_timezone_string();

	// wp_timezone_string() võib tagastada nihke kujul "+03:00" — see pole IANA nimi.
	if ( ! $tz || '+' === $tz[0] || '-' === $tz[0] ) {
		$tz = 'Europe/Tallinn';
	}

	return $tz;
}

/**
 * Kohalik seinakell -> Unixi ajatempel. Suve- ja talveaeg tuleb DateTimeZone'ist.
 *
 * @param string $local "Y-m-d H:i".
 * @param string $tz    IANA ajavöönd.
 * @return int Unixi ajatempel või 0.
 */
function wkr_local_to_ts( $local, $tz = '' ) {
	$local = trim( (string) $local );
	if ( '' === $local ) {
		return 0;
	}

	$tz = $tz ? $tz : wkr_default_tz();

	try {
		$zone = new DateTimeZone( $tz );
		$date = DateTime::createFromFormat( 'Y-m-d H:i', $local, $zone );
		if ( ! $date ) {
			return 0;
		}
		$date->setTime( (int) $date->format( 'H' ), (int) $date->format( 'i' ), 0 );
		return (int) $date->getTimestamp();
	} catch ( Exception $e ) {
		return 0;
	}
}

/**
 * Ajatempel -> kohalik seinakell.
 *
 * @param int    $ts Unixi ajatempel.
 * @param string $tz IANA ajavöönd.
 * @return string "Y-m-d H:i" või tühi.
 */
function wkr_ts_to_local( $ts, $tz = '' ) {
	$ts = (int) $ts;
	if ( ! $ts ) {
		return '';
	}

	$tz = $tz ? $tz : wkr_default_tz();

	try {
		$date = new DateTime( '@' . $ts );
		$date->setTimezone( new DateTimeZone( $tz ) );
		return $date->format( 'Y-m-d H:i' );
	} catch ( Exception $e ) {
		return '';
	}
}

/**
 * "2026-09-04 00:00" -> "04.09.2026 00:00" (see kuju on administraatorile nähtav).
 *
 * @param string $local Kohalik aeg.
 * @return string
 */
function wkr_pretty( $local ) {
	$local = trim( (string) $local );
	if ( ! preg_match( '/^(\d{4})-(\d{2})-(\d{2})[ T](\d{2}):(\d{2})/', $local, $m ) ) {
		return $local;
	}
	return $m[3] . '.' . $m[2] . '.' . $m[1] . ' ' . $m[4] . ':' . $m[5];
}

/**
 * Administraatori sisestatud kuupäev -> "Y-m-d H:i".
 * Lubab nii "4.9.2026 9:30" kui ka ISO kuju, et vorm töötaks ka ilma JS-ita.
 *
 * @param string $txt Sisestatud tekst.
 * @return string Tühi, kui ei õnnestunud lugeda.
 */
function wkr_parse_display( $txt ) {
	$txt = trim( (string) $txt );
	if ( '' === $txt ) {
		return '';
	}

	if ( preg_match( '#^(\d{1,2})[./-](\d{1,2})[./-](\d{4})(?:[\s,]+(\d{1,2})[:.](\d{1,2}))?$#', $txt, $m ) ) {
		return sprintf(
			'%04d-%02d-%02d %02d:%02d',
			(int) $m[3],
			(int) $m[2],
			(int) $m[1],
			isset( $m[4] ) ? (int) $m[4] : 0,
			isset( $m[5] ) ? (int) $m[5] : 0
		);
	}

	if ( preg_match( '/^(\d{4})-(\d{2})-(\d{2})[ T](\d{2}):(\d{2})/', $txt, $m ) ) {
		return $m[1] . '-' . $m[2] . '-' . $m[3] . ' ' . $m[4] . ':' . $m[5];
	}

	return '';
}

/**
 * Kampaania olek antud hetkel.
 *
 * @param int $post_id Kampaania ID.
 * @param int $now     Ajatempel, vaikimisi praegu.
 * @return string live | upcoming | ended | off
 */
function wkr_status( $post_id, $now = 0 ) {
	$now = $now ? (int) $now : time();

	if ( ! wkr_get( $post_id, 'enabled' ) || 'publish' !== get_post_status( $post_id ) ) {
		return 'off';
	}

	$start = (int) get_post_meta( $post_id, '_wkr_start_utc', true );
	$end   = (int) get_post_meta( $post_id, '_wkr_end_utc', true );

	if ( ! $start || ! $end ) {
		return 'off';
	}
	if ( $now < $start ) {
		return 'upcoming';
	}
	if ( $now > $end ) {
		return 'ended';
	}

	return 'live';
}

/**
 * Parasjagu eetris olevad kampaaniad, tähtsuselt esimene ees.
 *
 * @param int $now Ajatempel.
 * @return WP_Post[]
 */
function wkr_active_campaigns( $now = 0 ) {
	static $cache = array();

	$now = $now ? (int) $now : time();

	// Sama päringut küsitakse ühe lehe jooksul mitu korda (stiilid, päis, jalus).
	$bucket = (int) floor( $now / 30 );
	if ( isset( $cache[ $bucket ] ) ) {
		return $cache[ $bucket ];
	}

	$query = new WP_Query(
		array(
			'post_type'              => WKR_CPT,
			'post_status'            => 'publish',
			'posts_per_page'         => 20,
			'orderby'                => array(
				'menu_order' => 'ASC',
				'date'       => 'DESC',
			),
			'no_found_rows'          => true,
			'update_post_term_cache' => false,
			'meta_query'             => array(
				'relation' => 'AND',
				array(
					'key'   => '_wkr_enabled',
					'value' => '1',
				),
				array(
					'key'     => '_wkr_start_utc',
					'value'   => $now,
					'compare' => '<=',
					'type'    => 'NUMERIC',
				),
				array(
					'key'     => '_wkr_end_utc',
					'value'   => $now,
					'compare' => '>=',
					'type'    => 'NUMERIC',
				),
			),
		)
	);

	$cache[ $bucket ] = $query->posts;

	return $cache[ $bucket ];
}
