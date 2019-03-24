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
	}

	function admin_menu(){
		if( $this->settings['menu_mode'] == "SUBMENU" ){
			add_submenu_page('tools.php' , __("API Tracker","api-tracker") , __("API Tracker","api-tracker") , 'manage_options' , 'api-tracker' , [$this,"admin_page"] );
		}else{
			add_menu_page(__("API Tracker","api-tracker") , __("API Tracker","api-tracker") , "manage_options" , 'api-tracker' , [$this,"admin_page"] );
		}
	}

	function admin_page(){
		include __DIR__ . '/views/admin-panel.php';
	}



}