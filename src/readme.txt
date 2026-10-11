=== API Stats ===
Contributors: salar6990
Tags: API REST-API Statistics stats
Requires at least: 5.3
Tested up to: 7.1
Requires PHP: 7.2
License: GPLv3
License URI: https://www.gnu.org/licenses/gpl-3.0.html
Stable Tag: 1.5

View and filter API calls to your website with details about Method, Path, Response time, and Count.

== Description ==
Do you use WordPress's REST-API system to serve applications? 
API Stats allows you to view and monitor API requests to your website on a clear chart.
you can easily view statistics in different time spans and durations.

= API Stat's main Features =
* Record any route from all plugins (e.g WooCommerce) and Wordpress core
* Filter API requests by date
* Adjust duration of points
* Show/Filter known request types such as GET, POST, PUT, PATCH, DELETE,...
* List of most requested routes

You can view this project at [API stat's Github page](https://github.com/salar90/wp-api-stats)

== Screenshots ==
1. Statistics Page


== Changelog ==
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