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

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- ainult teate näitamine.
		if ( isset( $_GET['wkr_rebuilt'] ) ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$rebuilt = '1' === sanitize_text_field( wp_unslash( $_GET['wkr_rebuilt'] ) );
			printf(
				'<div class="notice %1$s is-dismissible"><p>%2$s</p></div>',
				$rebuilt ? 'notice-success' : 'notice-error',
				$rebuilt
					? esc_html__( 'Riba andmefail on uuesti kirjutatud.', 'wonom-kampaaniariba' )
					: esc_html__( 'Riba andmefaili ei õnnestunud kirjutada. Kontrolli, et uploads-kausta saab kirjutada.', 'wonom-kampaaniariba' )
			);
		}
		?>
		<div class="wrap wkr-wrap">
			<h1><?php esc_html_e( 'Kampaaniariba seaded', 'wonom-kampaaniariba' ); ?></h1>

			<form method="post" action="options.php">
				<?php settings_fields( 'wkr_settings' ); ?>

				<div class="wkr-card">
				<h2><?php esc_html_e( 'Riba vaikeseaded', 'wonom-kampaaniariba' ); ?></h2>

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

				</div>

				<div class="wkr-card">
				<h2><?php esc_html_e( 'Riba andmefail', 'wonom-kampaaniariba' ); ?></h2>
				<p class="description">
					<?php esc_html_e( 'Riba ei ole lehe HTML-i sees. Kõik praegused ja tulevased kampaaniad on ühes väikeses failis ja lehel olev skript loeb riba sealt. Millist kampaaniat näidata, otsustab kliendi brauser kellaaja järgi.', 'wonom-kampaaniariba' ); ?>
				</p>
				<ul class="wkr-list">
					<li><?php esc_html_e( 'Kampaania salvestamine kirjutab üle ainult selle faili. Uus või muudetud riba jõuab kõigile lehtedele umbes minutiga.', 'wonom-kampaaniariba' ); ?></li>
					<li><?php esc_html_e( 'Kampaania algus ja lõpp toimuvad täpsel sekundil kõigil lehtedel, ka neil, mis on parajasti kliendil lahti.', 'wonom-kampaaniariba' ); ?></li>
					<li><?php esc_html_e( 'Plugin ei tühjenda enam ühtegi vahemälu — ei salvestamisel, kampaania vahetumisel ega uuendamisel.', 'wonom-kampaaniariba' ); ?></li>
				</ul>
				<p class="wkr-warn">
					<?php esc_html_e( 'Uuendasid versioonilt 1.20 või vanemalt? Tühjenda kiirendusplugina lehtede vahemälu üks kord (FlyingPressis „Purge pages”). Enne seda vahemällu läinud lehtedel on veel vana, lehe sisse kirjutatud riba ja uut laadijat neil ei ole, nii et uued kampaaniad sinna ei jõua. Pärast seda ühte korda ei ole riba pärast enam kunagi vaja vahemälu tühjendada.', 'wonom-kampaaniariba' ); ?>
				</p>

				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><?php esc_html_e( 'Faili seis', 'wonom-kampaaniariba' ); ?></th>
						<td>
							<?php $data = WKR_Data::status(); ?>
							<p>
								<?php if ( $data['ok'] ) : ?>
									<span class="wkr-pill wkr-pill--live"><?php esc_html_e( 'Fail on olemas', 'wonom-kampaaniariba' ); ?></span>
								<?php else : ?>
									<span class="wkr-pill wkr-pill--upcoming"><?php esc_html_e( 'Fail puudub või kirjutamine ebaõnnestus', 'wonom-kampaaniariba' ); ?></span>
								<?php endif; ?>
								<?php
								if ( $data['time'] ) {
									printf(
										/* translators: 1: date and time, 2: number of campaigns */
										esc_html__( 'Viimati kirjutatud %1$s. Failis on %2$d kampaaniat (eetris olevad ja tulevased).', 'wonom-kampaaniariba' ),
										esc_html( wp_date( 'd.m.Y H:i:s', $data['time'] ) ),
										(int) $data['count']
									);
								}
								?>
							</p>
							<?php if ( ! $data['ok'] ) : ?>
								<p class="wkr-warn">
									<?php esc_html_e( 'Ilma andmefailita riba poes ei ilmu. Vajuta lehe lõpus „Kirjuta andmefail uuesti”. Kui see ei aita, kontrolli, et kausta wp-content/uploads saab kirjutada.', 'wonom-kampaaniariba' ); ?>
								</p>
							<?php endif; ?>
							<ul class="wkr-list">
								<?php foreach ( $data['files'] as $data_lang => $data_url ) : ?>
									<li>
										<?php echo esc_html( strtoupper( $data_lang ) ); ?>:
										<a href="<?php echo esc_url( $data_url ); ?>" target="_blank" rel="noopener"><code><?php echo esc_html( $data_url ); ?></code></a>
									</li>
								<?php endforeach; ?>
							</ul>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Kiirendusplugin', 'wonom-kampaaniariba' ); ?></th>
						<td>
							<p class="description">
								<?php esc_html_e( 'Lehe päises on väike laadija, mis küsib andmefaili. Kui su kiirendusplugin lükkab JavaScripti edasi või viivitab seda kuni külastaja esimese tegevuseni (FlyingPressis „Defer JavaScript” ja „Delay JavaScript”), lisa tema väljajätmiste hulka märksõna:', 'wonom-kampaaniariba' ); ?>
								<code>wkr-restore</code>
							</p>
							<p class="description">
								<?php esc_html_e( 'Ilma selleta riba töötab, aga ilmub hiljem: edasilükkamise korral pärast lehe laadimist, viivitamise korral alles siis, kui külastaja midagi teeb.', 'wonom-kampaaniariba' ); ?>
							</p>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'WP-Cron', 'wonom-kampaaniariba' ); ?></th>
						<td>
							<?php
							$cron = WKR_Cache::cron_status();

							if ( 'ok' === $cron['state'] ) :
								?>
								<p class="description">
									<span class="wkr-pill wkr-pill--live"><?php esc_html_e( 'Cron töötab', 'wonom-kampaaniariba' ); ?></span>
									<?php
									printf(
										/* translators: %s: human readable time difference */
										esc_html__( 'Viimane käivitus %s tagasi.', 'wonom-kampaaniariba' ),
										esc_html( human_time_diff( $cron['last'] ) )
									);

									if ( $cron['wp_off'] ) {
										echo ' ';
										esc_html_e( 'DISABLE_WP_CRON on sees ja cron’i käivitab serveri enda töö — see ongi kõige kindlam seadistus.', 'wonom-kampaaniariba' );
									}
									?>
								</p>
							<?php elseif ( 'waiting' === $cron['state'] ) : ?>
								<p class="description">
									<?php esc_html_e( 'Ootan esimest cron-käivitust. Kontrolli seda lehte paari minuti pärast uuesti.', 'wonom-kampaaniariba' ); ?>
								</p>
							<?php else : ?>
								<p class="wkr-warn">
									<?php
									if ( 'stale' === $cron['state'] ) {
										printf(
											/* translators: %s: human readable time difference */
											esc_html__( 'WP-Cron ei ole käivitunud alates %s tagasi.', 'wonom-kampaaniariba' ),
											esc_html( human_time_diff( $cron['last'] ) )
										);
									} else {
										esc_html_e( 'WP-Cron on välja lülitatud (DISABLE_WP_CRON) ega ole veel kordagi käivitunud.', 'wonom-kampaaniariba' );
									}
									?>
									<br>
									<code>*/5 * * * * curl -s <?php echo esc_html( home_url( '/wp-cron.php?doing_wp_cron' ) ); ?> &gt; /dev/null</code>
								</p>
							<?php endif; ?>
							<p class="description">
								<?php esc_html_e( 'Riba ise cronist ei sõltu: kampaania algab ja lõpeb brauseris kellaaja järgi ning sooduskood hakkab kehtima alguse sekundist. Cron teeb kord tunnis koristustööd — viskab andmefailist välja lõppenud kampaaniad.', 'wonom-kampaaniariba' ); ?>
							</p>
						</td>
					</tr>
				</table>
				</div>

				<?php if ( WKR_Coupon::woo_active() ) : ?>
					<div class="wkr-card">
					<h2><?php esc_html_e( 'Kupongide püsivälistused', 'wonom-kampaaniariba' ); ?></h2>
					<p class="description">
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
					</div>
				<?php endif; ?>

				<div class="wkr-card">
				<h2><?php esc_html_e( 'Automaatsed uuendused', 'wonom-kampaaniariba' ); ?></h2>
				<p class="description">
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
										<?php
										if ( current_user_can( 'update_plugins' ) ) :
											$plugin_file = plugin_basename( WKR_FILE );
											$upgrade_url = wp_nonce_url(
												self_admin_url( 'update.php?action=upgrade-plugin&plugin=' . rawurlencode( $plugin_file ) ),
												'upgrade-plugin_' . $plugin_file
											);
											?>
											<span class="wkr-update-box">
												<a href="<?php echo esc_url( $upgrade_url ); ?>"
													class="button button-primary wkr-update-now"
													data-plugin="<?php echo esc_attr( $plugin_file ); ?>"
													data-slug="<?php echo esc_attr( WKR_Updater::slug() ); ?>">
													<?php
													printf(
														/* translators: %s: version number */
														esc_html__( 'Uuenda kohe versioonile %s', 'wonom-kampaaniariba' ),
														esc_html( $remote['version'] )
													);
													?>
												</a>
												<span class="wkr-update-status" role="status" aria-live="polite"></span>
											</span>
										<?php endif; ?>
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
				</div>

				<?php submit_button(); ?>
			</form>

			<p class="wkr-actions">
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline">
					<input type="hidden" name="action" value="wkr_check_update">
					<?php wp_nonce_field( 'wkr_check_update' ); ?>
					<?php submit_button( __( 'Kontrolli uuendusi kohe', 'wonom-kampaaniariba' ), 'secondary', 'submit', false ); ?>
				</form>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline">
					<input type="hidden" name="action" value="wkr_rebuild">
					<?php wp_nonce_field( 'wkr_rebuild' ); ?>
					<?php submit_button( __( 'Kirjuta andmefail uuesti', 'wonom-kampaaniariba' ), 'secondary', 'submit', false ); ?>
				</form>
			</p>

			<div class="wkr-card">
			<h2><?php esc_html_e( 'Kuidas riba lehele saab', 'wonom-kampaaniariba' ); ?></h2>
			<p>
				<?php esc_html_e( 'Riba ilmub automaatselt: päiseriba lehe algusesse, jaluseriba ekraani alla. Teema ei pea selleks midagi toetama. Kui tahad riba lehe sisse kindlasse kohta, kasuta lühikoodi:', 'wonom-kampaaniariba' ); ?>
			</p>
			<p><code>[wonom_banner position="top"]</code> &nbsp; <code>[wonom_banner id="123" position="bottom"]</code></p>
			<p class="description">
				<?php esc_html_e( 'Või mallifailis: echo do_shortcode( \'[wonom_banner]\' );', 'wonom-kampaaniariba' ); ?>
			</p>
			</div>
		</div>
		<?php
		// Vajalik siis, kui server küsib uuendamiseks failiõigusi (FTP).
		if ( function_exists( 'wp_print_request_filesystem_credentials_modal' ) && current_user_can( 'update_plugins' ) ) {
			wp_print_request_filesystem_credentials_modal();
		}
	}
}
