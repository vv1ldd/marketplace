#!/usr/bin/env bash
# Resolve current registry digests for locked base images and rewrite Dockerfiles.
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
LOCK="$ROOT/docker/base-images.lock.json"

if ! command -v docker >/dev/null 2>&1; then
  echo "docker is required" >&2
  exit 1
fi

if ! command -v python3 >/dev/null 2>&1; then
  echo "python3 is required" >&2
  exit 1
fi

resolve_digest() {
  local image="$1"
  docker buildx imagetools inspect "$image" --format '{{json .Manifest.Digest}}' 2>/dev/null \
    | python3 -c "import sys,json; print(json.load(sys.stdin))"
}

update_lock() {
  python3 - "$LOCK" <<'PY'
import json, subprocess, sys
from datetime import date

lock_path = sys.argv[1]
with open(lock_path) as f:
    lock = json.load(f)

for image in lock["images"]:
    digest = subprocess.check_output(
        ["docker", "buildx", "imagetools", "inspect", image, "--format", "{{json .Manifest.Digest}}"],
        text=True,
    ).strip().strip('"')
    lock["images"][image] = digest

lock["updated_at"] = date.today().isoformat()
with open(lock_path, "w") as f:
    json.dump(lock, f, indent=2)
    f.write("\n")
PY
}

pin_dockerfile() {
  local file="$1"
  python3 - "$file" "$LOCK" <<'PY'
import json, re, sys

dockerfile, lock_path = sys.argv[1], sys.argv[2]
with open(lock_path) as f:
    images = json.load(f)["images"]

text = open(dockerfile).read()
for image, digest in images.items():
    pattern = rf"FROM {re.escape(image)}(@sha256:[a-f0-9]+)?"
    replacement = f"FROM {image}@{digest}"
    text, count = re.subn(pattern, replacement, text)
    if count == 0 and image.split(":")[0] in text:
        print(f"warn: no FROM match for {image} in {dockerfile}", file=sys.stderr)

open(dockerfile, "w").write(text)
PY
}

echo "Resolving digests into $LOCK ..."
update_lock

DOCKERFILES=(
  "$ROOT/Dockerfile"
  "$ROOT/frontend/Dockerfile"
)

for df in "${DOCKERFILES[@]}"; do
  if [[ -f "$df" ]]; then
    echo "Pinning $df"
    pin_dockerfile "$df"
  fi
done

echo "Done. Review git diff and run tests before commit."
