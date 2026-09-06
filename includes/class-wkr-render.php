<?php
/**
 * Riba väljastamine poe esiküljel.
 *
 * @package Wonom_Kampaaniariba
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Renderdab kampaaniariba päisesse ja jalusesse.
 */
class WKR_Render {

	/**
	 * Kampaaniad, mis on juba välja trükitud (et lühikood ja haak ei dubleeriks).
	 *
	 * @var array<string,bool>
	 */
	private static $printed = array();

	/**
	 * Haagid.
	 */
	public static function init() {
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'assets' ) );
		add_action( 'wp_body_open', array( __CLASS__, 'top' ), 5 );
		add_action( 'wp_footer', array( __CLASS__, 'bottom' ), 5 );
		add_shortcode( 'wonom_banner', array( __CLASS__, 'shortcode' ) );

		// WooCommerce: rakenda kood, kui klient jõudis ribalt.
		add_action( 'wp_loaded', array( __CLASS__, 'maybe_apply_coupon' ), 20 );
	}

	/**
	 * Stiilid, skript ja vajadusel Google Font.
	 */
	public static function assets() {
		$active = wkr_active_campaigns();
		if ( ! $active ) {
			return;
		}

		wp_enqueue_style( 'wkr-front', WKR_URL . 'assets/front.css', array(), WKR_VERSION );
		wp_enqueue_script( 'wkr-front', WKR_URL . 'assets/front.js', array(), WKR_VERSION, true );

		wp_localize_script(
			'wkr-front',
			'WKR',
			array(
				'pad'  => (int) get_option( 'wkr_body_padding', 1 ),
				'i18n' => array(
					'copied' => 'en' === wkr_current_lang() ? 'Copied!' : 'Kopeeritud!',
					'ends'   => 'en' === wkr_current_lang() ? 'ends in' : 'lõpeb',
					'days'   => 'en' === wkr_current_lang() ? 'd' : 'p',
				),
			)
		);

		$fonts   = wkr_fonts();
		$needed  = array();
		foreach ( $active as $post ) {
			$key = wkr_get( $post->ID, 'font' );
			if ( isset( $fonts[ $key ] ) && $fonts[ $key ]['google'] ) {
				$needed[ $fonts[ $key ]['google'] ] = true;
			}
		}

		if ( $needed ) {
			$url = 'https://fonts.googleapis.com/css2?family=' . implode( '&family=', array_keys( $needed ) ) . '&display=swap';
			wp_enqueue_style( 'wkr-fonts', $url, array(), null ); // phpcs:ignore WordPress.WP.EnqueuedResourceParameters.MissingVersion
		}
	}

	/**
	 * Päise riba.
	 */
	public static function top() {
		self::output( 'top' );
	}

	/**
	 * Jaluse riba.
	 */
	public static function bottom() {
		self::output( 'bottom' );
	}

	/**
	 * Lühikood [wonom_banner id="12" position="top"].
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

		$place = 'bottom' === $atts['position'] ? 'bottom' : 'top';

		if ( $atts['id'] ) {
			$post = get_post( (int) $atts['id'] );
			if ( ! $post || WKR_CPT !== $post->post_type || 'live' !== wkr_status( $post->ID ) ) {
				return '';
			}
		} else {
			$post = self::pick( $place );
			if ( ! $post ) {
				return '';
			}
		}

		return self::markup( $post, $place, true );
	}

	/**
	 * Valib esimese sobiva kampaania antud kohta.
	 *
	 * @param string $place top | bottom.
	 * @return WP_Post|null
	 */
	private static function pick( $place ) {
		foreach ( wkr_active_campaigns() as $post ) {
			$position = wkr_get( $post->ID, 'position' );
			if ( $position === $place || 'both' === $position ) {
				return $post;
			}
		}
		return null;
	}

	/**
	 * Trükib riba, kui sinna midagi kuulub.
	 *
	 * @param string $place top | bottom.
	 */
	private static function output( $place ) {
		if ( is_admin() || isset( self::$printed[ $place ] ) ) {
			return;
		}

		$post = self::pick( $place );
		if ( ! $post ) {
			return;
		}

		self::$printed[ $place ] = true;

		echo self::markup( $post, $place ); // phpcs:ignore WordPress.Security.EscapingOutput.OutputNotEscaped -- markup() paosüsteem on sees.
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

		$classes = array( 'wkr-slot', 'wkr-slot--' . $place );
		if ( 'bottom' === $place && ! $inline ) {
			$classes[] = 'floating' === $style ? 'wkr-slot--floating' : 'wkr-slot--stuck';
		}
		if ( $inline ) {
			$classes[] = 'wkr-slot--inline';
		}

		// Paosüsteem käib ainult korra, atribuudi väljastamisel — muidu muutuks
		// fondinime jutumärk stringiks &amp;quot; ja font ei laadiks.
		$inline_style = sprintf(
			'--wkr-bg:%1$s;--wkr-fg:%2$s;--wkr-hl:%3$s;--wkr-size:%4$spx;--wkr-z:%5$d;--wkr-font:%6$s',
			sanitize_hex_color( wkr_get( $post->ID, 'bg' ) ),
			sanitize_hex_color( wkr_get( $post->ID, 'fg' ) ),
			sanitize_hex_color( wkr_get( $post->ID, 'hl' ) ),
			(float) wkr_get( $post->ID, 'size' ),
			(int) wkr_get( $post->ID, 'zindex' ),
			$stack
		);

		$link = wkr_text( $post->ID, 'link' );
		if ( $link && $coupon && wkr_get( $post->ID, 'apply_coupon' ) ) {
			$link = add_query_arg( 'wkr_coupon', rawurlencode( $coupon ), $link );
		}

		ob_start();
		?>
		<div class="<?php echo esc_attr( implode( ' ', $classes ) ); ?>"
			style="<?php echo esc_attr( $inline_style ); ?>"
			data-wkr-id="<?php echo esc_attr( $id ); ?>"
			data-wkr-place="<?php echo esc_attr( $place ); ?>">

			<div class="wkr-banner">
				<?php if ( $link ) : ?>
					<a class="wkr-cover" href="<?php echo esc_url( $link ); ?>">
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

				<?php if ( wkr_get( $post->ID, 'countdown' ) && $end_ts ) : ?>
					<span class="wkr-line">
						<span class="wkr-countdown" data-wkr-end="<?php echo esc_attr( $end_ts ); ?>"></span>
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
		<?php if ( $closes ) : ?>
			<script>/* <![CDATA[ */(function(){try{var s=document.currentScript.previousElementSibling;if(s&&localStorage.getItem('wkr_d_'+s.getAttribute('data-wkr-id')+'_'+s.getAttribute('data-wkr-place'))){s.className+=' is-collapsed';}}catch(e){}})();/* ]]> */</script>
			<?php
		endif;

		return trim( ob_get_clean() );
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
	 * Rakendab ostukorvis koodi, kui külastaja tuli ribalt.
	 */
	public static function maybe_apply_coupon() {
		if ( is_admin() || ! function_exists( 'WC' ) ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- avalik link, mitte vormi saatmine.
		$code = isset( $_GET['wkr_coupon'] ) ? sanitize_text_field( wp_unslash( $_GET['wkr_coupon'] ) ) : '';
		if ( '' === $code ) {
			return;
		}

		$cart = WC()->cart;
		if ( ! $cart || $cart->has_discount( $code ) ) {
			return;
		}

		$cart->apply_coupon( $code );
	}
}
