#!/bin/bash
# The final gate before publishing. Imports the XML into a real Moodle, runs the
# question variables through Maxima, uses the XML's <deployedseed> values (5
# defaults if a question has none), and runs every embedded
# <qtest> case against every seed.
#
#   tools/test-question.sh path/to/questions.xml [--sandbox]
#
# Exit 0 = everything passed. Exit 1 = at least one failure.
#
# --sandbox runs against the optional second Moodle stack (SANDBOX_CONTAINER in
# project.conf), so a long validation can run while a push-to-live is happening
# on the main stack. STACK's grading is plugin-level and identical across the
# two, so results transfer. Leave the flag off if you only have one stack.

set -uo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
# shellcheck source=project.conf
source "$SCRIPT_DIR/project.conf"

XML_FILE="${1:-}"
shift 2>/dev/null

USE_SANDBOX=0
for arg in "$@"; do
    case "$arg" in
        --sandbox) USE_SANDBOX=1 ;;
    esac
done

if [ -z "$XML_FILE" ] || [ ! -f "$XML_FILE" ]; then
    echo "Usage: $0 <path-to-xml-file> [--sandbox]"
    exit 1
fi

if [ "$USE_SANDBOX" = "1" ]; then
    if [ -z "${SANDBOX_CONTAINER:-}" ]; then
        echo "--sandbox requested but SANDBOX_CONTAINER is empty in tools/project.conf" >&2
        exit 1
    fi
    CONTAINER="$SANDBOX_CONTAINER"
    ENV_ARGS=(-e "VALIDATE_CATEGORYID=$SANDBOX_CATEGORYID" -e "VALIDATE_CONTEXTID=$SANDBOX_CONTEXTID")
else
    CONTAINER="$LIVE_CONTAINER"
    ENV_ARGS=(-e "VALIDATE_CATEGORYID=$DEV_CATEGORYID" -e "VALIDATE_CONTEXTID=$DEV_CONTEXTID")
fi

# Make sure the container has the current validator.
docker cp "$SCRIPT_DIR/validate-questions.php" "$CONTAINER:/opt/validate-questions.php" >/dev/null

# Unique stage filename so two validations at once cannot clobber each other.
STAGE="/tmp/validate-$$-$(date +%s).xml"
docker cp "$XML_FILE" "$CONTAINER:$STAGE"
docker exec "${ENV_ARGS[@]}" "$CONTAINER" php /opt/validate-questions.php "$STAGE"
RC=$?
docker exec "$CONTAINER" rm -f "$STAGE"
exit $RC
