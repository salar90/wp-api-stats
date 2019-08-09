<?php
if ( ! defined( 'WPINC' ) ) {
	die;
}

?>
<div class="wrap">
	<h1 class="wp-heading-inline"><?php _e("API Stats", "api-stats") ?></h1>
	<hr class="wp-header-end">
	<br>

	<div class="at-controls" dir="ltr">
		<form action="" method="POST">
			<label>
				<?php _e("Date From", "api-stats") ?>
				<input type="date" name="date-from" id="date-from" value="<?= $current_date_from ?>" >
			</label>
			&emsp;

			<label>
				<?php _e("Date To", "api-stats") ?>
				<input type="date" name="date-to" id="date-to" value="<?= $current_date_to ?>">
			</label>
			&emsp;

			<label>
				<?php _e("Points", "api-stats") ?>
				<select name="chunk" id="chunks">
					<option <?php selected($selected_chunk, 'Minute') ?> value="Minute"><?php _e("Minute", "api-stats") ?></option>
					<option <?php selected($selected_chunk, 'Hour') ?> value="Hour"><?php _e("Hour", "api-stats") ?></option>
					<option <?php selected($selected_chunk, 'Day') ?> value="Day"><?php _e("Day", "api-stats") ?></option>
					<option <?php selected($selected_chunk, 'Week') ?> value="Week"><?php _e("Week", "api-stats") ?></option>
				</select>
			</label>
			&emsp;

			<button class="button">Apply</button>

		</form>
	</div>


	<canvas id="ApiChart" width="400" height="150"></canvas>

</div>
