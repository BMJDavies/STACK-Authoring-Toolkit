#!/bin/bash
# Display check via the standalone STACK REST API: renders every deployed seed of
# every STACK question and flags defects that grade correctly but read wrongly
# (style-guide.md, "Sign handling in displayed expressions"):
#   - `\\` straight before `{@...@}` (CASText drops a backslash; a column vector
#     shows as `{-5}\{3}`);
#   - 0, 1 or -1 printed as the coefficient of a symbol ("1 v_1", "(-1) e_2");
#   - `+ -` in mathematics;
#   - two seeds with the same question note (a repeated variant).
#
# Usage: ./tools/render-sweep.sh path/to/questions.xml [-- --verbose]
#
# Exit 0 = no flags, 1 = at least one flag, 2 = the API failed to render.
# Run it alongside tools/api-check.sh before hand-off. Neither api-check.sh nor
# test-question.sh looks at how the mathematics is displayed.

set -euo pipefail
HERE="$(cd "$(dirname "$0")" && pwd)"
COMPOSE="$HERE/stackapi/docker-compose.yml"
URL="http://localhost:3080"

XML_FILE="${1:-}"
shift || true
if [ -z "$XML_FILE" ] || [ ! -f "$XML_FILE" ]; then
    echo "Usage: $0 <path-to-xml-file> [-- extra render_sweep.py args]"
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

exec python3 "$HERE/stackapi/render_sweep.py" "$XML_FILE" --url "$URL" "$@"
