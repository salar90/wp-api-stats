<?php

if ( ! defined( 'WPINC' ) ) {
	die;
}

function sg_api_stats_activation() {
	sg_api_stats_create_db();

	if ( ! wp_next_scheduled( 'sg_api_stats_cron' ) ) {
		wp_schedule_event( time(), 'hourly', 'sg_api_stats_cron' );
	}
}

add_action( 'sg_api_stats_cron', 'sg_api_clear_old_data' );

function sg_api_clear_old_data() {
	global $wpdb;

	// SQL time format
	$old_time = gmdate( 'Y-m-d H:i:s', ( time() - ( 30 * DAY_IN_SECONDS ) ) );

	$table_name = $wpdb->prefix . 'sg_api_stats_events';
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name is not user input.
	$wpdb->query( $wpdb->prepare( "DELETE FROM `$table_name` WHERE `time` < %s", $old_time ) );
}


function sg_api_stats_deactivation() {
	wp_clear_scheduled_hook( 'sg_api_stats_cron' );
}

/**
 * Remove everything the plugin created: table, option, scheduled event.
 */
function sg_api_stats_uninstall() {
	global $wpdb;

	wp_clear_scheduled_hook( 'sg_api_stats_cron' );

	$table_name = $wpdb->prefix . 'sg_api_stats_events';
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.SchemaChange -- table name is not user input.
	$wpdb->query( "DROP TABLE IF EXISTS `$table_name`" );

	delete_option( 'sg_api_stats_db_version' );
}


function sg_api_stats_create_db() {
	global $wpdb;
	$table_name      = $wpdb->prefix . 'sg_api_stats_events';
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

	require_once ABSPATH . 'wp-admin/includes/upgrade.php';
	dbDelta( $sql );
	add_option( 'sg_api_stats_db_version', '1.0' );
}
