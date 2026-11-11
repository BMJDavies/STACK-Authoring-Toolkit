#!/bin/bash
# Display check: renders every deployed seed of every STACK question and flags
# defects that grade correctly but read wrongly (style-guide.md, "Sign handling
# in displayed expressions", and the checks listed in
# tools/stackapi/render_sweep.py):
#   - `\\` straight before `{@...@}` (CASText drops a backslash; a column vector
#     shows as `{-5}\{3}`);
#   - 0, 1 or -1 printed as the coefficient of a symbol ("1 v_1", "(-1) e_2");
#   - `+ -` and `- -` in mathematics;
#   - a surd printed as a fractional power ("2^{3/2}"), a raw Maxima list,
#     unprocessed `{@` or `matrix(`;
#   - two seeds with the same question note (a repeated variant).
#
# Usage: ./tools/render-sweep.sh path/to/questions.xml [--api | --moodle] [-- --verbose]
#
# Two routes, with the same checks:
#   --api     the standalone STACK API (tools/stackapi). Its images are
#             amd64-only: native on Intel/AMD, under Rosetta on Apple silicon.
#   --moodle  render in your Moodle (tools/render-sweep-moodle.php): any
#             machine that runs Moodle. Imports the file into the sandbox
#             category, as test-question.sh does.
# With neither, it uses the API if it answers or can be started on this
# machine, and Moodle otherwise. The first line says which route ran.
#
# Exit 0 = no flags, 1 = at least one flag, 2 = rendering failed.
# Neither api-check.sh nor test-question.sh looks at how the mathematics is
# displayed, so run this before hand-off too.

set -euo pipefail
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
HERE="$SCRIPT_DIR"

XML_FILE="${1:-}"
shift || true
if [ -z "$XML_FILE" ] || [ ! -f "$XML_FILE" ]; then
    echo "Usage: $0 <path-to-xml-file> [--api | --moodle] [-- extra render_sweep.py args]"
    exit 2
fi
ROUTE=auto
while [ "$#" -gt 0 ]; do
    case "$1" in
        --api) ROUTE=api; shift ;;
        --moodle) ROUTE=moodle; shift ;;
        --) shift; break ;;
        *) break ;;
    esac
done

# shellcheck source=lib/stackapi.sh
source "$SCRIPT_DIR/lib/stackapi.sh"
URL="$STACKAPI_URL"

if [ "$ROUTE" = auto ]; then
    if api_up; then
        ROUTE=api
    elif api_possible && start_api; then
        ROUTE=api
    else
        ROUTE=moodle
    fi
elif [ "$ROUTE" = api ] && ! api_up && ! api_possible; then
    echo "The STACK API's images are amd64-only and cannot run on this machine ($(uname -sm)); use --moodle." >&2
    exit 3
elif [ "$ROUTE" = api ] && ! api_up && ! start_api; then
    echo "The STACK API is not answering at $URL and could not be started." >&2
    exit 2
fi

if [ "$ROUTE" = api ]; then
    echo "Route: STACK API at $URL"
    exec python3 "$HERE/stackapi/render_sweep.py" "$XML_FILE" --url "$URL" "$@"
fi

# The Moodle route.
# shellcheck source=lib/project.sh
source "$SCRIPT_DIR/lib/project.sh"
load_project_conf
echo "Route: Moodle ($LIVE_CONTAINER), sandbox category $DEV_CATEGORYID"
STAGE="/tmp/sweep-$$-$(date +%s)"
LOCAL_JSON="$(mktemp "${TMPDIR:-/tmp}/render-sweep.XXXXXX")"
trap 'rm -f "$LOCAL_JSON"; docker exec "$LIVE_CONTAINER" rm -f "$STAGE.xml" "$STAGE.json" >/dev/null 2>&1 || true' EXIT
docker cp "$SCRIPT_DIR/render-sweep-moodle.php" "$LIVE_CONTAINER:/opt/render-sweep-moodle.php" >/dev/null
docker cp "$XML_FILE" "$LIVE_CONTAINER:$STAGE.xml" >/dev/null
if ! docker exec -e "VALIDATE_CATEGORYID=$DEV_CATEGORYID" -e "VALIDATE_CONTEXTID=$DEV_CONTEXTID" \
        -e "MOODLE_ROOT=${MOODLE_ROOT:-/var/www/html}" \
        "$LIVE_CONTAINER" php /opt/render-sweep-moodle.php "$STAGE.xml" "$STAGE.json"; then
    echo "Some variants could not be rendered; they are flagged below." >&2
fi
docker cp "$LIVE_CONTAINER:$STAGE.json" "$LOCAL_JSON" >/dev/null || exit 2
python3 "$HERE/stackapi/render_sweep.py" "$XML_FILE" --renders "$LOCAL_JSON" "$@"
