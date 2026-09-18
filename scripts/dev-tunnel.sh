#!/usr/bin/env bash
# ============================================================
# Local dev <-> production realtime link.
# Opens an SSH tunnel from this laptop to the VPS loopback
# listeners of the shared platform services:
#   localhost:15433 -> vps 127.0.0.1:15432  (platform-postgres)
#   localhost:16380 -> vps 127.0.0.1:16379  (platform-redis)
# Usage: scripts/dev-tunnel.sh start|stop|status|restart
# ============================================================
set -euo pipefail
cd "$(dirname "$0")/.."

SSH_HOST=vps
LOCAL_PG_PORT=15433
VPS_PG_TARGET=127.0.0.1:15432
LOCAL_RD_PORT=16380
VPS_RD_TARGET=127.0.0.1:16379
PID_FILE=storage/logs/deploy/dev-tunnel.pid
LOG_FILE=storage/logs/deploy/dev-tunnel.log

mkdir -p "$(dirname "$PID_FILE")"

is_up() { [ -f "$PID_FILE" ] && kill -0 "$(cat "$PID_FILE")" 2>/dev/null; }

start() {
  if is_up; then
    echo "tunnel already running (pid $(cat "$PID_FILE"))"
    return 0
  fi
  rm -f "$PID_FILE"
  ssh -N \
    -L "${LOCAL_PG_PORT}:${VPS_PG_TARGET}" \
    -L "${LOCAL_RD_PORT}:${VPS_RD_TARGET}" \
    -o ExitOnForwardFailure=yes \
    -o ServerAliveInterval=15 \
    -o ServerAliveCountMax=4 \
    -o ConnectTimeout=10 \
    "$SSH_HOST" >"$LOG_FILE" 2>&1 &
  echo $! >"$PID_FILE"
  sleep 1
  if is_up; then
    echo "tunnel UP (pid $(cat "$PID_FILE")): localhost:${LOCAL_PG_PORT} -> platform-postgres, localhost:${LOCAL_RD_PORT} -> platform-redis"
  else
    echo "tunnel FAILED to start; last log lines:" >&2
    tail -5 "$LOG_FILE" >&2
    exit 1
  fi
}

stop() {
  if is_up; then
    kill "$(cat "$PID_FILE")" 2>/dev/null || true
    rm -f "$PID_FILE"
    echo "tunnel stopped"
  else
    echo "tunnel not running"
    rm -f "$PID_FILE"
  fi
}

status() {
  if is_up; then
    echo "tunnel RUNNING (pid $(cat "$PID_FILE")) -> localhost:${LOCAL_PG_PORT} (pg), localhost:${LOCAL_RD_PORT} (redis)"
  else
    echo "tunnel STOPPED"
  fi
}

case "${1:-status}" in
  start) start ;;
  stop) stop ;;
  status) status ;;
  restart) stop; start ;;
  *) echo "usage: $0 {start|stop|status|restart}"; exit 1 ;;
esac
