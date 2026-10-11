# CLAUDE.md

WP API Stats: a small WordPress plugin (slug `wp-api-stats`, wp.org contributor `salar6990`) that logs
every REST API request into a custom table and shows a Chart.js chart + top-routes table under
Tools → API Stats.

## Source of truth & release flow
- **Git is the source of truth.** wordpress.org SVN is a deploy target only; never edit SVN by hand.
- Plugin code lives in `src/`. Anything outside `src/` never ships.
- `bash bin/build.sh` → `dist/wp-api-stats/` (+ zip). It fails if `Version:` in
  `src/wp-api-stats.php` ≠ `Stable tag:` in `src/readme.txt`.
- Release = push tag `vX.Y` → `.github/workflows/deploy.yml` (10up deploy action, `BUILD_DIR=dist/wp-api-stats`).
- Readme/asset-only updates → manual `.github/workflows/readme-assets.yml`.
- wp.org listing images go in `.wordpress-org/` (`icon-256x256.png`, `screenshot-N.png`, `banner-772x250.png`, ...).
- History note: v1.4 was originally released through SVN only and later imported into git (tag `v1.4`).
- Default branch is `main`. Line endings: LF (enforced by `.gitattributes`).

## Code map (`src/`)
- `wp-api-stats.php`: plugin header, activation/deactivation/uninstall hooks, instantiates `SG_API_Stats` into global `$WP_API_Stats`.
- `administration.php`: activation (creates table via `dbDelta`, schedules hourly cron), cron `sg_api_clear_old_data` (deletes rows > 30 days), deactivation.
- `class-wp-api-stats.php`: `SG_API_Stats`
  - `rest_api_init` records start time; `rest_pre_serve_request` filter (`pre_serve`) inserts one row per request.
  - `prepare_data()` builds chart buckets (Minute/Hour/Day/Week, max 3000 buckets) + top routes table from `$_POST` filters.
  - Admin page at `tools.php?page=api-stats` (hook suffix `tools_page_api-stats`); data passed to JS as `window.ApichartData`.
- `views/admin-panel.php` (filter form + canvas), `views/table.php` (top routes).
- `assets/draw.js` (Chart.js 2.8 rendering), `assets/api-stats.scss` → `api-stats.css` (compiled by hand; scss is excluded from builds).
- DB table: `{prefix}sg_api_stats_events` (id, user, time, duration, method, route, version, respose_code [sic]). Option `sg_api_stats_db_version`.

## Conventions
- Function/class prefix `sg_` / `SG_`. Text domain `api-stats` (note: differs from slug; wp.org expects the slug).
- Keep `Requires at least` / `Requires PHP` / `Tested up to` in readme.txt honest.
- Follow WordPress coding standards; escape output (`esc_html`, `esc_attr`), sanitize input, use `$wpdb->prepare`.

## Known issues / TODO (not fixed yet)
- SQL built by string interpolation from `$_POST` dates in `prepare_data()` (table query) and in `sg_api_clear_old_data`: use `$wpdb->prepare`.
- Filter form has no nonce / capability check on POST.
- `prepare_data()` runs one SELECT per bucket (up to 3000 queries) and fetches `SELECT *`; should be a single GROUP BY query.
- Table query compares local dates against UTC `time` column (chart applies tz offset, table doesn't).
- `sg_api_stats_uninstall()` is empty: table, option and cron are left behind.
- `Requires PHP: 5.6` but code uses `??` (PHP 7.0+).
- Text domain `api-stats` ≠ slug `wp-api-stats`; no `languages/` folder despite `Domain Path`.
- Request start time uses `rest_api_init`, which fires late-ish; `user` column never populated.
- Admin footer line echoes `$table_name` / size unescaped.
- Bundled Chart.js 2.8.0 is old.
