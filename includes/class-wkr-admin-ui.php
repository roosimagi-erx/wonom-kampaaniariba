<?php
/**
 * Administraatori kesta kujundus: body-klass ja ülemine riba.
 *
 * Sama muster mis Wonom Feedil — kujundus rakendub ainult selle plugina
 * ekraanidel, mujal jääb WordPressi admin täpselt selliseks nagu oli.
 *
 * @package Wonom_Kampaaniariba
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Plugina ekraanide kest.
 */
class WKR_Admin_UI {

	/**
	 * Haagid.
	 */
	public static function init() {
		add_filter( 'admin_body_class', array( __CLASS__, 'body_class' ) );
		add_action( 'in_admin_header', array( __CLASS__, 'appbar' ) );
	}

	/**
	 * Kas oleme plugina ekraanil.
	 *
	 * @return bool
	 */
	public static function is_screen() {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;

		return $screen && WKR_CPT === $screen->post_type;
	}

	/**
	 * Lisab kujunduse klassi ainult meie ekraanidele.
	 *
	 * @param string $classes Senised klassid.
	 * @return string
	 */
	public static function body_class( $classes ) {
		return self::is_screen() ? $classes . ' wkr-app' : $classes;
	}

	/**
	 * Alamlehe aadress.
	 *
	 * @param string $page Alamlehe võti.
	 * @return string
	 */
	public static function url( $page ) {
		return add_query_arg(
			array(
				'post_type' => WKR_CPT,
				'page'      => $page,
			),
			admin_url( 'edit.php' )
		);
	}

	/**
	 * Ülemine riba: nimi, navigatsioon, „Uus kampaania”.
	 */
	public static function appbar() {
		if ( ! self::is_screen() ) {
			return;
		}

		$screen = get_current_screen();

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- ainult aktiivse vahekaardi märkimiseks.
		$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';

		$active = $page ? $page : 'campaigns';

		// Muutmisvaates ei ole ükski vahekaart aktiivne.
		if ( $screen && in_array( $screen->base, array( 'post' ), true ) ) {
			$active = '';
		}

		$items = array(
			'campaigns'    => array(
				__( 'Kampaaniad', 'wonom-kampaaniariba' ),
				admin_url( 'edit.php?post_type=' . WKR_CPT ),
			),
			'wkr-calendar' => array(
				__( 'Kalender', 'wonom-kampaaniariba' ),
				self::url( 'wkr-calendar' ),
			),
		);

		if ( current_user_can( 'manage_options' ) ) {
			$items['wkr-settings'] = array(
				__( 'Seaded', 'wonom-kampaaniariba' ),
				self::url( 'wkr-settings' ),
			);
		}
		?>
		<div class="wkr-appbar">
			<a class="wkr-brand" href="<?php echo esc_url( admin_url( 'edit.php?post_type=' . WKR_CPT ) ); ?>">
				<span class="wkr-logo" aria-hidden="true"><span class="dashicons dashicons-megaphone"></span></span>
				<span class="wkr-brand-name">Wonom Kampaaniariba</span>
				<span class="wkr-brand-version"><?php echo esc_html( WKR_VERSION ); ?></span>
			</a>
			<nav class="wkr-nav" aria-label="Wonom Kampaaniariba">
				<?php foreach ( $items as $key => $item ) : ?>
					<a href="<?php echo esc_url( $item[1] ); ?>" class="<?php echo $key === $active ? 'is-active' : ''; ?>">
						<?php echo esc_html( $item[0] ); ?>
					</a>
				<?php endforeach; ?>
			</nav>
			<?php if ( ! $screen || 'add' !== $screen->action ) : ?>
				<a class="button button-primary wkr-new" href="<?php echo esc_url( admin_url( 'post-new.php?post_type=' . WKR_CPT ) ); ?>">
					+ <?php esc_html_e( 'Uus kampaania', 'wonom-kampaaniariba' ); ?>
				</a>
			<?php endif; ?>
		</div>
		<?php
	}
}
