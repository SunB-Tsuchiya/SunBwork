#!/usr/bin/env bash
# Lightweight wrapper for vendor/bin/sail to avoid terminal crashes and unset var warnings
export WWWUSER=${WWWUSER:-1000}
export WWWGROUP=${WWWGROUP:-1000}
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" >/dev/null 2>&1 && pwd)"
if [ -x "$SCRIPT_DIR/vendor/bin/sail" ]; then
  exec "$SCRIPT_DIR/vendor/bin/sail" "$@"
fi
echo "Could not find vendor/bin/sail. Run 'composer install' to install dependencies." >&2
exit 1
