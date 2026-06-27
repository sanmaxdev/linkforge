#!/usr/bin/env bash
#
# Builds the full, self-hostable install package: dist/linkforge-v<version>.zip
# Files sit at the archive ROOT (app/, public/, vendor/, artisan, ...), so a buyer extracts
# straight into their web root. Run from the repo root AFTER:
#   composer install --no-dev --optimize-autoloader
#   npm ci && npm run build
#
set -euo pipefail

VERSION="${1:?usage: build-install-zip.sh <version>}"
ROOT="$(pwd)"
STAGE="$(mktemp -d)"

# Export every TRACKED file. .env, node_modules, vendor, public/build and runtime cruft are
# gitignored and therefore excluded automatically.
git archive --format=tar HEAD | tar -x -C "$STAGE"

# Add the production dependencies + compiled assets (both gitignored; built in CI).
cp -r vendor "$STAGE/vendor"
cp -r public/build "$STAGE/public/build"

# Drop development-only files a production server never needs.
rm -rf \
  "$STAGE/.github" \
  "$STAGE/tests" \
  "$STAGE/phpunit.xml" \
  "$STAGE/pint.json" \
  "$STAGE/package.json" \
  "$STAGE/package-lock.json" \
  "$STAGE/vite.config.js" \
  "$STAGE/.editorconfig" \
  "$STAGE/.gitattributes"

mkdir -p "$ROOT/dist"
( cd "$STAGE" && zip -rqX "$ROOT/dist/linkforge-v${VERSION}.zip" . )
rm -rf "$STAGE"

echo "Built dist/linkforge-v${VERSION}.zip ($(du -h "$ROOT/dist/linkforge-v${VERSION}.zip" | cut -f1))"
