#!/usr/bin/env bash
#
# Report on every workaround in the kit's register, stack-workarounds.md.
#
#   tools/check-workarounds.sh [--offline]
#
# For each entry that is not "removed" it prints the GitHub issue's state, the
# fix commit on STACK's dev branch if there is one, the STACK version installed
# in this Moodle, and the project's questions that carry the workaround's id
# (in todo tags or code comments), or, for an entry with a "Marker:" line,
# the questions containing that text. An entry is READY TO REMOVE when its fix is
# in dev (the issue is closed, or the status is "fixed in dev") and the
# installed version is larger than the entry's Upstream version, the version
# on master, STACK's current release, when the entry was written: then restore
# the plain code in the questions listed (style guide, "Workarounds for STACK
# defects").
#
# GitHub is read through its public API, without credentials. --offline skips
# it. Nothing is written anywhere.

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
# shellcheck source=lib/project.sh
source "$SCRIPT_DIR/lib/project.sh"
load_project_conf

OFFLINE=0
[ "${1:-}" = "--offline" ] && OFFLINE=1

REGISTER="$KIT_DIR/stack-workarounds.md"
API="https://api.github.com/repos/maths/moodle-qtype_stack/issues"

# The installed STACK version, from the Moodle container (Moodle 5.1+ keeps
# plugins under public/).
INSTALLED=""
MR="${MOODLE_ROOT:-/var/www/html}"
for f in "$MR/question/type/stack/version.php" \
         "$MR/public/question/type/stack/version.php"; do
    INSTALLED="$(docker exec "$LIVE_CONTAINER" sed -n 's/.*plugin->version *= *\([0-9]*\).*/\1/p' "$f" 2>/dev/null || true)"
    [ -n "$INSTALLED" ] && break
done
echo "Installed STACK version: ${INSTALLED:-unknown (is $LIVE_CONTAINER running?)}"
echo

# One line per entry: id|issue|upstream|status|fix|marker
entries="$(awk '
    /^### / { if (id != "") print id "|" issue "|" up "|" st "|" fix "|" mk; id = $2; issue = ""; up = ""; st = ""; fix = ""; mk = "" }
    /^- Issue:/    { sub(/^- Issue: */, ""); issue = $0 }
    /^- Upstream:/ { sub(/^- Upstream: */, ""); up = $0 }
    /^- Status:/   { sub(/^- Status: */, ""); st = $0 }
    /^- Fix:/      { sub(/^- Fix: */, ""); gsub(/\|/, "/"); fix = $0 }
    /^- Marker:/   { sub(/^- Marker: */, ""); gsub(/`/, ""); mk = $0 }
    END { if (id != "") print id "|" issue "|" up "|" st "|" fix "|" mk }
' <(sed -n '/^## Entries/,$p' "$REGISTER"))"

if [ -z "$entries" ]; then
    echo "No entries in $REGISTER."
    exit 0
fi

ready=0
while IFS='|' read -r id issue upstream status fix marker; do
    [ "$status" = "removed" ] && continue
    echo "== $id ($status)"

    state="not raised"
    num="$(printf '%s' "$issue" | sed -n 's#.*/issues/\([0-9]*\).*#\1#p')"
    if [ -n "$num" ]; then
        if [ "$OFFLINE" = 1 ]; then
            state="not checked (--offline)"
        else
            state="$(curl -s -m 10 "$API/$num" | sed -n 's/^  "state": "\(.*\)",$/\1/p')"
            state="${state:-unknown (GitHub did not answer)}"
        fi
        echo "   Issue #$num: $state"
    elif [ "$status" = "fixed in dev" ]; then
        echo "   Issue: none, the fix is already in dev"
    else
        echo "   Issue: not raised yet. Raise it from the entry's draft, then rename the id."
    fi
    [ -n "$fix" ] && echo "   Fix: $fix"

    echo "   Upstream $upstream, installed ${INSTALLED:-unknown}"

    # Questions are found by the id, or by the entry's Marker text if it has one.
    pat="$id"
    [ -n "$marker" ] && pat="$marker" && echo "   Marker: $marker"
    files="$(grep -lF -- "$pat" "$PROJECT_DIR"/content/approved/*.xml "$PROJECT_DIR"/content/drafts/*.xml 2>/dev/null || true)"
    if [ -n "$files" ]; then
        echo "   Used in:"
        for f in $files; do
            echo "     ${f#"$PROJECT_DIR"/} ($(grep -cF -- "$pat" "$f") mentions)"
        done
    else
        echo "   Used in: no question in $PROJECT_DIR/content/"
    fi

    infix=0
    { [ "$state" = "closed" ] || [ "$status" = "fixed in dev" ]; } && infix=1
    if [ "$infix" = 1 ] && [ -n "$INSTALLED" ] && [ "$INSTALLED" -gt "$upstream" ]; then
        echo "   READY TO REMOVE: restore the plain code in the questions above."
        ready=$((ready + 1))
    fi
    echo
done <<< "$entries"

echo "$ready ready to remove."
