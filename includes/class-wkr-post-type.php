<?php
/**
 * Kampaania postitüüp ja selle nimekirjavaade.
 *
 * @package Wonom_Kampaaniariba
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registreerib kampaaniate postitüübi.
 */
class WKR_Post_Type {

	/**
	 * Haagid.
	 */
	public static function init() {
		add_action( 'init', array( __CLASS__, 'register' ) );
		add_filter( 'manage_' . WKR_CPT . '_posts_columns', array( __CLASS__, 'columns' ) );
		add_action( 'manage_' . WKR_CPT . '_posts_custom_column', array( __CLASS__, 'column' ), 10, 2 );

		// Kloonimine.
		add_filter( 'post_row_actions', array( __CLASS__, 'row_actions' ), 10, 2 );
		add_action( 'post_submitbox_misc_actions', array( __CLASS__, 'submitbox_link' ) );
		add_action( 'admin_post_wkr_clone', array( __CLASS__, 'handle_clone' ) );
		add_action( 'admin_notices', array( __CLASS__, 'clone_notice' ) );
	}

	/**
	 * Kloonimise aadress koos turvatunnusega.
	 *
	 * @param int $post_id Kampaania ID.
	 * @return string
	 */
	public static function clone_url( $post_id ) {
		return wp_nonce_url(
			admin_url( 'admin-post.php?action=wkr_clone&post=' . (int) $post_id ),
			'wkr_clone_' . (int) $post_id
		);
	}

	/**
	 * „Klooni” link kampaaniate nimekirjas.
	 *
	 * @param array   $actions Read.
	 * @param WP_Post $post    Postitus.
	 * @return array
	 */
	public static function row_actions( $actions, $post ) {
		if ( WKR_CPT !== $post->post_type || ! current_user_can( 'edit_post', $post->ID ) ) {
			return $actions;
		}

		$actions['wkr_clone'] = sprintf(
			'<a href="%1$s">%2$s</a>',
			esc_url( self::clone_url( $post->ID ) ),
			esc_html__( 'Klooni', 'wonom-kampaaniariba' )
		);

		return $actions;
	}

	/**
	 * „Klooni” link avaldamiskastis.
	 *
	 * @param WP_Post $post Postitus.
	 */
	public static function submitbox_link( $post ) {
		if ( WKR_CPT !== $post->post_type || 'auto-draft' === $post->post_status ) {
			return;
		}
		if ( ! current_user_can( 'edit_post', $post->ID ) ) {
			return;
		}
		?>
		<div class="misc-pub-section wkr-clone-row">
			<a href="<?php echo esc_url( self::clone_url( $post->ID ) ); ?>" class="button">
				<?php esc_html_e( 'Klooni see kampaania', 'wonom-kampaaniariba' ); ?>
			</a>
			<span class="wkr-hint">
				<?php esc_html_e( 'Teeb mustandi, kus kujundus, tekstid ja ajastus on samad.', 'wonom-kampaaniariba' ); ?>
			</span>
		</div>
		<?php
	}

	/**
	 * Teeb kampaaniast koopia ja viib selle muutmisvaatesse.
	 */
	public static function handle_clone() {
		$id = isset( $_GET['post'] ) ? absint( $_GET['post'] ) : 0;

		if ( ! $id ) {
			wp_die( esc_html__( 'Kampaaniat ei leitud.', 'wonom-kampaaniariba' ) );
		}

		check_admin_referer( 'wkr_clone_' . $id );

		if ( ! current_user_can( 'edit_post', $id ) ) {
			wp_die( esc_html__( 'Puuduvad õigused.', 'wonom-kampaaniariba' ) );
		}

		$source = get_post( $id );
		if ( ! $source || WKR_CPT !== $source->post_type ) {
			wp_die( esc_html__( 'Kampaaniat ei leitud.', 'wonom-kampaaniariba' ) );
		}

		$new_id = wp_insert_post(
			array(
				'post_type'   => WKR_CPT,
				'post_status' => 'draft',
				'post_title'  => $source->post_title . ' ' . __( '(koopia)', 'wonom-kampaaniariba' ),
				'menu_order'  => $source->menu_order,
			),
			true
		);

		if ( is_wp_error( $new_id ) ) {
			wp_die( esc_html( $new_id->get_error_message() ) );
		}

		/*
		 * Kupongi seosed jäävad originaalile. Kloon ei tohi ühtegi olemasolevat
		 * WooCommerce'i kupongi haarata ega muuta enne, kui inimene on need
		 * seaded üle vaadanud.
		 */
		$skip = array( '_wkr_coupon_id', '_wkr_wc_takeover' );

		foreach ( get_post_meta( $id ) as $key => $values ) {
			if ( strpos( $key, '_wkr_' ) !== 0 || in_array( $key, $skip, true ) ) {
				continue;
			}
			update_post_meta( $new_id, $key, maybe_unserialize( $values[0] ) );
		}

		update_post_meta( $new_id, '_wkr_wc_mode', 'none' );

		wp_safe_redirect(
			add_query_arg( 'wkr_cloned', 1, get_edit_post_link( $new_id, 'raw' ) )
		);
		exit;
	}

