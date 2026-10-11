<?php
/**
 * Stats page. Variables come from SG_API_Stats::admin_page().
 *
 * Filters (top bar) decide which requests are counted and apply to the cards, chart and table alike.
 * View options (chart breakdown, points, table grouping) only change how the same requests are shown.
 *
 * @var array    $state     See SG_API_Stats::get_state().
 * @var object   $summary
 * @var object[] $tableData
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
	array( __( 'Today', 'wp-api-stats' ), $today_date, $today_date, 'Hour' ),
	array( __( 'Yesterday', 'wp-api-stats' ), $today_date->modify( '-1 day' ), $today_date->modify( '-1 day' ), 'Hour' ),
	array( __( 'Last 7 days', 'wp-api-stats' ), $today_date->modify( '-6 days' ), $today_date, 'Hour' ),
	array( __( 'Last 30 days', 'wp-api-stats' ), $today_date->modify( '-29 days' ), $today_date, 'Day' ),
);

$status_options = array(
	''       => __( 'All statuses', 'wp-api-stats' ),
	'errors' => __( 'Errors (4xx + 5xx)', 'wp-api-stats' ),
	'2xx'    => __( '2xx Success', 'wp-api-stats' ),
	'3xx'    => __( '3xx Redirect', 'wp-api-stats' ),
	'4xx'    => __( '4xx Client error', 'wp-api-stats' ),
	'5xx'    => __( '5xx Server error', 'wp-api-stats' ),
);

$groups = array(
	'pattern' => array(
		'label' => __( 'By pattern', 'wp-api-stats' ),
		'help'  => __( 'Requests are grouped by the route they matched: /wp/v2/posts/12 and /wp/v2/posts/57 count as one row, /wp/v2/posts/(?P<id>[\d]+). Requests that matched no route (404) are listed by path.', 'wp-api-stats' ),
	),
	'path'    => array(
		'label' => __( 'By exact path', 'wp-api-stats' ),
		'help'  => __( 'Every requested path is its own row: /wp/v2/posts/12 and /wp/v2/posts/57 are listed separately. Labels set with the sg_api_stats_label filter are shown here.', 'wp-api-stats' ),
	),
);

// Active filters, each removable on its own.
$chips = array();
if ( '' !== $state['route'] ) {
	/* translators: %s: REST route */
	$chips['route'] = sprintf( __( 'Route is %s', 'wp-api-stats' ), $state['route'] );
}
if ( '' !== $state['q'] ) {
	/* translators: %s: search text */
	$chips['q'] = sprintf( __( 'Route contains "%s"', 'wp-api-stats' ), $state['q'] );
}
if ( '' !== $state['method'] ) {
	/* translators: %s: HTTP method */
	$chips['method'] = sprintf( __( 'Method %s', 'wp-api-stats' ), $state['method'] );
}
if ( '' !== $state['status'] ) {
	$chips['status'] = $status_options[ $state['status'] ];
}
$clear_filters = array(
	'route'  => '',
	'q'      => '',
	'method' => '',
	'status' => '',
);

$total       = (int) $summary->total;
$error_count = (int) $summary->errors;
$error_rate  = $total ? $error_count / $total * 100 : 0;

// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- only selects which notice to show.
$notice = isset( $_GET['sg-notice'] ) ? sanitize_key( $_GET['sg-notice'] ) : '';

/**
 * Hidden inputs carrying the current state, except the given keys (which the form itself sets).
 */
