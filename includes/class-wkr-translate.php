<?php
/**
 * Automaatne tõlge. DeepL, kui võti on antud; muidu sisseehitatud sõnastik.
 *
 * @package Wonom_Kampaaniariba
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Tõlkeabi administraatorile.
 */
class WKR_Translate {

	/**
	 * Haagid.
	 */
	public static function init() {
		add_action( 'wp_ajax_wkr_translate', array( __CLASS__, 'ajax' ) );
	}

	/**
	 * AJAX: tõlgi eesti keelest sihtkeelde.
	 */
	public static function ajax() {
		check_ajax_referer( 'wkr_translate', 'nonce' );

		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_send_json_error( array( 'message' => __( 'Puuduvad õigused.', 'wonom-kampaaniariba' ) ), 403 );
		}

		$source = isset( $_POST['source'] ) ? (array) wp_unslash( $_POST['source'] ) : array();
		$target = isset( $_POST['target'] ) ? sanitize_key( wp_unslash( $_POST['target'] ) ) : 'en';

		$clean = array();
		foreach ( wkr_text_fields() as $key => $label ) {
			$clean[ $key ] = isset( $source[ $key ] ) ? sanitize_text_field( $source[ $key ] ) : '';
		}

		$result = self::translate( $clean, $target );

		wp_send_json_success( $result );
	}

	/**
	 * Tõlgib välja väljade kaupa.
	 *
	 * @param array  $source Eestikeelsed väljad.
	 * @param string $target Sihtkeel.
	 * @return array {fields: array, engine: string}
	 */
	public static function translate( $source, $target = 'en' ) {
		/**
		 * Anna oma tõlketeenus. Tagasta array( 'fields' => array(), 'engine' => 'nimi' ) või null.
		 *
		 * @param null|array $result Tulemus.
		 * @param array      $source Lähtetekstid.
		 * @param string     $target Sihtkeel.
		 */
		$custom = apply_filters( 'wkr_autotranslate', null, $source, $target );
		if ( is_array( $custom ) && isset( $custom['fields'] ) ) {
			return $custom;
		}

		$key = trim( (string) get_option( 'wkr_deepl_key', '' ) );
		if ( $key ) {
			$deepl = self::deepl( $source, $target, $key );
			if ( $deepl ) {
				return array(
					'fields' => $deepl,
					'engine' => 'DeepL',
				);
			}
		}

		return array(
			'fields' => self::dictionary( $source ),
			'engine' => 'dictionary',
		);
	}

	/**
	 * DeepL API. Tagastab null, kui midagi ebaõnnestus.
	 *
	 * @param array  $source Lähtetekstid.
	 * @param string $target Sihtkeel.
	 * @param string $key    API võti.
	 * @return array|null
	 */
	private static function deepl( $source, $target, $key ) {
		$texts = array();
		$map   = array();

		foreach ( $source as $field => $text ) {
			if ( 'link' === $field || '' === trim( $text ) ) {
				continue;
			}
			$map[]   = $field;
			$texts[] = $text;
		}

		if ( ! $texts ) {
			return array();
		}

		$endpoint = ( strpos( $key, ':fx' ) !== false )
			? 'https://api-free.deepl.com/v2/translate'
			: 'https://api.deepl.com/v2/translate';

		$response = wp_remote_post(
			$endpoint,
			array(
				'timeout' => 15,
				'headers' => array(
					'Authorization' => 'DeepL-Auth-Key ' . $key,
					'Content-Type'  => 'application/json',
				),
				'body'    => wp_json_encode(
					array(
						'text'        => $texts,
						'source_lang' => 'ET',
						'target_lang' => strtoupper( $target ),
					)
				),
			)
		);

		if ( is_wp_error( $response ) || 200 !== (int) wp_remote_retrieve_response_code( $response ) ) {
			return null;
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( ! isset( $body['translations'] ) || ! is_array( $body['translations'] ) ) {
			return null;
		}

		$out = array();
		foreach ( $map as $i => $field ) {
			if ( isset( $body['translations'][ $i ]['text'] ) ) {
				$out[ $field ] = sanitize_text_field( $body['translations'][ $i ]['text'] );
			}
		}

		// Linki ei tõlgi keeleteenus — pakume tavapärase keeleprefiksi.
		if ( ! empty( $source['link'] ) ) {
			$out['link'] = self::guess_link( $source['link'], $target );
		}

		return $out;
	}

	/**
	 * Lihtne sõnastik, kui päris tõlketeenust pole. Tulemus on mustand.
	 *
	 * @param array $source Lähtetekstid.
	 * @return array
	 */
	private static function dictionary( $source ) {
		$dict = array(
			'sooduskoodiga'            => 'with coupon code',
			'kupongikoodiga'           => 'with coupon',
			'sooduskood'               => 'coupon code',
			'kupongikood'              => 'coupon code',
			'valitud linased kleidid'  => 'selected linen dresses',
			'suvelõpu tühjendusmüük'   => 'end-of-summer sale',
			'tühjendusmüük'            => 'clearance sale',
			'uus sügiskollektsioon'    => 'new autumn collection',
			'sügiskollektsioon'        => 'autumn collection',
			'kevadkollektsioon'        => 'spring collection',
			'talvekollektsioon'        => 'winter collection',
			'kõigile tellimustele'     => 'on all orders',
			'kuni laoseisu lõppemiseni' => 'while stocks last',
			'tasuta tagastus'          => 'free returns',
			'tasuta tarne'             => 'free delivery',
			'kõik tooted'              => 'all products',
			'laoseis lõppes'           => 'sold out',
			'kehtib alates'            => 'valid from',
			'ainult täna'              => 'today only',
			'viimane päev'             => 'last day',
			'ostukorvist'              => 'cart value',
			'ostust'                   => 'purchase',
			'koodiga'                  => 'with code',
			'kehtib'                   => 'valid',
			'alates'                   => 'from',
			'kleidid'                  => 'dresses',
			'päeva'                    => 'days',
		);

		$out = array();

		foreach ( $source as $field => $text ) {
			if ( 'link' === $field ) {
				$out[ $field ] = self::guess_link( $text, 'en' );
				continue;
			}

			$value = (string) $text;
			foreach ( $dict as $et => $en ) {
				$value = preg_replace_callback(
					'/' . preg_quote( $et, '/' ) . '/iu',
					function ( $m ) use ( $en ) {
						$first = mb_substr( $m[0], 0, 1 );
						return ( mb_strtoupper( $first ) === $first ) ? ucfirst( $en ) : $en;
					},
					$value
				);
			}
			$out[ $field ] = $value;
		}

		if ( ! empty( $source['coupon_label'] ) ) {
			$out['coupon_label'] = 'Coupon code';
		}

		return $out;
	}

	/**
	 * Pakub ingliskeelse lehe tee. Polylangi ja WPML-i puhul on see tavaliselt /en/ prefiks.
	 *
	 * @param string $link   Algne link.
	 * @param string $target Sihtkeel.
	 * @return string
	 */
	private static function guess_link( $link, $target ) {
		$link = trim( (string) $link );
		if ( '' === $link || 'et' === $target ) {
			return $link;
		}

		if ( preg_match( '#^https?://#i', $link ) ) {
			return $link;
		}

		$link = '/' . ltrim( $link, '/' );
		if ( 0 === strpos( $link, '/' . $target . '/' ) ) {
			return $link;
		}

		return '/' . $target . $link;
	}
}
