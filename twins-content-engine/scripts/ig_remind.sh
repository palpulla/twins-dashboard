#!/bin/zsh
# Daily 9:00 reminder: pending approvals, today's noon slot, weekend photo nudge.
# Only notifies when action is needed. Installed as com.twins.ig-remind.
set -u
ENGINE_DIR="$(cd "$(dirname "$0")/.." && pwd)"
cd "$ENGINE_DIR"
LOG="$ENGINE_DIR/logs/ig_remind.log"
echo "=== $(date '+%Y-%m-%d %H:%M:%S') remind run ===" >> "$LOG"

notify() {
  /usr/bin/osascript -e "display notification \"$1\" with title \"Twins IG\"" >> "$LOG" 2>&1
  echo "notify: $1" >> "$LOG"
}

TODAY=$(date +%Y-%m-%d)
DOW=$(date +%u)   # 1=Mon ... 7=Sun

PENDING=$(ls pending/instagram 2>/dev/null | grep -c '\.md$'); PENDING=${PENDING:-0}

# approved posts due today (filename starts with today's date)
DUE_TODAY=$(ls approved/instagram 2>/dev/null | grep -c "^${TODAY}_"); DUE_TODAY=${DUE_TODAY:-0}

# posting days: Mon(1) Wed(3) Fri(5)
if [ "$DOW" = "1" ] || [ "$DOW" = "3" ] || [ "$DOW" = "5" ]; then
  if [ "$DUE_TODAY" != "0" ]; then
    notify "$DUE_TODAY post(s) go out at noon today (FB + IG)."
  elif [ "$PENDING" != "0" ]; then
    notify "No post approved for today's noon slot — $PENDING draft(s) waiting. Approve before 12:00 to make it."
  fi
fi

# standing approval reminder (any day, until cleared)
if [ "$PENDING" != "0" ]; then
  notify "$PENDING Instagram draft(s) await your approval."
fi

# Saturday photo nudge: drafts generate Sunday evening
if [ "$DOW" = "6" ]; then
  PHOTOS=$(ls assets/instagram/inbox 2>/dev/null | grep -ciE '\.(jpg|jpeg|png)$'); PHOTOS=${PHOTOS:-0}
  if [ "$PHOTOS" = "0" ]; then
    notify "No job photos in IG Photos Inbox — drop some before Sunday 6pm or next week's posts go AI-only (unpublishable)."
  fi
fi
