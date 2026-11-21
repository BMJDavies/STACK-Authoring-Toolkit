#!/bin/bash
# Print one of STACK's guidance files for agents, verbatim.
#
#   tools/stack-guide.sh essentials     agents/stack_essentials.md
#   tools/stack-guide.sh review         agents/stack_question_review.md
#   tools/stack-guide.sh <file.md>      any other file in STACK's agents/
#
# Where it reads from, first success wins:
#   1. origin/master in the project's STACK clone, sources/moodle-qtype_stack
#   2. origin/dev in the same clone
#   3. GitHub, master then dev (curl), when there is no clone or neither
#      branch has the file
# The clone is read with git show, so its working tree and checked-out branch
# are never touched: your own STACK branches and drafts are left alone.
# tools/update-stack-sources.sh creates the clone and fetches it.
#
# A file counts as found only if its first line is the one expected
# ("# STACK essentials", "# Question quality review"; any "# " for others).
#
# Output: the file on stdout. On stderr, one line saying where it came from
# and, for the clone, when it was last fetched, with a warning after 30 days.
# Exit 0 = found; 1 = not found anywhere; 2 = bad arguments.
#
# This is the only place the GitHub URLs for STACK's guidance appear.

set -uo pipefail
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
# shellcheck source=lib/project.sh
source "$SCRIPT_DIR/lib/project.sh"

case "${1:-}" in
    essentials) FILE=stack_essentials.md;      HEAD='# STACK essentials' ;;
    review)     FILE=stack_question_review.md; HEAD='# Question quality review' ;;
    *.md)       FILE="$(basename "$1")";        HEAD='# ' ;;
    *) sed -n '2,6p' "$0" | sed 's/^# \{0,1\}//' >&2; exit 2 ;;
esac

URL_BASE=https://raw.githubusercontent.com/maths/moodle-qtype_stack
CLONE="$PROJECT_DIR/sources/moodle-qtype_stack"
STALE_DAYS=30

starts_right() { head -1 <<<"$1" | grep -q "^$HEAD"; }

if git -C "$CLONE" rev-parse --git-dir >/dev/null 2>&1; then
    # When was the clone last fetched? update-stack-sources.sh leaves a marker
    # on success; a fetch run by hand writes FETCH_HEAD. A failed fetch empties
    # FETCH_HEAD, so only a non-empty one counts.
    GITDIR="$(git -C "$CLONE" rev-parse --path-format=absolute --git-common-dir)"
    mtime() { stat -c %Y "$1" 2>/dev/null || stat -f %m "$1"; }
    LAST=0
    [ -f "$GITDIR/stack-kit-fetched" ] && LAST=$(mtime "$GITDIR/stack-kit-fetched")
    if [ -s "$GITDIR/FETCH_HEAD" ]; then
        T=$(mtime "$GITDIR/FETCH_HEAD"); [ "$T" -gt "$LAST" ] && LAST=$T
    fi
    AGE=""
    [ "$LAST" -gt 0 ] && AGE=$(( ( $(date +%s) - LAST ) / 86400 ))
    for branch in master dev; do
        TEXT="$(git -C "$CLONE" show "origin/$branch:agents/$FILE" 2>/dev/null)" || continue
        starts_right "$TEXT" || continue
        COMMIT="$(git -C "$CLONE" log -1 --format='%h, %cs' "origin/$branch" -- "agents/$FILE")"
        if [ -z "$AGE" ]; then
            echo "Read $FILE from the local clone, origin/$branch ($COMMIT); last fetch unknown." >&2
        else
            echo "Read $FILE from the local clone, origin/$branch ($COMMIT); fetched $AGE days ago." >&2
        fi
        if [ -z "$AGE" ] || [ "$AGE" -gt "$STALE_DAYS" ]; then
            echo "WARNING: the clone may be out of date. Tell the user, and suggest tools/update-stack-sources.sh." >&2
        fi
        printf '%s\n' "$TEXT"
        exit 0
    done
fi

for branch in master dev; do
    TEXT="$(curl -fsSL --max-time 30 "$URL_BASE/$branch/agents/$FILE" 2>/dev/null)" || continue
    starts_right "$TEXT" || continue
    echo "Read $FILE from GitHub, $branch (no local clone has it; tools/update-stack-sources.sh makes one)." >&2
    printf '%s\n' "$TEXT"
    exit 0
done

echo "Could not read agents/$FILE: not in the local clone's origin/master or origin/dev, and not on GitHub." >&2
exit 1
