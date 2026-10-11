# WP API Stats

WordPress plugin that records REST API calls and shows them on a chart and table
(method, route, response time, count) under **Tools → API Stats**.

- wordpress.org: https://wordpress.org/plugins/wp-api-stats/
- This git repo is the source of truth. SVN on wordpress.org is only a deploy target.

## Layout

```
src/              The plugin itself (exactly what ships). readme.txt lives here.
.wordpress-org/   wp.org listing assets: icon, banners, screenshots (→ SVN assets/)
bin/build.sh      Builds src/ → dist/wp-api-stats/ + dist/wp-api-stats-<ver>.zip
dist/             Build output (git-ignored)
.github/workflows CI: lint, GitHub release on tags
bin/deploy.sh     Local deploy to wordpress.org SVN (reads .env)
```

## Local development

Link `src/` into a WordPress install:

```bash
# Linux
ln -s "$PWD/src" /path/to/wp-content/plugins/wp-api-stats
```
```powershell
# Windows (admin or Developer Mode)
New-Item -ItemType SymbolicLink -Path C:\path\to\wp-content\plugins\wp-api-stats -Target $PWD\src
```

## Releasing

wordpress.org SVN credentials live in a local, git-ignored `.env` (see `.env.example`). They are never stored on GitHub.

1. Bump `Version:` in `src/wp-api-stats.php`, `SG_API_STATS_VERSION`, and `Stable tag:` in `src/readme.txt`; add a changelog entry.
2. `bash bin/deploy.sh`: builds, syncs into `./svn` (git-ignored) and shows the SVN changes (dry run).
3. Commit, then `git tag v1.5 && git push origin main v1.5`. CI attaches the zip to a GitHub release.
4. `bash bin/deploy.sh --commit` publishes `trunk/`, `tags/1.5/` and `assets/` to wordpress.org.

Readme/asset-only change (e.g. bump *Tested up to*): commit, then `bash bin/deploy.sh --readme-only [--commit]`.
It refuses if code differs from the released tag.

Needs `svn` on PATH (Windows: TortoiseSVN with command-line tools; Ubuntu: `apt install subversion`).
