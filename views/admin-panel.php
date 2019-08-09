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


$methods = ['GET', 'POST', 'DELETE', 'PUT', 'PATCH', 'OPTIONS'];

$start = strtotime($current_date_from);

global $wpdb;

$data['all'] = [];
$labels = [];


for($i=$start , $j=1; $j <= $chunk_count; $i+=$chunks[$selected_chunk] , $j++ ){
	
	$ch_start = $i;
	$ch_end = $ch_start + $chunks[$selected_chunk];
	
	$q_start = 	"'" . date("Y-m-d H:i:s", $ch_start) . "'";
	$q_end = 	"'" .date("Y-m-d H:i:s", $ch_end) . "'";
	
	$results = $wpdb->get_results( "SELECT * FROM {$wpdb->prefix}sg_api_stats_events WHERE time >= $q_start AND time < $q_end", OBJECT );
	$count = count($results);

	
	foreach($methods as $method){
		$c = 0;
		foreach($results as $entry){
			if( $entry->method == $method){
				$c++;
			}
		}
		$data[$method][] = $c;
	}
	
	if( in_array($selected_chunk, ['Minute', 'Hour']) ){
		$labels[] = date("H:i", $i);
	}

	if( in_array($selected_chunk, [ 'Day', 'Week']) ){
		$labels[] = date("m-d H:i", $i);
	}
	
	$data['all'][] = $count;
	
}

$json_labels = json_encode($labels);
$json_data_all = json_encode($data['all']);


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


	<canvas id="myChart" width="400" height="150"></canvas>
	<script>
	var ctx = document.getElementById('myChart').getContext('2d');
	var myChart = new Chart(ctx, {
		type: 'line',
		data: {
			labels: <?= $json_labels ?>,
			datasets: [
				{
					label: 'All',
					data: <?= $json_data_all ?>,
					borderColor : '#CCCCCC',
					backgroundColor : '#00000000',
					borderWidth: 1,
            		hidden: true,
				},
				{
					label: 'GET',
					data: <?= json_encode($data['GET']) ?>,
					borderColor : '#44EE44',
					backgroundColor : '#44EE4433',
					borderWidth: 1,
				},
				{
					label: 'POST',
					data: <?= json_encode($data['POST']) ?>,
					borderColor : '#883997',
					backgroundColor : '#88399733',
					borderWidth: 1,
				},
				{
					label: 'PUT',
					data: <?= json_encode($data['PUT']) ?>,
					borderColor : '#ffb300',
					backgroundColor : '#ffb30033',
					borderWidth: 1,
				},
				{
					label: 'PATCH',
					data: <?= json_encode($data['PATCH']) ?>,
					borderColor: '#f5fd67',
					backgroundColor : '#f5fd6733',
					borderWidth: 1,
				},
				{
					label: 'DELETE',
					data: <?= json_encode($data['DELETE']) ?>,
					borderColor : '#ab000d',
					backgroundColor : '#ab000d44',
					borderWidth: 1,
				}
			]
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
