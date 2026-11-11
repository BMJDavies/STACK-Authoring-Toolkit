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
#
# The API's images are amd64-only. On a machine that cannot run them (Linux on
# ARM, such as a Raspberry Pi) this stops with exit 3 and says so: use
# tools/test-question.sh as the inner loop there.

set -euo pipefail
HERE="$(cd "$(dirname "$0")" && pwd)"
# shellcheck source=lib/stackapi.sh
source "$HERE/lib/stackapi.sh"
URL="$STACKAPI_URL"

XML_FILE="${1:-}"
shift || true
if [ -z "$XML_FILE" ] || [ ! -f "$XML_FILE" ]; then
    echo "Usage: $0 <path-to-xml-file> [-- extra api_check.py args]"
    exit 1
fi
# Drop a leading `--` separator if present.
[ "${1:-}" = "--" ] && shift || true

# Ensure the API is up (cheap no-op if already running).
if ! api_up; then
    if ! api_possible; then
        echo "The STACK API's images are amd64-only and cannot run on this machine ($(uname -sm))."
        echo "Use tools/test-question.sh as the inner loop here; it validates in your Moodle."
        exit 3
    fi
    start_api || true
fi

# Refuse to validate against an old or stale validator: its verdicts are wrong.
if ! VERSION_REPORT=$("$HERE/check-stack-version.sh" --validator); then
    echo "$VERSION_REPORT"
    exit 1
fi

exec python3 "$HERE/stackapi/api_check.py" "$XML_FILE" --url "$URL" "$@"
