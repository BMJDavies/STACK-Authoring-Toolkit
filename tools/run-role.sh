#!/usr/bin/env bash
# run-role.sh: run one pipeline role as its own non-interactive process.
#
#   tools/run-role.sh <role> <input...>             run the role
#   tools/run-role.sh --dry-run <role> <input...>   print the command and prompt only
#   tools/run-role.sh --check                       confirm the configured tool answers
#
# <role> is planner, author, reviewer or enricher; its prompt is the body of
# .claude/agents/textbook-<role>.md. <input> is what the role works on: a
# section reference for the planner, a plan or XML path for the others.
#
# The assistant tool and its flags come from the run-role.* lines in
# extensions/harness.md, which onboarding (Stage 0) fills in. Each run is a
# fresh process with a fresh context, so a role sees only its inputs and the
# files on disk, never an earlier role's reasoning.
set -euo pipefail
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
# shellcheck source=lib/project.sh
source "$SCRIPT_DIR/lib/project.sh"

HARNESS="$KIT_DIR/extensions/harness.md"

die() { echo "run-role: $*" >&2; exit 1; }

usage() {
    sed -n '4,6p' "${BASH_SOURCE[0]}" | sed 's/^# \{0,1\}//' >&2
    exit 2
}

# The value of a run-role.<key> line in harness.md, or empty. A value still
# holding a template placeholder (<...>) counts as unset.
setting() {
    local v
    v="$(sed -n "s/^run-role\.$1:[[:space:]]*//p" "$HARNESS" | head -n 1)"
    case "$v" in "<"*) v="" ;; esac
    printf '%s' "$v"
}

load_harness() {
    [ -f "$HARNESS" ] || die "no $HARNESS. Ask your assistant to follow Stage 0 of ONBOARDING.md."
    if grep -q "Template from the kit" "$HARNESS"; then
        die "$HARNESS is still the template. Ask your assistant to follow Stage 0 of ONBOARDING.md."
    fi
    CLI="$(setting cli)"
    [ -n "$CLI" ] || die "no run-role.cli line in $HARNESS."
    ARGS="$(setting args)"
}

# Build CMD: the tool's non-interactive invocation, the prompt and the extra
# arguments, split on spaces as harness.md says. Claude's prompt goes before
# the extras, because options such as --allowedTools take any number of values
# and would swallow a prompt placed after them.
build_cmd() {
    local prompt="$1" extra="$2"
    local -a ex=()
    [ -n "$extra" ] && read -r -a ex <<< "$extra"
    case "$CLI" in
        claude)  CMD=(claude -p "$prompt" ${ex[@]+"${ex[@]}"}) ;;
        copilot) CMD=(copilot -s --no-ask-user ${ex[@]+"${ex[@]}"} -p "$prompt") ;;
        codex)   CMD=(codex exec ${ex[@]+"${ex[@]}"} "$prompt") ;;
        gemini)  CMD=(gemini ${ex[@]+"${ex[@]}"} -p "$prompt") ;;
        *) die "run-role.cli is '$CLI'; expected claude, copilot, codex or gemini." ;;
    esac
    command -v "$CLI" >/dev/null || die "'$CLI' is not on PATH."
}

DRY_RUN=0
case "${1:-}" in
    --check)
        load_harness
        build_cmd "Reply with the single word READY and nothing else." "$ARGS"
        echo "Tool: $CLI ($("$CLI" --version 2>/dev/null | head -n 1 || echo "version unknown"))"
        echo "Asking it to answer..."
        out="$(cd "$KIT_DIR" && "${CMD[@]}" 2>&1)" || die "$CLI exited with an error:
$out"
        if printf '%s' "$out" | grep -q READY; then
            echo "OK: $CLI answered."
            exit 0
        fi
        die "$CLI ran but did not answer as asked. It said:
$out"
        ;;
    --dry-run) DRY_RUN=1; shift ;;
    ""|-h|--help) usage ;;
esac

[ $# -ge 2 ] || usage
ROLE="$1"; shift
INPUT="$*"

case "$ROLE" in
    planner)           TIER=deepest ;;
    reviewer|enricher) TIER=rigorous ;;
    author)            TIER=standard ;;
    *) die "unknown role '$ROLE'; expected planner, author, reviewer or enricher." ;;
esac

ROLE_FILE="$KIT_DIR/.claude/agents/textbook-$ROLE.md"
[ -f "$ROLE_FILE" ] || die "no role file at $ROLE_FILE."

# The role body without its YAML header.
BODY="$(awk 'NR==1 && $0=="---" {h=1; next} h && $0=="---" {h=0; next} !h' "$ROLE_FILE")"

PROJECT_NOTE=""
if [ -n "${STACK_PROJECT:-}" ]; then
    PROJECT_NOTE="
The project folder for this run is \`$STACK_PROJECT\`, not \`project/\`: read its
AGENTS.md instead of \`project/AGENTS.md\`, and take every project path from it."
fi

PROMPT="$BODY

## This run

You are running as a separate, non-interactive process, started by
\`tools/run-role.sh\` from the kit's root folder. Nobody will answer questions
during the run. Read the kit's \`AGENTS.md\` and the layer files it names first,
if they are not already in your context.$PROJECT_NOTE

Where your role says to ask or escalate to the user, stop at that point and put
the question in your final report instead of waiting. Your final output is your
hand-off report.

Your input: $INPUT"

load_harness
TIER_ARGS="$(setting "args.$TIER")"
build_cmd "$PROMPT" "${ARGS:+$ARGS }$TIER_ARGS"

if [ "$DRY_RUN" = 1 ]; then
    shown=()
    for a in "${CMD[@]}"; do
        if [ "$a" = "$PROMPT" ]; then shown+=("<prompt below>"); else shown+=("$a"); fi
    done
    echo "Role: $ROLE (tier $TIER), tool: $CLI"
    echo "Command: ${shown[*]}"
    echo "----- prompt -----"
    printf '%s\n' "$PROMPT"
    exit 0
fi

echo "run-role: $ROLE (tier $TIER) via $CLI, input: $INPUT" >&2
cd "$KIT_DIR"
exec "${CMD[@]}"
