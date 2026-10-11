=== API Stats ===
Contributors: salar6990
Tags: rest-api, api, statistics, monitoring, performance
Requires at least: 5.3
Tested up to: 7.1
Requires PHP: 7.2
License: GPLv3
License URI: https://www.gnu.org/licenses/gpl-3.0.html
Stable Tag: 1.6

View and filter API calls to your website with details about Method, Path, Response time, and Count.

== Description ==
Do you use the WordPress REST API to serve apps, headless front ends or integrations?
API Stats records every REST API request to your site and shows you what is being called, how often, how fast, and what fails.

= Features =
* Records every REST route: WordPress core, WooCommerce and any other plugin or custom endpoint
* Requests-over-time chart, broken down by HTTP method or by status (2xx / 3xx / 4xx / 5xx)
* Summary of requests, error rate, average and maximum response time
* Routes table grouped by route pattern (e.g. `/wp/v2/posts/(?P<id>[\d]+)`) or by exact path, with error counts and timings; sortable and searchable
* Quick date ranges (today, yesterday, 7 and 30 days) or any custom range, with minute / hour / day / week resolution
* Configurable data retention, and a button to delete all recorded data
* Developer hooks to skip requests or attach a readable label to them

Stats are under **Tools → API Stats**. Data is stored in your own database; nothing is sent anywhere.

You can view this project on [GitHub](https://github.com/salar90/wp-api-stats).

== Frequently Asked Questions ==

= How long is data kept? =
30 days by default. Change it under Tools → API Stats → Stored data. Set it to 0 to keep data forever (the page shows how big the table is). Old events are removed hourly. Lowering the number deletes older events right away, after a confirmation that tells you how many; deleted events can't be restored by raising the number again. Developers can also set it in code:

`add_filter( 'sg_api_stats_retention_days', function () { return 90; } );`

= How do I delete all recorded data? =
Use the "Delete all data" button under Tools → API Stats → Stored data. Recording continues afterwards. Uninstalling the plugin also removes its table and settings.

= Can I stop some requests from being recorded? =
Yes, with the `sg_api_stats_should_record` filter. Return false to skip a request:

`add_filter( 'sg_api_stats_should_record', function ( $record, $request ) {
    return 0 !== strpos( $request->get_route(), '/wp/v2/' );
}, 10, 2 );`

= Can I show a readable name next to routes like /myapi/doc/123? =
Yes. Return a label from the `sg_api_stats_label` filter. It is stored with the request and shown in the routes table when grouping by exact path:

`add_filter( 'sg_api_stats_label', function ( $label, $request ) {
    if ( preg_match( '#^/myapi/doc/(\d+)$#', $request->get_route(), $m ) ) {
        return get_the_title( (int) $m[1] );
    }
    return $label;
}, 10, 2 );`

= What does "By pattern" mean in the routes table? =
Requests are grouped by the registered route they matched, so `/wp/v2/posts/1`, `/wp/v2/posts/2`, ... appear as one row `/wp/v2/posts/(?P<id>[\d]+)` with the number of distinct paths. Switch to "By exact path" to see each URL separately. Requests that matched no route (404) are always shown by path.

= Which timezone is used? =
Your site's timezone (Settings → General). Durations are measured from the start of REST API handling until the response is sent, in milliseconds.

== Screenshots ==
1. Stats page: summary, requests over time by HTTP method, routes grouped by route pattern, and data retention settings.
2. Chart by response status (an incident with 5xx errors stands out) and routes by exact path, with labels added through the sg_api_stats_label filter.


== Changelog ==
= 1.6 =
* New: refreshed stats page with summary cards, quick date ranges, sortable/searchable routes table
* New: chart breakdown by status class (2xx/3xx/4xx/5xx) in addition to HTTP method
* New: routes grouped by registered route pattern, so /posts/1, /posts/2, ... count as one route (switchable to exact paths)
* New: error counts and maximum duration per route
* New: configurable data retention (0 = keep forever) and a "Delete all data" button; lowering retention asks for confirmation and shows how many events will be deleted
* New: developer filters sg_api_stats_should_record, sg_api_stats_label and sg_api_stats_retention_days
* Fix: event IDs could overflow after about 8.4 million recorded requests, which stopped recording
* Performance: index on the event time column; old events are deleted in batches
* Database schema is upgraded automatically on update
* Chart.js updated from 2.8 to 4.5

= 1.5 =
* Security: escape recorded routes and methods on the stats page (fixes stored XSS via crafted REST URLs)
* Security: strictly validate date and point filters; all SQL queries now use prepared statements
* Faster stats page: chart data comes from a single grouped query instead of one query per point
* Fix: date filters now use the site timezone consistently for both the chart and the routes table
* Filters use the URL (GET), so filtered views can be bookmarked and reloaded
* Routes table limited to the top 200 routes
* Uninstalling the plugin now removes its database table, option and scheduled event
* Text domain changed to wp-api-stats to match the plugin slug (enables wordpress.org translations)
* Requires WordPress 5.3+ and PHP 7.2+
* Tested with WordPress 7.1

= 1.4 =
* Update plugin description
* Fix Input sanitization
* Add Feature to display size of data stored in database for API events
* Test with WP 6.4.1