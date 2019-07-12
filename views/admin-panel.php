<?php
if ( ! defined( 'WPINC' ) ) {
	die;
}

$current_date_from = filter_input(INPUT_POST, 'date-from', FILTER_SANITIZE_STRING);
$current_date_to = filter_input(INPUT_POST, 'date-to', FILTER_SANITIZE_STRING);
$selected_chunk =filter_input(INPUT_POST, 'chunk', FILTER_SANITIZE_STRING);

if(empty($current_date_from)){
	$current_date_from = date('Y-m-d', time()- 24*3600);
}

if(empty($current_date_to)){
	$current_date_to = date('Y-m-d',strtotime('today') );
}

if(empty($selected_chunk)){
	$selected_chunk = 'Hour';
}


$duration = strtotime($current_date_to) - strtotime($current_date_from) + 3600*24;

$chunks = [
	'Minute'	=> 60,
	'Hour'		=> 3600,
	'Day'		=> 3600*24,
	'Week'		=> 3600*24*7
];


$chunkUp = [
	'Minute'	=> 'Hour',
	'Hour'		=> 'Day',
	'Day'		=> 'Week'
];
while ( ($chunk_count = ceil( $duration / $chunks[$selected_chunk] )) > 3000){
	if( array_key_exists($selected_chunk, $chunkUp) ){
		$selected_chunk = $chunkUp[$selected_chunk];
	}else{
		break;
	}
}

$start = strtotime($current_date_from);

global $wpdb;

$data['all'] = [];
$labels = range(1,$chunk_count);

for($i=$start , $j=1; $j <= $chunk_count; $i+=$chunks[$selected_chunk] , $j++ ){
	
	$ch_start = $i;
	$ch_end = $ch_start + $chunks[$selected_chunk];
	
	$q_start = 	"'" . date("Y-m-d H:i:s", $ch_start) . "'";
	$q_end = 	"'" .date("Y-m-d H:i:s", $ch_end) . "'";
	
	$results = $wpdb->get_results( "SELECT * FROM {$wpdb->prefix}sg_api_Stats_events WHERE time >= $q_start AND time < $q_end", OBJECT );
	$count = count($results);
	$data['all'][] = $count;
	
}
$json_labels = json_encode($labels);
$json_data_all = json_encode($data['all']);


?>
<div class="wrap">
	<h1 class="wp-heading-inline"><?php _e("API Stats", "api-Stats") ?></h1>
	<hr class="wp-header-end">
	<br>

	<style>
	.at-controls{
		border: 1px solid #CCC;
		background: #EEEEEE;
		padding: 10px;
		border-radius: 5px;
	}
	</style>
	<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/2.8.0/Chart.min.css" />
	<script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/2.8.0/Chart.min.js"></script>

	<div class="at-controls" dir="ltr">
		<form action="" method="POST">
			<label>
				<?php _e("Date From", "api-Stats") ?>
				<input type="date" name="date-from" id="date-from" value="<?= $current_date_from ?>" >
			</label>
			&emsp;

			<label>
				<?php _e("Date To", "api-Stats") ?>
				<input type="date" name="date-to" id="date-to" value="<?= $current_date_to ?>">
			</label>
			&emsp;

			<label>
				<?php _e("Points", "api-Stats") ?>
				<select name="chunk" id="chunks">
					<option <?php selected($selected_chunk, 'Minute') ?> value="Minute"><?php _e("Minute", "api-Stats") ?></option>
					<option <?php selected($selected_chunk, 'Hour') ?> value="Hour"><?php _e("Hour", "api-Stats") ?></option>
					<option <?php selected($selected_chunk, 'Day') ?> value="Day"><?php _e("Day", "api-Stats") ?></option>
					<option <?php selected($selected_chunk, 'Week') ?> value="Week"><?php _e("Week", "api-Stats") ?></option>
				</select>
			</label>
			&emsp;

			<button class="button">Apply</button>

		</form>
	</div>


	<canvas id="myChart" width="400" height="150"></canvas>
	<script>
	var ctx = document.getElementById('myChart').getContext('2d');
	var myChart = new Chart(ctx, {
		type: 'line',
		data: {
			labels: <?= $json_labels ?>,
			datasets: [{
				label: 'Requests',
				data: <?= $json_data_all ?>,
				
			}]
		},
		options: {
			scales: {
				yAxes: [{
					ticks: {
						beginAtZero: true
					}
				}]
			}
		}
	});
	</script>

</div>
