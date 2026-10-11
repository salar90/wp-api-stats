<?php

if ( ! defined( 'WPINC' ) ) {
	die;
}

/** Schema version; bump when sg_api_stats_create_db() changes. */
define( 'SG_API_STATS_DB_VERSION', '2' );

const SG_API_STATS_DEFAULT_RETENTION_DAYS = 30;

function sg_api_stats_activation() {
	sg_api_stats_create_db();

	if ( ! wp_next_scheduled( 'sg_api_stats_cron' ) ) {
		wp_schedule_event( time(), 'hourly', 'sg_api_stats_cron' );
	}
}

/**
 * Plugin updates don't run the activation hook, so check the schema version on every load.
 */
function sg_api_stats_maybe_upgrade() {
	if ( get_option( 'sg_api_stats_db_version' ) !== SG_API_STATS_DB_VERSION ) {
		sg_api_stats_create_db();
	}
}
add_action( 'plugins_loaded', 'sg_api_stats_maybe_upgrade' );

/**
 * Stored plugin settings merged over defaults.
 *
 * @return array{retention_days:int}
 */
function sg_api_stats_get_settings() {
	$settings = get_option( 'sg_api_stats_settings', array() );
	return wp_parse_args(
		is_array( $settings ) ? $settings : array(),
		array( 'retention_days' => SG_API_STATS_DEFAULT_RETENTION_DAYS )
	);
}

/**
 * Days to keep events; 0 keeps them forever.
 */
function sg_api_stats_get_retention_days() {
	$days = sg_api_stats_get_settings()['retention_days'];

	/**
	 * Filters how many days of API events are kept. Return 0 to keep them forever.
	 *
	 * @param int $days Days configured on Tools → API Stats.
	 */
	return max( 0, (int) apply_filters( 'sg_api_stats_retention_days', (int) $days ) );
}

add_action( 'sg_api_stats_cron', 'sg_api_clear_old_data' );

function sg_api_clear_old_data() {
	global $wpdb;

	$days = sg_api_stats_get_retention_days();
	if ( 0 === $days ) {
		return;
	}

	// SQL time format
	$old_time = gmdate( 'Y-m-d H:i:s', ( time() - ( $days * DAY_IN_SECONDS ) ) );

	$table_name = $wpdb->prefix . 'sg_api_stats_events';

	// Delete in batches so a large backlog doesn't lock the table for long.
	for ( $i = 0; $i < 100; $i++ ) {
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name is not user input.
		$deleted = $wpdb->query( $wpdb->prepare( "DELETE FROM `$table_name` WHERE `time` < %s LIMIT 10000", $old_time ) );
		if ( ! $deleted || $deleted < 10000 ) {
			break;
		}
	}
}

/**
 * Delete every recorded event.
 */
function sg_api_stats_purge_all() {
	global $wpdb;

	$table_name = $wpdb->prefix . 'sg_api_stats_events';
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name is not user input.
	$wpdb->query( "TRUNCATE TABLE `$table_name`" );
}


function sg_api_stats_deactivation() {
	wp_clear_scheduled_hook( 'sg_api_stats_cron' );
}

/**
 * Remove everything the plugin created: table, options, scheduled event.
 */
function sg_api_stats_uninstall() {
	global $wpdb;

	wp_clear_scheduled_hook( 'sg_api_stats_cron' );

	$table_name = $wpdb->prefix . 'sg_api_stats_events';
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.SchemaChange -- table name is not user input.
	$wpdb->query( "DROP TABLE IF EXISTS `$table_name`" );

	delete_option( 'sg_api_stats_db_version' );
	delete_option( 'sg_api_stats_settings' );
}


/**
 * Create or upgrade the events table (dbDelta is idempotent).
 *
 * Schema 2: BIGINT id (MEDIUMINT overflowed after ~8.4M requests), index on time,
 * route_pattern (matched REST route) and label (set via the sg_api_stats_label filter).
 */
function sg_api_stats_create_db() {
	global $wpdb;
	$table_name      = $wpdb->prefix . 'sg_api_stats_events';
	$charset_collate = $wpdb->get_charset_collate();

	$sql = "CREATE TABLE $table_name (
	id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
	user mediumint(9),
	time timestamp,
	duration mediumint(9),
	method tinytext NOT NULL,
	route text NOT NULL,
	route_pattern text,
	label varchar(255),
	version varchar(45),
	respose_code varchar(45),
	PRIMARY KEY  (id),
	KEY time (time)
	) $charset_collate;";

	require_once ABSPATH . 'wp-admin/includes/upgrade.php';
	dbDelta( $sql );
	update_option( 'sg_api_stats_db_version', SG_API_STATS_DB_VERSION );
}
