<?php
/**
 * Riba poe esiküljel.
 *
 * Lehe HTML-is riba ei ole. Lehel on ainult väike laadija, mis loeb riba
 * andmefailist (vaata WKR_Data) ja laseb brauseril õige kampaania kellaaja
 * järgi välja valida. Tänu sellele võib leht seista vahemälus ükskõik kui
 * kaua: kampaania muutumisel ei ole lehte vaja puutuda.
 *
 * @package Wonom_Kampaaniariba
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Laadija lehe päises, riba HTML andmefaili jaoks ja koodi rakendamine ostukorvis.
 */
class WKR_Render {

	/**
	 * Haagid.
	 */
	public static function init() {
		add_action( 'wp_head', array( __CLASS__, 'loader' ), 1 );
		add_shortcode( 'wonom_banner', array( __CLASS__, 'shortcode' ) );

		// WooCommerce: rakenda kood, kui klient jõudis ribalt.
		add_action( 'wp_loaded', array( __CLASS__, 'maybe_apply_coupon' ), 20 );
	}

	/**
	 * Laadija lehe päises.
	 *
	 * See on ainus asi, mille plugin lehe HTML-i kirjutab, ja see ei sõltu
	 * ühestki kampaaniast — seepärast trükitakse see igale lehele ka siis,
	 * kui parajasti ühtegi kampaaniat ei ole. Laadija:
	 *
	 * 1. hoiab päiseriba jaoks ruumi kinni, kui eelmisel lehel oli päiseriba
	 *    näha (kõrgus on brauseris meeles), et leht ei hüppaks;
	 * 2. küsib andmefaili. Aadressi lõpus on minutipõhine number, nii et
	 *    vahemälu annab kõige rohkem minuti vanuse faili;
	 * 3. võrdleb oma kella serveri omaga (vastuse Date- ja Age-päis), et vale kellaga
	 *    seade ei näitaks valet kampaaniat;
	 * 4. laeb riba stiilid ja skripti ainult siis, kui failis on mõni kampaania.
	 *    Stiili ja skripti aadress tuleb andmefailist, nii et vana vahemälus
	 *    leht saab alati praeguse versiooni.
	 *
	 * Kiirenduspluginad, mis lükkavad JavaScripti edasi, lükkavad ka selle.
	 * Riba ilmub siis veidi hiljem, aga töötab. Kui tahad, et see jookseks
	 * kohe, lisa kiirendusplugina väljajätmiste hulka märksõna „wkr-restore”.
	 */
	public static function loader() {
		if ( is_admin() || is_feed() || is_embed() ) {
			return;
		}

		$url = WKR_Data::url( wkr_current_lang() );
		if ( '' === $url ) {
			return;
		}
		?>
<script id="wkr-restore" data-no-optimize="1" data-no-defer="1" data-no-delay="1" data-cfasync="false">
/* wkr-restore */
(function(w,d){if(w.wkrBoot){return;}var u=<?php echo wp_json_encode( $url, JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_SLASHES ); ?>,b=w.wkrBoot={u:u,off:0,data:null},H=function(){return d.head||d.documentElement;};
function unreserve(){var r=d.getElementById("wkr-reserve");if(r&&r.parentNode){r.parentNode.removeChild(r);}}
try{var h=JSON.parse(w.localStorage.getItem("wkr_top")||"null");if(h&&h.h>0&&h.e>Date.now()/1000){var s=d.createElement("style");s.id="wkr-reserve";s.textContent="html{padding-top:"+(+h.h)+"px}";H().appendChild(s);}}catch(e){}
function run(j,o){b.data=j;b.off=o;if(!j||!j.js||!j.c||!j.c.length){unreserve();if(w.wkrRun){w.wkrRun();}return;}
if(w.wkrRun){w.wkrRun();return;}if(d.getElementById("wkr-js")){return;}
if(j.css){var l=d.createElement("link");l.id="wkr-css";l.rel="stylesheet";l.href=j.css;H().appendChild(l);}
var s=d.createElement("script");s.id="wkr-js";s.async=true;s.src=j.js;H().appendChild(s);}
b.fetch=function(){try{b.at=Date.now();w.fetch(u+"?wkr="+Math.floor(Date.now()/60000),{credentials:"omit",cache:"no-store"}).then(function(r){var n=Date.now(),t=Date.parse(r.headers.get("date")||""),a=parseInt(r.headers.get("age")||"0",10)||0,o;if(t&&a>0&&Math.abs(t+a*1000-n)<Math.abs(t-n)){t+=a*1000;}o=t?Math.round((t+500-n)/1000):0;return r.json().then(function(j){run(j,o);});})["catch"](unreserve);}catch(e){unreserve();}};
b.fetch();})(window,document);
</script>
		<?php
	}

