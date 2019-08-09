<?php
if ( ! defined( 'WPINC' ) ) {
	die;
}

class SG_API_Stats{
	private $settings = [];
	private $prepared_data = false;

	function __construct() {
		$this->settings["menu_mode"] = "SUBMENU";
		$this->register_hooks();
	}

	function register_hooks(){
		add_action("admin_menu",[$this,"admin_menu"]);
		
		// Hooks for requests
		add_filter( 'rest_pre_serve_request' , [$this,'pre_serve'] , 5, 4 );
		add_action( 'rest_api_init' , [$this,'rest_api_init'] , 5 );

		// prepare data for admin page
		add_action( 'admin_print_scripts', [$this, 'add_js_data'] );

		// enqueue js and css files
		add_action( 'admin_enqueue_scripts', [$this, 'load_admin_style'] );

		// inline styles
		add_action( 'admin_print_styles-tools_page_api-stats', [$this, 'load_admin_inline_style']);
	}


	/**
	 * API init (the very beginning of a request)
	 *
	 */
	function rest_api_init(){
		global $api_stats_start_time;
		$api_stats_start_time = microtime(true);
	}

	/**
	 * Things to do just before echoing the API response.
	 *
	 *
	 * @param bool             $served  Whether the request has already been served.
	 * @param WP_HTTP_Response $response  Result to send to the client. Usually a WP_REST_Response.
	 * @param WP_REST_Request  $request Request used to generate the response.
	 * @param WP_REST_Server   $server    Server instance.
	 */
	function pre_serve($served, $response, $request, $server){
		global $wpdb;
		global $api_stats_start_time;
		$table_name = $wpdb->prefix . 'sg_api_stats_events'; 

		$response_status = 0;
		if(is_a($response,WP_HTTP_Response::class)){
			$response_status = $response->get_status();
		}
		if(is_a($response,WP_Error::class)){
			$response_status = $response->get_error_code();
		}
		$time = current_time('mysql', true);
		
		$end_time = microtime(true);
		$time_taken = floor( ($end_time - $api_stats_start_time)*1000 );
		

		$new_entry = [
			'method' => $request->get_method(),
			'route' => $request->get_route(),
			'respose_code' => $response_status,
			'time' => $time,
			'duration' => $time_taken,
			// 'user' => $user->ID  // Todo: detect user
		];
		$wpdb->insert($table_name, $new_entry);

		return $served;

	}

	function prepare_data(){
		if($this->prepared_data){
			return $this->prepared_data;
		}

		$current_date_from = filter_input(INPUT_POST, 'date-from', FILTER_SANITIZE_STRING);
		$current_date_to = filter_input(INPUT_POST, 'date-to', FILTER_SANITIZE_STRING);
		$selected_chunk =filter_input(INPUT_POST, 'chunk', FILTER_SANITIZE_STRING);

		//timezone offset
		$tz_offset = get_option( 'gmt_offset', 0 ) * HOUR_IN_SECONDS;

		if(empty($current_date_from)){
			$current_date_from = date('Y-m-d', time() - 24*3600);
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
			
			$ch_start = $i - $tz_offset ;
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

		$this->prepared_data = compact('data', 'current_date_from', 'current_date_to', 'selected_chunk', 'labels');

		return $this->prepared_data;

	}


	/**
	 * Admin menu setup
	 */
	function admin_menu(){
		if( $this->settings['menu_mode'] == "SUBMENU" ){
			add_submenu_page('tools.php' , __("API Stats","api-stats") , __("API Stats","api-stats") , 'manage_options' , 'api-stats' , [$this,"admin_page"] );
		}else{
			add_menu_page(__("API Stats","api-stats") , __("API Stats","api-stats") , "manage_options" , 'api-stats' , [$this,"admin_page"] );
		}
	}

	/**
	 * Display admin page contents
	 */
	function admin_page(){
		extract($this->prepare_data());
		include __DIR__ . '/views/admin-panel.php';
	}

	function load_admin_style($hook) {
        if($hook != 'tools_page_api-stats') {
                return;
        }
		wp_enqueue_style( 'chartjs-css', plugins_url('assets/chartjs/Chart.min.css', __FILE__), array(), '2.8.0' );
		wp_enqueue_script('chartjs', plugins_url('assets/chartjs/Chart.min.js', __FILE__), array(), '2.8.0' );
		wp_enqueue_script('api-stats-draw', plugins_url('assets/draw.js', __FILE__), array('chartjs'), '1.0', true );
	}

	function load_admin_inline_style(){
		echo '
		<style type="text/css">
		.at-controls{
			border: 1px solid #CCC;
			background: #EEEEEE;
			padding: 10px;
			border-radius: 5px;
		}
		</style>
		';
	}


	function add_js_data(){
		$screen = get_current_screen();
		if($screen->id !== 'tools_page_api-stats'){
			return;
		}
		$data_json = wp_json_encode($this->prepare_data());
		echo "<script type='text/javascript'>\n";
		echo 'var ApichartData = ' . $data_json . ';';
		echo '</script>';
	}


}