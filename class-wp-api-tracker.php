<?php
if ( ! defined( 'WPINC' ) ) {
	die;
}

class SG_API_Tracker{
	private $settings = [];

	function __construct() {
		$this->settings["menu_mode"] = "SUBMENU";
		$this->register_hooks();
	}

	function register_hooks(){
		add_action("admin_menu",[$this,"admin_menu"]);
//		add_filter( 'rest_pre_serve_request' , [$this,'track'] , 5 , 3 );
		add_filter( 'rest_post_dispatch' , [$this,'log_call'] , 5 , 3 );
	}

	function track( $response, WP_REST_Server $handler, WP_REST_Request $request ){

		$current_minute_json = get_option("sg_api_tracker_minute" , "[]");
		$current_minute = json_decode($current_minute_json , true);
		if(empty($current_minute)){
			$current_minute = [];
		}
		$time = time();
		$modified = false;
		foreach ($current_minute as $key => $entry_data){
			if($entry_data['time'] < $time - 60){
				unset($current_minute[$key]);
				$modified = true;
			}
		}
		if($modified){
			$current_minute = array_values($current_minute);
		}

		$response_status = 0;
		if(is_a($response,WP_HTTP_Response::class)){
			$response_status = $response->get_status();
		}
		if(is_a($response,WP_Error::class)){
			$response_status = $response->get_error_code();
		}


		$new_entry = [
			'method' => $request->get_method(),
			'route' => $request->get_route(),
			'response_status' => $response_status,
			'time' => $time
		];

		$current_minute[] = $new_entry;

		$current_minute_json = json_encode($current_minute);
		update_option( 'sg_api_tracker_minute' ,$current_minute_json ,false);
		return $response;
	}


	function log_call($response, WP_REST_Server $handler, WP_REST_Request $request){
		global $wpdb;
		$table_name = $wpdb->prefix . 'sg_api_tracker_events'; 

		$response_status = 0;
		if(is_a($response,WP_HTTP_Response::class)){
			$response_status = $response->get_status();
		}
		if(is_a($response,WP_Error::class)){
			$response_status = $response->get_error_code();
		}
		$time = time();
		
		

		$new_entry = [
			'method' => $request->get_method(),
			'route' => $request->get_route(),
			'respose_code' => $response_status,
			'time' => $time,
			// 'user' => 0  // Todo: detect user
		];
		$wpdb->insert($table_name, $new_entry);

		return $response;

	}


	function admin_menu(){
		if( $this->settings['menu_mode'] == "SUBMENU" ){
			add_submenu_page('tools.php' , __("API Tracker","api-tracker") , __("API Tracker","api-tracker") , 'manage_options' , 'api-tracker' , [$this,"admin_page"] );
		}else{
			add_menu_page(__("API Tracker","api-tracker") , __("API Tracker","api-tracker") , "manage_options" , 'api-tracker' , [$this,"admin_page"] );
		}
	}

	function admin_page(){
		$this->stats = $this->load_stats();
		include __DIR__ . '/views/admin-panel.php';
	}

	function load_stats(){
		$current_data = get_option("sg_api_tracker_requests",[]);
	}



}