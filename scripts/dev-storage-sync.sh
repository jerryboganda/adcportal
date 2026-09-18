#!/usr/bin/env bash
# ============================================================
# Bidirectional realtime-ish storage sync (localhost <-> prod).
#
# Syncs ONLY user-facing file trees — uploads/ and storage/app/ —
# between this laptop and /opt/adc-portal-data on the VPS, using
# tar streams over the existing SSH key. Never touches
# storage/framework, storage/logs, vendor, or code.
#
#   start    seed a full sync, then run a 2s delta loop (background)
#   stop     stop the loop
#   status   show loop state
#   full     one full pull + push pass (repairs drift, prod canonical)
#
# Design note: the merge is STATELESS and deterministic — prod is
# canonical for any same-file disagreement (different size), so a
# transient SSH hiccup can never wedge the two sides into permanent
# divergence; the next good tick heals it. New files sync in both
# directions. Use the `push` command to force local edits of EXISTING
# files up to prod (the loop would resolve them to prod's copy).
# ============================================================
set -euo pipefail
cd "$(dirname "$0")/.."

SSH_HOST=vps
REMOTE_ROOT=/opt/adc-portal-data
REMOTE_DIRS="uploads storage/app"
STATE_DIR=storage/logs/deploy
PID_FILE=$STATE_DIR/dev-storage-sync.pid
LOG_FILE=$STATE_DIR/dev-storage-sync.log
INTERVAL=2
mkdir -p "$STATE_DIR"

ssh_quiet() { ssh -o ConnectTimeout=10 -o BatchMode=yes "$SSH_HOST" "$@"; }

is_up() { [ -f "$PID_FILE" ] && kill -0 "$(cat "$PID_FILE")" 2>/dev/null; }

manifest_local()  { find $REMOTE_DIRS -type f -printf '%p\t%s\n' 2>/dev/null | sort; }
manifest_remote() { ssh_quiet "cd $REMOTE_ROOT && find $REMOTE_DIRS -type f -printf '%p\\t%s\\n' 2>/dev/null | sort"; }

push_files() {
  if [ -z "$1" ]; then return 0; fi
  printf '%s\n' "$1" | tar czf - -T - 2>/dev/null | ssh_quiet \
    "cd $REMOTE_ROOT && tar xzf - && chown -R 33:33 $REMOTE_DIRS" || true
}

pull_files() {
  if [ -z "$1" ]; then return 0; fi
  printf '%s\n' "$1" | ssh_quiet "cd $REMOTE_ROOT && tar czf - -T -" | tar xzf - 2>>"$LOG_FILE" || true
}

full_pull() { ssh_quiet "cd $REMOTE_ROOT && tar czf - $REMOTE_DIRS" | tar xzf - 2>>"$LOG_FILE" || true; }

full_push() {
  tar czf - $REMOTE_DIRS 2>/dev/null | ssh_quiet \
    "cd $REMOTE_ROOT && tar xzf - && chown -R 33:33 $REMOTE_DIRS" || true
}

sync_once() {
  local remote_m local_m push_list pull_list
  # A failed/empty remote manifest must SKIP the tick — otherwise the
  # merge would see "remote has nothing" and mass-push local files.
  remote_m=$(manifest_remote) || return 0
  [ -z "$remote_m" ] && return 0
  local_m=$(manifest_local)

  # New files: push local-only, pull remote-only.
  push_list=$(comm -23 <(printf '%s\n' "$local_m") <(printf '%s\n' "$remote_m") | cut -f1)
  pull_list=$(comm -13 <(printf '%s\n' "$local_m") <(printf '%s\n' "$remote_m") | cut -f1)

  # Same path, different size => PROD IS CANONICAL: pull. Deterministic
  # regardless of which side changed, so hiccups always self-heal.
  pull_list="$pull_list"$'\n'"$(join -t "$(printf '\t')" -j 1 \
      <(printf '%s\n' "$local_m") <(printf '%s\n' "$remote_m") \
    | awk -F'\t' '$2 != $4 { print $1 }')"

  push_files "$(printf '%s\n' "$push_list" | sed '/^$/d' | sort -u)"
  pull_files "$(printf '%s\n' "$pull_list" | sed '/^$/d' | sort -u)"
}

run_loop() {
  echo "$(date '+%F %T') storage sync loop started (interval ${INTERVAL}s, canonical-prod mode)" >>"$LOG_FILE"
  while true; do
    sync_once || echo "$(date '+%F %T') tick error: $?" >>"$LOG_FILE"
    sleep "$INTERVAL"
  done
}

start() {
  if is_up; then
    echo "storage sync already running (pid $(cat "$PID_FILE"))"
    return 0
  fi
  rm -f "$PID_FILE"
  echo "seeding full sync (prod is canonical)..."
  full_pull
  full_push
  run_loop >"$LOG_FILE" 2>&1 &
  echo $! >"$PID_FILE"
  sleep 1
  if is_up; then
    echo "storage sync UP (pid $(cat "$PID_FILE")): $REMOTE_DIRS <-> vps:$REMOTE_ROOT every ${INTERVAL}s"
  else
    echo "storage sync FAILED to start; log:" >&2
    tail -5 "$LOG_FILE" >&2
    exit 1
  fi
}

stop() {
  if is_up; then
    kill "$(cat "$PID_FILE")" 2>/dev/null || true
    rm -f "$PID_FILE"
    echo "storage sync stopped"
  else
    echo "storage sync not running"
    rm -f "$PID_FILE"
  fi
}

status() {
  if is_up; then
    echo "storage sync RUNNING (pid $(cat "$PID_FILE"))"
  else
    echo "storage sync STOPPED"
  fi
}

case "${1:-status}" in
  start) start ;;
  stop) stop ;;
  status) status ;;
  full) full_pull; full_push; echo "full sync done (prod canonical on pull)" ;;
  push) full_push; echo "local -> prod force-push done (existing-file edits included)" ;;
  once) sync_once; echo "once done" ;;
  *) echo "usage: $0 {start|stop|status|full|push|once}"; exit 1 ;;
esac