	/**
	 * Lühikood [wonom_banner id="12" position="top"].
	 *
	 * Jätab lehele koha, kuhu brauser riba joonistab. ID-ga lühikood näitab
	 * seda kampaaniat (kui see on eetris), ilma ID-ta lühikood seda, mis
	 * antud kohta parajasti kuulub.
	 *
	 * @param array $atts Atribuudid.
	 * @return string
	 */
	public static function shortcode( $atts ) {
		$atts = shortcode_atts(
			array(
				'id'       => 0,
				'position' => 'top',
			),
			$atts,
			'wonom_banner'
		);

		return sprintf(
			'<div class="wkr-inline-host" data-wkr-place="%1$s" data-wkr-id="%2$d"></div>',
			'bottom' === $atts['position'] ? 'bottom' : 'top',
			absint( $atts['id'] )
		);
	}

	/**
	 * Riba HTML. Kõik tekstid on siin juba paosüsteemi läbinud.
	 *
	 * @param WP_Post $post      Kampaania.
	 * @param string  $place     top | bottom.
	 * @param bool    $inline    Kas tegu on lühikoodiga (siis ei kleebi alla).
	 * @return string
	 */
	public static function markup( $post, $place, $inline = false ) {
		$id     = (int) $post->ID;
		$fonts  = wkr_fonts();
		$font   = wkr_get( $post->ID, 'font' );
		$stack  = isset( $fonts[ $font ] ) ? $fonts[ $font ]['stack'] : 'inherit';
		$coupon = (string) wkr_get( $post->ID, 'coupon' );
		$style  = wkr_get( $post->ID, 'style' );
		$closes = (int) wkr_get( $post->ID, 'dismissible' );
		$end_ts = (int) get_post_meta( $post->ID, '_wkr_end_utc', true );

		$close_pos = wkr_get( $post->ID, 'close_pos' );
		if ( ! in_array( $close_pos, array( 'right', 'left', 'top-right', 'top-left' ), true ) ) {
			$close_pos = 'right';
		}

		$classes = array( 'wkr-slot', 'wkr-slot--' . $place, 'wkr-slot--close-' . $close_pos );
		if ( 'bottom' === $place && ! $inline ) {
			$classes[] = 'floating' === $style ? 'wkr-slot--floating' : 'wkr-slot--stuck';
		}
		if ( $inline ) {
			$classes[] = 'wkr-slot--inline';
		}

		// Paosüsteem käib ainult korra, atribuudi väljastamisel — muidu muutuks
		// fondinime jutumärk stringiks &amp;quot; ja font ei laadiks.
		$inline_style = sprintf(
			'--wkr-bg:%1$s;--wkr-fg:%2$s;--wkr-hl:%3$s;--wkr-size:%4$spx;--wkr-z:%5$d;--wkr-font:%6$s;--wkr-close-offset:%7$dpx',
			sanitize_hex_color( wkr_get( $post->ID, 'bg' ) ),
			sanitize_hex_color( wkr_get( $post->ID, 'fg' ) ),
			sanitize_hex_color( wkr_get( $post->ID, 'hl' ) ),
			(float) wkr_get( $post->ID, 'size' ),
			(int) wkr_get( $post->ID, 'zindex' ),
			$stack,
			max( 0, (int) wkr_get( $post->ID, 'close_offset' ) )
		);

		// Kleepuva riba nihe alaservast. JS võib seda suurendada, kui teemal on
		// oma kleepuv alumine menüü.
		$bottom_offset = max( 0, (int) wkr_get( $post->ID, 'bottom_offset' ) );
		$inline_style .= ';--wkr-bottom:' . $bottom_offset . 'px';

		// Sulgemisnupu ja avamisnoole suurus.
		$inline_style .= ';--wkr-close-size:' . max( 8, (float) wkr_get( $post->ID, 'close_size' ) ) . 'px';
		$inline_style .= ';--wkr-expand-size:' . max( 8, (float) wkr_get( $post->ID, 'expand_size' ) ) . 'px';

		$link = wkr_text( $post->ID, 'link' );

		// Kood ei käi enam lingi külge: parameetriga aadress ei tule vahemälust.
		// Klõps jätab koodi küpsisesse (assets/front.js).
		$apply = ( $link && $coupon && wkr_get( $post->ID, 'apply_coupon' ) ) ? $coupon : '';

		ob_start();
		?>
		<div class="<?php echo esc_attr( implode( ' ', $classes ) ); ?>"
			id="<?php echo esc_attr( 'wkr-' . $id . '-' . $place ); ?>"
			style="<?php echo esc_attr( $inline_style ); ?>"
			data-wkr-id="<?php echo esc_attr( $id ); ?>"
			data-wkr-place="<?php echo esc_attr( $place ); ?>"
			data-wkr-end="<?php echo esc_attr( $end_ts ); ?>"
			data-wkr-avoid="<?php echo (int) wkr_get( $post->ID, 'avoid_bottom' ); ?>"
			data-wkr-offset="<?php echo esc_attr( $bottom_offset ); ?>"
			<?php if ( 'bottom' === $place && ! $inline ) : ?>
				data-wkr-float="<?php echo esc_attr( self::float_mode( $post->ID ) ); ?>"
				data-wkr-float-sel="<?php echo esc_attr( wkr_get( $post->ID, 'float_sel' ) ); ?>"
			<?php endif; ?>>

			<div class="wkr-banner">
				<?php if ( $link ) : ?>
					<a class="wkr-cover" href="<?php echo esc_url( $link ); ?>"<?php echo $apply ? ' data-wkr-apply="' . esc_attr( $apply ) . '"' : ''; ?>>
						<span class="screen-reader-text"><?php echo esc_html( get_the_title( $post ) ); ?></span>
					</a>
				<?php endif; ?>

				<?php foreach ( array( 'l1', 'l2', 'l3' ) as $line ) : ?>
					<?php $text = wkr_text( $post->ID, $line ); ?>
					<?php if ( '' !== $text ) : ?>
						<span class="wkr-line wkr-<?php echo esc_attr( $line ); ?>"><?php echo esc_html( $text ); ?></span>
					<?php endif; ?>
				<?php endforeach; ?>

				<?php if ( $coupon ) : ?>
					<span class="wkr-line"><?php echo self::coupon_html( $post, $coupon ); // phpcs:ignore WordPress.Security.EscapingOutput.OutputNotEscaped ?></span>
				<?php endif; ?>

				<?php
				/*
				 * Loendur on peidus, kuni kampaania lõpuni on alla 24 tunni.
				 * Otsuse teeb brauser, sest leht võib olla vahemälus päevi —
				 * serveris joonistatud olek oleks selleks ajaks vale.
				 */
				if ( wkr_get( $post->ID, 'countdown' ) && $end_ts ) :
					?>
					<span class="wkr-line wkr-line--countdown" style="display:none">
						<span class="wkr-countdown" data-wkr-end="<?php echo esc_attr( $end_ts ); ?>" data-wkr-window="<?php echo esc_attr( DAY_IN_SECONDS ); ?>"></span>
					</span>
				<?php endif; ?>

				<?php if ( $closes ) : ?>
					<button type="button" class="wkr-close" aria-label="<?php esc_attr_e( 'Sulge riba', 'wonom-kampaaniariba' ); ?>">&#10005;</button>
				<?php endif; ?>
			</div>

			<?php if ( $closes && $coupon ) : ?>
				<div class="wkr-mini">
					<?php echo self::coupon_html( $post, $coupon ); // phpcs:ignore WordPress.Security.EscapingOutput.OutputNotEscaped ?>
					<button type="button" class="wkr-expand" aria-label="<?php esc_attr_e( 'Näita pakkumist uuesti', 'wonom-kampaaniariba' ); ?>">
						<?php echo 'bottom' === $place ? '&#8963;' : '&#8964;'; ?>
					</button>
				</div>
			<?php endif; ?>
		</div>
		<?php
		/*
		 * Siin oli varem iga riba järel oma inline-skript, mis luges suletud
		 * oleku localStorage'ist. Kiirenduspluginad kirjutasid selle ümber
		 * välise defer-skriptiga, mille tõttu riba jõudis enne avatuna
		 * joonistuda. Nüüd paneb riba lehele brauseriskript, mis teab suletud
		 * olekut juba enne joonistamist.
		 */

		return trim( ob_get_clean() );
	}

