#!/bin/zsh
# Sunday-evening Instagram draft generation + approval reminder.
# Installed as launchd agent com.twins.ig-generate (see deploy/).
set -u
ENGINE_DIR="$(cd "$(dirname "$0")/.." && pwd)"
cd "$ENGINE_DIR"
LOG="$ENGINE_DIR/logs/ig_generate.log"
echo "=== $(date '+%Y-%m-%d %H:%M:%S') generate run ===" >> "$LOG"
"$ENGINE_DIR/.venv/bin/python" scripts/generate_ig_week.py >> "$LOG" 2>&1
STATUS=$?
# ls on the dir (not a glob) so zsh's no-match error can't fire under set -u
PENDING=$(ls pending/instagram 2>/dev/null | grep -c '\.md$'); PENDING=${PENDING:-0}
HELD=$(ls pending/instagram/held 2>/dev/null | grep -c '\.md$'); HELD=${HELD:-0}
if [ "$STATUS" -eq 0 ]; then
  MSG="$PENDING Instagram drafts await your approval"
  [ "$HELD" != "0" ] && MSG="$MSG ($HELD held)"
else
  MSG="Instagram draft generation FAILED - check logs/ig_generate.log"
fi
/usr/bin/osascript -e "display notification \"$MSG\" with title \"Twins IG\"" >> "$LOG" 2>&1
echo "notify: $MSG" >> "$LOG"
