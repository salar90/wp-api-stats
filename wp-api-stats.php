<?php
/*
Plugin Name: WP API Stats
Description: Track and Analyse WordPress API calls and requests.
Author: Salar Gholizadeh
Version: 1.3.0
Plugin URI: https://github.com/salar90/wp-api-stats
Author URI: http://salar.one/
License: GNU General Public License v3.0
License URI: http://www.gnu.org/licenses/gpl-3.0.txt
Text Domain: api-stats
Domain Path: /languages
 */


if ( ! defined( 'WPINC' ) ) {
	die;
}
require_once __DIR__ . "/administration.php";
register_uninstall_hook(__FILE__, 'sg_api_stats_uninstall');
register_activation_hook( __FILE__, 'sg_api_stats_activation' );
register_deactivation_hook( __FILE__, 'sg_api_stats_deactivation' );

function sg_init_api_stats(){
	include_once __DIR__ . "/class-wp-api-stats.php";
	global $WP_API_Stats;
	$WP_API_Stats = new SG_API_Stats();
}
sg_init_api_stats();