<?php
/*
Plugin Name: WP API Stats
Description: View and filter API calls to your website with details about Method, Path, Response time, and Count.
Author: Salar Gholizadeh
Version: 2.0
Requires at least: 5.3
Requires PHP: 7.2
Plugin URI: https://github.com/salar90/wp-api-stats
Author URI: http://salar.one/
License: GNU General Public License v3.0
License URI: http://www.gnu.org/licenses/gpl-3.0.txt
Text Domain: wp-api-stats
 */


if ( ! defined( 'WPINC' ) ) {
	die;
}
define( 'SG_API_STATS_VERSION', '2.0' );

require_once __DIR__ . '/administration.php';
register_uninstall_hook( __FILE__, 'sg_api_stats_uninstall' );
register_activation_hook( __FILE__, 'sg_api_stats_activation' );
register_deactivation_hook( __FILE__, 'sg_api_stats_deactivation' );

function sg_init_api_stats() { // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound -- legacy name.
	include_once __DIR__ . '/class-wp-api-stats.php';
	global $WP_API_Stats;
	$WP_API_Stats = new SG_API_Stats();
}
sg_init_api_stats();
