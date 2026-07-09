#!/bin/zsh
# Mon/Wed/Fri noon publish of APPROVED Instagram drafts only.
# Publishes nothing unless a human approved a draft via approve_ig.py.
# Installed as launchd agent com.twins.ig-publish (see deploy/).
set -u
ENGINE_DIR="$(cd "$(dirname "$0")/.." && pwd)"
cd "$ENGINE_DIR"
LOG="$ENGINE_DIR/logs/ig_publish.log"
echo "=== $(date '+%Y-%m-%d %H:%M:%S') publish run ===" >> "$LOG"
OUT=$("$ENGINE_DIR/.venv/bin/python" scripts/publish_ig.py --live 2>&1)
STATUS=$?
echo "$OUT" >> "$LOG"
PUBLISHED=$(echo "$OUT" | grep -oE "'published': [0-9]+" | grep -oE "[0-9]+" || echo 0)
if [ "$STATUS" -ne 0 ]; then
  /usr/bin/osascript -e "display notification \"Instagram publish run FAILED - check logs/ig_publish.log\" with title \"Twins IG\"" >> "$LOG" 2>&1
elif [ "$PUBLISHED" != "0" ]; then
  /usr/bin/osascript -e "display notification \"Published $PUBLISHED Instagram post(s)\" with title \"Twins IG\"" >> "$LOG" 2>&1
fi
