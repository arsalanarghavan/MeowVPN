#!/usr/bin/env bash
# DEPRECATED: WordPress import removed after Laravel cutover. Use migrate:fresh + seeders.
set -euo pipefail
echo "ERROR: wp:import was removed. Use: php artisan migrate --force && php artisan db:seed" >&2
exit 1
