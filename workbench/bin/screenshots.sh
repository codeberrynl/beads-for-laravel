#!/usr/bin/env bash
# Regenerates the README screenshots from the demo workspace with headless
# Chrome. Needs `composer demo` and a running `composer serve`.
set -euo pipefail

root="$(cd "$(dirname "$0")/../.." && pwd)"
base="${BEADS_URL:-http://127.0.0.1:8000/beads}"
chrome="${CHROME:-/Applications/Google Chrome.app/Contents/MacOS/Google Chrome}"
out="$root/art/screenshots"
profiles="$(mktemp -d)"
trap 'rm -rf "$profiles"' EXIT

id_of() {
  (cd "$root/workbench/demo" && bd list --all --json 2>/dev/null) \
    | php -r '$t = $argv[1]; foreach (json_decode(stream_get_contents(STDIN), true) as $i) { if ($i["title"] === $t) { echo $i["id"]; } }' "$1"
}

# shot <name> <width> <height> <scale> <scheme: 0 dark, 1 light> <url>
shot() {
  "$chrome" --headless=new --disable-gpu --hide-scrollbars \
    --user-data-dir="$profiles/$1" --window-size="$2,$3" --force-device-scale-factor="$4" \
    --blink-settings=preferredColorScheme="$5" --virtual-time-budget=8000 \
    --screenshot="$out/$1.png" "$6" 2>/dev/null
}

mkdir -p "$out"
shot issues-dark 1440 900 2 0 "$base/issues?peek=$(id_of 'Payment step: support iDEAL')"
shot board-dark 2650 1000 1.5 0 "$base/board"
shot detail-light 1440 900 2 1 "$base/issue/$(id_of 'Implement address autocomplete')"

if command -v optipng > /dev/null; then
  optipng -quiet -o2 "$out"/*.png
fi

echo "Screenshots written to $out"
