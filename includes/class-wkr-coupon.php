<?php
/**
 * WooCommerce'i kupongi loomine ja sünkroonimine kampaaniaga.
 *
 * @package Wonom_Kampaaniariba
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Hoiab kampaania ja WooCommerce'i kupongi kooskõlas.
 *
 * Plugin puudutab ainult neid kuponge, mille ta ise lõi või mille kasutaja
 * on sõnaselgelt üle andnud. Käsitsi tehtud kuponge ei muudeta kunagi vaikimisi.
 */
class WKR_Coupon {

	/**
	 * Kupongi külge jääv märge, kes seda haldab.
	 */
	const OWNER_META = '_wkr_owner_campaign';

	/**
	 * Haagid.
	 */
	public static function init() {
		add_filter( 'woocommerce_coupon_is_valid', array( __CLASS__, 'validate' ), 10, 2 );
		add_action( 'trashed_post', array( __CLASS__, 'on_trash' ) );
		add_action( 'untrashed_post', array( __CLASS__, 'on_untrash' ) );
		add_action( 'admin_notices', array( __CLASS__, 'notice' ) );
	}

	/**
	 * Kas WooCommerce on üldse olemas.
	 *
	 * @return bool
	 */
	public static function woo_active() {
		return class_exists( 'WC_Coupon' ) && function_exists( 'wc_get_coupon_id_by_code' );
	}

	/**
	 * Normaliseerib koodi WooCommerce'i moodi (Woo hoiab koode väiketähtedega).
	 *
	 * @param string $code Kood.
	 * @return string
	 */
	public static function format_code( $code ) {
		$code = trim( (string) $code );
		if ( '' === $code ) {
			return '';
		}
		return function_exists( 'wc_format_coupon_code' ) ? wc_format_coupon_code( $code ) : strtolower( $code );
	}

	/**
	 * Selle kampaania hallatava kupongi ID (0, kui pole).
	 *
	 * @param int $campaign_id Kampaania ID.
	 * @return int
	 */
	public static function owned_id( $campaign_id ) {
		$id = (int) get_post_meta( $campaign_id, '_wkr_coupon_id', true );

		if ( ! $id || 'shop_coupon' !== get_post_type( $id ) ) {
			return 0;
		}

		if ( (int) get_post_meta( $id, self::OWNER_META, true ) !== (int) $campaign_id ) {
			return 0;
		}

		return $id;
	}

	/**
	 * Loob või uuendab kupongi kampaania seadete järgi.
	 *
	 * @param int $campaign_id Kampaania ID.
	 * @return array {status: ok|skipped|conflict|error, message: string, coupon_id: int}
	 */
	public static function sync( $campaign_id ) {
		$mode = wkr_get( $campaign_id, 'wc_mode' );
		$code = self::format_code( wkr_get( $campaign_id, 'coupon' ) );

		if ( 'manage' !== $mode ) {
			return array(
				'status'    => 'skipped',
				'message'   => '',
				'coupon_id' => 0,
			);
		}

		if ( ! self::woo_active() ) {
			return array(
				'status'    => 'error',
				'message'   => __( 'WooCommerce ei ole aktiivne, kupongi ei saanud luua.', 'wonom-kampaaniariba' ),
				'coupon_id' => 0,
			);
		}

		if ( '' === $code ) {
			return array(
				'status'    => 'error',
				'message'   => __( 'Sooduskood on tühi — WooCommerce kupongi ei loodud.', 'wonom-kampaaniariba' ),
				'coupon_id' => 0,
			);
		}

		$owned_id    = self::owned_id( $campaign_id );
		$existing_id = (int) wc_get_coupon_id_by_code( $code );

		// Sama koodiga kupong on juba olemas ja see pole meie oma.
		if ( $existing_id && $existing_id !== $owned_id ) {
			$owner = (int) get_post_meta( $existing_id, self::OWNER_META, true );

			if ( $owner && $owner !== (int) $campaign_id ) {
				return array(
					'status'    => 'conflict',
					'message'   => sprintf(
						/* translators: 1: coupon code, 2: campaign title */
						__( 'Koodi „%1$s” haldab juba kampaania „%2$s”. Kupongi ei muudetud.', 'wonom-kampaaniariba' ),
						$code,
						get_the_title( $owner )
					),
					'coupon_id' => $existing_id,
				);
			}

			if ( ! $owner && ! wkr_get( $campaign_id, 'wc_takeover' ) ) {
				return array(
					'status'    => 'conflict',
					'message'   => sprintf(
						/* translators: %s: coupon code */
						__( 'WooCommerce’is on juba kupong „%s”, mille on teinud keegi käsitsi. Kampaaniariba ei muutnud seda. Kui tahad, et plugin hakkaks seda haldama, märgi „Võta olemasolev kupong üle”.', 'wonom-kampaaniariba' ),
						$code
					),
					'coupon_id' => $existing_id,
				);
			}

			// Kasutaja lubas üle võtta.
			$owned_id = $existing_id;
		}

		$coupon = $owned_id ? new WC_Coupon( $owned_id ) : new WC_Coupon();

		$type = 'fixed_cart' === wkr_get( $campaign_id, 'wc_type' ) ? 'fixed_cart' : 'percent';
		$end  = (int) get_post_meta( $campaign_id, '_wkr_end_utc', true );

		$coupon->set_code( $code );
		$coupon->set_discount_type( $type );
		$coupon->set_amount( (float) wkr_get( $campaign_id, 'wc_amount' ) );
		$coupon->set_description(
			sprintf(
				/* translators: %s: campaign title */
				__( 'Loodud kampaaniaribaga: %s', 'wonom-kampaaniariba' ),
				get_the_title( $campaign_id )
			)
		);

		$min = (float) wkr_get( $campaign_id, 'wc_min' );
		$coupon->set_minimum_amount( $min > 0 ? $min : '' );

		$limit = (int) wkr_get( $campaign_id, 'wc_limit' );
		$coupon->set_usage_limit( $limit > 0 ? $limit : '' );

		$per_user = (int) wkr_get( $campaign_id, 'wc_limit_user' );
		$coupon->set_usage_limit_per_user( $per_user > 0 ? $per_user : '' );

		$coupon->set_individual_use( (bool) wkr_get( $campaign_id, 'wc_individual' ) );
		$coupon->set_free_shipping( (bool) wkr_get( $campaign_id, 'wc_free_ship' ) );

		// Aegumine tuleb kampaania lõpuajast. Nii näeb klient pärast kampaaniat
		// WooCommerce'i enda teadet „kupong on aegunud”, mitte „koodi ei ole”.
		$coupon->set_date_expires( $end ? $end : null );

		try {
			$coupon_id = $coupon->save();
		} catch ( Exception $e ) {
			return array(
				'status'    => 'error',
				'message'   => __( 'Kupongi salvestamine ebaõnnestus.', 'wonom-kampaaniariba' ),
				'coupon_id' => 0,
			);
		}

		if ( ! $coupon_id ) {
			return array(
				'status'    => 'error',
				'message'   => __( 'Kupongi salvestamine ebaõnnestus.', 'wonom-kampaaniariba' ),
				'coupon_id' => 0,
			);
		}

		update_post_meta( $coupon_id, self::OWNER_META, (int) $campaign_id );
		update_post_meta( $campaign_id, '_wkr_coupon_id', (int) $coupon_id );

		// Ülevõtmise linnuke on ühekordne — järgmisel salvestusel pole seda vaja.
		update_post_meta( $campaign_id, '_wkr_wc_takeover', 0 );

		return array(
			'status'    => 'ok',
			'message'   => '',
			'coupon_id' => (int) $coupon_id,
		);
	}

