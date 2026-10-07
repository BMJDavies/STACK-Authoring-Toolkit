#!/bin/bash
# log.sh: append a timestamped line to the project's content/progress.log
# Usage: tools/log.sh "AUTHOR [Ch5C] validation PASS"
#        tools/log.sh --clear "MANAGER [Ch5C] starting"   # truncate first
# Wraps date+echo in a single command so callers don't trip the
# subshell-permission check that $(date ...) inside an inline echo
# triggers on every fresh message string.

set -e

CLEAR=0
if [ "$1" = "--clear" ]; then
    CLEAR=1
    shift
fi

if [ "$#" -lt 1 ]; then
    echo "Usage: $0 [--clear] <message>" >&2
    exit 2
fi

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
# shellcheck source=lib/project.sh
source "$SCRIPT_DIR/lib/project.sh"
if [ ! -d "$PROJECT_DIR" ]; then
    echo "No project folder at $PROJECT_DIR: see ONBOARDING.md." >&2
    exit 1
fi
mkdir -p "$PROJECT_DIR/content"
LOG="$PROJECT_DIR/content/progress.log"

if [ "$CLEAR" = "1" ]; then
    echo "[$(date '+%H:%M:%S')] $*" > "$LOG"
else
    echo "[$(date '+%H:%M:%S')] $*" >> "$LOG"
fi
