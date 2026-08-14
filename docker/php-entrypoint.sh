#!/usr/bin/env bash
set -euo pipefail

cd /app || exit 1

# Simply exec the passed command
# Composer installation is now controlled via Make or manual invocation
exec "$@"
