#!/usr/bin/env bash
# Build a distributable copy of the plugin from src/ into dist/.
#
#   dist/wp-api-stats/            -> exactly what goes to SVN trunk
#   dist/wp-api-stats-<ver>.zip   -> installable zip
#
# Works in Git Bash (Windows) and on Linux.
set -euo pipefail

SLUG="wp-api-stats"
ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
SRC="$ROOT/src"
DIST="$ROOT/dist"
OUT="$DIST/$SLUG"

# --- Version sanity check: plugin header and readme Stable tag must match.
header_ver=$(grep -m1 -iE '^\s*\*?\s*Version:' "$SRC/$SLUG.php" | awk '{print $NF}' | tr -d '\r')
stable_ver=$(grep -m1 -iE '^Stable tag:' "$SRC/readme.txt" | awk '{print $NF}' | tr -d '\r')
if [[ "$header_ver" != "$stable_ver" ]]; then
	echo "ERROR: version mismatch: $SLUG.php says '$header_ver', readme.txt Stable tag says '$stable_ver'" >&2
	exit 1
fi
echo "Building $SLUG $header_ver"

# --- Copy, excluding dev-only files.
rm -rf "$DIST"
mkdir -p "$OUT"
( cd "$SRC" && tar --exclude='*.scss' --exclude='.gitignore' --exclude='.DS_Store' -cf - . ) | ( cd "$OUT" && tar -xf - )

# --- Zip.
ZIP="$DIST/$SLUG-$header_ver.zip"
if command -v zip >/dev/null 2>&1; then
	( cd "$DIST" && zip -qr "$ZIP" "$SLUG" )
elif PY=$(command -v python3 || command -v python); then
	# PowerShell's Compress-Archive writes backslash paths, which break WP installs; use Python instead.
	( cd "$DIST" && "$PY" -c 'import sys, os, zipfile
with zipfile.ZipFile(sys.argv[1], "w", zipfile.ZIP_DEFLATED) as z:
    for d, _, fs in os.walk(sys.argv[2]):
        for f in fs:
            p = os.path.join(d, f); z.write(p, p.replace(os.sep, "/"))' "$ZIP" "$SLUG" )
else
	echo "WARN: no zip tool found; skipped creating $ZIP" >&2
fi

echo "Done:"
echo "  $OUT"
[[ -f "$ZIP" ]] && echo "  $ZIP"
exit 0
