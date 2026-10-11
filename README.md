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
.github/workflows CI deploy to SVN
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

1. Bump `Version:` in `src/wp-api-stats.php` **and** `Stable tag:` in `src/readme.txt`, add a changelog entry.
2. `bash bin/build.sh` (fails if versions don't match) and test the zip.
3. Commit, then `git tag v1.5 && git push origin main v1.5`.
4. The **Deploy to WordPress.org** workflow pushes to SVN `trunk/`, `tags/1.5/` and `assets/`, and creates a GitHub release with the zip.

Readme-only / asset-only change (e.g. bump *Tested up to*): commit, push, then run
**Update readme & assets on WordPress.org** manually from the Actions tab.

One-time setup: add repo secrets `SVN_USERNAME` and `SVN_PASSWORD`
(SVN password is set at https://profiles.wordpress.org/me/profile/edit/group/3/?screen=svn-password).
