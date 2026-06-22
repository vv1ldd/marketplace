#!/usr/bin/env bash
# Capture PaymentIntent (v3b) responses for staging evidence.
#
# Usage:
#   export LEVEL3_API_URL="https://meanly.one"
#   export LEVEL3_VAULT_TOKEN="<sender storefront:vault bearer>"
#   ./scripts/level3-payment-intent-capture.sh @alice 10
#   ./scripts/level3-payment-intent-capture.sh @alice 0.01 true | tee /tmp/v3b-payment-after.log
#
# Third arg "true" sets execute=true (requires IDENTITY_PAYMENTS_EXECUTE on server + gas/USDC).
set -euo pipefail

TO_ALIAS="${1:-}"
AMOUNT="${2:-}"
EXECUTE="${3:-false}"
API_URL="${LEVEL3_API_URL:-}"
TOKEN="${LEVEL3_VAULT_TOKEN:-}"
IDEMPOTENCY_KEY="${LEVEL3_PAYMENT_IDEMPOTENCY_KEY:-}"

if [[ -z "$TO_ALIAS" || -z "$AMOUNT" ]]; then
  echo "Usage: $0 <@alias> <amount> [execute:true|false]" >&2
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
echo "=== V3B PAYMENT INTENT CAPTURE $(date -u +"%Y-%m-%dT%H:%M:%SZ") ==="
echo "to_alias: $TO_ALIAS"
echo "amount:   $AMOUNT USDC"
echo "execute:  $EXECUTE"
echo ""

curl -fsS \
  -X POST \
  -H "Accept: application/json" \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer ${TOKEN}" \
  "${API_BASE%/}/api/storefront/v1/settlement/payment-intents" \
  -d "$(python3 - "$TO_ALIAS" "$AMOUNT" "$EXECUTE" "$IDEMPOTENCY_KEY" <<'PY'
import json, sys
payload = {
    "to_alias": sys.argv[1],
    "asset": "USDC",
    "amount": sys.argv[2],
    "execute": sys.argv[3].lower() == "true",
}
if sys.argv[4]:
    payload["idempotency_key"] = sys.argv[4]
print(json.dumps(payload))
PY
)" \
  | python3 -m json.tool

echo ""
echo "=== END ==="
