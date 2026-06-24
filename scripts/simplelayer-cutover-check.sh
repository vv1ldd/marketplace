#!/usr/bin/env bash
set -euo pipefail

TARGET_IP="${SIMPLELAYER_TARGET_IP:-135.106.162.147}"
HOST="${SIMPLELAYER_PROTOCOL_HOST:-pass.simplelayer.one}"

echo "Checking DNS for ${HOST}..."
resolved="$(dig +short "${HOST}" A | head -1 || true)"

if [[ -z "${resolved}" ]]; then
  echo "FAIL: ${HOST} has no A record yet."
  echo "Add: ${HOST}  A  ${TARGET_IP}"
  exit 1
fi

echo "Resolved: ${HOST} -> ${resolved}"

if [[ "${resolved}" != "${TARGET_IP}" ]]; then
  echo "WARN: A record is ${resolved}, expected ${TARGET_IP} (may be proxied — continuing)"
fi

echo "Checking healthcheck..."
if curl -fsS --max-time 15 "https://${HOST}/healthcheck" >/dev/null; then
  echo "OK: https://${HOST}/healthcheck"
else
  echo "FAIL: healthcheck not reachable on https://${HOST}"
  echo "Finish Sovereign env cutover (see deploy/simplelayer-cutover.md) then retry."
  exit 1
fi

echo "Checking connect status..."
curl -fsS --max-time 15 "https://${HOST}/api/sl1e/connect/status" | head -c 200
echo
echo "Simple Layer protocol host looks ready."
