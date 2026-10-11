<?php
if ( ! defined( 'WPINC' ) ) {
	die;
}

?>
<div class="wrap">
	<h1 class="wp-heading-inline"><?php esc_html_e( 'API Stats', 'wp-api-stats' ); ?></h1>
	<hr class="wp-header-end">
	<br>

	<div class="at-controls" dir="ltr">
		<form action="<?php echo esc_url( admin_url( 'tools.php' ) ); ?>" method="GET">
			<input type="hidden" name="page" value="<?php echo esc_attr( SG_API_Stats::PAGE_SLUG ); ?>">
			<label>
				<?php esc_html_e( 'Date From', 'wp-api-stats' ); ?>
				<input type="date" name="date-from" id="date-from" value="<?php echo esc_attr( $current_date_from ); ?>">
			</label>
			&emsp;

			<label>
				<?php esc_html_e( 'Date To', 'wp-api-stats' ); ?>
				<input type="date" name="date-to" id="date-to" value="<?php echo esc_attr( $current_date_to ); ?>">
			</label>
			&emsp;

			<label>
				<?php esc_html_e( 'Points', 'wp-api-stats' ); ?>
				<select name="chunk" id="chunks">
					<option <?php selected( $selected_chunk, 'Minute' ); ?> value="Minute"><?php esc_html_e( 'Minute', 'wp-api-stats' ); ?></option>
					<option <?php selected( $selected_chunk, 'Hour' ); ?> value="Hour"><?php esc_html_e( 'Hour', 'wp-api-stats' ); ?></option>
					<option <?php selected( $selected_chunk, 'Day' ); ?> value="Day"><?php esc_html_e( 'Day', 'wp-api-stats' ); ?></option>
					<option <?php selected( $selected_chunk, 'Week' ); ?> value="Week"><?php esc_html_e( 'Week', 'wp-api-stats' ); ?></option>
				</select>
			</label>
			&emsp;

			<button class="button"><?php esc_html_e( 'Apply', 'wp-api-stats' ); ?></button>

		</form>
	</div>


	<canvas id="ApiChart" width="400" height="150"></canvas>

</div>
