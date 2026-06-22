#!/usr/bin/env bash
# Emit LEVEL3_VAULT_TOKEN from browser localStorage (run in DevTools on meanly.one/vault).
# Paste output into terminal: eval "$(pbpaste)" or export manually.
#
# DevTools console one-liner:
#   copy(localStorage.getItem('meanly:storefront-token'))
#
cat <<'EOF'
After vault login on https://meanly.one/vault:

1. DevTools → Console
2. Run:
   copy(localStorage.getItem('meanly:storefront-token'))
3. Terminal:
   export LEVEL3_API_URL="https://meanly.one"
   export LEVEL3_VAULT_TOKEN="<paste>"
   export LEVEL3_POLYGON_RPC_URL="https://polygon-rpc.com"
   ./scripts/level3-run-staging.sh preflight
   ./scripts/level3-run-staging.sh before
EOF
