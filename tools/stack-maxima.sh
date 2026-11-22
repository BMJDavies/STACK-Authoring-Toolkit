#!/bin/bash
# Run Maxima code with STACK's Maxima libraries loaded, on desktop Maxima.
#
#   tools/stack-maxima.sh [source] <file.mac>
#   tools/stack-maxima.sh [source] -e '<maxima code>' [-e '<more code>' ...]
#
# source, one of:
#   --moodle        copy STACK's Maxima files from the Moodle container
#                   (LIVE_CONTAINER in project.conf): the STACK your questions
#                   run on
#   --stack=DIR     a STACK source folder (a clone of moodle-qtype_stack)
#   (neither)       the project's sources/stack-target, the release your
#                   questions are written for (tools/update-stack-sources.sh
#                   makes it), or STACK_SRC from the environment
#
# Other options:
#   --keep          keep the working folder and print where it is
#
# For trying question-variable code, helpers and validators, and for checking
# how a STACK version behaves before writing a workaround or an issue. It runs
# no CASText, inputs, PRTs or question tests, and plots do not work: use
# api-check.sh and test-question.sh for those. It uses this machine's Maxima,
# not the CAS server's, so check the version line it prints first.
#
# How: STACK's own sandbox, stack/maxima/sandbox.wxm, is copied to a fresh
# working folder and its paths set there; the STACK files are never edited.
# The setup and your code run in one Maxima process (maxima -b with two files
# runs only the last). Your code starts with simp:true, as question variables
# do; set simp:false yourself where you need it.
#
# Exit 0 = ran with no Maxima error; 1 = Maxima reported an error;
# 2 = bad arguments or no STACK source; 3 = Maxima is not installed.

set -uo pipefail
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
# shellcheck source=lib/project.sh
source "$SCRIPT_DIR/lib/project.sh"

usage() { sed -n '2,13p' "$0" | sed 's/^# \{0,1\}//' >&2; exit 2; }

