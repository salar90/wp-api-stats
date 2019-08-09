<?php
if ( ! defined( 'WPINC' ) ) {
	die;
}

class SG_API_Stats{
	private $settings = [];

	function __construct() {
		$this->settings["menu_mode"] = "SUBMENU";
		$this->register_hooks();
	}

	function register_hooks(){
		add_action("admin_menu",[$this,"admin_menu"]);
		add_filter( 'rest_pre_serve_request' , [$this,'pre_serve'] , 5, 4 );
		add_action( 'rest_api_init' , [$this,'rest_api_init'] , 5 );
		add_action( 'admin_enqueue_scripts', [$this, 'load_admin_style'] );
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
		$this->stats = $this->load_stats();
		include __DIR__ . '/views/admin-panel.php';
	}

	function load_stats(){
		$current_data = get_option("sg_api_stats_requests",[]);
	}

	function load_admin_style($hook) {
        if($hook != 'tools_page_api-stats') {
                return;
        }
		wp_enqueue_style( 'chartjs', 'https://cdnjs.cloudflare.com/ajax/libs/Chart.js/2.8.0/Chart.min.css' );
		wp_enqueue_script('chartjs', 'https://cdnjs.cloudflare.com/ajax/libs/Chart.js/2.8.0/Chart.min.js');
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



}