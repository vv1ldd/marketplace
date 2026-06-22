#!/usr/bin/env bash
# Level 3 / multi-rail staging evidence capture — rail-agnostic binding facts.
#
# Usage:
#   export LEVEL3_API_URL="https://meanly.one/backend"
#   export LEVEL3_VAULT_TOKEN="<storefront vault bearer token>"
#   export LEVEL3_VAULT_ID="<uuid>"                    # optional if token resolves vault
#   export LEVEL3_POLYGON_RPC_URL="https://..."        # optional chain cross-check per EVM rail
#   export LEVEL3_ETHEREUM_RPC_URL="https://..."
#   export LEVEL3_BASE_RPC_URL="https://..."
#   ./scripts/level3-evidence-capture.sh before
#   ./scripts/level3-evidence-capture.sh after
#
# DB capture (on staging host):
#   export LEVEL3_VAULT_ID="<uuid>"
#   ./scripts/level3-evidence-capture.sh db
#
set -euo pipefail

PHASE="${1:-}"
API_URL="${LEVEL3_API_URL:-}"
if [[ -n "$API_URL" && "$API_URL" != *"/backend" ]]; then
  API_BASE="${API_URL%/}/backend"
else
  API_BASE="${API_URL%/}"
fi
TOKEN="${LEVEL3_VAULT_TOKEN:-}"
VAULT_ID="${LEVEL3_VAULT_ID:-}"

if [[ "$PHASE" != "before" && "$PHASE" != "after" && "$PHASE" != "db" ]]; then
  echo "Usage: $0 before|after|db" >&2
  exit 1
fi

header() {
  echo ""
  echo "=== LEVEL3 CAPTURE ($PHASE) $(date -u +"%Y-%m-%dT%H:%M:%SZ") ==="
}

api_get() {
  local path="$1"
  curl -fsS \
    -H "Accept: application/json" \
    -H "Authorization: Bearer ${TOKEN}" \
    "${API_BASE%/}${path}"
}

enrich_bindings_json() {
  local bindings_json="$1"
  local assets_json="$2"
  LEVEL3_POLYGON_RPC_URL="${LEVEL3_POLYGON_RPC_URL:-${POLYGON_RPC_URL:-}}"
  LEVEL3_ETHEREUM_RPC_URL="${LEVEL3_ETHEREUM_RPC_URL:-${ETHEREUM_RPC_URL:-}}"
  LEVEL3_BASE_RPC_URL="${LEVEL3_BASE_RPC_URL:-${BASE_RPC_URL:-}}"
  export bindings_json assets_json
  python3 <<'PY'
import json
import os
import urllib.request

bindings_payload = json.loads(os.environ["bindings_json"])
assets_payload = json.loads(os.environ["assets_json"])

USDC_BY_RAIL = {
    "polygon": {
        "contract": "0x3c499c542cef5e3811e1192ce70d8cc03d5c3359",
        "rpc": os.environ.get("LEVEL3_POLYGON_RPC_URL", ""),
    },
    "ethereum": {
        "contract": "0xA0b86991c6218b36c1d19D4a2e9Eb0cE3606eB48",
        "rpc": os.environ.get("LEVEL3_ETHEREUM_RPC_URL", ""),
    },
    "base": {
        "contract": "0x833589fCD6eDb6E08f4c7C32D4f71b54bdA02913",
        "rpc": os.environ.get("LEVEL3_BASE_RPC_URL", ""),
    },
}

def usdc_from_assets(network_key):
    for wallet in assets_payload.get("network_wallets") or []:
        net = (wallet.get("network") or {}).get("key")
        if net != network_key:
            continue
        for coin in wallet.get("coins") or []:
            if str(coin.get("symbol", "")).upper() == "USDC":
                return {
                    "symbol": "USDC",
                    "amount": coin.get("amount"),
                    "display_amount": coin.get("display_amount"),
                    "status": coin.get("status"),
                }
    return None


def chain_usdc_balance(rpc_url, contract, address):
    if not rpc_url or not contract or not address:
        return None
    addr = address.lower().removeprefix("0x")
    padded = addr.rjust(64, "0")
    data = "0x70a08231" + padded
    body = json.dumps(
        {
            "jsonrpc": "2.0",
            "id": 1,
            "method": "eth_call",
            "params": [{"to": contract, "data": data}, "latest"],
        }
    ).encode()
    req = urllib.request.Request(
        rpc_url,
        data=body,
        headers={"Content-Type": "application/json"},
        method="POST",
    )
    with urllib.request.urlopen(req, timeout=20) as resp:
        result = json.loads(resp.read().decode()).get("result", "0x0")
    wei = int(result, 16)
    return {
        "balance_raw": result,
        "balance_human": f"{wei / 1_000_000:.6f} USDC",
    }


items = []
for item in bindings_payload.get("items") or []:
    if item.get("binding_type") != "wallet":
        continue
    if item.get("verification_state") == "revoked":
        continue
    network = item.get("binding_key")
    address = item.get("binding_value")
    entry = {
        "network": network,
        "binding_id": item.get("id"),
        "binding_source": item.get("binding_source"),
        "verification_method": item.get("verification_method"),
        "verification_state": item.get("verification_state"),
        "address": address,
    }
    api_obs = usdc_from_assets(network)
    observation = {}
    if api_obs:
        observation["api"] = api_obs
    rail = USDC_BY_RAIL.get(network or "")
    if rail and rail["rpc"] and address:
        try:
            chain_obs = chain_usdc_balance(rail["rpc"], rail["contract"], address)
            if chain_obs:
                observation["chain"] = chain_obs
        except Exception as exc:
            observation["chain_error"] = str(exc)
    if observation:
        entry["observation"] = observation
    items.append(entry)

items.sort(key=lambda row: str(row.get("network") or ""))
print(json.dumps(items, indent=2))
PY
}