	/**
	 * Kupong kehtib ainult siis, kui tema kampaania on eetris.
	 *
	 * See on tähtsam kui aegumiskuupäev: WooCommerce'il endal ei ole „kehtib
	 * alates” välja, nii et algusaega hoiab siin plugin.
	 *
	 * @param bool      $valid  Senine otsus.
	 * @param WC_Coupon $coupon Kupong.
	 * @return bool
	 */
	public static function validate( $valid, $coupon ) {
		if ( ! $valid || ! is_a( $coupon, 'WC_Coupon' ) || ! $coupon->get_id() ) {
			return $valid;
		}

		$campaign_id = (int) get_post_meta( $coupon->get_id(), self::OWNER_META, true );
		if ( ! $campaign_id ) {
			return $valid;
		}

		$campaign = get_post( $campaign_id );
		if ( ! $campaign || WKR_CPT !== $campaign->post_type ) {
			// Kampaania on kustutatud — ei hakka poe tööd segama.
			return $valid;
		}

		return 'live' === wkr_status( $campaign_id );
	}

	/**
	 * Kampaania prügikasti — hallatav kupong läheb mustandiks.
	 *
	 * @param int $post_id Postituse ID.
	 */
	public static function on_trash( $post_id ) {
		if ( WKR_CPT !== get_post_type( $post_id ) ) {
			return;
		}

		$coupon_id = self::owned_id( $post_id );
		if ( $coupon_id ) {
			wp_update_post(
				array(
					'ID'          => $coupon_id,
					'post_status' => 'draft',
				)
			);
		}
	}

	/**
	 * Prügikastist tagasi — kupong avaldatakse uuesti.
	 *
	 * @param int $post_id Postituse ID.
	 */
	public static function on_untrash( $post_id ) {
		if ( WKR_CPT !== get_post_type( $post_id ) ) {
			return;
		}

		$coupon_id = self::owned_id( $post_id );
		if ( $coupon_id ) {
			wp_update_post(
				array(
					'ID'          => $coupon_id,
					'post_status' => 'publish',
				)
			);
		}
	}

	/**
	 * Salvestab teate, mida näidatakse järgmisel administraatori lehel.
	 *
	 * @param string $status  ok|conflict|error.
	 * @param string $message Teade.
	 */
	public static function remember_notice( $status, $message ) {
		if ( ! $message ) {
			return;
		}

		set_transient(
			'wkr_notice_' . get_current_user_id(),
			array(
				'status'  => $status,
				'message' => $message,
			),
			60
		);
	}

	/**
	 * Näitab teate ära.
	 */
	public static function notice() {
		$key    = 'wkr_notice_' . get_current_user_id();
		$notice = get_transient( $key );

		if ( ! $notice || empty( $notice['message'] ) ) {
			return;
		}

		delete_transient( $key );

		$class = 'conflict' === $notice['status'] ? 'notice-warning' : 'notice-error';

		printf(
			'<div class="notice %1$s is-dismissible"><p>%2$s</p></div>',
			esc_attr( $class ),
			esc_html( $notice['message'] )
		);
	}
}
