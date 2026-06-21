#!/usr/bin/env bash

set -euo pipefail

REPO_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
RUNTIME_DIR="${MEANLY_TUNNEL_RUNTIME_DIR:-${REPO_ROOT}/.cloudflared}"
STATE_FILE="${MEANLY_BETA_NOTIFY_STATE:-${RUNTIME_DIR}/beta-notify.state}"
ENV_FILE="${MEANLY_BETA_NOTIFY_ENV:-${REPO_ROOT}/scripts/.beta-notify.env}"

DEV_PORT="${MEANLY_DEV_PORT:-3001}"
TUNNEL_HOST="${MEANLY_DEV_TUNNEL_HOST:-meanly.one}"
SIMPLE_L1_PORT="${MEANLY_SIMPLE_L1_PORT:-3000}"
INTERVAL="${MEANLY_BETA_NOTIFY_INTERVAL:-15}"
STABLE_CHECKS="${MEANLY_BETA_NOTIFY_STABLE_CHECKS:-2}"
CHECK_PUBLIC="${MEANLY_BETA_NOTIFY_PUBLIC:-1}"

load_config() {
    if [ -f "$ENV_FILE" ]; then
        # shellcheck disable=SC1090
        set -a
        source "$ENV_FILE"
        set +a
    fi

    DEV_PORT="${MEANLY_DEV_PORT:-$DEV_PORT}"
    TUNNEL_HOST="${MEANLY_DEV_TUNNEL_HOST:-$TUNNEL_HOST}"
    SIMPLE_L1_PORT="${MEANLY_SIMPLE_L1_PORT:-$SIMPLE_L1_PORT}"
    INTERVAL="${MEANLY_BETA_NOTIFY_INTERVAL:-$INTERVAL}"
    STABLE_CHECKS="${MEANLY_BETA_NOTIFY_STABLE_CHECKS:-$STABLE_CHECKS}"
    CHECK_PUBLIC="${MEANLY_BETA_NOTIFY_PUBLIC:-$CHECK_PUBLIC}"
}

require_telegram_config() {
    if [ -z "${MEANLY_BETA_TELEGRAM_BOT_TOKEN:-}" ] || [ -z "${MEANLY_BETA_TELEGRAM_CHAT_ID:-}" ]; then
        cat >&2 <<EOF
Missing Telegram config.

Set MEANLY_BETA_TELEGRAM_BOT_TOKEN and MEANLY_BETA_TELEGRAM_CHAT_ID, or copy:
  cp scripts/.beta-notify.env.example scripts/.beta-notify.env

Then fill token + chat id. See scripts/.beta-notify.env.example for setup steps.
EOF
        exit 1
    fi
}

curl_ok() {
    curl -fsS --max-time 8 "$1" >/dev/null 2>&1
}

check_front() {
    curl_ok "http://127.0.0.1:${DEV_PORT}/"
}

check_backend() {
    curl_ok "http://127.0.0.1:${DEV_PORT}/backend/healthcheck"
}

check_simple_l1() {
    curl_ok "http://127.0.0.1:${SIMPLE_L1_PORT}/manifest.webmanifest"
}

check_public() {
    if [ "$CHECK_PUBLIC" != "1" ]; then
        return 0
    fi
    curl_ok "https://${TUNNEL_HOST}/"
}

readiness_report() {
    local front backend l1 public tunnel
    front="fail"
    backend="fail"
    l1="fail"
    public="skip"
    tunnel="fail"

    check_front && front="ok"
    check_backend && backend="ok"
    check_simple_l1 && l1="ok"
    if [ "$CHECK_PUBLIC" = "1" ]; then
        check_public && public="ok" || public="fail"
    fi

    if [ "$front" = "ok" ] && [ "$backend" = "ok" ] && [ "$l1" = "ok" ]; then
        if [ "$CHECK_PUBLIC" != "1" ] || [ "$public" = "ok" ]; then
            tunnel="ok"
        fi
    fi

    printf 'front=%s backend=%s simple_l1=%s public=%s ready=%s\n' \
        "$front" "$backend" "$l1" "$public" "$tunnel"
}

is_ready() {
    local report ready
    report="$(readiness_report)"
    ready="$(printf '%s\n' "$report" | sed -n 's/^ready=//p')"
    [ "$ready" = "ok" ]
}

state_get() {
    if [ -f "$STATE_FILE" ]; then
        cat "$STATE_FILE"
    else
        printf 'down'
    fi
}

state_set() {
    mkdir -p "$(dirname "$STATE_FILE")"
    printf '%s' "$1" > "$STATE_FILE"
}

