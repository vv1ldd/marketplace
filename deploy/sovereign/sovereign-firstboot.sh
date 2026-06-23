#!/usr/bin/env bash
# Runs once on first boot after encrypted autoinstall (systemd oneshot).

set -euo pipefail

MARKER=/etc/sovereign-disk-ready
ENV_FILE=/etc/sovereign/firstboot.env
LOG=/var/log/sovereign-firstboot.log
INSTALL_URL="${SOVEREIGN_INSTALL_URL:-https://raw.githubusercontent.com/vv1ldd/coolify/sovereign/scripts/bootstrap-sovereign-from-git.sh}"

exec >>"$LOG" 2>&1
echo "=== sovereign-firstboot $(date -Iseconds) ==="

[ -f "$MARKER" ] || exit 0

if [ -f "$ENV_FILE" ]; then
    set -a
    # shellcheck disable=SC1090
    source "$ENV_FILE"
    set +a
fi

export SOVEREIGN_RUNTIME_CONVERGE_OWNER="${SOVEREIGN_RUNTIME_CONVERGE_OWNER:-true}"
export SOVEREIGN_DISK_ENCRYPT="${SOVEREIGN_DISK_ENCRYPT:-true}"
export SOVEREIGN_ASSUME_YES="${SOVEREIGN_ASSUME_YES:-true}"

curl -fsSL "$INSTALL_URL" -o /tmp/bootstrap-sovereign-from-git.sh
bash /tmp/bootstrap-sovereign-from-git.sh

rm -f "$MARKER"
systemctl disable sovereign-firstboot.service 2>/dev/null || true

echo "=== sovereign-firstboot complete ==="
