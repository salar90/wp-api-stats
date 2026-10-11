<?php
if ( ! defined( 'WPINC' ) ) {
	die;
}

class SG_API_Stats {

	const PAGE_SLUG  = 'api-stats';
	const PAGE_HOOK  = 'tools_page_api-stats';
	const MAX_POINTS = 3000;
	const MAX_ROUTES = 500;

	/** Chunk name => length in seconds. */
	const CHUNKS = array(
		'Minute' => MINUTE_IN_SECONDS,
		'Hour'   => HOUR_IN_SECONDS,
		'Day'    => DAY_IN_SECONDS,
		'Week'   => WEEK_IN_SECONDS,
	);

	const METHODS = array( 'GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS' );

	const STATUS_CLASSES = array( '2xx', '3xx', '4xx', '5xx' );

	/** Table grouping modes. */
	const GROUPS = array( 'pattern', 'path' );

	private $settings      = array();
	private $prepared_data = false;

	/** Microtime at which the current REST request started. */
	private $start_time = null;

	public function __construct() {
		$this->settings['menu_mode'] = 'SUBMENU';
		$this->register_hooks();
	}

	public function register_hooks() {
		add_action( 'admin_menu', array( $this, 'admin_menu' ) );

		// Hooks for requests
		add_filter( 'rest_pre_serve_request', array( $this, 'pre_serve' ), 5, 3 );
		add_action( 'rest_api_init', array( $this, 'rest_api_init' ), 5 );

		// enqueue js and css files, and pass chart data to js
		add_action( 'admin_enqueue_scripts', array( $this, 'load_admin_assets' ) );

		// form handlers
		add_action( 'admin_post_sg_api_stats_save_settings', array( $this, 'handle_save_settings' ) );
		add_action( 'admin_post_sg_api_stats_purge', array( $this, 'handle_purge' ) );
	}

	/**
	 * Name of the events table.
	 */
	public static function table_name() {
		global $wpdb;
		return $wpdb->prefix . 'sg_api_stats_events';
	}

	/**
	 * URL of the stats page, optionally with query args.
	 */
	public static function page_url( $args = array() ) {
		return add_query_arg( array_merge( array( 'page' => self::PAGE_SLUG ), $args ), admin_url( 'tools.php' ) );
	}

	/**
	 * API init (the very beginning of a request)
	 */
	public function rest_api_init() {
		$this->start_time = microtime( true );
	}

	/**
	 * Things to do just before echoing the API response.
	 *
	 * @param bool             $served   Whether the request has already been served.
	 * @param WP_HTTP_Response $response Result to send to the client. Usually a WP_REST_Response.
	 * @param WP_REST_Request  $request  Request used to generate the response.
	 */
	public function pre_serve( $served, $response, $request ) {
		global $wpdb;

		/**
		 * Filters whether this REST request is recorded.
		 *
		 * @param bool             $record   Whether to record the request. Default true.
		 * @param WP_REST_Request  $request  The request.
		 * @param WP_HTTP_Response $response The response about to be sent.
		 */
		if ( ! apply_filters( 'sg_api_stats_should_record', true, $request, $response ) ) {
			return $served;
		}

		$response_status = 0;
		if ( is_a( $response, WP_HTTP_Response::class ) ) {
			$response_status = $response->get_status();
		}
		if ( is_a( $response, WP_Error::class ) ) {
			$response_status = $response->get_error_code();
		}

		// Matched route pattern, e.g. /wp/v2/posts/(?P<id>[\d]+). Empty when no route matched (404).
		$route_pattern = '';
		if ( is_a( $response, WP_REST_Response::class ) ) {
			$route_pattern = (string) $response->get_matched_route();
		}

		/**
		 * Filters a human-readable label stored with the request, shown next to the route.
		 *
		 * Example: return the title of the post a route like /myapi/doc/123 refers to.
		 *
		 * @param string           $label    Label. Default empty.
		 * @param WP_REST_Request  $request  The request.
		 * @param WP_HTTP_Response $response The response about to be sent.
		 */
		$label = (string) apply_filters( 'sg_api_stats_label', '', $request, $response );

		$start_time = $this->start_time ? $this->start_time : microtime( true );
		$time_taken = floor( ( microtime( true ) - $start_time ) * 1000 );

		// Route, method and label come from the client or third-party code:
		// they are stored as-is and must always be escaped on output.
		$wpdb->insert(
			self::table_name(),
			array(
				'method'        => substr( $request->get_method(), 0, 20 ),
				'route'         => substr( $request->get_route(), 0, 2000 ),
				'route_pattern' => '' === $route_pattern ? null : substr( $route_pattern, 0, 2000 ),
				'label'         => '' === $label ? null : mb_substr( $label, 0, 255 ),
				'respose_code'  => substr( (string) $response_status, 0, 45 ),
				'time'          => current_time( 'mysql', true ),
				'duration'      => $time_taken,
			),
			array( '%s', '%s', '%s', '%s', '%s', '%s', '%d' )
		);

		return $served;
	}

