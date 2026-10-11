# CLAUDE.md

WP API Stats: a small WordPress plugin (slug `wp-api-stats`, wp.org contributor `salar6990`) that logs
every REST API request into a custom table and shows a Chart.js chart + top-routes table under
Tools → API Stats.

## Source of truth & release flow
- **Git is the source of truth.** wordpress.org SVN is a deploy target only; never edit SVN by hand.
- Plugin code lives in `src/`. Anything outside `src/` never ships.
- `bash bin/build.sh` → `dist/wp-api-stats/` (+ zip). It fails if `Version:` in
  `src/wp-api-stats.php` ≠ `Stable tag:` in `src/readme.txt`.
- Release: tag `vX.Y` and push (CI `release.yml` builds the zip for a GitHub release), then `bash bin/deploy.sh --commit`
  publishes to SVN locally. Without `--commit` it is a dry run into `./svn`. `--readme-only` updates readme/assets of the
  current stable tag without a new version.
- SVN credentials are in git-ignored `.env` (`SVN_USERNAME`, `SVN_PASSWORD`). Never commit them, never put them in
  GitHub secrets/workflows, never echo them. Publishing to wordpress.org ships to all users: get the user's explicit OK first.
- wp.org listing images go in `.wordpress-org/` (`icon-256x256.png`, `screenshot-N.png`, `banner-772x250.png`, ...).
- History note: v1.4 was originally released through SVN only and later imported into git (tag `v1.4`).
- Default branch is `main`. Line endings: LF (enforced by `.gitattributes`).

