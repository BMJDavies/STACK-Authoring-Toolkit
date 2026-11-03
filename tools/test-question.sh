#!/bin/bash
# The final gate before publishing. Imports the XML into a real Moodle, runs the
# question variables through Maxima, uses the XML's <deployedseed> values (5
# defaults if a question has none), and runs every embedded
# <qtest> case against every seed.
#
#   tools/test-question.sh path/to/questions.xml [--sandbox] [--lint-only]
#
# Exit 0 = everything passed. Exit 1 = at least one failure.
#
# It first lints the XML against the installed STACK (variable names the
# security map already uses, contrib functions called without their include).
# --lint-only stops there: no import and no CAS, a few seconds a file.
#
# The last line gives the elapsed time. It varies with the file's size, its
# seeds and the machine, so read it rather than relying on a recorded figure.
#
# --sandbox runs against the optional second Moodle stack (SANDBOX_CONTAINER in
# project/project.conf), so a long validation can run while a push-to-live is happening
# on the main stack. STACK's grading is plugin-level and identical across the
# two, so results transfer. Leave the flag off if you only have one stack.

set -uo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
# shellcheck source=lib/project.sh
source "$SCRIPT_DIR/lib/project.sh"
load_project_conf

XML_FILE="${1:-}"
shift 2>/dev/null

USE_SANDBOX=0
LINT_ARGS=()
for arg in "$@"; do
    case "$arg" in
        --sandbox) USE_SANDBOX=1 ;;
        --lint-only) LINT_ARGS=(--lint-only) ;;
    esac
done
START=$SECONDS

if [ -z "$XML_FILE" ] || [ ! -f "$XML_FILE" ]; then
    echo "Usage: $0 <path-to-xml-file> [--sandbox]"
    exit 1
fi

if [ "$USE_SANDBOX" = "1" ]; then
    if [ -z "${SANDBOX_CONTAINER:-}" ]; then
        echo "--sandbox requested but SANDBOX_CONTAINER is empty in $PROJECT_DIR/project.conf" >&2
        exit 1
    fi
    CONTAINER="$SANDBOX_CONTAINER"
    ENV_ARGS=(-e "VALIDATE_CATEGORYID=$SANDBOX_CATEGORYID" -e "VALIDATE_CONTEXTID=$SANDBOX_CONTEXTID")
else
    CONTAINER="$LIVE_CONTAINER"
    ENV_ARGS=(-e "VALIDATE_CATEGORYID=$DEV_CATEGORYID" -e "VALIDATE_CONTEXTID=$DEV_CONTEXTID")
fi
ENV_ARGS+=(-e "MOODLE_ROOT=${MOODLE_ROOT:-/var/www/html}")

# Route-style tests need STACK 4.13.0 or later in this Moodle.
if ! "$SCRIPT_DIR/check-stack-version.sh" --moodle "$CONTAINER"; then
    exit 1
fi

# Make sure the container has the current validator.
docker cp "$SCRIPT_DIR/validate-questions.php" "$CONTAINER:/opt/validate-questions.php" >/dev/null

# Unique stage filename so two validations at once cannot clobber each other.
STAGE="/tmp/validate-$$-$(date +%s).xml"
docker cp "$XML_FILE" "$CONTAINER:$STAGE"
docker exec "${ENV_ARGS[@]}" "$CONTAINER" php /opt/validate-questions.php "$STAGE" ${LINT_ARGS[@]+"${LINT_ARGS[@]}"}
RC=$?
docker exec "$CONTAINER" rm -f "$STAGE"
echo "Elapsed: $((SECONDS - START)) s for $(grep -c '<question type="stack"' "$XML_FILE") STACK question(s)."
exit $RC
