#!/usr/bin/env bash
#
# Push a draft into the review preview quiz so you can see it as a student.
#
#   tools/push-review.sh <xml-path> [quiz-id]
#
# If quiz-id is omitted, REVIEW_QUIZID from tools/project.conf is used.
# Prints a preview URL when it finishes.

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
# shellcheck source=project.conf
source "$SCRIPT_DIR/project.conf"

XML="${1:-}"
QUIZ="${2:-${REVIEW_QUIZID:-}}"

if [ -z "$XML" ] || [ ! -f "$XML" ]; then
    echo "Usage: $0 <xml-path> [quiz-id]" >&2
    exit 1
fi
if [ -z "$QUIZ" ]; then
    echo "No quiz id given and REVIEW_QUIZID is unset in tools/project.conf." >&2
    exit 1
fi

CPATH="$(docker exec "$LIVE_CONTAINER" mktemp /tmp/ptr_XXXXXX.xml)"
PTR="/tmp/ptr_$$.php"

cleanup() { docker exec "$LIVE_CONTAINER" rm -f "$CPATH" "$PTR" >/dev/null 2>&1 || true; }
trap cleanup EXIT

docker cp "$SCRIPT_DIR/push-to-review.php" "${LIVE_CONTAINER}:${PTR}"
docker cp "$XML" "${LIVE_CONTAINER}:${CPATH}"

docker exec "$LIVE_CONTAINER" php "$PTR" "$CPATH" "$QUIZ" \
    "--categoryid=$REVIEW_CATEGORYID" "--contextid=$REVIEW_CONTEXTID" "--url=$MOODLE_URL"
