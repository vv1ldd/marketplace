#!/usr/bin/env bash

set -euo pipefail

REPO_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
BACKEND_ENV="${MEANLY_BACKEND_ENV:-${REPO_ROOT}/.env}"
PROFILE="${1:-tunnel-global}"

case "$PROFILE" in
    tunnel-global)
        TEMPLATE="${REPO_ROOT}/deploy/regional/env/backend-mac-tunnel.env.example"
        ;;
    *)
        echo "Usage: $(basename "$0") <tunnel-global>" >&2
        exit 1
        ;;
esac

if [ ! -f "$BACKEND_ENV" ]; then
    echo "Missing ${BACKEND_ENV}" >&2
    exit 1
fi

if [ ! -f "$TEMPLATE" ]; then
    echo "Missing template: ${TEMPLATE}" >&2
    exit 1
fi

upsert_env() {
    local key="$1"
    local value="$2"
    if grep -q "^${key}=" "$BACKEND_ENV"; then
        sed -i '' "s|^${key}=.*|${key}=${value}|" "$BACKEND_ENV"
    else
        printf '%s=%s\n' "$key" "$value" >> "$BACKEND_ENV"
    fi
}

while IFS= read -r line || [ -n "$line" ]; do
    case "$line" in
        ''|\#*) continue ;;
        *=*)
            key="${line%%=*}"
            value="${line#*=}"
            upsert_env "$key" "$value"
            ;;
    esac
done < "$TEMPLATE"

# Keep the OAuth client that is registered in Simple L1 for local dev.
upsert_env "SIMPLE_L1_CLIENT_ID" "meanly.test"
upsert_env "SIMPLE_L1_RUNTIME_URL" "http://127.0.0.1:3000"
upsert_env "MARKET_DEFAULT" "global"
upsert_env "MARKET_GLOBAL_DOMAINS" "meanly.one,www.meanly.one,meanly.test,marketplace.one,www.marketplace.one"
upsert_env "SESSION_DOMAIN" "null"
upsert_env "SESSION_SECURE_COOKIE" "true"
upsert_env "COMMERCE_CRYPTO_RAILS_ENABLED" "true"
upsert_env "MANAGED_WALLETS_ENABLED" "true"
upsert_env "MANAGED_WALLET_POLYGON_ENABLED" "true"
upsert_env "MANAGED_WALLET_ETHEREUM_ENABLED" "true"
upsert_env "MANAGED_WALLET_BASE_ENABLED" "true"
upsert_env "SETTLEMENT_ADAPTER_POLYGON_ENABLED" "true"
upsert_env "SETTLEMENT_ADAPTER_POLYGON_MODE" "read_only"
upsert_env "SETTLEMENT_ADAPTER_ETHEREUM_ENABLED" "true"
upsert_env "SETTLEMENT_ADAPTER_ETHEREUM_MODE" "read_only"
upsert_env "SETTLEMENT_ADAPTER_BASE_ENABLED" "true"
upsert_env "SETTLEMENT_ADAPTER_BASE_MODE" "read_only"
upsert_env "POLYGON_RPC_ENABLED" "true"
upsert_env "ETHEREUM_RPC_ENABLED" "true"
upsert_env "BASE_RPC_ENABLED" "true"

echo "Applied ${PROFILE} backend env to ${BACKEND_ENV}"
echo "Run: php artisan config:clear"
