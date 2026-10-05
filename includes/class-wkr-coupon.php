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
	 * Kampaania külge jääv märge: see kampaania on koodi endale broneerinud
	 * ja võtab selle üle oma alguse hetkel.
	 */
	const CLAIM_META = '_wkr_wc_claim';

	/**
	 * Ajastatud üleandmise sündmus.
	 */
	const CRON_HOOK = 'wkr_coupon_handover';

	/**
	 * Valik, mis hoiab järgmise üleandmise aega. Nii piisab igal päringul
	 * ühest kergest lugemisest, et teada, kas üldse on midagi teha.
	 */
	const NEXT_OPTION = 'wkr_next_handover';

	/**
	 * Haagid.
	 */
	public static function init() {
		add_action( 'wp_ajax_wkr_coupon_status', array( __CLASS__, 'ajax_status' ) );
		add_filter( 'woocommerce_coupon_is_valid', array( __CLASS__, 'validate' ), 10, 2 );
		add_action( 'trashed_post', array( __CLASS__, 'on_trash' ) );
		add_action( 'untrashed_post', array( __CLASS__, 'on_untrash' ) );
		add_action( 'admin_notices', array( __CLASS__, 'notice' ) );

		add_action( self::CRON_HOOK, array( __CLASS__, 'handover' ) );

		/*
		 * Cron teeb üleandmise täpselt õigel ajal. See siin on varuvariant:
		 * kui cron hilineb või on surnud, teeb plugin vahetuse esimesel
		 * lehevaatel ise ära. Kupongi kehtivus ei tohi sõltuda cronist.
		 */
		add_action( 'init', array( __CLASS__, 'maybe_run_due' ), 20 );
	}

	/**
	 * Kas midagi on üle anda. Odav kontroll igale päringule.
	 */
	public static function maybe_run_due() {
		$next = (int) get_option( self::NEXT_OPTION, 0 );

		if ( $next && time() >= $next && self::woo_active() ) {
			self::run_due_handovers();
		}
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
	 * Kupongi haldav kampaania, kui see veel olemas on.
	 *
	 * Kustutatud kampaania jätab kupongile rippuva märke. Sellist kupongi
	 * koheldakse nagu käsitsi tehtut, muidu jääks kood igaveseks lukku.
	 *
	 * @param int $coupon_id Kupongi ID.
	 * @return int Kampaania ID või 0.
	 */
	public static function owner_campaign( $coupon_id ) {
		$owner = (int) get_post_meta( $coupon_id, self::OWNER_META, true );

		if ( ! $owner || WKR_CPT !== get_post_type( $owner ) ) {
			return 0;
		}

		return $owner;
	}

	/**
	 * Kas kampaania on koodi endale broneerinud.
	 *
	 * @param int $campaign_id Kampaania ID.
	 * @return bool
	 */
	public static function has_claim( $campaign_id ) {
		return (bool) get_post_meta( $campaign_id, self::CLAIM_META, true );
	}

	/**
	 * Võtab broneeringu maha ja koristab ajastuse.
	 *
	 * @param int $campaign_id Kampaania ID.
	 */
	public static function forget_claim( $campaign_id ) {
		delete_post_meta( $campaign_id, self::CLAIM_META );
		self::unschedule_claim( $campaign_id );
		self::refresh_next();
	}

	/**
	 * Paneb kampaania alguse hetkele üleandmise sündmuse.
	 *
	 * Kutsutakse igal salvestusel, nii et kuupäeva muutmine liigutab ka
	 * üleandmise kaasa.
	 *
	 * @param int $campaign_id Kampaania ID.
	 */
	public static function schedule_claim( $campaign_id ) {
		self::unschedule_claim( $campaign_id );

		if ( self::has_claim( $campaign_id ) ) {
			$start = (int) get_post_meta( $campaign_id, '_wkr_start_utc', true );

			if ( $start > time() ) {
				// Paar sekundit hiljem, et kampaania oleks selleks hetkeks juba eetris.
				wp_schedule_single_event( $start + 5, self::CRON_HOOK, array( (int) $campaign_id ) );
			}
		}

		self::refresh_next();
	}

	/**
	 * Eemaldab kampaania ajastatud üleandmise.
	 *
	 * @param int $campaign_id Kampaania ID.
	 */
	public static function unschedule_claim( $campaign_id ) {
		$args      = array( (int) $campaign_id );
		$timestamp = wp_next_scheduled( self::CRON_HOOK, $args );

		while ( $timestamp ) {
			wp_unschedule_event( $timestamp, self::CRON_HOOK, $args );
			$timestamp = wp_next_scheduled( self::CRON_HOOK, $args );
		}
	}

	/**
	 * Kõik broneeringuga kampaaniad.
	 *
	 * @return int[]
	 */
	public static function claimed_campaigns() {
		return get_posts(
			array(
				'post_type'        => WKR_CPT,
				'post_status'      => array( 'publish', 'draft', 'future', 'pending' ),
				'numberposts'      => 100,
				'fields'           => 'ids',
				'meta_key'         => self::CLAIM_META, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
				'meta_value'       => '1', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
				'suppress_filters' => true,
			)
		);
	}

	/**
	 * Arvutab järgmise üleandmise aja uuesti.
	 */
	public static function refresh_next() {
		$next = 0;

		foreach ( self::claimed_campaigns() as $id ) {
			$start = (int) get_post_meta( $id, '_wkr_start_utc', true );

			if ( $start && ( ! $next || $start < $next ) ) {
				$next = $start;
			}
		}

		update_option( self::NEXT_OPTION, $next, true );
	}

	/**
	 * Teeb ära kõik üleandmised, mille aeg on käes.
	 */
	public static function run_due_handovers() {
		$now = time();

		foreach ( self::claimed_campaigns() as $id ) {
			$start = (int) get_post_meta( $id, '_wkr_start_utc', true );

			if ( $start && $start <= $now ) {
				self::handover( $id );
			}
		}

		self::refresh_next();
	}

	/**
	 * Annab koodi broneerinud kampaaniale üle.
	 *
	 * Nõusolek on juba antud siis, kui kasutaja broneeringu tegi, nii et
	 * siin ülevõtmise linnukest enam ei küsita.
	 *
	 * @param int $campaign_id Kampaania ID.
	 */
	public static function handover( $campaign_id ) {
		$campaign_id = (int) $campaign_id;

		if ( ! $campaign_id || WKR_CPT !== get_post_type( $campaign_id ) ) {
			return;
		}

		if ( ! self::has_claim( $campaign_id ) ) {
			return;
		}

		/*
		 * Kui WooCommerce ei ole (veel) laetud, siis broneeringut ära ei
		 * kustuta — proovime järgmisel korral uuesti. Muidu kaoks üleandmine
		 * jäädavalt pelgalt laadimisjärjekorra pärast.
		 */
		if ( ! self::woo_active() ) {
			return;
		}

		// Broneering kehtib ühe korra, olenemata sellest, kuidas läheb.
		delete_post_meta( $campaign_id, self::CLAIM_META );
		self::unschedule_claim( $campaign_id );

		/*
		 * Koodi võtab üle ainult kampaania, kes päriselt eetrisse läks.
		 * Mustand, välja lülitatud või juba lõppenud kampaania ei tohi
		 * töötavat koodi endale kaasa viia.
		 */
		if ( 'live' !== wkr_status( $campaign_id ) ) {
			self::refresh_next();
			return;
		}

		$result = self::sync( $campaign_id, true );

		if ( ! empty( $result['message'] ) && 'ok' !== $result['status'] ) {
			self::remember_notice( 'warning', $result['message'] );
		}

		self::refresh_next();
	}

	/**
	 * Kampaaniad, kes on selle koodi endale broneerinud.
	 *
	 * @param string $code        Sooduskood.
	 * @param int    $exclude_id  Kampaania, keda vastusesse ei taheta.
	 * @return int[]
	 */
	public static function claims_for_code( $code, $exclude_id = 0 ) {
		$code = self::format_code( $code );
		$out  = array();

		if ( '' === $code ) {
			return $out;
		}

		foreach ( self::claimed_campaigns() as $id ) {
			if ( (int) $id === (int) $exclude_id ) {
				continue;
			}

			if ( self::format_code( wkr_get( $id, 'coupon' ) ) === $code ) {
				$out[] = (int) $id;
			}
		}

		return $out;
	}

	/**
	 * Kupongi kasutuskordade loendur nulli.
	 *
	 * WooCommerce hoiab kasutusarvu kahes kohas: `usage_count` ütleb, mitu
	 * korda kupongi on kasutatud, ja iga `_used_by` rida ütleb, kes seda tegi.
	 * Kliendipõhine piir loeb just neid ridu, nii et ainult loenduri nullimine
	 * jätaks varem ostnud kliendid endiselt ukse taha.
	 *
	 * CRUD seda ei tee: `set_used_by()` ei jõua andmesalvestusse, sestap
	 * kirjutame meta otse ja viskame WooCommerce'i vahemälu tühjaks.
	 *
	 * @param int $coupon_id Kupongi ID.
	 * @return int Endine kasutuskordade arv.
	 */
	public static function reset_usage( $coupon_id ) {
		$before = (int) get_post_meta( $coupon_id, 'usage_count', true );

		update_post_meta( $coupon_id, 'usage_count', 0 );
		delete_post_meta( $coupon_id, '_used_by' );

		clean_post_cache( $coupon_id );

		if ( is_callable( array( 'WC_Cache_Helper', 'invalidate_cache_group' ) ) ) {
			WC_Cache_Helper::invalidate_cache_group( 'coupons' );
		}

		return $before;
	}

	/**
	 * Kasutuskordade seis lühidalt, nii nagu see administraatorile paistab.
	 *
	 * @param int $coupon_id Kupongi ID.
	 * @return string
	 */
	public static function usage_label( $coupon_id ) {
		$coupon = new WC_Coupon( $coupon_id );
		$limit  = (int) $coupon->get_usage_limit();
		$used   = (int) $coupon->get_usage_count();

		if ( $limit > 0 ) {
			return sprintf(
				/* translators: 1: usage count, 2: usage limit */
				__( 'Seda kupongi on kasutatud %1$d korda, piir on %2$d.', 'wonom-kampaaniariba' ),
				$used,
				$limit
			);
		}

		return sprintf(
			/* translators: %d: usage count */
			__( 'Seda kupongi on kasutatud %d korda, kasutuskordade piirangut ei ole.', 'wonom-kampaaniariba' ),
			$used
		);
	}

	/**
	 * Hoiatus, kui kupongi kasutuskorrad on otsas.
	 *
	 * Korduvkasutatava koodi puhul on see kõige sagedasem põhjus, miks kupong
	 * ka kehtiva kampaania ajal ostukorvis vastu võtmata jääb. WooCommerce
	 * kontrollib kasutuspiiri enne meie oma kontrolli, nii et plugin seda
	 * kõrvale lükata ei saa — kasutaja peab piiri tõstma või loenduri nullima.
	 *
	 * @param int $coupon_id Kupongi ID.
	 * @return string Tühi, kui kõik on korras.
	 */
	public static function usage_note( $coupon_id ) {
		$coupon = new WC_Coupon( $coupon_id );
		$limit  = (int) $coupon->get_usage_limit();
		$used   = (int) $coupon->get_usage_count();

		if ( $limit > 0 && $used >= $limit ) {
			return sprintf(
				/* translators: 1: usage count, 2: usage limit */
				__( 'Kupongi kasutuskorrad on täis (%1$d / %2$d). Ka kehtiva kampaania ajal jääb kood ostukorvis vastu võtmata, kuni tõstad „Kasutuskordi kokku” piiri või nullid loenduri WooCommerce’i kupongi all.', 'wonom-kampaaniariba' ),
				$used,
				$limit
			);
		}

		return '';
	}

	/**
	 * Kupongi olek antud koodi jaoks.
	 *
	 * Sama funktsioon teenindab nii lehe esmast joonistamist kui ka AJAX-i, nii
	 * et administraator näeb koodi muutmisel kohe õiget olekut ega pea kampaaniat
	 * vahepeal salvestama. Kloonitud kampaanial oli see eriti segadust tekitav:
	 * väljal seisis uus kood, aga olek rääkis veel originaali omast.
	 *
	 * @param int    $campaign_id Kampaania ID.
	 * @param string $code        Sooduskood.
	 * @return array
	 */
	public static function status_for( $campaign_id, $code ) {
		$code = self::format_code( $code );

		$out = array(
			'code'          => $code,
			'state'         => 'empty',
			'pill'          => 'off',
			'label'         => __( 'Koodi pole sisestatud', 'wonom-kampaaniariba' ),
			'note'          => '',
			'usage'         => '',
			'edit_url'      => '',
			'takeover'      => false,
			'takeover_text' => '',
		);

		if ( '' === $code || ! self::woo_active() ) {
			return $out;
		}

		$owned    = self::owned_id( $campaign_id );
		$existing = (int) wc_get_coupon_id_by_code( $code );

		if ( ! $existing ) {
			$out['state'] = 'none';
			$out['label'] = __( 'Sellist kupongi WooCommerce’is veel ei ole — plugin loob selle salvestamisel', 'wonom-kampaaniariba' );
			return $out;
		}

		$out['edit_url'] = (string) get_edit_post_link( $existing, 'raw' );
		$out['note']     = self::usage_note( $existing );
		$out['usage']    = self::usage_label( $existing );
		$owner           = self::owner_campaign( $existing );

		if ( $existing === $owned || ( $owner && $owner === (int) $campaign_id ) ) {
			$out['state'] = 'ours';
			$out['pill']  = 'live';
			$out['label'] = __( 'Kupong on olemas ja seda haldab see kampaania', 'wonom-kampaaniariba' );

			// Kui keegi on koodi endale broneerinud, siis öeldakse see välja,
			// et ülevõtmine ei tuleks hiljem üllatusena.
			$next = self::claims_for_code( $code, $campaign_id );

			if ( $next ) {
				$heir             = $next[0];
				$out['note']      = sprintf(
					/* translators: 1: start time, 2: campaign title */
					__( 'Kood on broneeritud: %1$s läheb see üle kampaaniale „%2$s” ja lakkab siin kehtimast.', 'wonom-kampaaniariba' ),
					wkr_pretty( wkr_get( $heir, 'start_local' ) ),
					get_the_title( $heir )
				);
			}

			return $out;
		}

		// Oleme koodi ise broneerinud — ülevõtmist enam uuesti küsida ei ole vaja.
		if ( self::has_claim( $campaign_id ) ) {
			$out['state'] = 'claimed';
			$out['pill']  = 'upcoming';
			$out['label'] = sprintf(
				/* translators: %s: start time */
				__( 'Kood on broneeritud — liigub siia %s, kui kampaania algab', 'wonom-kampaaniariba' ),
				wkr_pretty( wkr_get( $campaign_id, 'start_local' ) )
			);

			if ( $owner ) {
				$out['note'] = sprintf(
					/* translators: %s: campaign title */
					__( 'Seni haldab koodi kampaania „%s” ja see töötab seal edasi.', 'wonom-kampaaniariba' ),
					get_the_title( $owner )
				);
			}

			return $out;
		}

		// Olemasolevat koodi saab alati üle võtta — nii saab sama koodi
		// kampaaniast kampaaniasse edasi anda, ilma uut välja mõtlemata.
		$out['takeover'] = true;

		if ( $owner ) {
			$other = wkr_status( $owner );
			$title = get_the_title( $owner );

			$out['state'] = 'other';
			$out['pill']  = 'upcoming';
			$out['label'] = sprintf(
				/* translators: %s: campaign title */
				__( 'Seda koodi haldab praegu kampaania „%s”', 'wonom-kampaaniariba' ),
				$title
			);

			$my_start = (int) get_post_meta( $campaign_id, '_wkr_start_utc', true );

			if ( $my_start > time() ) {
				/*
				 * Tulevikus algav kampaania koodi praegu ära ei kisu — see
				 * broneeritakse ja vahetus käib alguse hetkel.
				 */
				$out['takeover_text'] = sprintf(
					/* translators: 1: start time, 2: campaign title */
					__( 'Kood ei liigu kohe: see broneeritakse ja tuleb siia %1$s, kui kampaania algab. Kuni selle ajani töötab kood kampaanias „%2$s” edasi.', 'wonom-kampaaniariba' ),
					wkr_pretty( wkr_get( $campaign_id, 'start_local' ) ),
					$title
				);

				// Kattumise korral võidab hilisem algus — see tasub välja öelda.
				$other_end = (int) get_post_meta( $owner, '_wkr_end_utc', true );

				if ( $other_end > $my_start ) {
					$out['takeover_text'] .= ' ' . sprintf(
						/* translators: 1: campaign title, 2: that campaign's end time */
						__( 'NB! Kampaaniad kattuvad: „%1$s” kestab %2$s-ni, aga kood lakkab seal kehtimast juba siis, kui see kampaania algab.', 'wonom-kampaaniariba' ),
						$title,
						wkr_pretty( wkr_get( $owner, 'end_local' ) )
					);
				}
			} elseif ( 'live' === $other || 'upcoming' === $other ) {
				$out['takeover_text'] = sprintf(
					/* translators: 1: campaign title, 2: „praegu eetris” või „ootel” */
					__( 'NB! Kampaania „%1$s” on %2$s ja kasutab sama koodi. See kampaania on juba alanud, nii et ülevõtmine käib kohe ja kood lakkab seal kehtimast.', 'wonom-kampaaniariba' ),
					$title,
					'live' === $other
						? __( 'praegu eetris', 'wonom-kampaaniariba' )
						: __( 'ootel', 'wonom-kampaaniariba' )
				);
			} else {
				$out['takeover_text'] = sprintf(
					/* translators: %s: campaign title */
					__( 'Kampaania „%s” on läbi, nii et kupong kannab veel tema aegumiskuupäeva ega kehti. Võta kood üle — kupong saab selle kampaania kuupäevad ja hakkab uuesti kehtima.', 'wonom-kampaaniariba' ),
					$title
				);
			}

			return $out;
		}

		$out['state']         = 'manual';
		$out['pill']          = 'upcoming';
		$out['label']         = __( 'Selle koodiga kupong on WooCommerce’is juba olemas', 'wonom-kampaaniariba' );
		$out['takeover_text'] = sprintf(
			/* translators: %s: coupon code */
			__( 'Kood „%s” on juba olemas ja selle on keegi käsitsi teinud. Plugin ei muuda seda ilma sinu loata.', 'wonom-kampaaniariba' ),
			$code
		);

		return $out;
	}

	/**
	 * AJAX: kupongi olek koodi kirjutamise ajal.
	 */
	public static function ajax_status() {
		check_ajax_referer( 'wkr_coupon_status', 'nonce' );

		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_send_json_error( array( 'message' => __( 'Puuduvad õigused.', 'wonom-kampaaniariba' ) ), 403 );
		}

		$campaign = isset( $_POST['campaign'] ) ? absint( $_POST['campaign'] ) : 0;
		$code     = isset( $_POST['code'] ) ? sanitize_text_field( wp_unslash( $_POST['code'] ) ) : '';

		if ( $campaign && ! current_user_can( 'edit_post', $campaign ) ) {
			wp_send_json_error( array( 'message' => __( 'Puuduvad õigused.', 'wonom-kampaaniariba' ) ), 403 );
		}

		wp_send_json_success( self::status_for( $campaign, $code ) );
	}

	/**
	 * Loob või uuendab kupongi kampaania seadete järgi.
	 *
	 * @param int $campaign_id Kampaania ID.
	 * @return array {status: ok|skipped|conflict|error, message: string, coupon_id: int}
	 */
	public static function sync( $campaign_id, $force_takeover = false ) {
		$mode = wkr_get( $campaign_id, 'wc_mode' );
		$code = self::format_code( wkr_get( $campaign_id, 'coupon' ) );

		/*
		 * Mõlemad linnukesed kehtivad ühe korra. Kustutame märked kohe, enne
		 * kui ükski kontroll saab poole pealt välja hüpata — muidu jääksid
		 * nad rippuma ja mõjuksid ootamatult järgmisel salvestusel.
		 */
		$takeover = $force_takeover || (bool) wkr_get( $campaign_id, 'wc_takeover' );
		$reset    = (bool) wkr_get( $campaign_id, 'wc_reset_usage' );

		update_post_meta( $campaign_id, '_wkr_wc_takeover', 0 );
		update_post_meta( $campaign_id, '_wkr_wc_reset_usage', 0 );

		if ( 'manage' !== $mode ) {
			// Ilma halduseta ei ole ka broneeringul mõtet.
			self::forget_claim( $campaign_id );

			// Linnuke ilma halduseta ei tee midagi — ütleme seda, mitte ei vaiki.
			return array(
				'status'    => $takeover || $reset ? 'conflict' : 'skipped',
				'level'     => 'warning',
				'message'   => $takeover || $reset
					? __( 'Kupongi ei puututud, sest väli „Mida pluginaga kupongiga teha” on „Ainult näitan koodi ribal”. Kupongi ülevõtmiseks ja haldamiseks vali „Loo ja hoia WooCommerce kupong siit”.', 'wonom-kampaaniariba' )
					: '',
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

		$took_over_from = '';

		// Sama koodiga kupong on juba olemas ja see pole meie oma.
		if ( $existing_id && $existing_id !== $owned_id ) {
			$owner = self::owner_campaign( $existing_id );

			// Märge näitab juba meile — viide oli lihtsalt kaduma läinud.
			if ( $owner === (int) $campaign_id ) {
				$owner = 0;
			} elseif ( ! $takeover && ! self::has_claim( $campaign_id ) ) {
				// Juba broneerinud kampaania ei pea linnukest iga salvestusega uuesti märkima.
				return array(
					'status'    => 'conflict',
					'message'   => $owner
						? sprintf(
							/* translators: 1: coupon code, 2: campaign title */
							__( 'Koodi „%1$s” haldab kampaania „%2$s”, nii et kupongi ei muudetud. Kui tahad sama koodi siin edasi kasutada, märgi „Võta olemasolev kupong üle” ja salvesta uuesti.', 'wonom-kampaaniariba' ),
							$code,
							get_the_title( $owner )
						)
						: sprintf(
							/* translators: %s: coupon code */
							__( 'WooCommerce’is on juba kupong „%s”, mille on teinud keegi käsitsi. Kampaaniariba ei muutnud seda. Kui tahad, et plugin hakkaks seda haldama, märgi „Võta olemasolev kupong üle”.', 'wonom-kampaaniariba' ),
							$code
						),
					'coupon_id' => $existing_id,
				);
			}

			/*
			 * Kasutaja lubas üle võtta. Kui see kampaania algab alles
			 * tulevikus ja koodi haldab praegu mõni teine kampaania, siis me
			 * kupongi veel ei puutu — muidu kaotaks eetris olev kampaania
			 * koodi sealsamas ära. Selle asemel paneme broneeringu ja anname
			 * kupongi üle alles siis, kui see kampaania päriselt algab.
			 */
			$start = (int) get_post_meta( $campaign_id, '_wkr_start_utc', true );

			if ( ! $force_takeover && $owner && $start > time() ) {
				update_post_meta( $campaign_id, self::CLAIM_META, 1 );
				self::schedule_claim( $campaign_id );

				return array(
					'status'    => 'ok',
					'level'     => 'success',
					'message'   => sprintf(
						/* translators: 1: coupon code, 2: start time, 3: current owner campaign */
						__( 'Kood „%1$s” on broneeritud. See liigub siia %2$s, kui kampaania algab. Seni jääb kood kampaaniale „%3$s” ja töötab seal edasi.', 'wonom-kampaaniariba' ),
						$code,
						wkr_pretty( wkr_get( $campaign_id, 'start_local' ) ),
						get_the_title( $owner )
					),
					'coupon_id' => $existing_id,
				);
			}

			// Vana kampaania viide tuleb ära koristada, muidu näitaks see
			// edasi kupongi, mida ta enam ei halda.
			if ( $owner ) {
				$took_over_from = get_the_title( $owner );
				delete_post_meta( $owner, '_wkr_coupon_id' );
			}

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
				__( 'Loodud Wonom kampaaniaga: %s', 'wonom-kampaaniariba' ),
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
		$coupon->set_exclude_sale_items( (bool) wkr_get( $campaign_id, 'wc_exclude_sale' ) );

		/*
		 * Toote- ja kategooriapiiranguid puutume ainult siis, kui kasutaja on
		 * selle sõnaselgelt sisse lülitanud. Muidu jäävad WooCommerce'i kupongi
		 * all käsitsi tehtud valikud alles.
		 */
		if ( wkr_get( $campaign_id, 'wc_manage_items' ) ) {
			$exclude_products = array_values(
				array_unique(
					array_merge(
						wkr_get( $campaign_id, 'wc_products_ex' ),
						wkr_always_excluded( 'wkr_always_exclude_products' )
					)
				)
			);

			$exclude_cats = array_values(
				array_unique(
					array_merge(
						wkr_get( $campaign_id, 'wc_cats_ex' ),
						wkr_always_excluded( 'wkr_always_exclude_cats' )
					)
				)
			);

			$coupon->set_product_ids( wkr_get( $campaign_id, 'wc_products' ) );
			$coupon->set_excluded_product_ids( $exclude_products );
			$coupon->set_product_categories( wkr_get( $campaign_id, 'wc_cats' ) );
			$coupon->set_excluded_product_categories( $exclude_cats );
		}

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

		// Kood on käes — broneeringut enam vaja ei ole.
		if ( self::has_claim( $campaign_id ) ) {
			self::forget_claim( $campaign_id );
		}


		$parts = array();
		$level = 'success';

		if ( $took_over_from ) {
			$parts[] = sprintf(
				/* translators: 1: coupon code, 2: previous campaign title */
				__( 'Kood „%1$s” liikus kampaanialt „%2$s” siia ja sai selle kampaania kuupäevad.', 'wonom-kampaaniariba' ),
				$code,
				$took_over_from
			);
		}

		/*
		 * Nullimine käib pärast salvestust: salvestus kirjutab kupongi objekti
		 * välja koos vana loenduriga ja kustutaks nullimise muidu ära.
		 */
		if ( $reset ) {
			$before = self::reset_usage( $coupon_id );

			// Värskelt loodud kupongi juures ei ole millestki teatada.
			if ( $before > 0 ) {
				$parts[] = sprintf(
					/* translators: 1: coupon code, 2: previous usage count */
					__( 'Koodi „%1$s” kasutuskordade loendur nulliti — varem oli %2$d kasutust. Ka kliendipõhine piirang algab otsast peale.', 'wonom-kampaaniariba' ),
					$code,
					$before
				);
			}
		} else {
			$usage = self::usage_note( $coupon_id );

			// Kasutuspiiri hoiatus kaalub ülevõtmise rõõmusõnumi üles.
			if ( $usage ) {
				$parts[] = $usage;
				$level   = 'warning';
			}
		}

		$message = implode( ' ', $parts );

		return array(
			'status'    => 'ok',
			'level'     => $level,
			'message'   => $message,
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

		// Prügikasti visatud kampaania ei pea enam kellegi koodi ootama.
		self::forget_claim( $post_id );

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
	 * @param string $status  success|warning|error.
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

		$classes = array(
			'success'  => 'notice-success',
			'warning'  => 'notice-warning',
			'conflict' => 'notice-warning',
		);

		$class = isset( $classes[ $notice['status'] ] ) ? $classes[ $notice['status'] ] : 'notice-error';

		printf(
			'<div class="notice %1$s is-dismissible"><p>%2$s</p></div>',
			esc_attr( $class ),
			esc_html( $notice['message'] )
		);
	}
}
