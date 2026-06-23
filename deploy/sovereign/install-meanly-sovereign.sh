#!/usr/bin/env bash
# Meanly wrapper — delegates to vv1ldd/coolify sovereign (git install).
#
# Prefer running coolify directly:
#   git clone --depth 1 -b sovereign https://github.com/vv1ldd/coolify.git /tmp/coolify-sovereign
#   bash /tmp/coolify-sovereign/scripts/install-sovereign.sh

set -euo pipefail

export SOVEREIGN_RUNTIME_CONVERGE_OWNER="${SOVEREIGN_RUNTIME_CONVERGE_OWNER:-true}"
export SOVEREIGN_REPOSITORY="${SOVEREIGN_REPOSITORY:-vv1ldd/coolify}"
export SOVEREIGN_BRANCH="${SOVEREIGN_BRANCH:-sovereign}"
CLONE_DIR="${SOVEREIGN_GIT_CLONE_DIR:-/tmp/coolify-sovereign}"
BOOTSTRAP_URL="${SOVEREIGN_BOOTSTRAP_URL:-https://raw.githubusercontent.com/${SOVEREIGN_REPOSITORY}/${SOVEREIGN_BRANCH}/scripts/bootstrap-sovereign-from-git.sh}"

if [ -f "${CLONE_DIR}/scripts/install-sovereign.sh" ]; then
    export SOVEREIGN_LOCAL_REPO_PATH="$CLONE_DIR"
    exec bash "${CLONE_DIR}/scripts/install-sovereign.sh"
fi

if command -v git >/dev/null 2>&1; then
    git clone --depth 1 -b "$SOVEREIGN_BRANCH" "https://github.com/${SOVEREIGN_REPOSITORY}.git" "$CLONE_DIR"
    export SOVEREIGN_LOCAL_REPO_PATH="$CLONE_DIR"
    exec bash "${CLONE_DIR}/scripts/install-sovereign.sh"
fi

curl -fsSL "$BOOTSTRAP_URL" -o /tmp/bootstrap-sovereign-from-git.sh
exec bash /tmp/bootstrap-sovereign-from-git.sh
