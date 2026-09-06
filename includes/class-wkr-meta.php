<?php
/**
 * Kampaania seadete vorm ja salvestamine.
 *
 * @package Wonom_Kampaaniariba
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Meta box: sisu, kujundus, ajastus.
 */
class WKR_Meta {

	/**
	 * Haagid.
	 */
	public static function init() {
		add_action( 'add_meta_boxes', array( __CLASS__, 'boxes' ) );
		add_action( 'edit_form_after_title', array( __CLASS__, 'nonce' ) );
		add_action( 'save_post_' . WKR_CPT, array( __CLASS__, 'save' ), 10, 2 );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'assets' ) );
	}

	/**
	 * Skriptid ja stiilid ainult kampaania muutmise ekraanil.
	 *
	 * @param string $hook Ekraani identifikaator.
	 */
	public static function assets( $hook ) {
		$screen = get_current_screen();

		$is_editor   = in_array( $hook, array( 'post.php', 'post-new.php' ), true ) && $screen && WKR_CPT === $screen->post_type;
		$is_list     = 'edit.php' === $hook && $screen && WKR_CPT === $screen->post_type;
		$is_calendar = strpos( (string) $hook, 'wkr-calendar' ) !== false;
		$is_settings = strpos( (string) $hook, 'wkr-settings' ) !== false;

		if ( ! $is_editor && ! $is_list && ! $is_calendar && ! $is_settings ) {
			return;
		}

		wp_enqueue_style( 'wkr-admin', WKR_URL . 'assets/admin.css', array(), WKR_VERSION );

		if ( ! $is_editor ) {
			return;
		}

		wp_enqueue_style( 'wp-color-picker' );
		wp_enqueue_style( 'wkr-front', WKR_URL . 'assets/front.css', array(), WKR_VERSION );
		wp_enqueue_script( 'wkr-admin', WKR_URL . 'assets/admin.js', array( 'wp-color-picker' ), WKR_VERSION, true );

		$fonts = array();
		foreach ( wkr_fonts() as $key => $font ) {
			$fonts[ $key ] = $font['stack'];
		}

		wp_localize_script(
			'wkr-admin',
			'WKR_ADMIN',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'wkr_translate' ),
				'fonts'   => $fonts,
				'months'  => array(
					__( 'jaanuar', 'wonom-kampaaniariba' ),
					__( 'veebruar', 'wonom-kampaaniariba' ),
					__( 'märts', 'wonom-kampaaniariba' ),
					__( 'aprill', 'wonom-kampaaniariba' ),
					__( 'mai', 'wonom-kampaaniariba' ),
					__( 'juuni', 'wonom-kampaaniariba' ),
					__( 'juuli', 'wonom-kampaaniariba' ),
					__( 'august', 'wonom-kampaaniariba' ),
					__( 'september', 'wonom-kampaaniariba' ),
					__( 'oktoober', 'wonom-kampaaniariba' ),
					__( 'november', 'wonom-kampaaniariba' ),
					__( 'detsember', 'wonom-kampaaniariba' ),
				),
				'dow'     => array( 'E', 'T', 'K', 'N', 'R', 'L', 'P' ),
				'i18n'    => array(
					'translating' => __( 'Tõlgin…', 'wonom-kampaaniariba' ),
					'failed'      => __( 'Tõlkimine ebaõnnestus.', 'wonom-kampaaniariba' ),
					'now'         => __( 'Praegu', 'wonom-kampaaniariba' ),
					'done'        => __( 'Valmis', 'wonom-kampaaniariba' ),
					'copy'        => __( 'Kopeeri sooduskood', 'wonom-kampaaniariba' ),
				),
			)
		);
	}

	/**
	 * Turvatunnus. Eraldi haagi taga, et see ei kaoks meta boxi peitmisel.
	 *
	 * @param WP_Post $post Postitus.
	 */
	public static function nonce( $post ) {
		if ( WKR_CPT !== $post->post_type ) {
			return;
		}
		wp_nonce_field( 'wkr_save_' . $post->ID, 'wkr_nonce' );
	}

	/**
	 * Registreerib kastid.
	 */
	public static function boxes() {
		add_meta_box(
			'wkr_preview',
			__( 'Eelvaade', 'wonom-kampaaniariba' ),
			array( __CLASS__, 'render_preview' ),
			WKR_CPT,
			'normal',
			'high'
		);

		add_meta_box(
			'wkr_content',
			__( 'Sisu', 'wonom-kampaaniariba' ),
			array( __CLASS__, 'render_content' ),
			WKR_CPT,
			'normal',
			'high'
		);

		add_meta_box(
			'wkr_woo',
			__( 'WooCommerce kupong', 'wonom-kampaaniariba' ),
			array( __CLASS__, 'render_woo' ),
			WKR_CPT,
			'normal',
			'default'
		);

		add_meta_box(
			'wkr_design',
			__( 'Kujundus', 'wonom-kampaaniariba' ),
			array( __CLASS__, 'render_design' ),
			WKR_CPT,
			'normal',
			'default'
		);

		add_meta_box(
			'wkr_schedule',
			__( 'Ajastus', 'wonom-kampaaniariba' ),
			array( __CLASS__, 'render_schedule' ),
			WKR_CPT,
			'side',
			'high'
		);
	}

	/**
	 * Elav eelvaade.
	 *
	 * @param WP_Post $post Kampaania.
	 */
	public static function render_preview( $post ) {
		?>
		<div class="wkr-preview-wrap">
			<div class="wkr-preview-tools">
				<div class="wkr-switch" role="group" aria-label="<?php esc_attr_e( 'Eelvaate keel', 'wonom-kampaaniariba' ); ?>">
					<?php foreach ( wkr_langs() as $code => $label ) : ?>
						<button type="button" data-wkr-prevlang="<?php echo esc_attr( $code ); ?>" aria-pressed="<?php echo 'et' === $code ? 'true' : 'false'; ?>"><?php echo esc_html( strtoupper( $code ) ); ?></button>
					<?php endforeach; ?>
				</div>
				<span class="wkr-hint"><?php esc_html_e( 'Eelvaade uueneb kohe, kui midagi allpool muudad.', 'wonom-kampaaniariba' ); ?></span>
			</div>
			<div class="wkr-preview-stage"><div id="wkr-preview"></div></div>
		</div>
		<?php
	}

	/**
	 * Sisu: sooduskood ja keelepõhised tekstid.
	 *
	 * @param WP_Post $post Kampaania.
	 */
	public static function render_content( $post ) {
		$coupon  = wkr_get( $post->ID, 'coupon' );
		$apply   = wkr_get( $post->ID, 'apply_coupon' );
		$en_auto = wkr_get( $post->ID, 'en_auto' );
		$langs   = wkr_langs();
		$fields  = wkr_text_fields();
		?>
		<div class="wkr-grid wkr-grid--2">
			<p class="wkr-field">
				<label for="wkr_coupon"><?php esc_html_e( 'Sooduskood', 'wonom-kampaaniariba' ); ?></label>
				<input type="text" id="wkr_coupon" name="wkr[coupon]" value="<?php echo esc_attr( $coupon ); ?>" placeholder="AUGUST" data-wkr="coupon">
				<span class="wkr-hint"><?php esc_html_e( 'Sama mõlemas keeles. Tühi = koodikasti ribal ei näidata.', 'wonom-kampaaniariba' ); ?></span>
			</p>
			<p class="wkr-field">
				<label><?php esc_html_e( 'WooCommerce', 'wonom-kampaaniariba' ); ?></label>
				<label class="wkr-check">
					<input type="checkbox" name="wkr[apply_coupon]" value="1" <?php checked( $apply, 1 ); ?>>
					<?php esc_html_e( 'Rakenda kood ostukorvis, kui klient ribal klõpsab', 'wonom-kampaaniariba' ); ?>
				</label>
				<span class="wkr-hint"><?php esc_html_e( 'Lisab lingile ?wkr_coupon=KOOD ja paneb soodustuse kohe korvi.', 'wonom-kampaaniariba' ); ?></span>
			</p>
		</div>

		<div class="wkr-langbar">
			<div class="wkr-switch" role="group" aria-label="<?php esc_attr_e( 'Teksti keel', 'wonom-kampaaniariba' ); ?>">
				<?php foreach ( $langs as $code => $label ) : ?>
					<button type="button" data-wkr-tab="<?php echo esc_attr( $code ); ?>" aria-pressed="<?php echo 'et' === $code ? 'true' : 'false'; ?>"><?php echo esc_html( $label ); ?></button>
				<?php endforeach; ?>
			</div>
			<span class="wkr-spacer"></span>
			<button type="button" class="button" id="wkr-translate" data-post="<?php echo esc_attr( $post->ID ); ?>">
				<?php esc_html_e( 'Tõlgi eesti keelest', 'wonom-kampaaniariba' ); ?>
			</button>
		</div>

		<input type="hidden" name="wkr[en_auto]" id="wkr_en_auto" value="<?php echo esc_attr( $en_auto ); ?>">
		<div class="wkr-autonote" id="wkr-autonote" <?php echo $en_auto ? '' : 'hidden'; ?>>
			<?php esc_html_e( 'Automaatne tõlge — loe üle ja muuda vabalt. Käsitsi muutmine eemaldab selle märke.', 'wonom-kampaaniariba' ); ?>
		</div>

		<?php foreach ( $langs as $code => $label ) : ?>
			<div class="wkr-panel" data-wkr-panel="<?php echo esc_attr( $code ); ?>" <?php echo 'et' === $code ? '' : 'hidden'; ?>>
				<?php foreach ( $fields as $key => $field_label ) : ?>
					<?php $value = get_post_meta( $post->ID, '_wkr_' . $code . '_' . $key, true ); ?>
					<p class="wkr-field">
						<label for="wkr_<?php echo esc_attr( $code . '_' . $key ); ?>"><?php echo esc_html( $field_label ); ?></label>
						<input type="text"
							id="wkr_<?php echo esc_attr( $code . '_' . $key ); ?>"
							name="wkr_t[<?php echo esc_attr( $code ); ?>][<?php echo esc_attr( $key ); ?>]"
							value="<?php echo esc_attr( $value ); ?>"
							data-wkr-text="<?php echo esc_attr( $code . '.' . $key ); ?>"
							class="wkr-wide">
					</p>
				<?php endforeach; ?>
				<?php if ( 'et' !== $code ) : ?>
					<p class="wkr-hint">
						<?php esc_html_e( 'Tühjaks jäetud väli võtab eestikeelse teksti — riba ei jää kunagi tühjaks.', 'wonom-kampaaniariba' ); ?>
					</p>
				<?php endif; ?>
			</div>
		<?php endforeach; ?>
		<?php
	}

	/**
	 * WooCommerce'i kupong.
	 *
	 * @param WP_Post $post Kampaania.
	 */
	public static function render_woo( $post ) {
		$mode      = wkr_get( $post->ID, 'wc_mode' );
		$code      = WKR_Coupon::format_code( wkr_get( $post->ID, 'coupon' ) );
		$woo       = WKR_Coupon::woo_active();
		$owned_id  = $woo ? WKR_Coupon::owned_id( $post->ID ) : 0;
		$existing  = ( $woo && $code ) ? (int) wc_get_coupon_id_by_code( $code ) : 0;
		$foreign   = $existing && $existing !== $owned_id && ! (int) get_post_meta( $existing, WKR_Coupon::OWNER_META, true );

		if ( ! $woo ) {
			echo '<p class="wkr-warn">' . esc_html__( 'WooCommerce ei ole aktiivne. Riba töötab edasi, aga kupongi luua ei saa.', 'wonom-kampaaniariba' ) . '</p>';
			return;
		}
		?>
		<p class="wkr-field">
			<label for="wkr_wc_mode"><?php esc_html_e( 'Mida pluginaga kupongiga teha', 'wonom-kampaaniariba' ); ?></label>
			<select id="wkr_wc_mode" name="wkr[wc_mode]" data-wkr="wc_mode">
				<option value="none" <?php selected( $mode, 'none' ); ?>>
					<?php esc_html_e( 'Ainult näitan koodi ribal (kupong on WooCommerce’is käsitsi tehtud)', 'wonom-kampaaniariba' ); ?>
				</option>
				<option value="manage" <?php selected( $mode, 'manage' ); ?>>
					<?php esc_html_e( 'Loo ja hoia WooCommerce kupong siit', 'wonom-kampaaniariba' ); ?>
				</option>
			</select>
		</p>

		<div class="wkr-woo-status">
			<?php if ( ! $code ) : ?>
				<span class="wkr-pill wkr-pill--off"><?php esc_html_e( 'Koodi pole sisestatud', 'wonom-kampaaniariba' ); ?></span>
			<?php elseif ( $owned_id ) : ?>
				<span class="wkr-pill wkr-pill--live"><?php esc_html_e( 'Kupong on olemas ja seda haldab see kampaania', 'wonom-kampaaniariba' ); ?></span>
				<a href="<?php echo esc_url( get_edit_post_link( $owned_id ) ); ?>"><?php esc_html_e( 'Ava WooCommerce’is', 'wonom-kampaaniariba' ); ?></a>
			<?php elseif ( $existing ) : ?>
				<span class="wkr-pill wkr-pill--upcoming"><?php esc_html_e( 'Selle koodiga kupong on WooCommerce’is juba olemas', 'wonom-kampaaniariba' ); ?></span>
				<a href="<?php echo esc_url( get_edit_post_link( $existing ) ); ?>"><?php esc_html_e( 'Ava WooCommerce’is', 'wonom-kampaaniariba' ); ?></a>
			<?php else : ?>
				<span class="wkr-pill wkr-pill--off"><?php esc_html_e( 'Sellist kupongi WooCommerce’is veel ei ole', 'wonom-kampaaniariba' ); ?></span>
			<?php endif; ?>
		</div>

		<div class="wkr-woo-fields" <?php echo 'manage' === $mode ? '' : 'hidden'; ?>>
			<?php if ( $foreign ) : ?>
				<p class="wkr-warn">
					<?php
					printf(
						/* translators: %s: coupon code */
						esc_html__( 'Kood „%s” on WooCommerce’is juba olemas ja selle on keegi käsitsi teinud. Plugin ei muuda seda ilma sinu loata.', 'wonom-kampaaniariba' ),
						esc_html( $code )
					);
					?>
				</p>
				<p class="wkr-field">
					<label class="wkr-check">
						<input type="checkbox" name="wkr[wc_takeover]" value="1">
						<?php esc_html_e( 'Võta olemasolev kupong üle ja hakka seda siit haldama', 'wonom-kampaaniariba' ); ?>
					</label>
				</p>
			<?php endif; ?>

			<div class="wkr-grid wkr-grid--3">
				<p class="wkr-field">
					<label for="wkr_wc_type"><?php esc_html_e( 'Soodustuse liik', 'wonom-kampaaniariba' ); ?></label>
					<select id="wkr_wc_type" name="wkr[wc_type]">
						<option value="percent" <?php selected( wkr_get( $post->ID, 'wc_type' ), 'percent' ); ?>><?php esc_html_e( 'Protsendipõhine allahindlus', 'wonom-kampaaniariba' ); ?></option>
						<option value="fixed_cart" <?php selected( wkr_get( $post->ID, 'wc_type' ), 'fixed_cart' ); ?>><?php esc_html_e( 'Kindel ostukorvi allahindluse summa', 'wonom-kampaaniariba' ); ?></option>
					</select>
				</p>
				<p class="wkr-field">
					<label for="wkr_wc_amount"><?php esc_html_e( 'Kupongi väärtus', 'wonom-kampaaniariba' ); ?></label>
					<input type="number" id="wkr_wc_amount" name="wkr[wc_amount]" min="0" step="0.01"
						value="<?php echo esc_attr( wkr_get( $post->ID, 'wc_amount' ) ); ?>">
				</p>
				<p class="wkr-field">
					<label for="wkr_wc_min"><?php esc_html_e( 'Vähim ostukorvi summa', 'wonom-kampaaniariba' ); ?></label>
					<input type="number" id="wkr_wc_min" name="wkr[wc_min]" min="0" step="0.01"
						value="<?php echo esc_attr( wkr_get( $post->ID, 'wc_min' ) ); ?>">
					<span class="wkr-hint"><?php esc_html_e( '0 = piirangut ei ole', 'wonom-kampaaniariba' ); ?></span>
				</p>
			</div>

			<div class="wkr-grid wkr-grid--3">
				<p class="wkr-field">
					<label for="wkr_wc_limit"><?php esc_html_e( 'Kasutuskordi kokku', 'wonom-kampaaniariba' ); ?></label>
					<input type="number" id="wkr_wc_limit" name="wkr[wc_limit]" min="0" step="1"
						value="<?php echo esc_attr( wkr_get( $post->ID, 'wc_limit' ) ); ?>">
					<span class="wkr-hint"><?php esc_html_e( '0 = piiramatu', 'wonom-kampaaniariba' ); ?></span>
				</p>
				<p class="wkr-field">
					<label for="wkr_wc_limit_user"><?php esc_html_e( 'Kasutuskordi kliendi kohta', 'wonom-kampaaniariba' ); ?></label>
					<input type="number" id="wkr_wc_limit_user" name="wkr[wc_limit_user]" min="0" step="1"
						value="<?php echo esc_attr( wkr_get( $post->ID, 'wc_limit_user' ) ); ?>">
					<span class="wkr-hint"><?php esc_html_e( '0 = piiramatu', 'wonom-kampaaniariba' ); ?></span>
				</p>
				<p class="wkr-field">
					<label><?php esc_html_e( 'Lisavalikud', 'wonom-kampaaniariba' ); ?></label>
					<label class="wkr-check">
						<input type="checkbox" name="wkr[wc_individual]" value="1" <?php checked( wkr_get( $post->ID, 'wc_individual' ), 1 ); ?>>
						<?php esc_html_e( 'Ainult üksinda kasutatav', 'wonom-kampaaniariba' ); ?>
					</label>
					<label class="wkr-check">
						<input type="checkbox" name="wkr[wc_free_ship]" value="1" <?php checked( wkr_get( $post->ID, 'wc_free_ship' ), 1 ); ?>>
						<?php esc_html_e( 'Annab tasuta tarne', 'wonom-kampaaniariba' ); ?>
					</label>
				</p>
			</div>

			<p class="wkr-hint">
				<?php esc_html_e( 'Kupongi aegumiskuupäev tuleb kampaania lõpuajast — seda eraldi sisestada ei ole vaja. Enne kampaania algust kood ei kehti, isegi kui keegi selle ära arvab: WooCommerce’il endal ei ole „kehtib alates” välja, seda hoiab plugin.', 'wonom-kampaaniariba' ); ?>
			</p>
			<p class="wkr-hint">
				<?php esc_html_e( 'Kupong luuakse kampaania salvestamisel. Toote- ja kategooriapiirangud ning muud peenemad seaded saad lisada WooCommerce’i kupongi enda all — plugin neid üle ei kirjuta.', 'wonom-kampaaniariba' ); ?>
			</p>
		</div>
		<?php
	}

	/**
	 * Kujundus.
	 *
	 * @param WP_Post $post Kampaania.
	 */
	public static function render_design( $post ) {
		$fonts = wkr_fonts();
		?>
		<div class="wkr-grid wkr-grid--3">
			<?php
			$colors = array(
				'bg' => __( 'Taustavärv', 'wonom-kampaaniariba' ),
				'fg' => __( 'Tekstivärv', 'wonom-kampaaniariba' ),
				'hl' => __( 'Esiletõst (1. rida)', 'wonom-kampaaniariba' ),
			);
			foreach ( $colors as $key => $label ) :
				?>
				<p class="wkr-field">
					<label for="wkr_<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $label ); ?></label>
					<input type="text" class="wkr-color" id="wkr_<?php echo esc_attr( $key ); ?>"
						name="wkr[<?php echo esc_attr( $key ); ?>]"
						value="<?php echo esc_attr( wkr_get( $post->ID, $key ) ); ?>"
						data-wkr="<?php echo esc_attr( $key ); ?>">
				</p>
			<?php endforeach; ?>
		</div>

		<div class="wkr-grid wkr-grid--3">
			<p class="wkr-field">
				<label for="wkr_font"><?php esc_html_e( 'Font', 'wonom-kampaaniariba' ); ?></label>
				<select id="wkr_font" name="wkr[font]" data-wkr="font">
					<?php foreach ( $fonts as $key => $font ) : ?>
						<option value="<?php echo esc_attr( $key ); ?>" <?php selected( wkr_get( $post->ID, 'font' ), $key ); ?>>
							<?php echo esc_html( $font['label'] ); ?>
						</option>
					<?php endforeach; ?>
				</select>
			</p>
			<p class="wkr-field">
				<label for="wkr_size"><?php esc_html_e( 'Kirja suurus (px)', 'wonom-kampaaniariba' ); ?></label>
				<input type="number" id="wkr_size" name="wkr[size]" min="10" max="24" step="0.5"
					value="<?php echo esc_attr( wkr_get( $post->ID, 'size' ) ); ?>" data-wkr="size">
			</p>
			<p class="wkr-field">
				<label for="wkr_zindex"><?php esc_html_e( 'Kihi järjekord (z-index)', 'wonom-kampaaniariba' ); ?></label>
				<input type="number" id="wkr_zindex" name="wkr[zindex]" min="1" max="9999" step="1"
					value="<?php echo esc_attr( wkr_get( $post->ID, 'zindex' ) ); ?>" data-wkr="zindex">
				<span class="wkr-hint"><?php esc_html_e( 'Ostukorvi paneel ja modaalid on enamikus teemades 400–600. Hoia riba sellest allpool, muidu katab riba ostukorvi. Soovitus: 90.', 'wonom-kampaaniariba' ); ?></span>
				<span class="wkr-warn" id="wkr-zwarn" hidden>
					<?php esc_html_e( 'Nii kõrge kiht katab tõenäoliselt ostukorvi paneeli ja modaalaknad.', 'wonom-kampaaniariba' ); ?>
				</span>
			</p>
		</div>

		<div class="wkr-grid wkr-grid--2">
			<p class="wkr-field">
				<label for="wkr_position"><?php esc_html_e( 'Asukoht', 'wonom-kampaaniariba' ); ?></label>
				<select id="wkr_position" name="wkr[position]" data-wkr="position">
					<option value="top" <?php selected( wkr_get( $post->ID, 'position' ), 'top' ); ?>><?php esc_html_e( 'Päises (lehe ülaservas)', 'wonom-kampaaniariba' ); ?></option>
					<option value="bottom" <?php selected( wkr_get( $post->ID, 'position' ), 'bottom' ); ?>><?php esc_html_e( 'Jaluses (kleepub alla)', 'wonom-kampaaniariba' ); ?></option>
					<option value="both" <?php selected( wkr_get( $post->ID, 'position' ), 'both' ); ?>><?php esc_html_e( 'Mõlemas', 'wonom-kampaaniariba' ); ?></option>
				</select>
			</p>
			<p class="wkr-field">
				<label for="wkr_style"><?php esc_html_e( 'Alumise riba stiil', 'wonom-kampaaniariba' ); ?></label>
				<select id="wkr_style" name="wkr[style]" data-wkr="style">
					<option value="full" <?php selected( wkr_get( $post->ID, 'style' ), 'full' ); ?>><?php esc_html_e( 'Üle ekraani laiune', 'wonom-kampaaniariba' ); ?></option>
					<option value="floating" <?php selected( wkr_get( $post->ID, 'style' ), 'floating' ); ?>><?php esc_html_e( 'Ujuv kaart servadega', 'wonom-kampaaniariba' ); ?></option>
				</select>
			</p>
		</div>

		<fieldset class="wkr-toggles">
			<legend class="screen-reader-text"><?php esc_html_e( 'Valikud', 'wonom-kampaaniariba' ); ?></legend>
			<label class="wkr-check">
				<input type="checkbox" name="wkr[enabled]" value="1" <?php checked( wkr_get( $post->ID, 'enabled' ), 1 ); ?> data-wkr="enabled">
				<?php esc_html_e( 'Kampaania sisse lülitatud', 'wonom-kampaaniariba' ); ?>
			</label>
			<label class="wkr-check">
				<input type="checkbox" name="wkr[dismissible]" value="1" <?php checked( wkr_get( $post->ID, 'dismissible' ), 1 ); ?> data-wkr="dismissible">
				<?php esc_html_e( 'Külastaja saab sulgeda', 'wonom-kampaaniariba' ); ?>
			</label>
			<label class="wkr-check">
				<input type="checkbox" name="wkr[countdown]" value="1" <?php checked( wkr_get( $post->ID, 'countdown' ), 1 ); ?> data-wkr="countdown">
				<?php esc_html_e( 'Näita lõpuni jäänud aega', 'wonom-kampaaniariba' ); ?>
			</label>
		</fieldset>
		<p class="wkr-hint">
			<?php esc_html_e( 'Kui külastaja riba sulgeb ja sooduskood on määratud, jääb alles kitsas riba ainult koodiga.', 'wonom-kampaaniariba' ); ?>
		</p>
		<?php
	}

	/**
	 * Ajastus.
	 *
	 * @param WP_Post $post Kampaania.
	 */
	public static function render_schedule( $post ) {
		$tz    = wkr_get( $post->ID, 'tz' );
		$tz    = $tz ? $tz : wkr_default_tz();
		$start = wkr_get( $post->ID, 'start_local' );
		$end   = wkr_get( $post->ID, 'end_local' );
		?>
		<p class="wkr-field">
			<label for="wkr_start"><?php esc_html_e( 'Algab', 'wonom-kampaaniariba' ); ?></label>
			<span class="wkr-dt">
				<input type="text" id="wkr_start" name="wkr[start_local]" class="wkr-date"
					value="<?php echo esc_attr( wkr_pretty( $start ) ); ?>" placeholder="pp.kk.aaaa hh:mm" autocomplete="off">
			</span>
		</p>
		<p class="wkr-field">
			<label for="wkr_end"><?php esc_html_e( 'Lõpeb', 'wonom-kampaaniariba' ); ?></label>
			<span class="wkr-dt">
				<input type="text" id="wkr_end" name="wkr[end_local]" class="wkr-date"
					value="<?php echo esc_attr( wkr_pretty( $end ) ); ?>" placeholder="pp.kk.aaaa hh:mm" autocomplete="off">
			</span>
		</p>
		<p class="wkr-field">
			<label for="wkr_tz"><?php esc_html_e( 'Ajavöönd', 'wonom-kampaaniariba' ); ?></label>
			<select id="wkr_tz" name="wkr[tz]">
				<?php foreach ( timezone_identifiers_list() as $zone ) : ?>
					<option value="<?php echo esc_attr( $zone ); ?>" <?php selected( $tz, $zone ); ?>><?php echo esc_html( $zone ); ?></option>
				<?php endforeach; ?>
			</select>
			<span class="wkr-hint">
				<?php esc_html_e( 'Kellaajad on selle vööndi seinakella järgi. Suve- ja talveaja vahetus arvestatakse automaatselt.', 'wonom-kampaaniariba' ); ?>
			</span>
		</p>
		<?php if ( $start && $end ) : ?>
			<p class="wkr-hint">
				<?php
				printf(
					/* translators: %s: campaign status */
					esc_html__( 'Praegune olek: %s', 'wonom-kampaaniariba' ),
					'<strong>' . esc_html( wkr_status( $post->ID ) ) . '</strong>'
				);
				?>
			</p>
		<?php endif; ?>
		<?php
	}

	/**
	 * Salvestab kampaania väljad.
	 *
	 * @param int     $post_id Kampaania ID.
	 * @param WP_Post $post    Postitus.
	 */
	public static function save( $post_id, $post ) {
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}
		if ( wp_is_post_revision( $post_id ) ) {
			return;
		}
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		$nonce = isset( $_POST['wkr_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['wkr_nonce'] ) ) : '';
		if ( ! $nonce || ! wp_verify_nonce( $nonce, 'wkr_save_' . $post_id ) ) {
			return;
		}

		$raw    = isset( $_POST['wkr'] ) ? wp_unslash( $_POST['wkr'] ) : array();
		$schema = wkr_meta_schema();

		foreach ( $schema as $key => $def ) {
			$value = isset( $raw[ $key ] ) ? $raw[ $key ] : '';

			switch ( $def['type'] ) {
				case 'bool':
					$value = ! empty( $value ) ? 1 : 0;
					break;
				case 'int':
					$value = (int) $value;
					break;
				case 'float':
					$value = (float) $value;
					break;
				case 'color':
					$clean = sanitize_hex_color( is_string( $value ) ? $value : '' );
					$value = $clean ? $clean : $def['default'];
					break;
				case 'key':
					$value = sanitize_key( is_string( $value ) ? $value : '' );
					$value = $value ? $value : $def['default'];
					break;
				case 'datetime':
					$value = wkr_parse_display( is_string( $value ) ? $value : '' );
					break;
				default:
					$value = sanitize_text_field( is_string( $value ) ? $value : '' );
			}

			update_post_meta( $post_id, '_wkr_' . $key, $value );
		}

		// Ajavöönd peab olema päris IANA nimi.
		$tz = isset( $raw['tz'] ) ? sanitize_text_field( $raw['tz'] ) : '';
		if ( ! in_array( $tz, timezone_identifiers_list(), true ) ) {
			$tz = wkr_default_tz();
		}
		update_post_meta( $post_id, '_wkr_tz', $tz );

		// Ajatemplid arvutame salvestamisel välja, et päringud oleksid kiired ja
		// suveaja nihe ei saaks hiljem tulemust muuta.
		$start_local = wkr_parse_display( isset( $raw['start_local'] ) ? $raw['start_local'] : '' );
		$end_local   = wkr_parse_display( isset( $raw['end_local'] ) ? $raw['end_local'] : '' );

		update_post_meta( $post_id, '_wkr_start_utc', wkr_local_to_ts( $start_local, $tz ) );
		update_post_meta( $post_id, '_wkr_end_utc', wkr_local_to_ts( $end_local, $tz ) );

		// Keelepõhised tekstid.
		$texts = isset( $_POST['wkr_t'] ) ? wp_unslash( $_POST['wkr_t'] ) : array();
		foreach ( wkr_langs() as $code => $lang_label ) {
			foreach ( wkr_text_fields() as $field => $field_label ) {
				$value = isset( $texts[ $code ][ $field ] ) ? $texts[ $code ][ $field ] : '';
				$value = is_string( $value ) ? $value : '';

				if ( 'link' === $field ) {
					$value = '' === trim( $value ) ? '' : esc_url_raw( trim( $value ) );
				} else {
					$value = sanitize_text_field( $value );
				}

				update_post_meta( $post_id, '_wkr_' . $code . '_' . $field, $value );
			}
		}

		// WooCommerce'i kupong luuakse või uuendatakse alles siis, kui kõik
		// ülejäänud väljad on juba salvestatud — sünkroonimine loeb neid.
		$result = WKR_Coupon::sync( $post_id );
		if ( in_array( $result['status'], array( 'conflict', 'error' ), true ) ) {
			WKR_Coupon::remember_notice( $result['status'], $result['message'] );
		}
	}
}
