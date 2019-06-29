<?php

	$chunks = [
		'Minute'	=> 60,
		'Hour'		=> 3600,
		'Day'		=> 3600*24,
		'Week'		=> 3600*24*7
	];

	$selected_chunk = 'Minute';
	
	$end = time() + 3600*4;
	
	$duration = ( 20 ) * $chunks[$selected_chunk]  ;

	$start = time() - $duration;


	$chunk_count = ceil( $duration / $chunks[$selected_chunk] );
	
	global $wpdb;

	$data['all'] = [];
	$labels = range(1,$chunk_count);

	for($i=$start , $j=1; $j <= $chunk_count; $i+=$chunks[$selected_chunk] , $j++ ){
		
		$ch_start = $i;
		$ch_end = $ch_start + $chunks[$selected_chunk];
		
		$q_start = 	"'" . date("Y-m-d H:i:s", $ch_start) . "'";
		$q_end = 	"'" .date("Y-m-d H:i:s", $ch_end) . "'";
		
		$results = $wpdb->get_results( "SELECT * FROM {$wpdb->prefix}sg_api_tracker_events WHERE time >= $q_start AND time < $q_end", OBJECT );
		$count = count($results);
		$data['all'][] = $count;
		
	}
	$json_labels = json_encode($labels);
	$json_data_all = json_encode($data['all']);


?>
<div class="wrap">
	<h1 class="wp-heading-inline"><?php _e("API Tracker Stats", "api-tracker") ?></h1>
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

	<div class="at-controls">
		
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
