<?php

if ( ! defined( 'WPINC' ) ) {
	die;
}

function sg_api_tracker_activation(){
	sg_api_tracker_create_db();

	if (! wp_next_scheduled ( 'sg_api_stats_cron' )) {
		wp_schedule_event(time(), 'hourly', 'sg_api_stats_cron');
	}

}

add_action('sg_api_stats_cron', 'sg_api_clear_old_data');

function sg_api_clear_old_data(){
	
}


function sg_api_tracker_deactivation(){
	if ( $scheduled = wp_next_scheduled ( 'sg_api_stats_cron' )) {
		wp_unschedule_event( $scheduled, 'sg_api_stats_cron' );
	}
}

function sg_api_tracker_uninstall(){
	
}


function sg_api_tracker_create_db(){
	global $wpdb;
	$table_name = $wpdb->prefix . 'sg_api_tracker_events'; 
	$charset_collate = $wpdb->get_charset_collate();

	$sql = "CREATE TABLE $table_name (
	id mediumint(9) NOT NULL AUTO_INCREMENT,
	user mediumint(9),
	time timestamp,
	duration mediumint(9),
	method tinytext NOT NULL,
	route text NOT NULL,
	version varchar(45),
	respose_code varchar(45),
	PRIMARY KEY  (id)
	) $charset_collate;";

	require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
	dbDelta( $sql );
	add_option( "sg_api_tracker_db_version", "1.0" );

}