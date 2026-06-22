#!/usr/bin/env bash
# Validate v3a ResolveRecipient capture logs.
#
# Usage:
#   ./scripts/level3-validate-resolve.sh gate /tmp/v3a-resolve-before.log
#   ./scripts/level3-validate-resolve.sh compare /tmp/v3a-resolve-before.log /tmp/v3a-resolve-after.log
#
set -euo pipefail

MODE="${1:-}"
INPUT_A="${2:-}"
INPUT_B="${3:-}"

usage() {
  echo "Usage: $0 gate <capture-log|json>" >&2
  echo "       $0 compare <before-log|json> <after-log|json>" >&2
  exit 1
}

extract_resolve_json() {
  local file="$1"
  python3 - "$file" <<'PY'
import json
import re
import sys

path = sys.argv[1]
text = open(path, encoding="utf-8").read().strip()

try:
    data = json.loads(text)
    if isinstance(data, dict):
        print(json.dumps(data, indent=2))
        sys.exit(0)
except json.JSONDecodeError:
    pass

# Strip capture header/footer; take first JSON object in file
start = text.find("{")
end = text.rfind("}")
if start == -1 or end == -1 or end <= start:
    print(f"Could not extract resolve JSON from {path}", file=sys.stderr)
    sys.exit(1)
print(text[start : end + 1])
PY
}

gate_resolve() {
  local file="$1"
  local json
  json="$(extract_resolve_json "$file")"

  echo "== v3a ResolveRecipient gate =="
  echo "Source: $file"
  echo ""
  echo "$json" | python3 -m json.tool

  if echo "$json" | jq -e '.address' >/dev/null 2>&1; then
    echo ""
    echo "V3A GATE: FAIL — root address key (wallet-centric regression)" >&2
    exit 1
  fi

  if ! echo "$json" | jq -e '
    .contract.name == "resolve-recipient"
    and (.identity_id | type == "string" and length > 0)
    and (.ownership.bindings | type == "array")
    and (.receiving_capabilities | type == "array")
    and (.ownership.bindings | length) > 0
    and all(.ownership.bindings[]; .binding_id != null and .network != null and .capability == "receive")
  ' >/dev/null; then
    echo ""
    echo "V3A GATE: FAIL — expected capability graph contract" >&2
    exit 1
  fi

  echo ""
  echo "V3A GATE: PASS"
  echo "  capability graph (not address lookup)"
  echo "  identity_id: $(echo "$json" | jq -r '.identity_id')"
  echo "  bindings: $(echo "$json" | jq -r '[.ownership.bindings[].binding_id] | join(", ")')"
}

compare_resolves() {
  local before_file="$1"
  local after_file="$2"
  local before_json after_json

  before_json="$(extract_resolve_json "$before_file")"
  after_json="$(extract_resolve_json "$after_file")"

  echo "== v3a ResolveRecipient compare =="
  echo "BEFORE: $before_file"
  echo "AFTER:  $after_file"
  echo ""

  gate_resolve "$before_file" >/dev/null
  gate_resolve "$after_file" >/dev/null
  gate_resolve "$before_file"
  echo ""
  gate_resolve "$after_file"
  echo ""

  if ! python3 - <<'PY' "$before_json" "$after_json"
import json
import sys

before = json.loads(sys.argv[1])
after = json.loads(sys.argv[2])

def bindings_map(payload):
    rows = payload.get("ownership", {}).get("bindings") or payload.get("receiving_capabilities") or []
    return {int(row["binding_id"]): row for row in rows}

b_before = bindings_map(before)
b_after = bindings_map(after)

identity_before = before.get("identity_id")
identity_after = after.get("identity_id")

print("--- 1. identity_id (alias → subject) ---")
if identity_before != identity_after:
    print(f"FAIL: identity_id changed ({identity_before!r} → {identity_after!r})", file=sys.stderr)
    sys.exit(1)
print(f"PASS: identity_id stable ({identity_before})")

print("--- 2. existing binding_id unchanged ---")
failed = False
for bid, row in sorted(b_before.items()):
  if bid not in b_after:
    failed = True
    print(f"FAIL: binding {bid} ({row.get('network')}) disappeared — graph recreated?", file=sys.stderr)
    continue
  after_row = b_after[bid]
  if after_row.get("network") != row.get("network"):
    failed = True
    print(f"FAIL: binding {bid} network changed", file=sys.stderr)

if failed:
  print("\nV3A COMPARE: FAIL — anti-case: B14/B15 → B17/B18/B19 (recreated graph)", file=sys.stderr)
  sys.exit(1)

for bid, row in sorted(b_before.items()):
  print(f"PASS: B{bid} {row.get('network')} unchanged")

print("--- 3. new bindings additive only ---")
new_ids = sorted(set(b_after) - set(b_before))
removed = sorted(set(b_before) - set(b_after))
if removed:
  print(f"FAIL: bindings removed: {removed}", file=sys.stderr)
  sys.exit(1)
if not new_ids:
  print("NOTE: no new bindings (ok if drill had no mutation)")
else:
  for bid in new_ids:
    print(f"PASS: B{bid} {b_after[bid].get('network')} additive")

print("--- 4. not address lookup ---")
for label, payload in ("BEFORE", before), ("AFTER", after):
  if "address" in payload:
    print(f"FAIL {label}: root address key present", file=sys.stderr)
    sys.exit(1)
print("PASS: no root address in either capture")

print("\nV3A COMPARE: PASS")
print("Alias continuity > instrument continuity")
print("identity stable · old bindings stable · new bindings additive")
PY
  then
    exit 1
  fi
}

case "$MODE" in
  gate)
    [[ -n "$INPUT_A" ]] || usage
    gate_resolve "$INPUT_A"
    ;;
  compare)
    [[ -n "$INPUT_A" && -n "$INPUT_B" ]] || usage
    compare_resolves "$INPUT_A" "$INPUT_B"
    ;;
  *)
    usage
    ;;
esac
