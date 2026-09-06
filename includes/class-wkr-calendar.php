<?php
/**
 * Kampaaniate kuukalender administraatorile.
 *
 * @package Wonom_Kampaaniariba
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Kalendrivaade: näeb korraga, millal miski eetris on.
 */
class WKR_Calendar {

	/**
	 * Haagid.
	 */
	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ) );
	}

	/**
	 * Alammenüü.
	 */
	public static function menu() {
		add_submenu_page(
			'edit.php?post_type=' . WKR_CPT,
			__( 'Kampaaniate kalender', 'wonom-kampaaniariba' ),
			__( 'Kalender', 'wonom-kampaaniariba' ),
			'edit_posts',
			'wkr-calendar',
			array( __CLASS__, 'page' )
		);
	}

	/**
	 * Kalendri leht.
	 */
	public static function page() {
		if ( ! current_user_can( 'edit_posts' ) ) {
			return;
		}

		$tz  = wkr_default_tz();
		$now = time();

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- ainult vaate navigeerimine.
		$month = isset( $_GET['wkr_m'] ) ? absint( $_GET['wkr_m'] ) : 0;
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- ainult vaate navigeerimine.
		$year = isset( $_GET['wkr_y'] ) ? absint( $_GET['wkr_y'] ) : 0;

		$today_local = wkr_ts_to_local( $now, $tz );
		if ( ! $month || $month > 12 ) {
			$month = (int) substr( $today_local, 5, 2 );
		}
		if ( ! $year ) {
			$year = (int) substr( $today_local, 0, 4 );
		}

		$campaigns = get_posts(
			array(
				'post_type'      => WKR_CPT,
				'post_status'    => array( 'publish', 'draft' ),
				'posts_per_page' => 200,
				'orderby'        => 'meta_value_num',
				'meta_key'       => '_wkr_start_utc', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
				'order'          => 'ASC',
			)
		);

		$months = array(
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
		);

		$prev = array(
			'y' => 1 === $month ? $year - 1 : $year,
			'm' => 1 === $month ? 12 : $month - 1,
		);
		$next = array(
			'y' => 12 === $month ? $year + 1 : $year,
			'm' => 12 === $month ? 1 : $month + 1,
		);

		$base = admin_url( 'edit.php?post_type=' . WKR_CPT . '&page=wkr-calendar' );

		// Nädal algab esmaspäevast.
		$first_dow = (int) gmdate( 'N', strtotime( sprintf( '%04d-%02d-01', $year, $month ) ) );
		$shift     = $first_dow - 1;
		$dow       = array( 'E', 'T', 'K', 'N', 'R', 'L', 'P' );
		?>
		<div class="wrap wkr-wrap">
			<h1 class="wkr-cal-head">
				<span><?php echo esc_html( $months[ $month - 1 ] . ' ' . $year ); ?></span>
				<span class="wkr-cal-nav">
					<a class="button" href="<?php echo esc_url( add_query_arg( array( 'wkr_y' => $prev['y'], 'wkr_m' => $prev['m'] ), $base ) ); ?>">&larr;</a>
					<a class="button" href="<?php echo esc_url( $base ); ?>"><?php esc_html_e( 'Täna', 'wonom-kampaaniariba' ); ?></a>
					<a class="button" href="<?php echo esc_url( add_query_arg( array( 'wkr_y' => $next['y'], 'wkr_m' => $next['m'] ), $base ) ); ?>">&rarr;</a>
				</span>
			</h1>

			<div class="wkr-cal">
				<?php foreach ( $dow as $day_label ) : ?>
					<div class="wkr-cal-dow"><?php echo esc_html( $day_label ); ?></div>
				<?php endforeach; ?>

				<?php
				for ( $i = 0; $i < 42; $i++ ) {
					$day_num   = $i - $shift + 1;
					$stamp     = mktime( 12, 0, 0, $month, $day_num, $year );
					$cell_date = gmdate( 'Y-m-d', $stamp );
					$out_month = (int) gmdate( 'n', $stamp ) !== $month;

					$day_start = wkr_local_to_ts( $cell_date . ' 00:00', $tz );
					$day_end   = $day_start + DAY_IN_SECONDS;
					$is_today  = substr( $today_local, 0, 10 ) === $cell_date;

					$classes = array( 'wkr-cal-day' );
					if ( $out_month ) {
						$classes[] = 'is-out';
					}
					if ( $is_today ) {
						$classes[] = 'is-today';
					}
					?>
					<div class="<?php echo esc_attr( implode( ' ', $classes ) ); ?>">
						<span class="wkr-cal-num"><?php echo esc_html( gmdate( 'j', $stamp ) ); ?></span>
						<?php
						foreach ( $campaigns as $campaign ) {
							$start = (int) get_post_meta( $campaign->ID, '_wkr_start_utc', true );
							$end   = (int) get_post_meta( $campaign->ID, '_wkr_end_utc', true );

							if ( ! $start || ! $end || $start >= $day_end || $end < $day_start ) {
								continue;
							}

							$bg     = wkr_get( $campaign->ID, 'bg' );
							$faded  = ( $end < $now || ! wkr_get( $campaign->ID, 'enabled' ) ) ? ' is-dim' : '';
							$link   = get_edit_post_link( $campaign->ID );
							?>
							<a class="wkr-cal-bar<?php echo esc_attr( $faded ); ?>"
								href="<?php echo esc_url( $link ); ?>"
								style="--wkr-bar:<?php echo esc_attr( $bg ); ?>"
								title="<?php echo esc_attr( get_the_title( $campaign ) ); ?>">
								<?php echo esc_html( get_the_title( $campaign ) ); ?>
							</a>
							<?php
						}
						?>
					</div>
					<?php
				}
				?>
			</div>

			<h2><?php esc_html_e( 'Ajakava', 'wonom-kampaaniariba' ); ?></h2>
			<table class="widefat striped wkr-sched">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Kampaania', 'wonom-kampaaniariba' ); ?></th>
						<th><?php esc_html_e( 'Algab', 'wonom-kampaaniariba' ); ?></th>
						<th><?php esc_html_e( 'Lõpeb', 'wonom-kampaaniariba' ); ?></th>
						<th><?php esc_html_e( 'Ajavöönd', 'wonom-kampaaniariba' ); ?></th>
						<th><?php esc_html_e( 'Sooduskood', 'wonom-kampaaniariba' ); ?></th>
						<th><?php esc_html_e( 'Olek', 'wonom-kampaaniariba' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php if ( ! $campaigns ) : ?>
						<tr><td colspan="6"><?php esc_html_e( 'Ühtegi kampaaniat pole veel loodud.', 'wonom-kampaaniariba' ); ?></td></tr>
					<?php endif; ?>
					<?php
					foreach ( $campaigns as $campaign ) :
						$status = wkr_status( $campaign->ID, $now );
						$labels = array(
							'live'     => __( 'Eetris', 'wonom-kampaaniariba' ),
							'upcoming' => __( 'Tulemas', 'wonom-kampaaniariba' ),
							'ended'    => __( 'Lõppenud', 'wonom-kampaaniariba' ),
							'off'      => __( 'Väljas', 'wonom-kampaaniariba' ),
						);
						?>
						<tr>
							<td><a href="<?php echo esc_url( get_edit_post_link( $campaign->ID ) ); ?>"><strong><?php echo esc_html( get_the_title( $campaign ) ); ?></strong></a></td>
							<td><?php echo esc_html( wkr_pretty( wkr_get( $campaign->ID, 'start_local' ) ) ); ?></td>
							<td><?php echo esc_html( wkr_pretty( wkr_get( $campaign->ID, 'end_local' ) ) ); ?></td>
							<td><?php echo esc_html( wkr_get( $campaign->ID, 'tz' ) ); ?></td>
							<td><?php echo wkr_get( $campaign->ID, 'coupon' ) ? '<code>' . esc_html( wkr_get( $campaign->ID, 'coupon' ) ) . '</code>' : '—'; ?></td>
							<td><span class="wkr-pill wkr-pill--<?php echo esc_attr( $status ); ?>"><?php echo esc_html( $labels[ $status ] ); ?></span></td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</div>
		<?php
	}
}
