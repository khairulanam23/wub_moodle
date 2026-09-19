#!/usr/bin/env bash
# Push theme-custom/academi-customcss.css into the theme_academi/customcss
# setting and purge caches. Runs Moodle CLI as www-data (never root).
set -euo pipefail
cd "$(dirname "$0")/.."
docker compose exec -T -u www-data php php admin/cli/cfg.php --component=theme_academi --name=customcss --set="$(cat theme-custom/academi-customcss.css)"
docker compose exec -T -u www-data php php admin/cli/purge_caches.php
echo "customcss applied ($(wc -c < theme-custom/academi-customcss.css) bytes) and caches purged."