telegram_send() {
    local text="$1"
    curl -fsS --max-time 15 \
        -X POST "https://api.telegram.org/bot${MEANLY_BETA_TELEGRAM_BOT_TOKEN}/sendMessage" \
        --data-urlencode "chat_id=${MEANLY_BETA_TELEGRAM_CHAT_ID}" \
        --data-urlencode "parse_mode=HTML" \
        --data-urlencode "disable_web_page_preview=true" \
        --data-urlencode "text=${text}" \
        >/dev/null
}

build_up_message() {
    local profile="${MEANLY_DEV_PROFILE:-global}"
    local now
    now="$(date '+%Y-%m-%d %H:%M %Z')"

    cat <<EOF
🟢 <b>Meanly beta dev online</b>

Профиль: <code>${profile}</code>
Storefront: <a href="https://${TUNNEL_HOST}/">https://${TUNNEL_HOST}/</a>
Ops: <a href="https://${TUNNEL_HOST}/ops">https://${TUNNEL_HOST}/ops</a>

Front ✅ · Backend ✅ · Simple L1 ✅ · Tunnel ✅
Открыто для тестов · ${now}
EOF
}

build_down_message() {
    local report="$1"
    local now
    now="$(date '+%Y-%m-%d %H:%M %Z')"

    cat <<EOF
🔴 <b>Meanly beta dev offline</b>

Front: $(printf '%s' "$report" | sed -n 's/^front=//p')
Backend: $(printf '%s' "$report" | sed -n 's/^backend=//p')
Simple L1: $(printf '%s' "$report" | sed -n 's/^simple_l1=//p')
Public: $(printf '%s' "$report" | sed -n 's/^public=//p')

${now}
EOF
}

cmd_check() {
    load_config
    readiness_report
}

cmd_test() {
    load_config
    require_telegram_config
    telegram_send "🧪 <b>Meanly beta notify test</b>

If you see this, the bot can post to the beta group."
    echo "Test message sent."
}

cmd_notify_up() {
    load_config
    require_telegram_config
    telegram_send "$(build_up_message)"
    state_set "up"
    echo "Notified: beta dev online."
}

cmd_notify_down() {
    load_config
    require_telegram_config
    local report
    report="$(readiness_report)"
    telegram_send "$(build_down_message "$report")"
    state_set "down"
    echo "Notified: beta dev offline."
}

cmd_watch() {
    load_config
    require_telegram_config

    local stable=0
    local last_state
    last_state="$(state_get)"

    echo "Watching beta dev readiness every ${INTERVAL}s (stable checks: ${STABLE_CHECKS})."
    echo "Host: https://${TUNNEL_HOST}/ · state file: ${STATE_FILE}"
    echo "Press Ctrl+C to stop (does not send offline unless health fails)."

    while true; do
        if is_ready; then
            stable=$((stable + 1))
            if [ "$stable" -ge "$STABLE_CHECKS" ] && [ "$last_state" != "up" ]; then
                telegram_send "$(build_up_message)"
                state_set "up"
                last_state="up"
                echo "[$(date '+%H:%M:%S')] Notified: online"
            fi
        else
            if [ "$last_state" = "up" ] && [ "$stable" -ge "$STABLE_CHECKS" ]; then
                local report
                report="$(readiness_report)"
                telegram_send "$(build_down_message "$report")"
                state_set "down"
                last_state="down"
                echo "[$(date '+%H:%M:%S')] Notified: offline"
            fi
            stable=0
        fi

        sleep "$INTERVAL"
    done
}

usage() {
    cat <<EOF
Notify beta Telegram group when local dev tunnel is ready for testing.

Usage: $(basename "$0") <command>

Commands:
  check       Print local health (front/backend/simple_l1/public)
  test        Send a one-off test message to the beta group
  notify-up   Force "online" message
  notify-down Force "offline" message
  watch       Poll health and notify on up/down transitions

Config:
  ${ENV_FILE}
  MEANLY_BETA_TELEGRAM_BOT_TOKEN
  MEANLY_BETA_TELEGRAM_CHAT_ID

Typical workflow (second terminal):
  cp scripts/.beta-notify.env.example scripts/.beta-notify.env
  # fill token + chat id
  ./scripts/dev-tunnel-notify.sh test
  ./scripts/dev-tunnel-notify.sh watch

Or auto-start with tunnel:
  MEANLY_BETA_NOTIFY=1 ./scripts/dev-tunnel.sh run
EOF
}

case "${1:-}" in
    check) cmd_check ;;
    test) cmd_test ;;
    notify-up) cmd_notify_up ;;
    notify-down) cmd_notify_down ;;
    watch) cmd_watch ;;
    *) usage; exit 1 ;;
esac