	/**
	 * Read a GET param restricted to a set of allowed values.
	 */
	private static function read_choice_param( $key, $allowed, $fallback ) {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only filter.
		$value = isset( $_GET[ $key ] ) ? sanitize_text_field( wp_unslash( $_GET[ $key ] ) ) : '';
		return in_array( $value, $allowed, true ) ? $value : $fallback;
	}

	/**
	 * Validate a Y-m-d date string from the request.
	 *
	 * @return string|null The date, or null if missing/invalid.
	 */
	private static function read_date_param( $key ) {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only filter.
		$value = isset( $_GET[ $key ] ) ? sanitize_text_field( wp_unslash( $_GET[ $key ] ) ) : '';
		$date  = DateTimeImmutable::createFromFormat( '!Y-m-d', $value, wp_timezone() );
		if ( ! $date || $date->format( 'Y-m-d' ) !== $value ) {
			return null;
		}
		return $value;
	}

	public function prepare_data() {
		global $wpdb;

		if ( $this->prepared_data ) {
			return $this->prepared_data;
		}

		$tz    = wp_timezone();
		$today = new DateTimeImmutable( 'today', $tz );

		$current_date_from = self::read_date_param( 'date-from' );
		$current_date_to   = self::read_date_param( 'date-to' );
		if ( null === $current_date_from ) {
			$current_date_from = $today->modify( '-1 day' )->format( 'Y-m-d' );
		}
		if ( null === $current_date_to ) {
			$current_date_to = $today->format( 'Y-m-d' );
		}
		if ( $current_date_to < $current_date_from ) {
			list( $current_date_from, $current_date_to ) = array( $current_date_to, $current_date_from );
		}

		$selected_chunk = self::read_choice_param( 'chunk', array_keys( self::CHUNKS ), 'Hour' );
		$group          = self::read_choice_param( 'group', self::GROUPS, 'pattern' );

		// Range is [from 00:00, to+1 day 00:00) in the site's timezone.
		$range_start = DateTimeImmutable::createFromFormat( '!Y-m-d', $current_date_from, $tz );
		$range_end   = DateTimeImmutable::createFromFormat( '!Y-m-d', $current_date_to, $tz )->modify( '+1 day' );
		$start_ts    = $range_start->getTimestamp();
		$duration    = $range_end->getTimestamp() - $start_ts;

		// Grow the chunk size until the number of points is manageable.
		$chunk_names = array_keys( self::CHUNKS );
		while ( ceil( $duration / self::CHUNKS[ $selected_chunk ] ) > self::MAX_POINTS ) {
			$next = array_search( $selected_chunk, $chunk_names, true ) + 1;
			if ( ! isset( $chunk_names[ $next ] ) ) {
				break;
			}
			$selected_chunk = $chunk_names[ $next ];
		}
		$chunk_seconds = self::CHUNKS[ $selected_chunk ];
		$chunk_count   = (int) ceil( $duration / $chunk_seconds );

		// The `time` column holds UTC.
		$utc_start = gmdate( 'Y-m-d H:i:s', $start_ts );
		$utc_end   = gmdate( 'Y-m-d H:i:s', $range_end->getTimestamp() );
		$table     = self::table_name();

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name and group expressions are not user input.

		// Chart: one grouped query for every point, by method and status class.
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT method, LEFT( respose_code, 1 ) AS status_class,
					FLOOR( TIMESTAMPDIFF( SECOND, %s, `time` ) / %d ) AS bucket, COUNT(*) AS c
				FROM `$table`
				WHERE `time` >= %s AND `time` < %s
				GROUP BY bucket, method, status_class",
				$utc_start,
				$chunk_seconds,
				$utc_start,
				$utc_end
			)
		);

		$zeros  = array_fill( 0, $chunk_count, 0 );
		$data   = array( 'all' => $zeros );
		$status = array();
		foreach ( self::METHODS as $method ) {
			$data[ $method ] = $zeros;
		}
		foreach ( self::STATUS_CLASSES as $class ) {
			$status[ $class ] = $zeros;
		}
		foreach ( $rows as $row ) {
			$bucket = (int) $row->bucket;
			if ( $bucket < 0 || $bucket >= $chunk_count ) {
				continue;
			}
			$c = (int) $row->c;
			if ( isset( $data[ $row->method ] ) ) {
				$data[ $row->method ][ $bucket ] += $c;
			}
			$class = $row->status_class . 'xx';
			if ( isset( $status[ $class ] ) ) {
				$status[ $class ][ $bucket ] += $c;
			}
			$data['all'][ $bucket ] += $c;
		}

		$multi_day = $duration > DAY_IN_SECONDS;
		if ( in_array( $selected_chunk, array( 'Minute', 'Hour' ), true ) ) {
			$label_format = $multi_day ? 'm-d H:i' : 'H:i';
		} else {
			$label_format = 'Y-m-d';
		}
		$labels = array();
		for ( $i = 0; $i < $chunk_count; $i++ ) {
			$labels[] = wp_date( $label_format, $start_ts + $i * $chunk_seconds, $tz );
		}

		// Old rows (schema 1) and 404s have no pattern: fall back to the concrete path.
		$route_expr = 'pattern' === $group ? "COALESCE( NULLIF( route_pattern, '' ), route )" : 'route';
		$is_error   = 'CAST( respose_code AS UNSIGNED ) >= 400';

		$summary = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT COUNT(*) AS total, SUM( $is_error ) AS errors, AVG( duration ) AS avg_duration,
					MAX( duration ) AS max_duration, COUNT( DISTINCT $route_expr ) AS routes
				FROM `$table`
				WHERE `time` >= %s AND `time` < %s",
				$utc_start,
				$utc_end
			)
		);

		$tableData = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT method, $route_expr AS route, COUNT(*) AS count, SUM( $is_error ) AS errors,
					AVG( duration ) AS average_duration, MAX( duration ) AS max_duration,
					COUNT( DISTINCT route ) AS paths, MAX( label ) AS label
				FROM `$table`
				WHERE `time` >= %s AND `time` < %s
				GROUP BY method, $route_expr
				ORDER BY count DESC
				LIMIT %d",
				$utc_start,
				$utc_end,
				self::MAX_ROUTES
			)
		);
		// phpcs:enable

		$this->prepared_data = compact( 'data', 'status', 'summary', 'tableData', 'current_date_from', 'current_date_to', 'selected_chunk', 'group', 'labels' );

		return $this->prepared_data;
	}

	/**
	 * Size of the events table in MB, or null if unknown.
	 */
	public function getDataSize() {
		global $wpdb;

		return $wpdb->get_var(
			$wpdb->prepare(
				'SELECT ROUND( ( data_length + index_length ) / 1024 / 1024, 2 )
				FROM information_schema.TABLES
				WHERE table_schema = %s AND table_name = %s',
				$wpdb->dbname,
				self::table_name()
			)
		);
	}

	/**
	 * Total number of stored events.
	 */
	public function getRowCount() {
		global $wpdb;
		$table = self::table_name();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name is not user input.
		return (int) $wpdb->get_var( "SELECT COUNT(*) FROM `$table`" );
	}

	/**
	 * Number of stored events per age in whole days, for the "lowering retention" warning.
	 *
	 * @return array<int,int> Age in days => event count.
	 */
	public function getEventAges() {
		global $wpdb;
		$table = self::table_name();
		$rows  = $wpdb->get_results(
			$wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name is not user input.
				"SELECT TIMESTAMPDIFF( DAY, `time`, %s ) AS age, COUNT(*) AS c FROM `$table` GROUP BY age",
				gmdate( 'Y-m-d H:i:s' )
			)
		);
		$ages = array();
		foreach ( $rows as $row ) {
			$ages[ (int) $row->age ] = (int) $row->c;
		}
		return $ages;
	}

	/**
	 * Admin menu setup
	 */
	public function admin_menu() {
		if ( 'SUBMENU' === $this->settings['menu_mode'] ) {
			add_submenu_page( 'tools.php', __( 'API Stats', 'wp-api-stats' ), __( 'API Stats', 'wp-api-stats' ), 'manage_options', self::PAGE_SLUG, array( $this, 'admin_page' ) );
		} else {
			add_menu_page( __( 'API Stats', 'wp-api-stats' ), __( 'API Stats', 'wp-api-stats' ), 'manage_options', self::PAGE_SLUG, array( $this, 'admin_page' ) );
		}
	}

	/**
	 * Display admin page contents
	 */
	public function admin_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Sorry, you are not allowed to access this page.', 'wp-api-stats' ) );
		}

		extract( $this->prepare_data() ); // phpcs:ignore WordPress.PHP.DontExtract.extract_extract

		$data_size      = $this->getDataSize();
		$row_count      = $this->getRowCount();
		$settings       = sg_api_stats_get_settings();
		$retention_days = sg_api_stats_get_retention_days();
		$tz_name        = wp_timezone_string();

		include __DIR__ . '/views/admin-panel.php';
	}

	public function handle_save_settings() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Sorry, you are not allowed to do that.', 'wp-api-stats' ), 403 );
		}
		check_admin_referer( 'sg_api_stats_save_settings' );

		$days = isset( $_POST['retention_days'] ) ? absint( wp_unslash( $_POST['retention_days'] ) ) : SG_API_STATS_DEFAULT_RETENTION_DAYS;

		$old_days                   = sg_api_stats_get_retention_days();
		$settings                   = sg_api_stats_get_settings();
		$settings['retention_days'] = min( $days, 3650 );
		update_option( 'sg_api_stats_settings', $settings, false );

		// A shorter retention takes effect right away (the page asked for confirmation),
		// instead of whenever the hourly cron happens to run.
		$new_days = sg_api_stats_get_retention_days();
		if ( $new_days > 0 && ( 0 === $old_days || $new_days < $old_days ) ) {
			sg_api_clear_old_data();
		}

		wp_safe_redirect( self::page_url( array( 'sg-notice' => 'saved' ) ) . '#api-stats-data' );
		exit;
	}

	public function handle_purge() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Sorry, you are not allowed to do that.', 'wp-api-stats' ), 403 );
		}
		check_admin_referer( 'sg_api_stats_purge' );

		sg_api_stats_purge_all();

		wp_safe_redirect( self::page_url( array( 'sg-notice' => 'purged' ) ) );
		exit;
	}

	public function load_admin_assets( $hook ) {
		if ( self::PAGE_HOOK !== $hook ) {
			return;
		}
		wp_enqueue_script( 'chartjs', plugins_url( 'assets/vendor/chartjs/chart.umd.min.js', __FILE__ ), array(), '4.5.1', true );
		wp_enqueue_script( 'api-stats-admin', plugins_url( 'assets/admin.js', __FILE__ ), array( 'chartjs' ), SG_API_STATS_VERSION, true );
		wp_enqueue_style( 'api-stats', plugins_url( 'assets/api-stats.css', __FILE__ ), array(), SG_API_STATS_VERSION );

		$prepared = $this->prepare_data();
		$js_data  = array(
			'labels'    => $prepared['labels'],
			'methods'   => array_diff_key( $prepared['data'], array( 'all' => true ) ),
			'status'    => $prepared['status'],
			'retention' => array(
				'days' => sg_api_stats_get_retention_days(),
				'ages' => (object) $this->getEventAges(),
			),
			'i18n'      => array(
				'requests'         => __( 'Requests', 'wp-api-stats' ),
				'confirmPurge'     => __( 'Delete ALL recorded API events? This cannot be undone.', 'wp-api-stats' ),
				/* translators: 1: number of events, 2: number of days */
				'confirmRetention' => __( '%1$s events are older than %2$s days and will be deleted now.', 'wp-api-stats' ),
				'cannotRestore'    => __( 'Deleted events cannot be restored, even if you increase the number of days again later.', 'wp-api-stats' ),
			),
		);
		wp_add_inline_script( 'api-stats-admin', 'var ApiStatsData = ' . wp_json_encode( $js_data ) . ';', 'before' );
	}
}
