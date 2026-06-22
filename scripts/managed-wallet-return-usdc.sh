#!/usr/bin/env bash
# Operator-only: return USDC from a managed EVM binding to an external address.
# Requires native gas on the settlement address (managed wallets start with 0).
#
# Usage:
#   export POLYGON_RPC_URL=https://polygon-bor-rpc.publicnode.com
#   ./scripts/managed-wallet-return-usdc.sh \
#     --binding-id 5 \
#     --to 0xde466641fa3aedf83c9df259b313005f9bd44b94 \
#     [--amount-wei 1000000]
#
# Does not print the private key. Revoke the binding separately after funds leave chain.
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
BINDING_ID=""
TO=""
AMOUNT_WEI="1000000"

while [[ $# -gt 0 ]]; do
  case "$1" in
    --binding-id) BINDING_ID="$2"; shift 2 ;;
    --to) TO="$2"; shift 2 ;;
    --amount-wei) AMOUNT_WEI="$2"; shift 2 ;;
    *) echo "Unknown arg: $1" >&2; exit 1 ;;
  esac
done

if [[ -z "$BINDING_ID" || -z "$TO" ]]; then
  echo "Usage: $0 --binding-id <id> --to <0x...> [--amount-wei 1000000]" >&2
  exit 1
fi

if ! command -v cast >/dev/null 2>&1; then
  echo "cast (Foundry) is required." >&2
  exit 1
fi

read -r FROM PK_HEX NETWORK_KEY <<<"$(cd "$ROOT" && php artisan tinker --execute="
\$row = \\App\\Models\\VaultManagedWalletKey::query()
    ->where('identity_binding_id', (int) ${BINDING_ID})
    ->first();
if (!\$row) { fwrite(STDERR, 'No managed key for binding ${BINDING_ID}'.PHP_EOL); exit(1); }
\$hex = \\Illuminate\\Support\\Facades\\Crypt::decryptString(\$row->encrypted_secret);
echo \$row->address_normalized.' '.\$hex.' '.\$row->network_key;
" 2>/dev/null | tail -1)"

if [[ -z "$FROM" || -z "$PK_HEX" || -z "$NETWORK_KEY" ]]; then
  echo "Failed to resolve managed key for binding #${BINDING_ID}." >&2
  exit 1
fi

case "$NETWORK_KEY" in
  polygon)
    USDC_CONTRACT="0x3c499c542cef5e3811e1192ce70d8cc03d5c3359"
    CHAIN_ID=137
    RPC_URL="${POLYGON_RPC_URL:-https://polygon-bor-rpc.publicnode.com}"
    GAS_SYMBOL="POL"
    ;;
  ethereum)
    USDC_CONTRACT="0xA0b86991c6218b36c1d19D4a2e9Eb0cE3606eB48"
    CHAIN_ID=1
    RPC_URL="${ETHEREUM_RPC_URL:-https://ethereum-rpc.publicnode.com}"
    GAS_SYMBOL="ETH"
    ;;
  base)
    USDC_CONTRACT="0x833589fCD6eDb6E08f4c7C32D4f71b54bdA02913"
    CHAIN_ID=8453
    RPC_URL="${BASE_RPC_URL:-https://base-rpc.publicnode.com}"
    GAS_SYMBOL="ETH"
    ;;
  *)
    echo "Unsupported managed network: ${NETWORK_KEY}" >&2
    exit 1
    ;;
esac

POL_WEI="$(cast balance "$FROM" --rpc-url "$RPC_URL")"
USDC_WEI="$(cast call "$USDC_CONTRACT" 'balanceOf(address)(uint256)' "$FROM" --rpc-url "$RPC_URL" | awk '{print $1}')"

echo "Network:          $NETWORK_KEY"
echo "From (managed S): $FROM"
echo "To:               $TO"
echo "${GAS_SYMBOL} balance:      $POL_WEI wei"
echo "USDC balance:     $USDC_WEI wei"
echo "Send amount:      $AMOUNT_WEI wei"

if [[ "$POL_WEI" == "0" ]]; then
  echo ""
  echo "BLOCKED: settlement address has no ${GAS_SYMBOL} for gas."
  echo "Send a small ${GAS_SYMBOL} amount to $FROM on ${NETWORK_KEY}, then re-run."
  exit 2
fi

if [[ "$USDC_WEI" -lt "$AMOUNT_WEI" ]]; then
  echo "BLOCKED: insufficient USDC on settlement address." >&2
  exit 2
fi

echo ""
echo "Broadcasting USDC transfer..."
TX_HASH="$(cast send "$USDC_CONTRACT" \
  'transfer(address,uint256)(bool)' "$TO" "$AMOUNT_WEI" \
  --private-key "0x${PK_HEX}" \
  --rpc-url "$RPC_URL" \
  --chain "$CHAIN_ID" \
  --json | python3 -c 'import sys,json; print(json.load(sys.stdin)["transactionHash"])')"

echo "tx: $TX_HASH"
echo "Verify: cast receipt $TX_HASH --rpc-url $RPC_URL"
