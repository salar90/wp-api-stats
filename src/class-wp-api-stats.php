<?php
if ( ! defined( 'WPINC' ) ) {
	die;
}

class SG_API_Stats {

	const PAGE_SLUG  = 'api-stats';
	const PAGE_HOOK  = 'tools_page_api-stats';
	const MAX_POINTS = 3000;
	const MAX_ROUTES = 200;

	/** Chunk name => length in seconds. */
	const CHUNKS = array(
		'Minute' => MINUTE_IN_SECONDS,
		'Hour'   => HOUR_IN_SECONDS,
		'Day'    => DAY_IN_SECONDS,
		'Week'   => WEEK_IN_SECONDS,
	);

	const METHODS = array( 'GET', 'POST', 'DELETE', 'PUT', 'PATCH', 'OPTIONS' );

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
	}

	/**
	 * Name of the events table.
	 */
	public static function table_name() {
		global $wpdb;
		return $wpdb->prefix . 'sg_api_stats_events';
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

		$response_status = 0;
		if ( is_a( $response, WP_HTTP_Response::class ) ) {
			$response_status = $response->get_status();
		}
		if ( is_a( $response, WP_Error::class ) ) {
			$response_status = $response->get_error_code();
		}

		$start_time = $this->start_time ? $this->start_time : microtime( true );
		$time_taken = floor( ( microtime( true ) - $start_time ) * 1000 );

		// Route and method come straight from the client: they are stored as-is
		// and must always be escaped on output.
		$wpdb->insert(
			self::table_name(),
			array(
				'method'       => substr( $request->get_method(), 0, 20 ),
				'route'        => substr( $request->get_route(), 0, 2000 ),
				'respose_code' => substr( (string) $response_status, 0, 45 ),
				'time'         => current_time( 'mysql', true ),
				'duration'     => $time_taken,
			),
			array( '%s', '%s', '%s', '%s', '%d' )
		);

		return $served;
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

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only filter.
		$selected_chunk = isset( $_GET['chunk'] ) ? sanitize_text_field( wp_unslash( $_GET['chunk'] ) ) : '';
		if ( ! array_key_exists( $selected_chunk, self::CHUNKS ) ) {
			$selected_chunk = 'Hour';
		}

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

		// One grouped query for the whole chart instead of one per point.
		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name is not user input.
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT method, FLOOR( TIMESTAMPDIFF( SECOND, %s, `time` ) / %d ) AS bucket, COUNT(*) AS c
				FROM `$table`
				WHERE `time` >= %s AND `time` < %s
				GROUP BY bucket, method",
				$utc_start,
				$chunk_seconds,
				$utc_start,
				$utc_end
			)
		);
		// phpcs:enable

		$data = array( 'all' => array_fill( 0, $chunk_count, 0 ) );
		foreach ( self::METHODS as $method ) {
			$data[ $method ] = array_fill( 0, $chunk_count, 0 );
		}
		foreach ( $rows as $row ) {
			$bucket = (int) $row->bucket;
			if ( $bucket < 0 || $bucket >= $chunk_count ) {
				continue;
			}
			if ( isset( $data[ $row->method ] ) ) {
				$data[ $row->method ][ $bucket ] += (int) $row->c;
			}
			$data['all'][ $bucket ] += (int) $row->c;
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

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name is not user input.
		$tableData = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT method, route, COUNT(id) AS count, AVG(duration) AS average_duration
				FROM `$table`
				WHERE `time` >= %s AND `time` < %s
				GROUP BY method, route
				ORDER BY count DESC
				LIMIT %d",
				$utc_start,
				$utc_end,
				self::MAX_ROUTES
			)
		);
		// phpcs:enable

		$this->prepared_data = compact( 'data', 'tableData', 'current_date_from', 'current_date_to', 'selected_chunk', 'labels' );

		return $this->prepared_data;
	}

	/**
	 * Size of the events table in MB, or null if unknown.
	 */
	public function getDataSize() {
		global $wpdb;

		$size = $wpdb->get_var(
			$wpdb->prepare(
				'SELECT ROUND( ( data_length + index_length ) / 1024 / 1024, 2 )
				FROM information_schema.TABLES
				WHERE table_schema = %s AND table_name = %s',
				$wpdb->dbname,
				self::table_name()
			)
		);

		return $size;
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
		include __DIR__ . '/views/admin-panel.php';
		include __DIR__ . '/views/table.php';

		$data_size = $this->getDataSize();
		if ( null !== $data_size ) {
			echo '<br><hr><p>';
			printf(
				/* translators: 1: database table name, 2: size in MB */
				esc_html__( 'API events are stored in %1$s table. Size: %2$s MB', 'wp-api-stats' ),
				'<strong>' . esc_html( self::table_name() ) . '</strong>',
				'<strong>' . esc_html( $data_size ) . '</strong>'
			);
			echo '</p>';
		}
	}

	public function load_admin_assets( $hook ) {
		if ( self::PAGE_HOOK !== $hook ) {
			return;
		}
		wp_enqueue_style( 'chartjs-css', plugins_url( 'assets/chartjs/Chart.min.css', __FILE__ ), array(), '2.8.0' );
		wp_enqueue_script( 'chartjs', plugins_url( 'assets/chartjs/Chart.min.js', __FILE__ ), array(), '2.8.0', false );
		wp_enqueue_script( 'api-stats-draw', plugins_url( 'assets/draw.js', __FILE__ ), array( 'chartjs' ), SG_API_STATS_VERSION, true );
		wp_enqueue_style( 'api-stats', plugins_url( 'assets/api-stats.css', __FILE__ ), array(), SG_API_STATS_VERSION );

		wp_add_inline_script(
			'api-stats-draw',
			'var ApichartData = ' . wp_json_encode( array_intersect_key( $this->prepare_data(), array_flip( array( 'data', 'labels' ) ) ) ) . ';',
			'before'
		);
	}
}