	/**
	 * Teade pärast kloonimist.
	 */
	public static function clone_notice() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- ainult teate näitamine.
		if ( empty( $_GET['wkr_cloned'] ) ) {
			return;
		}

		$screen = get_current_screen();
		if ( ! $screen || WKR_CPT !== $screen->post_type ) {
			return;
		}

		printf(
			'<div class="notice notice-success is-dismissible"><p>%s</p></div>',
			esc_html__( 'Kampaania on kloonitud ja salvestatud mustandina. Vaata üle algus- ja lõpuaeg ning sooduskood, seejärel avalda. WooCommerce’i kupongi haldus on koopial välja lülitatud, et see ei muudaks originaali kupongi.', 'wonom-kampaaniariba' )
		);
	}

	/**
	 * Postitüüp.
	 */
	public static function register() {
		register_post_type(
			WKR_CPT,
			array(
				'labels'          => array(
					'name'               => __( 'Kampaaniaribad', 'wonom-kampaaniariba' ),
					'singular_name'      => __( 'Kampaaniariba', 'wonom-kampaaniariba' ),
					'add_new'            => __( 'Lisa uus', 'wonom-kampaaniariba' ),
					'add_new_item'       => __( 'Lisa uus kampaania', 'wonom-kampaaniariba' ),
					'edit_item'          => __( 'Muuda kampaaniat', 'wonom-kampaaniariba' ),
					'new_item'           => __( 'Uus kampaania', 'wonom-kampaaniariba' ),
					'search_items'       => __( 'Otsi kampaaniaid', 'wonom-kampaaniariba' ),
					'not_found'          => __( 'Kampaaniaid ei leitud', 'wonom-kampaaniariba' ),
					'menu_name'          => __( 'Kampaaniariba', 'wonom-kampaaniariba' ),
					'all_items'          => __( 'Kõik kampaaniad', 'wonom-kampaaniariba' ),
				),
				'public'          => false,
				'show_ui'         => true,
				'show_in_menu'    => true,
				'menu_icon'       => 'dashicons-megaphone',
				'menu_position'   => 26,
				'supports'        => array( 'title', 'page-attributes' ),
				'capability_type' => 'page',
				'has_archive'     => false,
				'rewrite'         => false,
				'show_in_rest'    => false,
			)
		);
	}

	/**
	 * Nimekirja veerud.
	 *
	 * @param array $cols Olemasolevad veerud.
	 * @return array
	 */
	public static function columns( $cols ) {
		$new = array();

		foreach ( $cols as $key => $label ) {
			$new[ $key ] = $label;
			if ( 'title' === $key ) {
				$new['wkr_status'] = __( 'Olek', 'wonom-kampaaniariba' );
				$new['wkr_when']   = __( 'Kestus', 'wonom-kampaaniariba' );
				$new['wkr_coupon'] = __( 'Sooduskood', 'wonom-kampaaniariba' );
			}
		}

		unset( $new['date'] );

		return $new;
	}

	/**
	 * Veeru sisu.
	 *
	 * @param string $col     Veeru võti.
	 * @param int    $post_id Kampaania ID.
	 */
	public static function column( $col, $post_id ) {
		if ( 'wkr_status' === $col ) {
			$status = wkr_status( $post_id );
			$labels = array(
				'live'     => __( 'Eetris', 'wonom-kampaaniariba' ),
				'upcoming' => __( 'Tulemas', 'wonom-kampaaniariba' ),
				'ended'    => __( 'Lõppenud', 'wonom-kampaaniariba' ),
				'off'      => __( 'Väljas', 'wonom-kampaaniariba' ),
			);
			printf(
				'<span class="wkr-pill wkr-pill--%1$s">%2$s</span>',
				esc_attr( $status ),
				esc_html( $labels[ $status ] )
			);
			return;
		}

		if ( 'wkr_when' === $col ) {
			$tz    = wkr_get( $post_id, 'tz' );
			$start = wkr_get( $post_id, 'start_local' );
			$end   = wkr_get( $post_id, 'end_local' );

			if ( ! $start || ! $end ) {
				echo '—';
				return;
			}

			printf(
				'<span class="wkr-when">%1$s → %2$s</span><br><span class="wkr-tz">%3$s</span>',
				esc_html( wkr_pretty( $start ) ),
				esc_html( wkr_pretty( $end ) ),
				esc_html( $tz ? $tz : wkr_default_tz() )
			);
			return;
		}

		if ( 'wkr_coupon' === $col ) {
			$coupon = wkr_get( $post_id, 'coupon' );
			echo $coupon ? '<code>' . esc_html( $coupon ) . '</code>' : '—';
		}
	}
}