capture_api() {
  if [[ -z "$API_URL" || -z "$TOKEN" ]]; then
    echo "SKIP API: set LEVEL3_API_URL and LEVEL3_VAULT_TOKEN" >&2
    return 0
  fi

  echo "--- API: GET /api/storefront/v1/wallet (identity anchor) ---"
  WALLET_JSON=$(api_get "/api/storefront/v1/wallet")
  echo "$WALLET_JSON" | jq '{
    entity_l1_address: .identity.entity_l1_address,
    vault_id: .vault.id,
    managed_wallets_enabled: .capabilities.managed_wallets_enabled,
    managed_wallet_networks: .capabilities.managed_wallet_networks
  }'

  if [[ -z "$VAULT_ID" ]]; then
    VAULT_ID=$(echo "$WALLET_JSON" | jq -r '.vault.id // empty')
  fi

  echo "--- API: GET /api/storefront/v1/wallet/bindings ---"
  BINDINGS_JSON=$(api_get "/api/storefront/v1/wallet/bindings")
  echo "$BINDINGS_JSON" | jq '{
    vault_id: .vault_id,
    binding_count: ([.items[]? | select(.binding_type=="wallet" and .verification_state!="revoked")] | length)
  }'

  echo "--- API: GET /api/storefront/v1/wallet/assets ---"
  ASSETS_JSON=$(api_get "/api/storefront/v1/wallet/assets")

  echo "--- BINDINGS[] (rail-agnostic; observation optional per rail) ---"
  enrich_bindings_json "$BINDINGS_JSON" "$ASSETS_JSON"
}

capture_db() {
  if [[ -z "$VAULT_ID" ]]; then
    echo "SKIP DB: set LEVEL3_VAULT_ID" >&2
    return 0
  fi

  echo "--- DB (artisan tinker; all active wallet bindings) ---"
  php artisan tinker --execute="
\$vault = \\App\\Models\\VaultIdentity::query()->find('${VAULT_ID}');
\$bindings = \\App\\Models\\IdentityBinding::query()
    ->where('vault_id', '${VAULT_ID}')
    ->where('binding_type', 'wallet')
    ->where('verification_state', '!=', 'revoked')
    ->orderBy('binding_key')
    ->get()
    ->map(fn (\$b) => [
        'network' => \$b->binding_key,
        'binding_id' => \$b->id,
        'binding_source' => \$b->binding_source,
        'verification_method' => \$b->verification_method,
        'verification_state' => \$b->verification_state,
        'address' => \$b->binding_value_normalized,
    ])
    ->values()
    ->all();
echo json_encode([
    'vault_id' => \$vault?->id,
    'anchor_address' => \$vault?->anchor_address,
    'bindings' => \$bindings,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
"
}

header

if [[ "$PHASE" == "db" ]]; then
  capture_db
else
  capture_api
  if [[ -n "$VAULT_ID" ]]; then
    capture_db
  fi
fi

echo ""
echo "=== END ($PHASE) ==="
