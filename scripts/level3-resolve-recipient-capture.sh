#!/usr/bin/env bash
# Capture ResolveRecipient (v3a) responses for staging evidence.
#
# Usage:
#   export LEVEL3_API_URL="https://meanly.one"
#   export LEVEL3_VAULT_TOKEN="<sender storefront:vault bearer>"
#   ./scripts/level3-resolve-recipient-capture.sh @alice
#   ./scripts/level3-resolve-recipient-capture.sh @alice | tee /tmp/v3a-resolve-after.log
#
set -euo pipefail

ALIAS="${1:-}"
API_URL="${LEVEL3_API_URL:-}"
TOKEN="${LEVEL3_VAULT_TOKEN:-}"

if [[ -z "$ALIAS" ]]; then
  echo "Usage: $0 <@alias>" >&2
  exit 1
fi

if [[ -n "$API_URL" && "$API_URL" != *"/backend" ]]; then
  API_BASE="${API_URL%/}/backend"
else
  API_BASE="${API_URL%/}"
fi

if [[ -z "$API_BASE" || -z "$TOKEN" ]]; then
  echo "Set LEVEL3_API_URL and LEVEL3_VAULT_TOKEN" >&2
  exit 1
fi

echo ""
echo "=== V3A RESOLVE CAPTURE $(date -u +"%Y-%m-%dT%H:%M:%SZ") ==="
echo "alias: $ALIAS"
echo ""

curl -fsS \
  -X POST \
  -H "Accept: application/json" \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer ${TOKEN}" \
  "${API_BASE%/}/api/storefront/v1/settlement/resolve-recipient" \
  -d "$(python3 -c "import json,sys; print(json.dumps({'alias': sys.argv[1]}))" "$ALIAS")" \
  | python3 -m json.tool

echo ""
echo "=== END ==="