	/**
	 * Mida teha teema ujuva „keri üles” nupuga.
	 *
	 * @param int $post_id Kampaania ID.
	 * @return string none | lift | behind | hide
	 */
	public static function float_mode( $post_id ) {
		$mode = wkr_get( $post_id, 'float_btn' );
		return in_array( $mode, array( 'none', 'lift', 'behind', 'hide' ), true ) ? $mode : 'none';
	}

	/**
	 * Sooduskoodi nupp.
	 *
	 * @param WP_Post $post   Kampaania.
	 * @param string  $coupon Kood.
	 * @return string
	 */
	private static function coupon_html( $post, $coupon ) {
		$label = wkr_text( $post->ID, 'coupon_label' );
		if ( '' === $label ) {
			$label = 'en' === wkr_current_lang() ? 'Coupon code' : 'Sooduskood';
		}

		$hint = 'en' === wkr_current_lang() ? 'Copy coupon code' : 'Kopeeri sooduskood';

		return sprintf(
			'<button type="button" class="wkr-coupon" data-wkr-code="%1$s" title="%2$s" aria-label="%3$s">
				<span class="wkr-coupon-label">%4$s</span><span class="wkr-code">%1$s</span>
				<svg width="11" height="11" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linejoin="round" aria-hidden="true" focusable="false"><rect x="5.4" y="5.4" width="8.9" height="8.9" rx="1.5"/><path d="M10.6 5.4V3.2a1.5 1.5 0 0 0-1.5-1.5H3.2a1.5 1.5 0 0 0-1.5 1.5v5.9a1.5 1.5 0 0 0 1.5 1.5h2.2"/></svg>
			</button>',
			esc_attr( $coupon ),
			esc_attr( $hint ),
			esc_attr( $hint . ': ' . $coupon ),
			esc_html( $label . ':' )
		);
	}

