#!/bin/bash
# Fast question validator via the standalone STACK REST API (no Moodle import).
#
# Usage: ./tools/api-check.sh path/to/questions.xml [-- extra api_check.py args]
#
# Mirrors tools/test-question.sh's interface (path arg, exit 0=pass/1=fail) but
# POSTs each question's Moodle-XML to the /test route instead of importing into
# a Moodle DB. Brings the stackapi + goemaxima containers up if they're not
# already running. See tools/stackapi/ for the compose file and config override.
#
# NOTE: this is the fast authoring-loop pre-check, not the final word. Keep
# tools/test-question.sh (real Moodle import) as the gate before publishing.

set -euo pipefail
HERE="$(cd "$(dirname "$0")" && pwd)"
COMPOSE="$HERE/stackapi/docker-compose.yml"
URL="http://localhost:3080"

XML_FILE="${1:-}"
shift || true
if [ -z "$XML_FILE" ] || [ ! -f "$XML_FILE" ]; then
    echo "Usage: $0 <path-to-xml-file> [-- extra api_check.py args]"
    exit 1
fi
# Drop a leading `--` separator if present.
[ "${1:-}" = "--" ] && shift || true

# Ensure the API is up (cheap no-op if already running).
if ! curl -s -o /dev/null -m 3 "$URL/render" -X POST \
        -H 'Content-Type: application/json' \
        -d '{"questionDefinition":"<quiz><question type=\"stack\"></question></quiz>"}'; then
    echo "Starting stackapi containers..."
    docker compose -f "$COMPOSE" -p stackapi up -d >/dev/null
    # Wait for the API to answer.
    for i in $(seq 1 30); do
        curl -s -o /dev/null -m 3 "$URL/render" -X POST \
            -H 'Content-Type: application/json' \
            -d '{"questionDefinition":"<quiz><question type=\"stack\"></question></quiz>"}' && break
        sleep 2
    done
fi

# Refuse to validate against an old or stale validator: its verdicts are wrong.
if ! VERSION_REPORT=$("$HERE/check-stack-version.sh" --validator); then
    echo "$VERSION_REPORT"
    exit 1
fi

exec python3 "$HERE/stackapi/api_check.py" "$XML_FILE" --url "$URL" "$@"
