#!/usr/bin/env bash
#
# Push an approved XML file into a live course question bank and quiz.
#
#   tools/push-live.sh <xml-path> <quiz-id> [--course=<key>]
#
# <key> is one of the keys defined in COURSES in project/project.conf. If you
# omit --course, DEFAULT_COURSE from project.conf is used.
#
# WHY THE WRAPPER: the obvious approach (copy the XML to a fixed container path
# like /tmp/live.xml, then run the importer) has a race. Two pushes at once
# (two agents, or two terminal windows) clobber each other's file, so one push
# silently imports the OTHER push's questions. The slot count still looks right,
# which is what makes it nasty. This wrapper stamps a unique container path per
# invocation so that cannot happen.

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
# shellcheck source=lib/project.sh
source "$SCRIPT_DIR/lib/project.sh"
load_project_conf

if [ "$#" -lt 2 ]; then
    echo "Usage: $0 <xml-path> <quiz-id> [--course=<key>]" >&2
    echo "Course keys in $PROJECT_DIR/project.conf: $(echo "$COURSES" | tr ' ' '\n' | cut -d: -f1 | tr '\n' ' ')" >&2
    exit 1
fi

XML="$1"
QUIZ="$2"
shift 2

COURSE_KEY="${DEFAULT_COURSE:-}"
for arg in "$@"; do
    case "$arg" in
        --course=*) COURSE_KEY="${arg#--course=}" ;;
    esac
done

if [ -z "$COURSE_KEY" ]; then
    echo "No --course given and DEFAULT_COURSE is unset in $PROJECT_DIR/project.conf." >&2
    exit 1
fi

# Resolve the key against COURSES ("key:courseid:categoryid:contextid:Label").
CATID=""; CTXID=""; LABEL=""
for entry in $COURSES; do
    IFS=':' read -r k _cid catid ctxid label <<< "$entry"
    if [ "$k" = "$COURSE_KEY" ]; then
        CATID="$catid"; CTXID="$ctxid"; LABEL="${label//_/ }"
    fi
done

if [ -z "$CATID" ] || [ -z "$CTXID" ]; then
    echo "Unknown course key '$COURSE_KEY'. Defined keys: $(echo "$COURSES" | tr ' ' '\n' | cut -d: -f1 | tr '\n' ' ')" >&2
    exit 1
fi

if [ ! -f "$XML" ]; then
    echo "File not found: $XML" >&2
    exit 1
fi

# Unique container paths (mktemp reserves the name atomically inside the container).
CPATH="$(docker exec "$LIVE_CONTAINER" mktemp /tmp/ptl_XXXXXX.xml)"
PTL="/tmp/ptl_$$.php"
LIB="/tmp/ptl_$$_lib.php"

cleanup() { docker exec "$LIVE_CONTAINER" rm -f "$CPATH" "$PTL" "$LIB" >/dev/null 2>&1 || true; }
trap cleanup EXIT

# Ship a fresh copy of the importer and its library under unique names too,
# then the XML. The importer finds the library by its own name plus _lib.
docker cp "$SCRIPT_DIR/push-to-live.php" "${LIVE_CONTAINER}:${PTL}"
docker cp "$SCRIPT_DIR/lib/publish.php" "${LIVE_CONTAINER}:${LIB}"
docker cp "$XML" "${LIVE_CONTAINER}:${CPATH}"

docker exec -e "MOODLE_ROOT=${MOODLE_ROOT:-/var/www/html}" "$LIVE_CONTAINER" php "$PTL" "$CPATH" "$QUIZ" \
    "--categoryid=$CATID" "--contextid=$CTXID" "--label=$LABEL" "--url=$MOODLE_URL"
