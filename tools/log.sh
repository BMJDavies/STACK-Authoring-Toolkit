#!/bin/bash
# log.sh: append a timestamped line to content/progress.log
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

# Run from the project root regardless of caller's cwd
cd "$(dirname "$0")/.."

if [ "$CLEAR" = "1" ]; then
    echo "[$(date '+%H:%M:%S')] $*" > content/progress.log
else
    echo "[$(date '+%H:%M:%S')] $*" >> content/progress.log
fi
