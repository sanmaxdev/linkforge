#!/usr/bin/env bash
#
# Builds the incremental in-app update package: dist/linkforge-update-v<version>.zip
# Layout matches what App\Services\Update\Updater expects:
#   update.json   – { version, requires, name, notes }
#   files/...     – only the files changed since the previous tag, mirrored at app-root paths
# An operator uploads this in Admin -> Updates; the Updater backs up, overlays files/, then migrates.
# Run from the repo root AFTER `npm run build` (so freshly-compiled assets are available).
#
set -euo pipefail

VERSION="${1:?usage: build-update-zip.sh <version>}"
TAG="v${VERSION}"
ROOT="$(pwd)"
STAGE="$(mktemp -d)"
mkdir -p "$STAGE/files"

# Only ship runtime paths an operator actually needs overlaid (never tests/, CI, or dev configs).
INCLUDE='^(app/|resources/|config/|routes/|database/|bootstrap/app\.php|bootstrap/providers\.php|public/docs/|public/(favicon\.(ico|png)|apple-touch-icon\.png|logo\.png)|composer\.(json|lock)|artisan)'

PREV="$(git describe --tags --abbrev=0 "${TAG}^" 2>/dev/null || true)"
if [ -n "$PREV" ]; then
  echo "Diffing $PREV..$TAG for changed files"
  mapfile -t CHANGED < <(git diff --name-only "$PREV".."$TAG" | grep -E "$INCLUDE" || true)
else
  echo "No previous tag — shipping the full runtime tree"
  mapfile -t CHANGED < <(git ls-files | grep -E "$INCLUDE" || true)
fi

for f in "${CHANGED[@]}"; do
  if [ -n "$f" ] && [ -f "$f" ]; then
    mkdir -p "$STAGE/files/$(dirname "$f")"
    cp "$f" "$STAGE/files/$f"
  fi
done

# Bundle the freshly-built assets whenever front-end sources (or the manifest) changed.
if [ -z "$PREV" ] || git diff --name-only "$PREV".."$TAG" | grep -qE '^(resources/(css|js)/|vite\.config\.js|package(-lock)?\.json)'; then
  mkdir -p "$STAGE/files/public"
  cp -r public/build "$STAGE/files/public/build"
fi

cat > "$STAGE/update.json" <<JSON
{
    "version": "${VERSION}",
    "requires": "1.0.0",
    "name": "LinkForge ${VERSION}",
    "notes": "Release notes: https://github.com/sanmaxdev/linkforge/releases/tag/${TAG}"
}
JSON

mkdir -p "$ROOT/dist"
( cd "$STAGE" && zip -rqX "$ROOT/dist/linkforge-update-v${VERSION}.zip" . )
rm -rf "$STAGE"

echo "Built dist/linkforge-update-v${VERSION}.zip ($(du -h "$ROOT/dist/linkforge-update-v${VERSION}.zip" | cut -f1))"
