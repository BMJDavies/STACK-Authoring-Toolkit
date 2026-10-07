#!/bin/bash
# render-check.sh: load each question through Moodle's renderer and grep the
# resulting HTML for known-bad signatures: raw [[jsxgraph]] / [[input:]] text
# that never got processed, <p>-with-validation-<div> stacking, substitution
# failures. Catches the class of bug that validation cannot see, because the
# question grades fine but looks wrong on screen.
#
# Usage:
#   tools/render-check.sh path/to/file.xml       # check one file
#   tools/render-check.sh --match Ch6            # all project/content/approved/*Ch6*.xml
#   tools/render-check.sh --all                  # every project/content/approved/*.xml
#
# Exit 0 = no flags. Exit 1 = at least one flag. Exit 2 = bad invocation.
#
# IMPORTANT: this reads the DATABASE (highest-id question of each name), not the
# file on disk. After editing XML you must re-import (validate or push) before
# re-running, or you will be checking the stale version.

set -e

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
# shellcheck source=lib/project.sh
source "$SCRIPT_DIR/lib/project.sh"
load_project_conf

if [ "$#" -lt 1 ]; then
    echo "Usage: $0 <xml-path> | --match <substring> | --all" >&2
    exit 2
fi

# Run from the kit root, so a file argument such as
# project/content/approved/Ch1_A.xml works as before.
cd "$KIT_DIR"

CONTAINER="$LIVE_CONTAINER"

docker cp tools/render-check.php "$CONTAINER:/opt/render-check.php" >/dev/null

stage_batch() {
    docker exec "$CONTAINER" bash -c "rm -rf /tmp/rc-all && mkdir -p /tmp/rc-all" >/dev/null
    for f in "$@"; do
        docker cp "$f" "$CONTAINER:/tmp/rc-all/$(basename "$f")" >/dev/null
    done
    docker exec "$CONTAINER" php /opt/render-check.php --all
}

case "$1" in
    --all)
        stage_batch "$PROJECT_DIR"/content/approved/*.xml
        exit $?
        ;;
    --match)
        if [ -z "${2:-}" ]; then
            echo "Usage: $0 --match <substring>  (e.g. --match Ch6)" >&2
            exit 2
        fi
        FILES=("$PROJECT_DIR"/content/approved/*"$2"*.xml)
        if [ ! -e "${FILES[0]}" ]; then
            echo "No files matching $PROJECT_DIR/content/approved/*$2*.xml" >&2
            exit 2
        fi
        stage_batch "${FILES[@]}"
        exit $?
        ;;
    *)
        if [ ! -f "$1" ]; then
            echo "Not a file: $1" >&2
            exit 2
        fi
        docker cp "$1" "$CONTAINER:/tmp/rc-target.xml" >/dev/null
        docker exec "$CONTAINER" php /opt/render-check.php /tmp/rc-target.xml
        exit $?
        ;;
esac
