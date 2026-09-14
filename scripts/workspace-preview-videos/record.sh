#!/usr/bin/env bash
# Record chapters of the workspace-preview how-to videos.
#
#   REC_EMAIL=... REC_PASSWORD=... ./record.sh 04          # chapter 4, both languages
#   REC_EMAIL=... REC_PASSWORD=... ./record.sh 02 05 en    # chapters 2 and 5, English only
#   REC_EMAIL=... REC_PASSWORD=... ./record.sh all ar      # every chapter, Arabic only
#
# Records against the DEMO instance (REC_BASE_URL, default http://127.0.0.1:8077).
# Output: recordings/preview/<chapter>.<lang>.mp4 + frames-* to check.
# Then publish with: python3 publish.py <chapter>.<lang> ...
set -uo pipefail
HERE="$(cd "$(dirname "$0")" && pwd)"
ROOT="$(cd "$HERE/../.." && pwd)"
OUT="$ROOT/recordings/preview"
mkdir -p "$OUT" "$HERE/.cache"

: "${REC_EMAIL:?Set REC_EMAIL to a demo-instance admin login}"
: "${REC_PASSWORD:?Set REC_PASSWORD}"

langs=(en ar); chapters=()
for a in "$@"; do
  case "$a" in
    en|ar) langs=("$a") ;;
    all)   chapters=(01 02 03 04 05 06 07) ;;
    *)     chapters+=("$(printf '%02d' "$((10#$a))")") ;;
  esac
done
[ ${#chapters[@]} -gt 0 ] || { echo "usage: record.sh <chapter...|all> [en|ar]"; exit 2; }

status=0
for c in "${chapters[@]}"; do
  for l in "${langs[@]}"; do
    wrapper="$HERE/.cache/ch$c.$l.mjs"
    echo "import build from '../chapters/ch$c.mjs'; export default build('$l');" > "$wrapper"
    # The recorder converts the FIRST webm it finds — clear any left by a failed take.
    rm -rf "$OUT"/.raw-*
    echo "=== chapter $c ($l)  $(date +%T)"
    node "$HERE/recorder/record.mjs" "$wrapper" --project="$ROOT" --out="$OUT" --frames=16 || status=1
  done
done
exit $status
