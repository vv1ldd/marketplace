#!/usr/bin/env bash

set -euo pipefail

REPO_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
FRONTEND_DIR="${MEANLY_FRONTEND_DIR:-${REPO_ROOT}/frontend}"
FRONTEND_ENV="${FRONTEND_DIR}/.env"
PROFILE="${1:-global}"

case "$PROFILE" in
    global)
        TEMPLATE="${REPO_ROOT}/deploy/regional/env/frontend-global.env.example"
        ;;
    ru)
        TEMPLATE="${REPO_ROOT}/deploy/regional/env/frontend-ru.env.example"
        ;;
    *)
        echo "Usage: $(basename "$0") <global|ru>" >&2
        exit 1
        ;;
esac

if [ ! -f "$TEMPLATE" ]; then
    echo "Missing template: ${TEMPLATE}" >&2
    exit 1
fi

upsert_env() {
    local key="$1"
    local value="$2"
    if [ ! -f "$FRONTEND_ENV" ]; then
        cp "${FRONTEND_DIR}/.env.example" "$FRONTEND_ENV"
    fi
    if grep -q "^${key}=" "$FRONTEND_ENV"; then
        sed -i '' "s|^${key}=.*|${key}=${value}|" "$FRONTEND_ENV"
    else
        printf '%s=%s\n' "$key" "$value" >> "$FRONTEND_ENV"
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

if [ "$PROFILE" = "global" ]; then
    upsert_env "NEXT_PUBLIC_MARKETPLACE_API_URL" "${NEXT_PUBLIC_MARKETPLACE_API_URL:-https://api.meanly.test}"
elif [ "$PROFILE" = "ru" ]; then
    upsert_env "NEXT_PUBLIC_MARKETPLACE_API_URL" "${NEXT_PUBLIC_MARKETPLACE_API_URL:-https://api.meanly.test}"
fi

echo "Applied ${PROFILE} storefront env to ${FRONTEND_ENV}"
echo "Restart Next dev if it is already running."
