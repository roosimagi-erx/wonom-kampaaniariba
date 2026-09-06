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
