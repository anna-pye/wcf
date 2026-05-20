#!/usr/bin/env bash
# shellcheck disable=SC1091
set -euo pipefail

# Import the Drupal 7 legacy database into D11 DDEV as database `legacy`.
#
# Prerequisite: D11 project has additional_databases: legacy in .ddev/config.yaml
# and settings.migrate.php is included from settings.php.
#
# Usage (from drupal11-upgrade repo root):
#   ./scripts/wcf-import-legacy-database.sh
#   ./scripts/wcf-import-legacy-database.sh /path/to/wcf-legacy.sql.gz
#
# Default: export from ~/drupal7-legacy DDEV project (WCF) when that project exists.

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
DUMP="${1:-}"

if ! command -v ddev >/dev/null 2>&1; then
  echo "ddev is required." >&2
  exit 1
fi

cd "$ROOT"

if [[ -n "$DUMP" && ! -f "$DUMP" ]]; then
  echo "Dump file not found: $DUMP" >&2
  exit 1
fi

if [[ -z "$DUMP" ]]; then
  D7_ROOT="${WCF_D7_ROOT:-$HOME/drupal7-legacy}"
  if [[ ! -d "$D7_ROOT/.ddev" ]]; then
    echo "Set WCF_D7_ROOT to your D7 codebase or pass a .sql.gz dump path." >&2
    echo "Example: $0 ~/Downloads/wcf-legacy.sql.gz" >&2
    exit 1
  fi
  DUMP="$(mktemp /tmp/wcf-legacy-XXXXXX.sql.gz)"
  trap 'rm -f "$DUMP"' EXIT
  echo "Exporting legacy DB from $D7_ROOT ..."
  (cd "$D7_ROOT" && ddev export-db --gzip --file="$DUMP")
fi

echo "Restarting D11 DDEV (ensures legacy database exists) ..."
ddev restart

echo "Importing into D11 database 'legacy' ..."
ddev import-db --database=legacy --file="$DUMP"

echo "Verifying wcf_product table ..."
ddev exec mysql legacy -e "SELECT COUNT(*) AS products FROM wcf_product;" 2>/dev/null \
  || ddev mysql -e "SELECT COUNT(*) AS products FROM wcf_product;" legacy

echo ""
echo "Done. Next:"
echo "  ddev drush php:script scripts/wcf-map-product-display-names.php"
echo "  ddev drush migrate:import wcf_d7_node_stallion --update   # optional full re-import"
