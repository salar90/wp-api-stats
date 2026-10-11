<?php
/**
 * Stats page. Variables come from SG_API_Stats::admin_page().
 *
 * @var array    $data
 * @var object   $summary
 * @var object[] $tableData
 * @var string   $current_date_from
 * @var string   $current_date_to
 * @var string   $selected_chunk
 * @var string   $group
 * @var string   $data_size
 * @var int      $row_count
 * @var array    $settings
 * @var int      $retention_days
 * @var string   $tz_name
 */

if ( ! defined( 'WPINC' ) ) {
	die;
}

$today_date = new DateTimeImmutable( 'today', wp_timezone() );
$ranges     = array(
	'today'     => array( __( 'Today', 'wp-api-stats' ), $today_date, $today_date, 'Hour' ),
	'yesterday' => array( __( 'Yesterday', 'wp-api-stats' ), $today_date->modify( '-1 day' ), $today_date->modify( '-1 day' ), 'Hour' ),
	'7d'        => array( __( 'Last 7 days', 'wp-api-stats' ), $today_date->modify( '-6 days' ), $today_date, 'Hour' ),
	'30d'       => array( __( 'Last 30 days', 'wp-api-stats' ), $today_date->modify( '-29 days' ), $today_date, 'Day' ),
);

$filter_args = array(
	'date-from' => $current_date_from,
	'date-to'   => $current_date_to,
	'chunk'     => $selected_chunk,
	'group'     => $group,
);

$total       = (int) $summary->total;
$error_count = (int) $summary->errors;
$error_rate  = $total ? $error_count / $total * 100 : 0;

// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- only selects which notice to show.
$notice = isset( $_GET['sg-notice'] ) ? sanitize_key( $_GET['sg-notice'] ) : '';
?>
<div class="wrap api-stats">
	<h1 class="wp-heading-inline"><?php esc_html_e( 'API Stats', 'wp-api-stats' ); ?></h1>
	<hr class="wp-header-end">

	<?php if ( 'saved' === $notice ) : ?>
		<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Settings saved.', 'wp-api-stats' ); ?></p></div>
	<?php elseif ( 'purged' === $notice ) : ?>
		<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'All recorded API events were deleted.', 'wp-api-stats' ); ?></p></div>
	<?php endif; ?>

	<div class="api-stats-toolbar">
		<div class="api-stats-ranges" role="group" aria-label="<?php esc_attr_e( 'Quick ranges', 'wp-api-stats' ); ?>">
			<?php
			foreach ( $ranges as $range ) :
				list( $range_label, $range_from, $range_to, $range_chunk ) = $range;
				$is_current = $range_from->format( 'Y-m-d' ) === $current_date_from && $range_to->format( 'Y-m-d' ) === $current_date_to;
				$range_url  = SG_API_Stats::page_url(
					array(
						'date-from' => $range_from->format( 'Y-m-d' ),
						'date-to'   => $range_to->format( 'Y-m-d' ),
						'chunk'     => $range_chunk,
						'group'     => $group,
					)
				);
				?>
				<a class="button<?php echo $is_current ? ' button-primary' : ''; ?>" href="<?php echo esc_url( $range_url ); ?>"<?php echo $is_current ? ' aria-current="true"' : ''; ?>><?php echo esc_html( $range_label ); ?></a>
			<?php endforeach; ?>
		</div>

		<form class="api-stats-filters" action="<?php echo esc_url( admin_url( 'tools.php' ) ); ?>" method="GET">
			<input type="hidden" name="page" value="<?php echo esc_attr( SG_API_Stats::PAGE_SLUG ); ?>">
			<input type="hidden" name="group" value="<?php echo esc_attr( $group ); ?>">
			<label>
				<span><?php esc_html_e( 'From', 'wp-api-stats' ); ?></span>
				<input type="date" name="date-from" value="<?php echo esc_attr( $current_date_from ); ?>">
			</label>
			<label>
				<span><?php esc_html_e( 'To', 'wp-api-stats' ); ?></span>
				<input type="date" name="date-to" value="<?php echo esc_attr( $current_date_to ); ?>">
			</label>
			<label>
				<span><?php esc_html_e( 'Points', 'wp-api-stats' ); ?></span>
				<select name="chunk">
					<option <?php selected( $selected_chunk, 'Minute' ); ?> value="Minute"><?php esc_html_e( 'Minute', 'wp-api-stats' ); ?></option>
					<option <?php selected( $selected_chunk, 'Hour' ); ?> value="Hour"><?php esc_html_e( 'Hour', 'wp-api-stats' ); ?></option>
					<option <?php selected( $selected_chunk, 'Day' ); ?> value="Day"><?php esc_html_e( 'Day', 'wp-api-stats' ); ?></option>
					<option <?php selected( $selected_chunk, 'Week' ); ?> value="Week"><?php esc_html_e( 'Week', 'wp-api-stats' ); ?></option>
				</select>
			</label>
			<button class="button"><?php esc_html_e( 'Apply', 'wp-api-stats' ); ?></button>
		</form>
	</div>

	<p class="api-stats-tz">
		<?php
		/* translators: %s: timezone name, e.g. Europe/Berlin or +03:30 */
		printf( esc_html__( 'Times are in the site timezone (%s).', 'wp-api-stats' ), esc_html( $tz_name ) );
		?>
	</p>

	<div class="api-stats-cards">
		<div class="api-stats-card">
			<span class="api-stats-card__label"><?php esc_html_e( 'Requests', 'wp-api-stats' ); ?></span>
			<span class="api-stats-card__value"><?php echo esc_html( number_format_i18n( $total ) ); ?></span>
		</div>
		<div class="api-stats-card<?php echo $error_rate >= 5 ? ' is-bad' : ''; ?>">
			<span class="api-stats-card__label"><?php esc_html_e( 'Errors (4xx/5xx)', 'wp-api-stats' ); ?></span>
			<span class="api-stats-card__value"><?php echo esc_html( number_format_i18n( $error_rate, 1 ) ); ?>%</span>
			<span class="api-stats-card__sub"><?php echo esc_html( number_format_i18n( $error_count ) ); ?></span>
		</div>
		<div class="api-stats-card">
			<span class="api-stats-card__label"><?php esc_html_e( 'Avg. duration', 'wp-api-stats' ); ?></span>
			<span class="api-stats-card__value"><?php echo esc_html( number_format_i18n( (float) $summary->avg_duration, 1 ) ); ?> <small>ms</small></span>
		</div>
		<div class="api-stats-card">
			<span class="api-stats-card__label"><?php esc_html_e( 'Max. duration', 'wp-api-stats' ); ?></span>
			<span class="api-stats-card__value"><?php echo esc_html( number_format_i18n( (int) $summary->max_duration ) ); ?> <small>ms</small></span>
		</div>
		<div class="api-stats-card">
			<span class="api-stats-card__label"><?php esc_html_e( 'Routes', 'wp-api-stats' ); ?></span>
			<span class="api-stats-card__value"><?php echo esc_html( number_format_i18n( (int) $summary->routes ) ); ?></span>
		</div>
	</div>

	<div class="api-stats-panel">
		<div class="api-stats-panel__head">
			<h2><?php esc_html_e( 'Requests over time', 'wp-api-stats' ); ?></h2>
			<div class="api-stats-segmented" role="group" aria-label="<?php esc_attr_e( 'Chart breakdown', 'wp-api-stats' ); ?>">
				<button type="button" class="button button-primary" data-chart-mode="methods" aria-pressed="true"><?php esc_html_e( 'By method', 'wp-api-stats' ); ?></button>
				<button type="button" class="button" data-chart-mode="status" aria-pressed="false"><?php esc_html_e( 'By status', 'wp-api-stats' ); ?></button>
			</div>
		</div>
		<div class="api-stats-chart">
			<canvas id="api-stats-chart" aria-label="<?php esc_attr_e( 'Requests over time', 'wp-api-stats' ); ?>" role="img"></canvas>
		</div>
	</div>

	<div class="api-stats-panel">
		<div class="api-stats-panel__head">
			<h2><?php esc_html_e( 'Routes', 'wp-api-stats' ); ?></h2>
			<div class="api-stats-panel__tools">
				<div class="api-stats-segmented" role="group" aria-label="<?php esc_attr_e( 'Group routes', 'wp-api-stats' ); ?>">
					<?php
					$groups = array(
						'pattern' => array(
							'label'       => __( 'By pattern', 'wp-api-stats' ),
							'help'        => __( 'Requests are grouped by the route they matched: /wp/v2/posts/12 and /wp/v2/posts/57 count as one row, /wp/v2/posts/(?P<id>[\d]+). Requests that matched no route (404) are listed by path.', 'wp-api-stats' ),
							'placeholder' => __( 'Filter, e.g. /wp/v2/posts', 'wp-api-stats' ),
						),
						'path'    => array(
							'label'       => __( 'By exact path', 'wp-api-stats' ),
							'help'        => __( 'Every requested path is its own row: /wp/v2/posts/12 and /wp/v2/posts/57 are listed separately. Labels set with the sg_api_stats_label filter are shown here.', 'wp-api-stats' ),
							'placeholder' => __( 'Filter, e.g. /wp/v2/posts/12', 'wp-api-stats' ),
						),
					);
					foreach ( $groups as $group_key => $group_info ) :
						?>
						<a class="button<?php echo $group === $group_key ? ' button-primary' : ''; ?>" href="<?php echo esc_url( SG_API_Stats::page_url( array_merge( $filter_args, array( 'group' => $group_key ) ) ) ); ?>"<?php echo $group === $group_key ? ' aria-current="true"' : ''; ?>><?php echo esc_html( $group_info['label'] ); ?></a>
					<?php endforeach; ?>
				</div>
				<input type="search" class="api-stats-search" placeholder="<?php echo esc_attr( $groups[ $group ]['placeholder'] ); ?>" aria-label="<?php esc_attr_e( 'Filter routes', 'wp-api-stats' ); ?>">
			</div>
		</div>
		<p class="description api-stats-group-help"><?php echo esc_html( $groups[ $group ]['help'] ); ?></p>

		<?php if ( empty( $tableData ) ) : ?>
			<p class="api-stats-empty"><?php esc_html_e( 'No API requests recorded in this period.', 'wp-api-stats' ); ?></p>
		<?php else : ?>
			<div class="api-stats-table-wrap">
				<table class="widefat striped api-stats-table">
					<thead>
						<tr>
							<th scope="col" data-sort="text"><?php esc_html_e( 'Method', 'wp-api-stats' ); ?></th>
							<th scope="col" data-sort="text"><?php esc_html_e( 'Route', 'wp-api-stats' ); ?></th>
							<th scope="col" data-sort="num" class="num" aria-sort="descending"><?php esc_html_e( 'Requests', 'wp-api-stats' ); ?></th>
							<th scope="col" data-sort="num" class="num"><?php esc_html_e( 'Errors', 'wp-api-stats' ); ?></th>
							<th scope="col" data-sort="num" class="num"><?php esc_html_e( 'Avg. ms', 'wp-api-stats' ); ?></th>
							<th scope="col" data-sort="num" class="num"><?php esc_html_e( 'Max. ms', 'wp-api-stats' ); ?></th>
						</tr>
					</thead>
					<tbody>
					<?php
					foreach ( $tableData as $row ) :
						$row_errors  = (int) $row->errors;
						$route_count = (int) $row->count;
						?>
						<tr>
							<td><span class="api-stats-method api-stats-method--<?php echo esc_attr( strtolower( preg_replace( '/[^A-Za-z]/', '', $row->method ) ) ); ?>"><?php echo esc_html( $row->method ); ?></span></td>
							<td class="api-stats-route">
								<code><?php echo esc_html( $row->route ); ?></code>
								<?php if ( 'path' === $group && '' !== (string) $row->label ) : ?>
									<span class="api-stats-label"><?php echo esc_html( $row->label ); ?></span>
								<?php elseif ( 'pattern' === $group && (int) $row->paths > 1 ) : ?>
									<span class="api-stats-paths">
										<?php
										/* translators: %s: number of distinct request paths */
										echo esc_html( sprintf( _n( '%s path', '%s paths', (int) $row->paths, 'wp-api-stats' ), number_format_i18n( (int) $row->paths ) ) );
										?>
									</span>
								<?php endif; ?>
							</td>
							<td class="num" data-value="<?php echo esc_attr( $route_count ); ?>"><?php echo esc_html( number_format_i18n( $route_count ) ); ?></td>
							<td class="num<?php echo $row_errors ? ' api-stats-has-errors' : ''; ?>" data-value="<?php echo esc_attr( $row_errors ); ?>">
								<?php
								echo esc_html( number_format_i18n( $row_errors ) );
								if ( $row_errors ) {
									echo ' <small>(' . esc_html( number_format_i18n( $row_errors / $route_count * 100, 0 ) ) . '%)</small>';
								}
								?>
							</td>
							<td class="num" data-value="<?php echo esc_attr( (float) $row->average_duration ); ?>"><?php echo esc_html( number_format_i18n( (float) $row->average_duration, 1 ) ); ?></td>
							<td class="num" data-value="<?php echo esc_attr( (int) $row->max_duration ); ?>"><?php echo esc_html( number_format_i18n( (int) $row->max_duration ) ); ?></td>
						</tr>
					<?php endforeach; ?>
					</tbody>
				</table>
			</div>
			<?php if ( count( $tableData ) >= SG_API_Stats::MAX_ROUTES ) : ?>
				<p class="description">
					<?php
					/* translators: %s: maximum number of rows shown */
					printf( esc_html__( 'Showing the %s busiest routes.', 'wp-api-stats' ), esc_html( number_format_i18n( SG_API_Stats::MAX_ROUTES ) ) );
					?>
				</p>
			<?php endif; ?>
		<?php endif; ?>
	</div>

	<div class="api-stats-panel" id="api-stats-data">
		<div class="api-stats-panel__head">
			<h2><?php esc_html_e( 'Stored data', 'wp-api-stats' ); ?></h2>
		</div>

		<p>
			<?php
			printf(
				/* translators: 1: number of events, 2: database table name, 3: size in MB */
				esc_html__( '%1$s events stored in the %2$s table (%3$s MB).', 'wp-api-stats' ),
				'<strong>' . esc_html( number_format_i18n( $row_count ) ) . '</strong>',
				'<code>' . esc_html( SG_API_Stats::table_name() ) . '</code>',
				esc_html( null === $data_size ? '?' : $data_size )
			);
			?>
		</p>

		<div class="api-stats-data-forms">
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="api-stats-retention">
				<input type="hidden" name="action" value="sg_api_stats_save_settings">
				<?php wp_nonce_field( 'sg_api_stats_save_settings' ); ?>
				<label for="api-stats-retention-days"><strong><?php esc_html_e( 'Keep events for', 'wp-api-stats' ); ?></strong></label>
				<input type="number" min="0" max="3650" step="1" id="api-stats-retention-days" name="retention_days" class="small-text" value="<?php echo esc_attr( (int) $settings['retention_days'] ); ?>">
				<?php esc_html_e( 'days', 'wp-api-stats' ); ?>
				<button class="button"><?php esc_html_e( 'Save', 'wp-api-stats' ); ?></button>
				<p class="description">
					<?php esc_html_e( 'Use 0 to keep everything (watch the table size). Lowering the number deletes older events right away; deleted events cannot be restored.', 'wp-api-stats' ); ?>
					<?php if ( (int) $settings['retention_days'] !== $retention_days ) : ?>
						<br><strong>
						<?php
						/* translators: %s: number of days */
						printf( esc_html__( 'Overridden by the sg_api_stats_retention_days filter: %s days.', 'wp-api-stats' ), esc_html( $retention_days ) );
						?>
						</strong>
					<?php endif; ?>
				</p>
			</form>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="api-stats-purge" data-confirm-purge>
				<input type="hidden" name="action" value="sg_api_stats_purge">
				<?php wp_nonce_field( 'sg_api_stats_purge' ); ?>
				<button class="button api-stats-button-danger"<?php disabled( 0 === $row_count ); ?>><?php esc_html_e( 'Delete all data', 'wp-api-stats' ); ?></button>
				<p class="description"><?php esc_html_e( 'Permanently removes every recorded event. Recording continues afterwards.', 'wp-api-stats' ); ?></p>
			</form>
		</div>
	</div>
</div>
