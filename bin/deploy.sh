#!/usr/bin/env bash
# Deploy to wordpress.org SVN from this machine. Credentials come from .env (git-ignored).
#
#   bin/deploy.sh                 dry run: build, sync into ./svn, show what would change
#   bin/deploy.sh --commit        release: trunk/ + tags/<version>/ + assets/
#   bin/deploy.sh --readme-only   dry run of a readme/assets-only update (no new version)
#   bin/deploy.sh --readme-only --commit
#
# Release flow: bump versions, commit, `git tag vX.Y && git push origin main vX.Y`, then `bin/deploy.sh --commit`.
# Works in Git Bash (Windows, svn from TortoiseSVN command-line tools) and on Linux.
set -euo pipefail

SLUG="wp-api-stats"
SVN_URL="https://plugins.svn.wordpress.org/$SLUG"
ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
SVN_DIR="$ROOT/svn"
BUILD="$ROOT/dist/$SLUG"
ASSETS="$ROOT/.wordpress-org"

COMMIT=false
README_ONLY=false
for arg in "$@"; do
	case "$arg" in
		--commit) COMMIT=true ;;
		--readme-only) README_ONLY=true ;;
		-h|--help) sed -n '2,10p' "$0"; exit 0 ;;
		*) echo "Unknown option: $arg" >&2; exit 1 ;;
	esac
done

die() { echo "ERROR: $*" >&2; exit 1; }

# Read KEY='value' from .env without sourcing it (passwords may contain shell characters).
env_get() {
	[[ -f "$ROOT/.env" ]] || die ".env not found (copy .env.example)"
	sed -nE "s/^$1=['\"]?([^'\"]*)['\"]?\s*$/\1/p" "$ROOT/.env" | tr -d '\r' | head -n1
}

# Replace the contents of svn dir $2 with the contents of $1 (keeping .svn).
mirror() {
	local src="$1" dst="$2"
	mkdir -p "$dst"
	find "$dst" -mindepth 1 -maxdepth 1 ! -name .svn -exec rm -rf {} +
	cp -R "$src/." "$dst/"
}

# Tell svn about added/removed files under the given paths.
svn_stage() {
	svn add --force --quiet "$@"
	svn status "$@" | awk '/^!/ { sub(/^![ \t]+/, ""); print }' | while IFS= read -r f; do svn rm --quiet "$f"; done
	find "$@" -path '*/.svn' -prune -o -type f -name '*.png' -print | while IFS= read -r f; do svn propset -q svn:mime-type image/png "$f"; done
	find "$@" -path '*/.svn' -prune -o -type f \( -name '*.jpg' -o -name '*.jpeg' \) -print | while IFS= read -r f; do svn propset -q svn:mime-type image/jpeg "$f"; done
	find "$@" -path '*/.svn' -prune -o -type f -name '*.svg' -print | while IFS= read -r f; do svn propset -q svn:mime-type image/svg+xml "$f"; done
}

command -v svn >/dev/null || die "svn not found (Windows: install TortoiseSVN with command line tools; Linux: apt install subversion)"

# --- Git sanity: deploy only committed code.
cd "$ROOT"
if [[ -n "$(git status --porcelain)" ]]; then
	$COMMIT && die "working tree is not clean; commit first"
	echo "WARN: working tree is not clean (fine for a dry run)"
fi

bash "$ROOT/bin/build.sh" >/dev/null
VERSION=$(grep -m1 -iE '^Stable tag:' "$BUILD/readme.txt" | awk '{print $NF}' | tr -d '\r')
echo "Plugin $SLUG, version $VERSION"

if ! $README_ONLY && $COMMIT; then
	git rev-parse -q --verify "refs/tags/v$VERSION" >/dev/null || die "git tag v$VERSION missing; tag and push it first"
fi

# --- SVN working copy (sparse: trunk + assets + the one tag we need).
if [[ ! -d "$SVN_DIR/.svn" ]]; then
	svn checkout --quiet --depth immediates "$SVN_URL" "$SVN_DIR"
fi
cd "$SVN_DIR"
svn revert --quiet --recursive . >/dev/null
svn update --quiet --set-depth infinity trunk assets
svn status --no-ignore | awk '/^[?I]/ { sub(/^[?I][ \t]+/, ""); print }' | while IFS= read -r f; do rm -rf "$f"; done

TAG_EXISTS=false
if svn ls "$SVN_URL/tags/$VERSION" >/dev/null 2>&1; then TAG_EXISTS=true; fi

if $README_ONLY; then
	$TAG_EXISTS || die "tags/$VERSION does not exist in SVN; this is a new version, do a full release"
	svn update --quiet --set-depth infinity "tags/$VERSION"
	# Code must match the released tag; only readme.txt may differ.
	if ! diff -rq -x .svn -x readme.txt -x '*.scss' -x .gitignore "$BUILD" "tags/$VERSION"; then
		die "src/ code differs from released $VERSION; bump the version and do a full release"
	fi
	cp "$BUILD/readme.txt" trunk/readme.txt
	cp "$BUILD/readme.txt" "tags/$VERSION/readme.txt"
	mirror "$ASSETS" assets
	svn_stage trunk assets
	MSG="Update readme/assets ($VERSION) from git $(git -C "$ROOT" rev-parse --short HEAD)"
else
	$TAG_EXISTS && die "tags/$VERSION already exists in SVN; bump the version (or use --readme-only)"
	mirror "$BUILD" trunk
	mirror "$ASSETS" assets
	svn_stage trunk assets
	svn cp --quiet trunk "tags/$VERSION"
	MSG="Release $VERSION from git $(git -C "$ROOT" rev-parse --short HEAD)"
fi

echo
echo "SVN changes:"
svn status
if [[ -z "$(svn status)" ]]; then
	echo "Nothing to deploy."
	exit 0
fi

if ! $COMMIT; then
	echo
	echo "Dry run only. Inspect $SVN_DIR, then re-run with --commit to publish: \"$MSG\""
	exit 0
fi

SVN_USERNAME="$(env_get SVN_USERNAME)"
SVN_PASSWORD="$(env_get SVN_PASSWORD)"
[[ -n "$SVN_USERNAME" && -n "$SVN_PASSWORD" ]] || die "SVN_USERNAME / SVN_PASSWORD missing in .env"

printf '%s' "$SVN_PASSWORD" | svn commit -m "$MSG" --no-auth-cache --non-interactive \
	--username "$SVN_USERNAME" --password-from-stdin
echo "Published: https://wordpress.org/plugins/$SLUG/ (can take a few minutes to update)"