SOURCE_MODE=""
STACK_DIR=""
KEEP=0
CODE=()
FILE=""
for arg in "$@"; do
    case "$arg" in
        --moodle)   SOURCE_MODE=moodle ;;
        --stack=*)  SOURCE_MODE=dir; STACK_DIR="${arg#--stack=}" ;;
        --keep)     KEEP=1 ;;
        -e)         CODE+=("") ;;
        -h|--help)  usage ;;
        -*)         echo "Unknown option: $arg" >&2; usage ;;
        *)
            if [ ${#CODE[@]} -gt 0 ] && [ -z "${CODE[-1]}" ]; then
                CODE[-1]="$arg"
            elif [ -z "$FILE" ]; then
                FILE="$arg"
            else
                echo "Only one file, or -e code." >&2; usage
            fi ;;
    esac
done
if [ -z "$FILE" ] && [ ${#CODE[@]} -eq 0 ]; then usage; fi
if [ -n "$FILE" ] && [ ${#CODE[@]} -gt 0 ]; then echo "Give a file or -e code, not both." >&2; usage; fi
if [ -n "$FILE" ] && [ ! -f "$FILE" ]; then echo "No such file: $FILE" >&2; exit 2; fi

if ! command -v maxima >/dev/null 2>&1; then
    echo "Maxima is not installed on this machine (no 'maxima' on PATH)." >&2
    echo "Install it from your package manager, or use api-check.sh instead." >&2
    exit 3
fi

WORK="$(mktemp -d "${TMPDIR:-/tmp}/stack-maxima.XXXXXX")"
if [ "$KEEP" -eq 0 ]; then trap 'rm -rf "$WORK"' EXIT; fi

# Find STACK's Maxima files, and STACK's version.php beside them.
case "$SOURCE_MODE" in
    moodle)
        load_project_conf
        CONTAINER="${LIVE_CONTAINER:-}"
        STACK_IN_CONTAINER="${STACK_MOODLE_PATH:-/var/www/html/question/type/stack}"
        if [ -z "$CONTAINER" ]; then echo "LIVE_CONTAINER is not set in project.conf." >&2; exit 2; fi
        mkdir -p "$WORK/stack/stack"
        if ! docker cp -q "$CONTAINER:$STACK_IN_CONTAINER/stack/maxima" "$WORK/stack/stack/maxima" 2>/dev/null; then
            echo "Could not copy $STACK_IN_CONTAINER/stack/maxima from container $CONTAINER." >&2
            echo "Is it running? (docker ps). Set STACK_MOODLE_PATH in project.conf if STACK is elsewhere." >&2
            exit 2
        fi
        docker cp -q "$CONTAINER:$STACK_IN_CONTAINER/version.php" "$WORK/stack/version.php" 2>/dev/null
        STACK_DIR="$WORK/stack"
        FROM="Moodle container $CONTAINER"
        ;;
    dir)
        FROM="$STACK_DIR"
        ;;
    "")
        if [ -n "${STACK_SRC:-}" ]; then
            STACK_DIR="$STACK_SRC"
            FROM="$STACK_DIR (STACK_SRC)"
        elif [ -d "$PROJECT_DIR/sources/stack-target" ]; then
            STACK_DIR="$PROJECT_DIR/sources/stack-target"
            FROM="$STACK_DIR"
        else
            echo "No STACK source. Give --moodle or --stack=DIR, or run tools/update-stack-sources.sh" >&2
            echo "to make the project's sources/stack-target." >&2
            exit 2
        fi
        ;;
esac
STACK_DIR="$(cd "$STACK_DIR" 2>/dev/null && pwd)" || { echo "No such folder: $FROM" >&2; exit 2; }
SANDBOX="$STACK_DIR/stack/maxima/sandbox.wxm"
if [ ! -f "$SANDBOX" ]; then
    echo "Not a STACK source folder (no stack/maxima/sandbox.wxm): $STACK_DIR" >&2
    exit 2
fi

# The sandbox's first input cell is the setup. Copy it, point its three path
# variables at STACK and the working folder, and move its :lisp lines into a
# .lisp file, because batchload (which keeps the setup quiet) cannot read them.
mkdir -p "$WORK/tmp"
awk '/\[wxMaxima: input   start \]/{f=1; n++; next}
     /\[wxMaxima: input   end   \]/{f=0}
     f && n==1' "$SANDBOX" > "$WORK/sandbox-setup.mac"
if ! grep -q '^load("stackmaxima.mac")' "$WORK/sandbox-setup.mac"; then
    echo "sandbox.wxm has changed: its first input cell no longer loads stackmaxima.mac." >&2
    echo "Update tools/stack-maxima.sh to match $SANDBOX." >&2
    exit 2
fi
sed -n 's/^:lisp //p' "$WORK/sandbox-setup.mac" > "$WORK/setup.lisp"
{
    echo "load(\"$WORK/setup.lisp\")\$"
    grep -v '^:lisp ' "$WORK/sandbox-setup.mac" | sed \
        -e "s#^maximaplatform:.*#maximaplatform:\"linux\"\$#" \
        -e "s#^stacklocation:.*#stacklocation:\"$STACK_DIR\"\$#" \
        -e "s#^stacktmplocation:.*#stacktmplocation:\"$WORK/tmp\"\$#"
} > "$WORK/setup.mac"

# The user's code, after simp:true.
{
    echo 'simp:true$'
    if [ -n "$FILE" ]; then cat "$FILE"; else printf '%s\n' "${CODE[@]}"; fi
} > "$WORK/user.mac"

STACK_VERSION=""
if [ -f "$STACK_DIR/version.php" ]; then
    STACK_VERSION="$(sed -n "s/.*plugin->version *= *\([0-9]*\).*/\1/p; s/.*plugin->release *= *'\([^']*\)'.*/(\1)/p" "$STACK_DIR/version.php" | tr '\n' ' ')"
fi
echo "STACK ${STACK_VERSION:-version unknown}from $FROM; $(maxima --version)"

OUT="$(cd "$WORK" && maxima --very-quiet \
    --batch-string="batchload(\"$WORK/setup.mac\")\$ batch(\"$WORK/user.mac\")\$" 2>&1)"
# Drop the echoed loads, the expected warnings from STACK's own definitions,
# and the simp:true added above.
printf '%s\n' "$OUT" | grep -v \
    -e '^read and interpret ' -e '^batchload("' -e '^batch("' -e '^simp:true$' \
    -e '^define: warning: redefining the built-in function \(intervalp\|texnumformat\)$' \
    | sed '/./,$!d'

if [ "$KEEP" -eq 1 ]; then echo "Working folder kept: $WORK" >&2; fi
if printf '%s\n' "$OUT" | grep -q -e '-- an error' -e 'incorrect syntax' -e '^Maxima encountered a Lisp error'; then
    exit 1
fi
exit 0
