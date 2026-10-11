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
- Not yet smoke-tested on a live WordPress (no local WP install on the Windows box yet).
- `Tested up to` in readme.txt is still 6.4.1; bump only after testing on current WP.
- Request start time uses `rest_api_init`; `user` column is never populated; `version` column unused.
- No DB schema upgrade routine (activation hook doesn't run on plugin update); fine while schema is unchanged.
- Bundled Chart.js 2.8.0 is old.
- Every REST request does a synchronous INSERT; no sampling/opt-out filter.
