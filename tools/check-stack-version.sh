#!/bin/bash
# Check that every STACK this kit talks to is new enough, and that they agree.
#
#   tools/check-stack-version.sh                 validator, plus Moodle if project.conf names one
#   tools/check-stack-version.sh --validator     the validation service only (api-check.sh uses this)
#   tools/check-stack-version.sh --moodle <container>   one Moodle only (test-question.sh uses this)
#
# Exit 0 = all good (mismatches between Moodle and the validator only warn).
# Exit 1 = something is below the minimum, or the validator is running an old
# image. The message says what to run.
#
# The minimum lives in tools/stack-version and nowhere else. The validator's
# version is the image tag in tools/stackapi/docker-compose.yml.

set -uo pipefail
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
COMPOSE="$SCRIPT_DIR/stackapi/docker-compose.yml"
# shellcheck source=stack-version
source "$SCRIPT_DIR/stack-version"

MODE=all
MOODLE_CONTAINER=""
case "${1:-}" in
    --validator) MODE=validator ;;
    --moodle)    MODE=moodle; MOODLE_CONTAINER="${2:-}" ;;
    "")          ;;
    *) echo "Usage: $0 [--validator | --moodle <container>]" >&2; exit 1 ;;
esac

FAIL=0
PIN=$(sed -n 's/.*goemaxima:\([0-9]\{10\}\).*/\1/p' "$COMPOSE" | head -1)

check_validator() {
    # The validation service: the standalone STACK API and its Maxima, in two
    # Docker containers on localhost:3080. It checks XML; students never see it.
    if [ -z "$PIN" ] || [ "$PIN" -lt "$MIN_STACK_VERSION" ]; then
        echo "FAIL validation service: docker-compose.yml pins ${PIN:-nothing}, below the minimum $MIN_STACK_VERSION ($MIN_STACK_RELEASE)."
        echo "     Raise both image tags in $COMPOSE and the two versions in tools/stackapi/config.php."
        FAIL=1
        return
    fi
    if grep -q "stackmaxima.*$PIN" <(curl -s -m 5 -X POST http://localhost:3080/render \
            -H 'Content-Type: application/json' \
            -d '{"questionDefinition":"<quiz><question type=\"stack\"></question></quiz>"}'); then
        echo "ok   validation service (localhost:3080): STACK $PIN"
        return
    fi
    local running
    running=$(docker compose -f "$COMPOSE" -p stackapi ps --format '{{.Image}}' 2>/dev/null | tr '\n' ' ')
    if [ -z "$running" ]; then
        echo "ok   validation service: not running; it will start at STACK $PIN"
        return
    fi
    echo "FAIL validation service: running $running but docker-compose.yml pins $PIN."
    echo "     Editing the pin does not upgrade running containers. Recreate them:"
    echo "     docker compose -f $COMPOSE -p stackapi up -d --force-recreate"
    FAIL=1
}

check_moodle() {
    # A Moodle site: where questions are imported for the final test, and where
    # students work.
    local c="$1" v
    v=$(docker exec "$c" sed -n 's/.*plugin->version *= *\([0-9]\{10\}\).*/\1/p' \
        /var/www/html/question/type/stack/version.php 2>/dev/null)
    if [ -z "$v" ]; then
        echo "??   Moodle ($c): could not read STACK's version.php; not checked."
        return
    fi
    if [ "$v" -lt "$MIN_STACK_VERSION" ]; then
        echo "FAIL Moodle ($c): STACK $v, below the minimum $MIN_STACK_VERSION ($MIN_STACK_RELEASE)."
        echo "     Route-style question tests fail on older STACK. Upgrade it: SETUP.md section 3."
        FAIL=1
    elif [ -n "$PIN" ] && [ "$v" != "$PIN" ]; then
        echo "warn Moodle ($c): STACK $v, but the validation service pins $PIN. Random"
        echo "     sequences can differ between versions. Align them: SETUP.md section 8."
    else
        echo "ok   Moodle ($c): STACK $v"
    fi
}

case "$MODE" in
    validator) check_validator ;;
    moodle)    check_moodle "$MOODLE_CONTAINER" ;;
    all)
        check_validator
        # shellcheck source=lib/project.sh
        source "$SCRIPT_DIR/lib/project.sh"
        if [ -f "$PROJECT_DIR/project.conf" ]; then
            load_project_conf
            [ -n "${LIVE_CONTAINER:-}" ] && check_moodle "$LIVE_CONTAINER"
            [ -n "${SANDBOX_CONTAINER:-}" ] && check_moodle "$SANDBOX_CONTAINER"
        fi
        ;;
esac
exit $FAIL
