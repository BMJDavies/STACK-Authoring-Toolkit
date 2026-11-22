#!/bin/bash
# Keep a local copy of STACK's source in the project, for reference.
#
#   tools/update-stack-sources.sh                    clone if needed, fetch, set the target
#   tools/update-stack-sources.sh --version=2026080600   target this STACK version
#   tools/update-stack-sources.sh --ref=<tag|commit>     target this exact commit
#   tools/update-stack-sources.sh --no-fetch         work offline, with what is there
#
# It maintains two checkouts in the project's sources/ folder, sharing one .git:
#
#   sources/moodle-qtype_stack   a clone of STACK. The kit reads STACK's agent
#                                guidance from its origin/master (or origin/dev)
#                                with tools/stack-guide.sh, so its own working
#                                tree is yours: this script never changes its
#                                branch or files, only fetches.
#   sources/stack-target         a git worktree at the release your questions
#                                are written for: its docs, sample questions and
#                                Maxima code are the ones you have. Moved to a
#                                new tag when STACK is upgraded.
#
# The target version is, in order: --ref, --version, or the STACK installed in
# the Moodle container (LIVE_CONTAINER in project.conf). Run this again after
# every STACK upgrade, and now and then to refresh the guidance.
#
# The first clone is about 0.5 GB and needs a network connection.
# Exit 0 = done; 1 = something failed (the message says what); 2 = bad arguments.

set -uo pipefail
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
# shellcheck source=lib/project.sh
source "$SCRIPT_DIR/lib/project.sh"

REPO_URL=https://github.com/maths/moodle-qtype_stack.git
CLONE="$PROJECT_DIR/sources/moodle-qtype_stack"
TARGET="$PROJECT_DIR/sources/stack-target"

VERSION=""
REF=""
FETCH=1
for arg in "$@"; do
    case "$arg" in
        --version=*) VERSION="${arg#--version=}" ;;
        --ref=*)     REF="${arg#--ref=}" ;;
        --no-fetch)  FETCH=0 ;;
        -h|--help)   sed -n '2,7p' "$0" | sed 's/^# \{0,1\}//'; exit 0 ;;
        *) echo "Unknown option: $arg (see --help)" >&2; exit 2 ;;
    esac
done

# 1. The clone.
if [ ! -e "$CLONE" ]; then
    if [ "$FETCH" -eq 0 ]; then echo "No clone at $CLONE, and --no-fetch given." >&2; exit 1; fi
    echo "Cloning STACK into $CLONE (about 0.5 GB)..."
    mkdir -p "$(dirname "$CLONE")"
    git clone --quiet "$REPO_URL" "$CLONE" || { echo "Clone failed." >&2; exit 1; }
    touch "$CLONE/.git/stack-kit-fetched"
elif ! git -C "$CLONE" rev-parse --git-dir >/dev/null 2>&1; then
    echo "$CLONE exists but is not a git repository. Move it aside and run this again." >&2
    exit 1
elif [ "$FETCH" -eq 1 ]; then
    # Never prompt: a key with a passphrase or a login fails at once instead.
    # STACK is public, so if the remote needs a key or login, fetch the same
    # branches over HTTPS into origin/* instead; the remote is left as it is.
    if GIT_TERMINAL_PROMPT=0 GIT_SSH_COMMAND="${GIT_SSH_COMMAND:-ssh -o BatchMode=yes}" \
            git -C "$CLONE" fetch --quiet --tags origin 2>/dev/null \
       || GIT_TERMINAL_PROMPT=0 git -C "$CLONE" fetch --quiet --tags "$REPO_URL" \
            '+refs/heads/*:refs/remotes/origin/*' 2>/dev/null; then
        # A failed fetch empties FETCH_HEAD, so tools/stack-guide.sh also reads
        # this marker, written only on success, to know how fresh the clone is.
        touch "$(git -C "$CLONE" rev-parse --path-format=absolute --git-common-dir)/stack-kit-fetched"
    else
        echo "WARNING: could not fetch from origin or from GitHub (no network?)."
        echo "         Run it yourself:  git -C $CLONE fetch --tags origin"
        echo "         Carrying on with what the clone already has."
    fi
fi

# 2. The target version.
if [ -z "$REF" ]; then
    if [ -z "$VERSION" ]; then
        load_project_conf
        if [ -z "${LIVE_CONTAINER:-}" ]; then
            echo "LIVE_CONTAINER is not set in project.conf: give --version=<STACK version>." >&2
            exit 2
        fi
        VERSION="$(docker exec "$LIVE_CONTAINER" cat /var/www/html/question/type/stack/version.php 2>/dev/null \
            | sed -n 's/.*plugin->version *= *\([0-9]*\).*/\1/p')"
        if [ -z "$VERSION" ]; then
            echo "Could not read STACK's version from container $LIVE_CONTAINER (is it running?)." >&2
            echo "Give --version=<STACK version> instead." >&2
            exit 1
        fi
        echo "Moodle ($LIVE_CONTAINER) runs STACK $VERSION."
    fi
    for tag in $(git -C "$CLONE" tag -l 'v[0-9]*' --sort=-v:refname); do
        if git -C "$CLONE" show "$tag:version.php" 2>/dev/null \
                | grep -q "plugin->version *= *$VERSION;"; then
            REF="$tag"; break
        fi
    done
    if [ -z "$REF" ]; then
        echo "No release tag in the clone has version $VERSION." >&2
        echo "If it is a development build, give --ref=<commit or branch>." >&2
        exit 1
    fi
fi
git -C "$CLONE" rev-parse --verify --quiet "$REF^{commit}" >/dev/null \
    || { echo "Not a commit in the clone: $REF" >&2; exit 1; }

# 3. The target worktree.
if [ ! -e "$TARGET" ]; then
    git -C "$CLONE" worktree add --quiet --detach "$TARGET" "$REF" \
        || { echo "Could not create the worktree $TARGET." >&2; exit 1; }
else
    CLONE_GIT="$(cd "$CLONE" && cd "$(git rev-parse --git-common-dir)" && pwd)"
    TARGET_GIT="$(cd "$TARGET" 2>/dev/null && cd "$(git rev-parse --git-common-dir 2>/dev/null)" 2>/dev/null && pwd)"
    if [ "$CLONE_GIT" != "$TARGET_GIT" ]; then
        echo "$TARGET exists but is not a worktree of $CLONE. Move it aside and run this again." >&2
        exit 1
    fi
    if [ -n "$(git -C "$TARGET" status --porcelain)" ]; then
        echo "$TARGET has local changes. It is for reference only: discard them, then run this again." >&2
        exit 1
    fi
    git -C "$TARGET" checkout --quiet --detach "$REF" \
        || { echo "Could not move $TARGET to $REF." >&2; exit 1; }
fi

# 4. Report.
describe() { git -C "$CLONE" log -1 --format='%h, %cs' "$1" 2>/dev/null || echo "missing"; }
TV="$(sed -n 's/.*plugin->version *= *\([0-9]*\).*/\1/p' "$TARGET/version.php")"
TR="$(sed -n "s/.*plugin->release *= *'\([^']*\)'.*/\1/p" "$TARGET/version.php")"
echo "Guidance:  origin/master $(describe origin/master); origin/dev $(describe origin/dev)"
echo "Clone:     $CLONE, on $(git -C "$CLONE" rev-parse --abbrev-ref HEAD) (left as it was)"
echo "Target:    $TARGET at $REF: STACK $TV ($TR)"
echo "Moodle may carry local patches on top of a release: for exact behaviour, use"
echo "tools/stack-maxima.sh --moodle."
