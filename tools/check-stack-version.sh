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
# version is the image tag in tools/stackapi/docker-compose.yml, or STACK_VERSION
# in tools/stackapi/.env when the compose file takes it from there.

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
# docker-compose.yml may take the version from STACK_VERSION in the
# machine-local tools/stackapi/.env instead of a literal tag.
if [ -z "$PIN" ] && [ -f "$SCRIPT_DIR/stackapi/.env" ]; then
    PIN=$(sed -n 's/^STACK_VERSION=\([0-9]\{10\}\).*/\1/p' "$SCRIPT_DIR/stackapi/.env" | head -1)
fi

check_validator() {
    # The validation service: the standalone STACK API and its Maxima, in two
    # Docker containers on localhost:3080. It checks XML; students never see it.
    if [ -z "$PIN" ] || [ "$PIN" -lt "$MIN_STACK_VERSION" ]; then
        echo "FAIL validation service: configured for STACK ${PIN:-nothing}, below the minimum $MIN_STACK_VERSION ($MIN_STACK_RELEASE)."
        echo "     Raise STACK_VERSION in tools/stackapi/.env (or the image tags in $COMPOSE)."
        FAIL=1
        return
    fi
    # The API's replies carry no version, so compare the running images with
    # the ones docker-compose.yml and .env select.
    local running pinned
    running=$(docker compose -f "$COMPOSE" -p stackapi ps --format '{{.Image}}' 2>/dev/null | sort | tr '\n' ' ')
    pinned=$(docker compose -f "$COMPOSE" config --images 2>/dev/null | sort | tr '\n' ' ')
    if [ -z "$running" ]; then
        echo "ok   validation service: not running; it will start at STACK $PIN"
        return
    fi
    if [ "$running" = "$pinned" ]; then
        echo "ok   validation service (localhost:3080): STACK $PIN"
        return
    fi
    echo "FAIL validation service: running ${running% } but docker-compose.yml and tools/stackapi/.env select ${pinned% }."
    echo "     Editing the pin does not upgrade running containers. Recreate them:"
    echo "     docker compose -f $COMPOSE -p stackapi up -d --force-recreate"
    FAIL=1
}

check_moodle() {
    # A Moodle site: where questions are imported for the final test, and where
    # students work.
    local c="$1" v
    # Moodle 5.1 and later keep plugins under public/.
    local mr="${MOODLE_ROOT:-/var/www/html}" f
    for f in "$mr/question/type/stack/version.php" "$mr/public/question/type/stack/version.php"; do
        v=$(docker exec "$c" sed -n 's/.*plugin->version *= *\([0-9]\{10\}\).*/\1/p' "$f" 2>/dev/null)
        [ -n "$v" ] && break
    done
    if [ -z "$v" ]; then
        echo "??   Moodle ($c): could not read STACK's version.php; not checked."
        return
    fi
    if [ "$v" -lt "$MIN_STACK_VERSION" ]; then
        echo "FAIL Moodle ($c): STACK $v, below the minimum $MIN_STACK_VERSION ($MIN_STACK_RELEASE)."
        echo "     Route-style question tests fail on older STACK. Upgrade it: docs/SETUP.md section 3."
        FAIL=1
    elif [ -n "$PIN" ] && [ "$v" != "$PIN" ]; then
        echo "warn Moodle ($c): STACK $v, but the validation service runs $PIN. Random"
        echo "     sequences can differ between versions. Align them: docs/SETUP.md section 8."
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