	/**
	 * Küpsis, kuhu riba klõps sooduskoodi jätab.
	 */
	const COUPON_COOKIE = 'wkr_coupon';

	/**
	 * Rakendab ostukorvis koodi, kui külastaja tuli ribalt.
	 *
	 * Kood tuleb küpsisest, mille riba klõps brauserisse jättis. Varem oli kood
	 * lingi küljes parameetrina (?wkr_coupon=KOOD), aga parameetriga aadressi
	 * ei saa vahemälust serveerida ja iga klõps pani serveri lehte nullist
	 * kokku panema. Vana kuju töötab edasi, sest vahemälus võib selliseid
	 * linke veel olla.
	 */
	public static function maybe_apply_coupon() {
		if ( is_admin() || ! function_exists( 'WC' ) ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- avalik link, mitte vormi saatmine.
		$code        = isset( $_GET['wkr_coupon'] ) ? sanitize_text_field( wp_unslash( $_GET['wkr_coupon'] ) ) : '';
		$from_cookie = false;

		if ( '' === $code && isset( $_COOKIE[ self::COUPON_COOKIE ] ) ) {
			$code        = sanitize_text_field( wp_unslash( $_COOKIE[ self::COUPON_COOKIE ] ) );
			$from_cookie = true;
		}

		if ( '' === $code || strlen( $code ) > 60 ) {
			return;
		}

		$cart = WC()->cart;
		if ( ! $cart ) {
			return;
		}

		if ( $cart->has_discount( $code ) ) {
			if ( $from_cookie ) {
				self::forget_coupon_cookie();
			}
			return;
		}

		if ( ! $from_cookie ) {
			$cart->apply_coupon( $code );
			return;
		}

		// Tühja korvi külge koodi ei pane — ootame, kuni seal midagi on.
		if ( $cart->is_empty() || ! class_exists( 'WC_Discounts' ) ) {
			return;
		}

		/*
		 * Klient ei sisestanud koodi ise, seega ei tohi ta näha ka veateadet,
		 * kui kood parajasti ei sobi (näiteks korvi summa on veel liiga väike).
		 * Kontrollime vaikselt ette; küpsis jääb alles ja proovime uuesti,
		 * kui korv muutub.
		 */
		$coupon    = new WC_Coupon( $code );
		$discounts = new WC_Discounts( $cart );

		if ( ! $coupon->get_id() || is_wp_error( $discounts->is_coupon_valid( $coupon ) ) ) {
			return;
		}

		self::forget_coupon_cookie();
		$cart->apply_coupon( $code );
	}

	/**
	 * Kustutab koodiküpsise, kui kood on korvis.
	 */
	private static function forget_coupon_cookie() {
		unset( $_COOKIE[ self::COUPON_COOKIE ] );

		if ( ! headers_sent() ) {
			setcookie( self::COUPON_COOKIE, '', time() - DAY_IN_SECONDS, '/' );
		}
	}
}