$hidden_state = function ( $except ) use ( $state ) {
	echo '<input type="hidden" name="page" value="' . esc_attr( SG_API_Stats::PAGE_SLUG ) . '">';
	foreach ( SG_API_Stats::state_args( $state ) as $name => $value ) {
		if ( ! in_array( $name, $except, true ) ) {
			echo '<input type="hidden" name="' . esc_attr( $name ) . '" value="' . esc_attr( $value ) . '">';
		}
	}
};
?>
<div class="wrap api-stats">
	<h1 class="wp-heading-inline"><?php esc_html_e( 'API Stats', 'wp-api-stats' ); ?></h1>
	<hr class="wp-header-end">

	<?php if ( 'saved' === $notice ) : ?>
		<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Settings saved.', 'wp-api-stats' ); ?></p></div>
	<?php elseif ( 'purged' === $notice ) : ?>
		<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'All recorded API events were deleted.', 'wp-api-stats' ); ?></p></div>
	<?php endif; ?>

	<div class="api-stats-filterbar">
		<div class="api-stats-ranges" role="group" aria-label="<?php esc_attr_e( 'Quick ranges', 'wp-api-stats' ); ?>">
			<?php
			foreach ( $ranges as $range ) :
				list( $range_label, $range_from, $range_to, $range_chunk ) = $range;
				$is_current = $range_from->format( 'Y-m-d' ) === $state['date_from'] && $range_to->format( 'Y-m-d' ) === $state['date_to'];
				$range_url  = SG_API_Stats::state_url(
					$state,
					array(
						'date_from' => $range_from->format( 'Y-m-d' ),
						'date_to'   => $range_to->format( 'Y-m-d' ),
						'chunk'     => $range_chunk,
					)
				);
				?>
				<a class="button<?php echo $is_current ? ' button-primary' : ''; ?>" href="<?php echo esc_url( $range_url ); ?>"<?php echo $is_current ? ' aria-current="true"' : ''; ?>><?php echo esc_html( $range_label ); ?></a>
			<?php endforeach; ?>
		</div>

		<form class="api-stats-filters" action="<?php echo esc_url( admin_url( 'tools.php' ) ); ?>" method="GET">
			<?php $hidden_state( array( 'date-from', 'date-to', 'q', 'method', 'status' ) ); ?>
			<label>
				<span><?php esc_html_e( 'From', 'wp-api-stats' ); ?></span>
				<input type="date" name="date-from" value="<?php echo esc_attr( $state['date_from'] ); ?>">
			</label>
			<label>
				<span><?php esc_html_e( 'To', 'wp-api-stats' ); ?></span>
				<input type="date" name="date-to" value="<?php echo esc_attr( $state['date_to'] ); ?>">
			</label>
			<label class="api-stats-filter-q">
				<span><?php esc_html_e( 'Route contains', 'wp-api-stats' ); ?></span>
				<input type="search" name="q" value="<?php echo esc_attr( $state['q'] ); ?>" placeholder="<?php esc_attr_e( 'e.g. /wp/v2/posts or a label', 'wp-api-stats' ); ?>">
			</label>
			<label>
				<span><?php esc_html_e( 'Method', 'wp-api-stats' ); ?></span>
				<select name="method">
					<option value=""><?php esc_html_e( 'All methods', 'wp-api-stats' ); ?></option>
					<?php foreach ( SG_API_Stats::METHODS as $method_option ) : ?>
						<option value="<?php echo esc_attr( $method_option ); ?>" <?php selected( $state['method'], $method_option ); ?>><?php echo esc_html( $method_option ); ?></option>
					<?php endforeach; ?>
				</select>
			</label>
			<label>
				<span><?php esc_html_e( 'Status', 'wp-api-stats' ); ?></span>
				<select name="status">
					<?php foreach ( $status_options as $status_value => $status_label ) : ?>
						<option value="<?php echo esc_attr( $status_value ); ?>" <?php selected( $state['status'], $status_value ); ?>><?php echo esc_html( $status_label ); ?></option>
					<?php endforeach; ?>
				</select>
			</label>
			<button class="button button-primary"><?php esc_html_e( 'Apply', 'wp-api-stats' ); ?></button>
		</form>
	</div>

	<?php if ( $chips ) : ?>
		<div class="api-stats-chips">
			<span class="api-stats-chips__title"><?php esc_html_e( 'Filtered:', 'wp-api-stats' ); ?></span>
			<?php foreach ( $chips as $chip_key => $chip_label ) : ?>
				<a class="api-stats-chip" href="<?php echo esc_url( SG_API_Stats::state_url( $state, array( $chip_key => '' ) ) ); ?>" title="<?php esc_attr_e( 'Remove this filter', 'wp-api-stats' ); ?>">
					<span><?php echo esc_html( $chip_label ); ?></span>
					<span class="api-stats-chip__x" aria-hidden="true">&times;</span>
					<span class="screen-reader-text"><?php esc_html_e( 'Remove filter', 'wp-api-stats' ); ?></span>
				</a>
			<?php endforeach; ?>
			<?php if ( count( $chips ) > 1 ) : ?>
				<a class="api-stats-chips__clear" href="<?php echo esc_url( SG_API_Stats::state_url( $state, $clear_filters ) ); ?>"><?php esc_html_e( 'Clear all filters', 'wp-api-stats' ); ?></a>
			<?php endif; ?>
		</div>
	<?php endif; ?>

	<p class="api-stats-tz">
		<?php
		/* translators: %s: timezone name, e.g. Europe/Berlin or +03:30 */
		printf( esc_html__( 'Times are in the site timezone (%s).', 'wp-api-stats' ), esc_html( $tz_name ) );
		?>
	</p>

	<div class="api-stats-cards<?php echo $chips ? ' is-filtered' : ''; ?>">
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
			<div class="api-stats-panel__tools">
				<div class="api-stats-segmented" role="group" aria-label="<?php esc_attr_e( 'Chart breakdown', 'wp-api-stats' ); ?>">
					<button type="button" class="button button-primary" data-chart-mode="methods" aria-pressed="true"><?php esc_html_e( 'By method', 'wp-api-stats' ); ?></button>
					<button type="button" class="button" data-chart-mode="status" aria-pressed="false"><?php esc_html_e( 'By status', 'wp-api-stats' ); ?></button>
				</div>
				<form class="api-stats-points" action="<?php echo esc_url( admin_url( 'tools.php' ) ); ?>" method="GET">
					<?php $hidden_state( array( 'chunk' ) ); ?>
					<label>
						<span><?php esc_html_e( 'Points', 'wp-api-stats' ); ?></span>
						<select name="chunk" data-autosubmit>
							<option <?php selected( $state['chunk'], 'Minute' ); ?> value="Minute"><?php esc_html_e( 'Minute', 'wp-api-stats' ); ?></option>
							<option <?php selected( $state['chunk'], 'Hour' ); ?> value="Hour"><?php esc_html_e( 'Hour', 'wp-api-stats' ); ?></option>
							<option <?php selected( $state['chunk'], 'Day' ); ?> value="Day"><?php esc_html_e( 'Day', 'wp-api-stats' ); ?></option>
							<option <?php selected( $state['chunk'], 'Week' ); ?> value="Week"><?php esc_html_e( 'Week', 'wp-api-stats' ); ?></option>
						</select>
					</label>
					<noscript><button class="button"><?php esc_html_e( 'Apply', 'wp-api-stats' ); ?></button></noscript>
				</form>
			</div>
		</div>
		<div class="api-stats-chart">
			<canvas id="api-stats-chart" aria-label="<?php esc_attr_e( 'Requests over time', 'wp-api-stats' ); ?>" role="img"></canvas>
		</div>
	</div>

	<div class="api-stats-panel">
		<div class="api-stats-panel__head">
			<h2><?php esc_html_e( 'Routes', 'wp-api-stats' ); ?></h2>
			<div class="api-stats-segmented" role="group" aria-label="<?php esc_attr_e( 'Group routes', 'wp-api-stats' ); ?>">
				<?php foreach ( $groups as $group_key => $group_info ) : ?>
					<a class="button<?php echo $state['group'] === $group_key ? ' button-primary' : ''; ?>" href="<?php echo esc_url( SG_API_Stats::state_url( $state, array( 'group' => $group_key ) ) ); ?>"<?php echo $state['group'] === $group_key ? ' aria-current="true"' : ''; ?>><?php echo esc_html( $group_info['label'] ); ?></a>
				<?php endforeach; ?>
			</div>
		</div>
		<p class="description api-stats-group-help">
			<?php echo esc_html( $groups[ $state['group'] ]['help'] ); ?>
			<?php esc_html_e( 'Click a route to filter the whole page by it.', 'wp-api-stats' ); ?>
		</p>

		<?php if ( empty( $tableData ) ) : ?>
			<p class="api-stats-empty">
				<?php if ( $chips ) : ?>
					<?php esc_html_e( 'No requests match these filters.', 'wp-api-stats' ); ?>
					<a href="<?php echo esc_url( SG_API_Stats::state_url( $state, $clear_filters ) ); ?>"><?php esc_html_e( 'Clear all filters', 'wp-api-stats' ); ?></a>
				<?php else : ?>
					<?php esc_html_e( 'No API requests recorded in this period.', 'wp-api-stats' ); ?>
				<?php endif; ?>
			</p>
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
						$row_url     = SG_API_Stats::state_url(
							$state,
							array(
								'route'  => $row->route,
								'method' => $row->method,
							)
						);
						?>
						<tr>
							<td><span class="api-stats-method api-stats-method--<?php echo esc_attr( strtolower( preg_replace( '/[^A-Za-z]/', '', $row->method ) ) ); ?>"><?php echo esc_html( $row->method ); ?></span></td>
							<td class="api-stats-route">
								<a href="<?php echo esc_url( $row_url ); ?>" title="<?php esc_attr_e( 'Show only this route', 'wp-api-stats' ); ?>"><code><?php echo esc_html( $row->route ); ?></code></a>
								<?php if ( 'path' === $state['group'] && '' !== (string) $row->label ) : ?>
									<span class="api-stats-label"><?php echo esc_html( $row->label ); ?></span>
								<?php elseif ( 'pattern' === $state['group'] && (int) $row->paths > 1 ) : ?>
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
					printf( esc_html__( 'Showing the %s busiest routes. Use the filters above to find others.', 'wp-api-stats' ), esc_html( number_format_i18n( SG_API_Stats::MAX_ROUTES ) ) );
					?>
				</p>
			<?php endif; ?>
		<?php endif; ?>
	</div>

	<div class="api-stats-panel" id="api-stats-data">
		<div class="api-stats-panel__head">
			<h2><?php esc_html_e( 'Stored data', 'wp-api-stats' ); ?> <span class="api-stats-panel__note"><?php esc_html_e( '(all time, not filtered)', 'wp-api-stats' ); ?></span></h2>
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
