#!/usr/bin/env bash
#
# Run this ON THE SERVER (via SSH) after every `git pull`/code update, for
# ANY environment of ANY project laid out as ~/apps/<project>/<env>/ — re-
# syncs that environment's subdomain public_html/ with its own public/
# folder. Generic and reusable on purpose (not project- or env-specific):
# the same script serves gcm/dev, gcm/staging, gcm/testing, gcm/production,
# and any future project's environments, each with its own args.
#
# Needed because this Hostinger plan can't set a subdomain's Document Root
# outside its own public_html (see DEPLOYMENT.md §1) — that public_html is a
# real, COPIED folder, not a symlink to <app_dir>/public/, so it silently
# goes stale after every deploy unless something re-syncs it.
#
# Usage:
#   hostinger-sync-public.sh <app_dir> <web_dir>
#
# Example (one line per environment, e.g. in each environment's own deploy
# script or Git-deploy hook):
#   hostinger-sync-public.sh ~/apps/gcm/dev      ~/domains/dev.digitswat.com/public_html
#   hostinger-sync-public.sh ~/apps/gcm/testing  ~/domains/testing.digitswat.com/public_html
#   hostinger-sync-public.sh ~/apps/gcm/staging  ~/domains/staging.digitswat.com/public_html
#   hostinger-sync-public.sh ~/apps/gcm/production ~/domains/digitswat.com/public_html
#
# Run `ls ~/domains/` over SSH after creating each subdomain to confirm its
# actual folder name before using it as <web_dir> — don't assume.
set -euo pipefail

APP_DIR="${1:?Usage: $0 <app_dir> <web_dir>}"
WEB_DIR="${2:?Usage: $0 <app_dir> <web_dir>}"

# Resolve to absolute paths up front — everything below (especially the
# index.php rewrite) depends on APP_DIR being an absolute path, not
# something relative to whatever directory this script happened to be
# invoked from.
APP_DIR="$(cd "$APP_DIR" && pwd)"
mkdir -p "$WEB_DIR"
WEB_DIR="$(cd "$WEB_DIR" && pwd)"

if command -v rsync >/dev/null 2>&1; then
  # --delete removes anything in WEB_DIR that no longer exists in public/
  # (e.g. an old build/ hash-named asset) so it never accumulates cruft.
  rsync -a --delete --exclude 'index.php' --exclude 'storage' "$APP_DIR/public/" "$WEB_DIR/"
else
  # Fallback if rsync isn't available on this account — less thorough
  # (won't remove stale files), but works.
  cp -r "$APP_DIR"/public/* "$WEB_DIR/"
  rm -f "$WEB_DIR/index.php"
fi

# index.php's three `__DIR__ . '/../...'` paths assume public/ is a direct
# child of the project root — false here, since WEB_DIR isn't inside
# APP_DIR at all. Rewritten to plain ABSOLUTE paths (not a recalculated
# relative depth) so this keeps working no matter how deeply WEB_DIR is
# nested under domains/, and no matter which project/environment this is —
# depth-counting a relative path would need re-deriving by hand for every
# different hosting layout, an absolute path sidesteps that entirely.
# Re-applied on every sync (not hand-edited once) so a fresh `git pull`,
# which resets public/index.php to its original relative paths, can never
# silently un-fix this.
sed \
  -e "s#__DIR__ \. '/\.\./storage/framework/maintenance\.php'#'$APP_DIR/storage/framework/maintenance.php'#" \
  -e "s#__DIR__ \. '/\.\./vendor/autoload\.php'#'$APP_DIR/vendor/autoload.php'#" \
  -e "s#__DIR__ \. '/\.\./bootstrap/app\.php'#'$APP_DIR/bootstrap/app.php'#" \
  "$APP_DIR/public/index.php" > "$WEB_DIR/index.php"

# storage/ must stay a symlink to the REAL storage/app/public, never a
# copy — copying would silently desync from every future upload (a driver
# document uploaded tomorrow wouldn't exist in a copy made today).
ln -sfn "$APP_DIR/storage/app/public" "$WEB_DIR/storage"

echo "Synced $APP_DIR/public/ -> $WEB_DIR (index.php paths rewritten to absolute, storage/ symlinked)."
