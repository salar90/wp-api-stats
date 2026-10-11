<?php
if ( ! defined( 'WPINC' ) ) {
	die;
} ?>

<table class="api-stats-table">
	<thead>
		<tr>
			<th><?php esc_html_e( 'Method', 'wp-api-stats' ); ?></th>
			<th><?php esc_html_e( 'Route', 'wp-api-stats' ); ?></th>
			<th><?php esc_html_e( 'Request Count', 'wp-api-stats' ); ?></th>
			<th><?php esc_html_e( 'Average Duration (ms)', 'wp-api-stats' ); ?></th>
		</tr>
	</thead>
	<tbody>
	<?php foreach ( $tableData as $row ) { ?>
		<tr>
			<td><?php echo esc_html( $row->method ); ?></td>
			<td><?php echo esc_html( $row->route ); ?></td>
			<td><?php echo esc_html( number_format_i18n( $row->count ) ); ?></td>
			<td><?php echo esc_html( number_format_i18n( (float) $row->average_duration, 1 ) ); ?></td>
		</tr>
	<?php } ?>
	</tbody>
</table>
