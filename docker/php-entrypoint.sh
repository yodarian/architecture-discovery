#!/usr/bin/env bash
set -euo pipefail

cd /app || exit 1

# Install composer dependencies if composer.json exists
if [ -f composer.json ]; then
  composer install --prefer-dist --no-interaction --no-progress || true
fi

exec "$@"
