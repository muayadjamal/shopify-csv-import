#!/usr/bin/env bash
# Records the app walkthrough to demo.mp4.
# Prereqs: app served at http://localhost:8000 with a running queue worker,
#          plus Node, Playwright (npm i -D playwright && npx playwright install chromium) and ffmpeg.
set -euo pipefail
cd "$(dirname "$0")/.."
webm="$(node scripts/record-demo.mjs | tail -1)"
ffmpeg -y -i "$webm" -r 30 -vf "scale=1920:1080:flags=lanczos,format=yuv420p" \
  -c:v libx264 -preset slow -crf 18 -movflags +faststart demo.mp4
rm -f "$webm"
echo "Wrote demo.mp4"
