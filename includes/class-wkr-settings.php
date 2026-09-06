<?php
/**
 * Plugina seadete leht.
 *
 * @package Wonom_Kampaaniariba
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Seaded: kihi vaikeväärtus, jaluse ruum, tõlkevõti.
 */
class WKR_Settings {

	/**
	 * Haagid.
	 */
	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ) );
		add_action( 'admin_init', array( __CLASS__, 'register' ) );
	}

	/**
	 * Alammenüü.
	 */
	public static function menu() {
		add_submenu_page(
			'edit.php?post_type=' . WKR_CPT,
			__( 'Kampaaniariba seaded', 'wonom-kampaaniariba' ),
			__( 'Seaded', 'wonom-kampaaniariba' ),
			'manage_options',
			'wkr-settings',
			array( __CLASS__, 'page' )
		);
	}

	/**
	 * Seadete registreerimine.
	 */
	public static function register() {
		register_setting(
			'wkr_settings',
			'wkr_default_zindex',
			array(
				'type'              => 'integer',
				'sanitize_callback' => 'absint',
				'default'           => 90,
			)
		);

		register_setting(
			'wkr_settings',
			'wkr_body_padding',
			array(
				'type'              => 'boolean',
				'sanitize_callback' => array( __CLASS__, 'sanitize_bool' ),
				'default'           => 1,
			)
		);

		register_setting(
			'wkr_settings',
			'wkr_deepl_key',
			array(
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
				'default'           => '',
			)
		);

		register_setting(
			'wkr_settings',
			'wkr_always_exclude_products',
			array(
				'type'              => 'array',
				'sanitize_callback' => array( __CLASS__, 'sanitize_ids' ),
				'default'           => array(),
			)
		);

		register_setting(
			'wkr_settings',
			'wkr_always_exclude_cats',
			array(
				'type'              => 'array',
				'sanitize_callback' => array( __CLASS__, 'sanitize_ids' ),
				'default'           => array(),
			)
		);

		register_setting(
			'wkr_settings',
			'wkr_update_source',
			array(
				'type'              => 'string',
				'sanitize_callback' => array( __CLASS__, 'sanitize_source' ),
				'default'           => 'off',
			)
		);

		register_setting(
			'wkr_settings',
			'wkr_update_repo',
			array(
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
				'default'           => '',
			)
		);

		register_setting(
			'wkr_settings',
			'wkr_update_token',
			array(
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
				'default'           => '',
			)
		);

		register_setting(
			'wkr_settings',
			'wkr_update_json',
			array(
				'type'              => 'string',
				'sanitize_callback' => 'esc_url_raw',
				'default'           => '',
			)
		);
	}

	/**
	 * Uuenduste allikas peab olema üks kolmest.
	 *
	 * @param mixed $value Väärtus.
	 * @return string
	 */
	public static function sanitize_source( $value ) {
		$value = is_string( $value ) ? $value : '';
		return in_array( $value, array( 'github', 'json' ), true ) ? $value : 'off';
	}

	/**
	 * Checkbox -> 0/1.
	 *
	 * @param mixed $value Väärtus.
	 * @return int
	 */
	public static function sanitize_bool( $value ) {
		return $value ? 1 : 0;
	}

	/**
	 * ID-de nimekiri -> puhtad täisarvud.
	 *
	 * @param mixed $value Väärtus.
	 * @return int[]
	 */
	public static function sanitize_ids( $value ) {
		if ( ! is_array( $value ) ) {
			return array();
		}
		return array_values( array_unique( array_filter( array_map( 'absint', $value ) ) ) );
	}

	/**
	 * Seadete leht.
	 */
	public static function page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		?>
		<div class="wrap wkr-wrap">
			<h1><?php esc_html_e( 'Kampaaniariba seaded', 'wonom-kampaaniariba' ); ?></h1>

			<form method="post" action="options.php">
				<?php settings_fields( 'wkr_settings' ); ?>

				<table class="form-table" role="presentation">
					<tr>
						<th scope="row">
							<label for="wkr_default_zindex"><?php esc_html_e( 'Vaikimisi kihi järjekord', 'wonom-kampaaniariba' ); ?></label>
						</th>
						<td>
							<input type="number" id="wkr_default_zindex" name="wkr_default_zindex" min="1" max="9999"
								value="<?php echo esc_attr( get_option( 'wkr_default_zindex', 90 ) ); ?>" class="small-text">
							<p class="description">
								<?php esc_html_e( 'Uute kampaaniate z-index. Ostukorvi paneel ja modaalid on enamikus teemades 400–600 — hoia riba sellest allpool.', 'wonom-kampaaniariba' ); ?>
							</p>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Jaluse ruum', 'wonom-kampaaniariba' ); ?></th>
						<td>
							<label>
								<input type="checkbox" name="wkr_body_padding" value="1" <?php checked( (int) get_option( 'wkr_body_padding', 1 ), 1 ); ?>>
								<?php esc_html_e( 'Lisa lehe alla ruumi, et kleepuv riba ei kataks jaluse linke', 'wonom-kampaaniariba' ); ?>
							</label>
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="wkr_deepl_key"><?php esc_html_e( 'DeepL API võti', 'wonom-kampaaniariba' ); ?></label>
						</th>
						<td>
							<input type="password" id="wkr_deepl_key" name="wkr_deepl_key" class="regular-text"
								value="<?php echo esc_attr( get_option( 'wkr_deepl_key', '' ) ); ?>" autocomplete="off">
							<p class="description">
								<?php esc_html_e( 'Vabatahtlik. Kui võti on olemas, kasutab nupp „Tõlgi eesti keelest” DeepL-i. Ilma võtmeta täidetakse väljad sisseehitatud sõnastikuga, mis on ainult mustand ja vajab alati ülelugemist.', 'wonom-kampaaniariba' ); ?>
							</p>
						</td>
					</tr>
				</table>

				<?php if ( WKR_Coupon::woo_active() ) : ?>
					<h2><?php esc_html_e( 'Kupongide püsivälistused', 'wonom-kampaaniariba' ); ?></h2>
					<p class="description" style="max-width:640px">
						<?php esc_html_e( 'Need tooted ja kategooriad lisatakse iga plugina hallatava kupongi välistuste hulka. Nii ei pea kinkekaarte iga kampaania juures uuesti meelde tuletama. Kehtib ainult kampaaniatele, kus toote- ja kategooriapiiranguid hallatakse plugina alt.', 'wonom-kampaaniariba' ); ?>
					</p>

					<table class="form-table" role="presentation">
						<tr>
							<th scope="row">
								<label for="wkr_always_exclude_products"><?php esc_html_e( 'Alati välistatud tooted', 'wonom-kampaaniariba' ); ?></label>
							</th>
							<td>
								<select class="wc-product-search" multiple="multiple" style="width:400px;max-width:100%"
									id="wkr_always_exclude_products" name="wkr_always_exclude_products[]"
									data-placeholder="<?php esc_attr_e( 'Otsi toodet…', 'wonom-kampaaniariba' ); ?>"
									data-action="woocommerce_json_search_products_and_variations">
									<?php
									foreach ( wkr_always_excluded( 'wkr_always_exclude_products' ) as $pid ) {
										$product = wc_get_product( $pid );
										if ( ! $product ) {
											continue;
										}
										printf(
											'<option value="%1$s" selected="selected">%2$s</option>',
											esc_attr( $pid ),
											esc_html( wp_strip_all_tags( $product->get_formatted_name() ) )
										);
									}
									?>
								</select>
								<p class="description"><?php esc_html_e( 'Näiteks kinkekaardid.', 'wonom-kampaaniariba' ); ?></p>
							</td>
						</tr>
						<tr>
							<th scope="row">
								<label for="wkr_always_exclude_cats"><?php esc_html_e( 'Alati välistatud kategooriad', 'wonom-kampaaniariba' ); ?></label>
							</th>
							<td>
								<?php
								$terms = get_terms(
									array(
										'taxonomy'   => 'product_cat',
										'hide_empty' => false,
									)
								);
								$terms    = is_wp_error( $terms ) ? array() : $terms;
								$selected = wkr_always_excluded( 'wkr_always_exclude_cats' );
								?>
								<select class="wc-enhanced-select" multiple="multiple" style="width:400px;max-width:100%"
									id="wkr_always_exclude_cats" name="wkr_always_exclude_cats[]"
									data-placeholder="<?php esc_attr_e( 'Ükskõik milline kategooria', 'wonom-kampaaniariba' ); ?>">
									<?php foreach ( $terms as $term ) : ?>
										<option value="<?php echo esc_attr( $term->term_id ); ?>" <?php selected( in_array( (int) $term->term_id, $selected, true ) ); ?>>
											<?php echo esc_html( $term->name ); ?>
										</option>
									<?php endforeach; ?>
								</select>
							</td>
						</tr>
					</table>
				<?php endif; ?>

				<h2><?php esc_html_e( 'Automaatsed uuendused', 'wonom-kampaaniariba' ); ?></h2>
				<p class="description" style="max-width:640px">
					<?php esc_html_e( 'Kui allikas on määratud, ilmub uus versioon Pluginad-lehele tavalise uuendusteatena ja „Uuenda kohe” töötab. ZIP-i ei pea enam käsitsi üles laadima.', 'wonom-kampaaniariba' ); ?>
				</p>

				<table class="form-table" role="presentation">
					<tr>
						<th scope="row">
							<label for="wkr_update_source"><?php esc_html_e( 'Uuenduste allikas', 'wonom-kampaaniariba' ); ?></label>
						</th>
						<td>
							<select id="wkr_update_source" name="wkr_update_source">
								<option value="off" <?php selected( get_option( 'wkr_update_source', 'off' ), 'off' ); ?>><?php esc_html_e( 'Väljas — uuendan käsitsi ZIP-iga', 'wonom-kampaaniariba' ); ?></option>
								<option value="github" <?php selected( get_option( 'wkr_update_source', 'off' ), 'github' ); ?>><?php esc_html_e( 'GitHubi väljalase (release)', 'wonom-kampaaniariba' ); ?></option>
								<option value="json" <?php selected( get_option( 'wkr_update_source', 'off' ), 'json' ); ?>><?php esc_html_e( 'Oma serveris olev JSON-fail', 'wonom-kampaaniariba' ); ?></option>
							</select>
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="wkr_update_repo"><?php esc_html_e( 'GitHubi hoidla', 'wonom-kampaaniariba' ); ?></label>
						</th>
						<td>
							<input type="text" id="wkr_update_repo" name="wkr_update_repo" class="regular-text"
								value="<?php echo esc_attr( get_option( 'wkr_update_repo', '' ) ); ?>" placeholder="wonomdigital/wonom-kampaaniariba">
							<p class="description"><?php esc_html_e( 'Kujul kasutaja/hoidla. Plugin võtab viimase väljalaske (release).', 'wonom-kampaaniariba' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="wkr_update_token"><?php esc_html_e( 'GitHubi juurdepääsuvõti', 'wonom-kampaaniariba' ); ?></label>
						</th>
						<td>
							<input type="password" id="wkr_update_token" name="wkr_update_token" class="regular-text"
								value="<?php echo esc_attr( get_option( 'wkr_update_token', '' ) ); ?>" autocomplete="off">
							<p class="description"><?php esc_html_e( 'Vajalik ainult siis, kui hoidla on privaatne. Avaliku hoidla puhul jäta tühjaks.', 'wonom-kampaaniariba' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="wkr_update_json"><?php esc_html_e( 'JSON-faili aadress', 'wonom-kampaaniariba' ); ?></label>
						</th>
						<td>
							<input type="url" id="wkr_update_json" name="wkr_update_json" class="regular-text"
								value="<?php echo esc_attr( get_option( 'wkr_update_json', '' ) ); ?>" placeholder="https://wonom.ee/updates/kampaaniariba.json">
							<p class="description"><?php esc_html_e( 'Kasutatakse ainult siis, kui allikaks on valitud JSON-fail.', 'wonom-kampaaniariba' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Olek', 'wonom-kampaaniariba' ); ?></th>
						<td>
							<?php
							$installed = WKR_VERSION;
							$remote    = WKR_Updater::remote();
							?>
							<p>
								<?php
								printf(
									/* translators: %s: version number */
									esc_html__( 'Paigaldatud versioon: %s', 'wonom-kampaaniariba' ),
									'<code>' . esc_html( $installed ) . '</code>'
								);
								?>
								<br>
								<?php if ( $remote ) : ?>
									<?php
									printf(
										/* translators: %s: version number */
										esc_html__( 'Allikas pakub: %s', 'wonom-kampaaniariba' ),
										'<code>' . esc_html( $remote['version'] ) . '</code>'
									);
									?>
									<?php if ( version_compare( $remote['version'], $installed, '>' ) ) : ?>
										<span class="wkr-pill wkr-pill--live"><?php esc_html_e( 'Uuendus saadaval', 'wonom-kampaaniariba' ); ?></span>
									<?php else : ?>
										<span class="wkr-pill wkr-pill--off"><?php esc_html_e( 'Kõik on värske', 'wonom-kampaaniariba' ); ?></span>
									<?php endif; ?>
								<?php elseif ( 'off' !== get_option( 'wkr_update_source', 'off' ) ) : ?>
									<span class="wkr-pill wkr-pill--upcoming"><?php esc_html_e( 'Allikast ei saanud vastust — kontrolli hoidla nime või aadressi', 'wonom-kampaaniariba' ); ?></span>
								<?php endif; ?>
							</p>
						</td>
					</tr>
				</table>

				<?php submit_button(); ?>
			</form>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="wkr_check_update">
				<?php wp_nonce_field( 'wkr_check_update' ); ?>
				<?php submit_button( __( 'Kontrolli uuendusi kohe', 'wonom-kampaaniariba' ), 'secondary', 'submit', false ); ?>
			</form>

			<h2><?php esc_html_e( 'Kuidas riba lehele saab', 'wonom-kampaaniariba' ); ?></h2>
			<p>
				<?php esc_html_e( 'Riba ilmub automaatselt: päisesse haagi wp_body_open kaudu, jalusesse wp_footer kaudu. Kui teema wp_body_open haaki ei kasuta, saad riba paigutada käsitsi lühikoodiga:', 'wonom-kampaaniariba' ); ?>
			</p>
			<p><code>[wonom_banner position="top"]</code> &nbsp; <code>[wonom_banner id="123" position="bottom"]</code></p>
			<p class="description">
				<?php esc_html_e( 'Või mallifailis: echo do_shortcode( \'[wonom_banner]\' );', 'wonom-kampaaniariba' ); ?>
			</p>
		</div>
		<?php
	}
}
