#!/usr/bin/env bash
# =============================================================================
# AmEveryWhere — WordPress.org SVN Deploy Script
# =============================================================================
# Usage:
#   chmod +x deploy-to-wporg.sh
#   ./deploy-to-wporg.sh
#
# Requirements:
#   - svn installed
#   - composer installed
#   - Your WP.org username and plugin slug set below
# =============================================================================

set -e

PLUGIN_SLUG="ameverywhere"
WP_ORG_USER="${WP_ORG_USER:-ameverywhereteam}"
VERSION=$(grep "Stable tag:" readme.txt | sed 's/Stable tag: //' | tr -d '[:space:]')
SVN_URL="https://plugins.svn.wordpress.org/${PLUGIN_SLUG}"
BUILD_DIR="$(pwd)/.svn-build"
PLUGIN_DIR="$(pwd)"

echo "==> Deploying AmEveryWhere v${VERSION} to WordPress.org"
echo "    SVN URL : ${SVN_URL}"
echo "    WP User : ${WP_ORG_USER}"
echo ""

# ── 1. Install production Composer dependencies ───────────────────────────────
echo "==> Installing production composer dependencies..."
composer install --no-dev --optimize-autoloader --quiet

# ── 2. Checkout SVN trunk ─────────────────────────────────────────────────────
echo "==> Checking out SVN..."
rm -rf "${BUILD_DIR}"
svn checkout "${SVN_URL}" "${BUILD_DIR}" --depth immediates
svn update "${BUILD_DIR}/trunk" --set-depth infinity
svn update "${BUILD_DIR}/assets" --set-depth infinity

# ── 3. Sync plugin files to trunk (exclude dev files) ─────────────────────────
echo "==> Syncing files to SVN trunk..."
rsync -rc --delete \
    --exclude=".git" \
    --exclude=".svn" \
    --exclude=".svn-build" \
    --exclude=".svnignore" \
    --exclude=".gitignore" \
    --exclude=".phpcs.xml" \
    --exclude=".editorconfig" \
    --exclude="node_modules" \
    --exclude="tests" \
    --exclude="phpunit.xml" \
    --exclude="phpunit.xml.dist" \
    --exclude="composer.phar" \
    --exclude="composer.lock" \
    --exclude="package.json" \
    --exclude="package-lock.json" \
    --exclude="tailwind.config.js" \
    --exclude="postcss.config.js" \
    --exclude="backlog.md" \
    --exclude="deferred-backlog.md" \
    --exclude="about.md" \
    --exclude="features.md" \
    --exclude="tasklist.md" \
    --exclude="specification.md" \
    --exclude="deploy-to-wporg.sh" \
    "${PLUGIN_DIR}/" "${BUILD_DIR}/trunk/"

# ── 4. Copy assets (banner, icons, screenshots) ───────────────────────────────
if [ -d "${PLUGIN_DIR}/assets/wporg" ]; then
    echo "==> Syncing WP.org assets (banners, icons)..."
    rsync -rc "${PLUGIN_DIR}/assets/wporg/" "${BUILD_DIR}/assets/"
fi

# ── 5. SVN add new files, remove deleted files ───────────────────────────────
echo "==> Updating SVN file statuses..."
cd "${BUILD_DIR}"
svn status trunk | grep "^?" | awk '{print $2}' | xargs -r svn add
svn status trunk | grep "^!" | awk '{print $2}' | xargs -r svn delete

# ── 6. Tag the release ────────────────────────────────────────────────────────
echo "==> Tagging release v${VERSION}..."
svn cp trunk "tags/${VERSION}"

# ── 7. Commit ─────────────────────────────────────────────────────────────────
echo "==> Committing to WordPress.org SVN..."
svn commit \
    --username "${WP_ORG_USER}" \
    -m "Release v${VERSION}"

# 8. Restore dev composer dependencies ─────────────────────────────────────
echo "==> Restoring dev composer dependencies..."
cd "${PLUGIN_DIR}"
composer install --quiet

echo ""
echo "✅ AmEveryWhere v${VERSION} deployed to WordPress.org"
echo "   View at: https://wordpress.org/plugins/${PLUGIN_SLUG}/"
