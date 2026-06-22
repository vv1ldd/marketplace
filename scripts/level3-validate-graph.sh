#!/usr/bin/env bash
# Validate settlement graph snapshots from level3-evidence-capture output.
#
# Usage:
#   ./scripts/level3-validate-graph.sh gate /tmp/multi-rail-before.log
#   ./scripts/level3-validate-graph.sh gate bindings.json
#   ./scripts/level3-validate-graph.sh compare /tmp/multi-rail-before.log /tmp/multi-rail-after.log
#
# Multi-rail v1 expects three managed EVM bindings (override with LEVEL3_EXPECT_NETWORKS).
# Multi-rail v2: set LEVEL3_REQUIRE_OBSERVATION=1 to require and compare U (API USDC) per rail.
set -euo pipefail

MODE="${1:-}"
INPUT_A="${2:-}"
INPUT_B="${3:-}"
EXPECTED_NETWORKS="${LEVEL3_EXPECT_NETWORKS:-base,ethereum,polygon}"
REQUIRE_OBSERVATION="${LEVEL3_REQUIRE_OBSERVATION:-0}"

usage() {
  echo "Usage: $0 gate <capture-log|bindings.json>" >&2
  echo "       $0 compare <before-log|json> <after-log|json>" >&2
  exit 1
}

extract_anchor_json() {
  local file="$1"
  python3 - "$file" <<'PY'
import json
import re
import sys

path = sys.argv[1]
text = open(path, encoding="utf-8").read()

match = re.search(
    r"identity anchor\)[^\n]*\n(\{[^}]+\})",
    text,
    re.S,
)
if not match:
    print(f"Could not extract identity anchor from {path}", file=sys.stderr)
    sys.exit(1)
obj = json.loads(match.group(1))
print(json.dumps({
    "entity_l1_address": obj.get("entity_l1_address"),
    "vault_id": obj.get("vault_id"),
}, indent=2))
PY
}

extract_bindings_json() {
  local file="$1"
  if [[ ! -f "$file" ]]; then
    echo "Missing file: $file" >&2
    return 1
  fi
  python3 - "$file" <<'PY'
import json
import re
import sys

path = sys.argv[1]
text = open(path, encoding="utf-8").read().strip()

try:
    data = json.loads(text)
    if isinstance(data, list):
        print(json.dumps(data, indent=2))
        sys.exit(0)
except json.JSONDecodeError:
    pass

match = re.search(
    r"--- BINDINGS\[\][^\n]*\n(\[\s*\{.*?\}\s*\])\s*(?:\n---|\n=== END)",
    text,
    re.S,
)
if not match:
    print(f"Could not extract bindings[] from {path}", file=sys.stderr)
    sys.exit(1)
print(match.group(1))
PY
}