## Code map (`src/`)
- `wp-api-stats.php`: plugin header, `SG_API_STATS_VERSION`, activation/deactivation/uninstall hooks, instantiates `SG_API_Stats` into global `$WP_API_Stats`.
- `administration.php`: schema (`sg_api_stats_create_db`, `SG_API_STATS_DB_VERSION`), `sg_api_stats_maybe_upgrade` on
  `plugins_loaded` (updates don't run activation), settings (`sg_api_stats_settings` option: `retention_days`),
  hourly cron `sg_api_clear_old_data` (batched deletes, respects retention, 0 = keep), purge, uninstall.
- `class-wp-api-stats.php`: `SG_API_Stats`
  - `rest_api_init` records start time; `rest_pre_serve_request` (`pre_serve`) inserts one row per request
    with route, matched `route_pattern`, optional `label`, status, duration.
  - `prepare_data()`: GET filters (`date-from`, `date-to`, `chunk`, `group` = pattern|path), chart buckets by method
    and status class, summary, routes table (top 500). All in the site timezone; `time` column is UTC.
  - `admin-post.php` handlers `sg_api_stats_save_settings` / `sg_api_stats_purge` (capability + nonce).
  - Data passed to JS as `window.ApiStatsData` via `wp_add_inline_script`.
- `views/admin-panel.php`: the whole Tools → API Stats page (toolbar, cards, chart, routes table, stored-data card).
- `assets/admin.js`: chart (method/status toggle), table sort + search, purge confirm. Plain JS, no build step.
- `assets/api-stats.css`: page styles (plain CSS, WP admin look).
- `assets/vendor/chartjs/`: Chart.js 4.5.1 UMD build + MIT license. To update: download
  `https://registry.npmjs.org/chart.js/-/chart.js-<ver>.tgz`, check its `dist.integrity` from the registry, copy
  `package/dist/chart.umd.min.js`, bump the version in `load_admin_assets()`.
- DB table `{prefix}sg_api_stats_events` (schema 2): id BIGINT, user, time (UTC, indexed), duration (ms), method, route,
  route_pattern, label, version, respose_code [sic]. Options: `sg_api_stats_db_version`, `sg_api_stats_settings`.

## Public hooks (documented in readme FAQ; keep backwards compatible)
- `sg_api_stats_should_record( bool $record, WP_REST_Request $request, WP_HTTP_Response $response )`
- `sg_api_stats_label( string $label, WP_REST_Request $request, WP_HTTP_Response $response )`
- `sg_api_stats_retention_days( int $days )`

## Local dev environment (Windows box)
- Laravel **Herd** serves every folder in `C:\Users\Salar\Dev` as `<folder>.test`. Herd must be running
  (`C:\Program Files\Herd\Herd.exe`), otherwise all `.test` sites fail.
- Test site: **http://wordpress.test** = `C:\Users\Salar\Dev\wordpress` (WP 7.1.x, DB `wp`, Persian locale, PHP 8.3 via Herd).
- `wordpress\wp-content\plugins\wp-api-stats` is a **junction to this repo's `src/`**, so edits are live immediately; no copying.
  Recreate: `cmd /c mklink /J C:\Users\Salar\Dev\wordpress\wp-content\plugins\wp-api-stats C:\Users\Salar\Dev\api-stats-project\src`.
  Remove only with `cmd /c rmdir <link>` (never `rm -rf`, which follows into `src/`).
- **Never run `wp plugin uninstall` / `wp plugin delete` on this plugin**: it deletes files through the junction, i.e. `src/`.
  To test uninstall, `require` `administration.php` and call `sg_api_stats_uninstall()` via `wp eval`.
- MariaDB 11.3 on localhost:3306, user `root`; the password is in `C:\Users\Salar\Dev\wp-mitra\wp-config.php` (don't copy it elsewhere).
  CLI: `"C:\Program Files\MariaDB 11.3\bin\mysql.exe"`.
- WP-CLI is not installed globally: download `wp-cli.phar` from https://raw.githubusercontent.com/wp-cli/builds/gh-pages/phar/wp-cli.phar
  (verify the `.sha512`), run it with Herd's PHP from the WP dir: `~/.config/herd/bin/php83/php.exe wp-cli.phar <cmd>`.
- PHP: Herd `~/.config/herd/bin/php83` and `php74`; system CLI PHP 8.2 (`D:\Software\php-8.2`).
- Admin-page checks over HTTP: create a throwaway admin with WP-CLI, log in with curl cookies, delete the user afterwards.
- On Ubuntu: symlink `src/` into any local WP's `wp-content/plugins/wp-api-stats`.

## Smoke test checklist
1. Hit a few REST routes (incl. 404, POST, OPTIONS, and `/?rest_route=/x<script>`); rows appear in `wp_sg_api_stats_events`.
2. Stats page `/wp-admin/tools.php?page=api-stats`: chart data, routes table, payload rendered escaped, no PHP notices from
   this plugin (a twentysixteen `_load_textdomain_just_in_time` notice is pre-existing and unrelated).
3. Filters: each chunk, reversed dates; invalid or malicious params fall back to defaults.
4. Deactivate (cron cleared), activate (cron back), `sg_api_clear_old_data()` removes >30-day rows, uninstall function drops table + option.

## Checks (run before committing)
- `composer install` once, then `vendor/bin/phpcs` (WordPress-Extra + Security + PHPCompatibility 7.2+, config in `phpcs.xml.dist`). Must be clean. `vendor/bin/phpcbf` auto-fixes style.
- `php -l` on changed files; `bash bin/build.sh` before a release.
- CI (`.github/workflows/lint.yml`) runs the same on push/PR.

## Conventions
- Prefix `sg_api` / `SG_API` for new globals (legacy `sg_init_api_stats` is grandfathered). Text domain `wp-api-stats` (= slug).
- Minimum: WordPress 5.3 (uses `wp_timezone`, `wp_date`), PHP 7.2. Keep code 7.2-compatible.
- Escape everything on output: `route` and `method` columns are attacker-controlled (any visitor can hit `/wp-json/<anything>`).
- All SQL through `$wpdb->prepare`; the `time` column holds UTC, filters are in the site timezone.
- Bump `SG_API_STATS_VERSION`, header `Version:` and readme `Stable tag:` together.

## Known issues / TODO
- Only smoke-tested on PHP 8.3; PHP 7.2 compatibility is checked by lint/PHPCompatibility only.
- Request start time uses `rest_api_init`; `user` column is never populated; `version` column unused.
- Every REST request does a synchronous INSERT; no sampling.
- Ideas not done yet: exclude routes via UI, CSV export, WP-CLI commands, PHPUnit tests, banner/screenshots for wp.org.
