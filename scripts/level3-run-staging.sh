#!/usr/bin/env bash
# Level 3 staging run helper — operator executes playbook steps; script captures facts.
#
# Usage:
#   export LEVEL3_API_URL="https://meanly.one"
#   export LEVEL3_VAULT_TOKEN="<bearer after vault login>"
#   export LEVEL3_POLYGON_RPC_URL="https://polygon-rpc.com/..."   # optional
#   export LEVEL3_EVIDENCE_FILE="docs/evidence/level-3-run-$(date -u +%Y-%m-%d).md"
#
#   ./scripts/level3-run-staging.sh preflight
#   ./scripts/level3-run-staging.sh before    # after Safe + USDC observed
#   ./scripts/level3-run-staging.sh after     # after destructive drill + re-auth
#   ./scripts/level3-run-staging.sh db
#   ./scripts/level3-run-staging.sh validate-graph /tmp/multi-rail-before.log
#   ./scripts/level3-run-staging.sh compare-graph /tmp/multi-rail-before.log /tmp/multi-rail-after.log
#
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
EVIDENCE_FILE="${LEVEL3_EVIDENCE_FILE:-$ROOT/docs/evidence/level-3-run-$(date -u +%Y-%m-%d).md}"
CAPTURE="$ROOT/scripts/level3-evidence-capture.sh"
VALIDATE="$ROOT/scripts/level3-validate-graph.sh"
LOG_DIR="${LEVEL3_LOG_DIR:-/tmp}"

cmd="${1:-}"

mkdir -p "$LOG_DIR"

preflight() {
  echo "== Level 3 preflight =="
  echo "Evidence file: $EVIDENCE_FILE"
  if [[ ! -f "$EVIDENCE_FILE" ]]; then
    cp "$ROOT/docs/evidence/level-3-run-TEMPLATE.md" "$EVIDENCE_FILE"
    echo "Created evidence file from template."
  fi
  if [[ -z "${LEVEL3_VAULT_TOKEN:-}" ]]; then
    echo "WARN: LEVEL3_VAULT_TOKEN not set — complete vault login first." >&2
    echo "  Browser: https://meanly.one/vault → passkey → DevTools → localStorage vault token" >&2
  fi
  if [[ -n "${LEVEL3_VAULT_TOKEN:-}" && -n "${LEVEL3_API_URL:-}" ]]; then
    echo "--- capabilities ---"
    curl -fsS "${LEVEL3_API_URL%/}/backend/api/storefront/v1/wallet" \
      -H "Accept: application/json" \
      -H "Authorization: Bearer ${LEVEL3_VAULT_TOKEN}" \
      | jq '{managed: .capabilities.managed_wallets_enabled, crypto: .capabilities.crypto_rails_enabled, vault_id: .vault.id, entity: .identity.entity_l1_address}' \
      || echo "API probe failed — check LEVEL3_API_URL (use https://meanly.one, script uses /backend proxy)"
  fi
  echo "Playbook: docs/evidence/level-3-run-playbook.md"
}

capture_phase() {
  local phase="$1"
  local log="$LOG_DIR/level3-${phase}.log"
  echo "Capturing $phase → $log"
  "$CAPTURE" "$phase" | tee "$log"
  echo ""
  phase_upper="$(printf '%s' "$phase" | tr '[:lower:]' '[:upper:]')"
  echo "Paste $log into $EVIDENCE_FILE section: ## ${phase_upper}"
}

local_ci() {
  echo "== Local CI simulation (NOT staging Level 3 PASS) =="
  cd "$ROOT"
  php artisan test --filter='ManagedWalletAttachmentOperationalDrillTest|StorefrontManagedWalletProvisioningTest' 2>&1 | tee "$LOG_DIR/level3-local-ci.log"
  echo ""
  echo "Local CI proves architecture + simulated observation replay."
  echo "Staging Level 3 still requires meanly.one evidence capture with real USDC."
}

case "$cmd" in
  preflight) preflight ;;
  before) capture_phase before ;;
  after) capture_phase after ;;
  db) "$CAPTURE" db ;;
  validate-graph) shift; "$VALIDATE" gate "$@" ;;
  compare-graph) shift; "$VALIDATE" compare "$@" ;;
  local-ci) local_ci ;;
  *)
    echo "Usage: $0 preflight|before|after|db|validate-graph|compare-graph|local-ci" >&2
    exit 1
    ;;
esac