gate_graph() {
  local file="$1"
  local bindings_json expected_json count
  bindings_json="$(extract_bindings_json "$file")"
  IFS=',' read -r -a expected <<<"$EXPECTED_NETWORKS"
  count="${#expected[@]}"

  expected_json="$(printf '%s\n' "${expected[@]}" | jq -R . | jq -s 'sort')"

  echo "== Settlement graph gate =="
  echo "Source: $file"
  if anchor_json="$(extract_anchor_json "$file" 2>/dev/null)"; then
    echo ""
    echo "Identity anchor:"
    echo "$anchor_json" | jq .
  fi
  echo "Expected networks (${count}): ${expected[*]}"
  echo ""
  echo "$bindings_json" | jq .

  if ! echo "$bindings_json" | jq -e --argjson count "$count" --argjson expected "$expected_json" '
    length == $count
    and (map(.network) | sort) == $expected
    and (map(.binding_source) | unique) == ["managed"]
    and (map(.verification_method) | unique) == ["vault_key"]
    and (map(.verification_state) | unique) == ["verified"]
    and ([.[].network] | unique | length) == $count
    and ([.[].binding_id] | unique | length) == $count
    and ([.[].address] | unique | length) == $count
  ' >/dev/null; then
    echo ""
    echo "GRAPH GATE: FAIL" >&2
    echo "Require: ${count} rails, all managed/vault_key/verified, unique network/binding_id/address." >&2
    exit 1
  fi

  if [[ "$REQUIRE_OBSERVATION" == "1" ]]; then
    if ! echo "$bindings_json" | jq -e --argjson count "$count" '
      [.[] | select((.observation.api.amount // "") != "" and (.observation.api.amount | tonumber? // 0) > 0)]
      | length == $count
    ' >/dev/null; then
      echo ""
      echo "GRAPH + OBSERVATION GATE: FAIL" >&2
      echo "LEVEL3_REQUIRE_OBSERVATION=1: each rail needs observation.api.amount > 0" >&2
      exit 1
    fi

    echo ""
    echo "--- Pre-drill checklist (v2) ---"
    if anchor_json="$(extract_anchor_json "$file" 2>/dev/null)"; then
      echo "A ✓  $(echo "$anchor_json" | jq -r '.entity_l1_address')"
      echo "V ✓  $(echo "$anchor_json" | jq -r '.vault_id')"
    fi
    echo "$bindings_json" | jq -r '.[] | "\(.network):\n  B\(.binding_id) ✓\n  S ✓  \(.address)\n  U ✓  \(.observation.api.display_amount // .observation.api.amount)"'

    echo ""
    echo "GRAPH + OBSERVATION GATE: PASS"
    echo "  question: same economic object through same settlement path?"
    echo "  graph + observation: VALID — proceed to surface destruction"
  else
    echo ""
    echo "GRAPH GATE: PASS"
    echo "  question: same settlement graph?"
    echo "  identity: captured"
    echo "  vault: captured"
    echo "  rails: ${expected[*]}"
    echo "  bindings: ${count}"
    echo "  managed / vault_key / verified: ok"
    echo "  graph: VALID"
  fi
}

compare_anchors() {
  local before_file="$1"
  local after_file="$2"
  local before_anchor after_anchor

  before_anchor="$(extract_anchor_json "$before_file")"
  after_anchor="$(extract_anchor_json "$after_file")"

  echo "--- 1. Identity (A) ---"
  local a_before a_after v_before v_after
  a_before=$(echo "$before_anchor" | jq -r '.entity_l1_address')
  a_after=$(echo "$after_anchor" | jq -r '.entity_l1_address')
  if [[ "$a_before" != "$a_after" ]]; then
    echo "FAIL: entity_l1_address changed ($a_before → $a_after)" >&2
    return 1
  fi
  echo "PASS: A == A' ($a_before)"

  echo "--- 2. Vault (V) ---"
  v_before=$(echo "$before_anchor" | jq -r '.vault_id')
  v_after=$(echo "$after_anchor" | jq -r '.vault_id')
  if [[ "$v_before" != "$v_after" ]]; then
    echo "FAIL: vault_id changed ($v_before → $v_after)" >&2
    return 1
  fi
  echo "PASS: V == V' ($v_before)"
}

compare_graphs() {
  local before_file="$1"
  local after_file="$2"
  local before_json after_json

  before_json="$(extract_bindings_json "$before_file")"
  after_json="$(extract_bindings_json "$after_file")"

  echo "== Settlement graph compare =="
  echo "BEFORE: $before_file"
  echo "AFTER:  $after_file"
  echo ""

  echo ""
  echo "--- Compare order: A → V → B → S → U (never U-first) ---"
  compare_anchors "$before_file" "$after_file"
  echo "--- 3. Binding objects (B) + 4. Settlement endpoints (S) ---"

  if ! python3 - <<'PY' "$before_json" "$after_json" "$REQUIRE_OBSERVATION"
import json
import os
import sys

before = {row["network"]: row for row in json.loads(sys.argv[1])}
after = {row["network"]: row for row in json.loads(sys.argv[2])}
require_obs = sys.argv[3] == "1"

if set(before) != set(after):
    print("GRAPH COMPARE: FAIL — network set changed", file=sys.stderr)
    print(f"  before: {sorted(before)}", file=sys.stderr)
    print(f"  after:  {sorted(after)}", file=sys.stderr)
    sys.exit(1)

failed = False
for network in sorted(before):
    b = before[network]
    a = after[network]
    checks = [
        ("binding_id", b.get("binding_id"), a.get("binding_id")),
        ("address", b.get("address"), a.get("address")),
        ("binding_source", b.get("binding_source"), a.get("binding_source")),
        ("verification_method", b.get("verification_method"), a.get("verification_method")),
    ]
    for field, old, new in checks:
        if old != new:
            failed = True
            print(
                f"FAIL {network}: {field} changed ({old!r} → {new!r})",
                file=sys.stderr,
            )
            if field == "binding_id" and old != new and b.get("address") == a.get("address"):
                print(
                    f"  anti-pattern: address matched but binding_id recreated on {network}",
                    file=sys.stderr,
                )

if failed:
    print("\nGRAPH COMPARE: FAIL — recover existing truth, not recreate equivalent truth", file=sys.stderr)
    sys.exit(1)

print("Per-rail continuity:")
for network in sorted(before):
    b = before[network]
    print(
        f"  {network}: B={b.get('binding_id')} S={b.get('address')} "
        f"({b.get('binding_source')}/{b.get('verification_method')}) == AFTER"
    )

print("\n--- 5. Economic observation (U) ---")
obs_failed = False
for network in sorted(before):
    b = before[network]
    a = after[network]
    b_api = ((b.get("observation") or {}).get("api") or {})
    a_api = ((a.get("observation") or {}).get("api") or {})
    b_amt = b_api.get("amount")
    a_amt = a_api.get("amount")
    b_chain = ((b.get("observation") or {}).get("chain") or {}).get("balance_raw")
    a_chain = ((a.get("observation") or {}).get("chain") or {}).get("balance_raw")

    if require_obs and not b_amt:
        obs_failed = True
        print(f"FAIL {network}: missing observation.api before capture", file=sys.stderr)
        continue
    if require_obs and not a_amt:
        obs_failed = True
        print(f"FAIL {network}: missing observation.api after capture", file=sys.stderr)
        continue

    if b_amt is not None and a_amt is not None:
        if b_amt != a_amt:
            obs_failed = True
            print(
                f"FAIL {network}: U api amount changed ({b_amt!r} → {a_amt!r})",
                file=sys.stderr,
            )
            if b.get("binding_id") != a.get("binding_id"):
                print(
                    f"  anti-pattern: U may match by coincidence on recreated binding {network}",
                    file=sys.stderr,
                )
        else:
            chain_note = ""
            if b_chain and a_chain:
                chain_note = f" chain={b_chain}"
                if b_chain != a_chain:
                    obs_failed = True
                    print(
                        f"FAIL {network}: U chain balance changed ({b_chain} → {a_chain})",
                        file=sys.stderr,
                    )
            print(f"PASS {network}: U api == U' api ({b_amt}){chain_note}")
    elif not require_obs:
        print(f"SKIP {network}: no observation captured (v1 mode)")

if obs_failed:
    print("\nGRAPH COMPARE: FAIL — observation continuity", file=sys.stderr)
    sys.exit(1)

print("\nGRAPH COMPARE: PASS")
if require_obs:
    print("Multi-rail Level 3 Recovery v2 = PASS")
    print("invariant: identity controls settlement surface (B→S→U through same durable binding)")
    print("U confirms observation path — B/S continuity is primary")
else:
    print("Multi-rail Level 3 Recovery = PASS")
    print("One identity → one vault → many managed settlement instruments → durable recovery")
PY
  then
    exit 1
  fi
}

case "$MODE" in
  gate)
    [[ -n "$INPUT_A" ]] || usage
    gate_graph "$INPUT_A"
    ;;
  compare)
    [[ -n "$INPUT_A" && -n "$INPUT_B" ]] || usage
    gate_graph "$INPUT_A" >/dev/null || true
    gate_graph "$INPUT_A"
    echo ""
    compare_graphs "$INPUT_A" "$INPUT_B"
    ;;
  *)
    usage
    ;;
esac
