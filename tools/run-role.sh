#!/usr/bin/env bash
# run-role.sh: run one pipeline role as its own non-interactive process, for an
# assistant tool without subagents. The coordinating session runs it; the user
# normally does not.
#
#   tools/run-role.sh <role> <input...>             run the role
#   tools/run-role.sh --dry-run <role> <input...>   print the command and prompt only
#   tools/run-role.sh --check                       can a role edit files and run tools unattended
#
# <role> is planner, author, reviewer or enricher; its prompt is the body of
# .claude/agents/textbook-<role>.md. <input> is free text: a section reference
# for the planner; plan or XML paths, quiz id and notes for the others.
#
# A role run this way never writes to Moodle: it validates, then stops where its
# role says to publish and reports what it would have done. The coordinating
# session, which the user is watching, publishes after asking.
#
# Settings, as lines in extensions/harness.md:
#
#   run-role.cli: claude | copilot | codex | gemini
#   run-role.args: flags for every role, split on spaces, no shell quoting
#   run-role.args.deepest: extra flags for the planner (optional: model choice)
#   run-role.args.rigorous: extra flags for the reviewer and enricher (optional)
#   run-role.args.standard: extra flags for the author (optional)
#
# Nobody is present to approve anything, so run-role.args must allow editing
# files and running tools/*, docker, pdftotext, git and grep. Use the narrowest
# flags that make --check pass. Starting points (only Claude Code tested):
#
#   claude   --permission-mode acceptEdits --allowedTools Bash(tools/*)
#            Bash(docker:*) Bash(pdftotext:*) Bash(git:*) Bash(grep:*)
#   copilot  --allow-tool=write --allow-tool=shell(tools/*) --allow-tool=shell(docker:*) ...
#   codex    --sandbox workspace-write  (its sandbox may block docker)
#   gemini   --approval-mode=auto_edit, or yolo if shell commands are refused
#
# Each run is a fresh process with a fresh context, so a role sees only its
# inputs and the files on disk, never an earlier role's reasoning.
set -euo pipefail
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
# shellcheck source=lib/project.sh
source "$SCRIPT_DIR/lib/project.sh"

HARNESS="$KIT_DIR/extensions/harness.md"

die() { echo "run-role: $*" >&2; exit 1; }

usage() {
    sed -n '2,/^set -euo/p' "${BASH_SOURCE[0]}" | sed '$d; s/^# \{0,1\}//' >&2
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
        # Proves what a role needs with nobody present: the tool answers, may
        # create a file, and may run a kit tool. The file is checked on disk;
        # the tool run is checked from the reply, as api-check.sh with no
        # arguments prints a usage line and touches nothing.
        load_harness
        nonce="check-$$-$RANDOM"
        probe="extensions/.run-role-check"
        rm -f "$KIT_DIR/$probe"
        build_cmd "This is a permissions check; do nothing else. 1. Using your file-writing tool, create the file $probe containing exactly: $nonce
2. Run this shell command from the current folder: tools/api-check.sh
3. Reply with the first line that command printed, or with DENIED if you were not allowed to do step 1 or step 2." "$ARGS"
        echo "Tool: $CLI ($("$CLI" --version 2>/dev/null | head -n 1 || echo "version unknown"))"
        echo "Asking it to create a file and run a kit tool..."
        out="$(cd "$KIT_DIR" && "${CMD[@]}" 2>&1)" || die "$CLI exited with an error:
$out"
        wrote=no; ran=no
        grep -qx "$nonce" "$KIT_DIR/$probe" 2>/dev/null && wrote=yes
        rm -f "$KIT_DIR/$probe"
        printf '%s' "$out" | grep -q "Usage:" && ran=yes
        echo "  answers:            yes"
        echo "  may create files:   $wrote"
        echo "  may run kit tools:  $ran"
        if [ "$wrote" = yes ] && [ "$ran" = yes ]; then
            echo "OK: roles can run unattended with these settings."
            exit 0
        fi
        die "roles would stall or fail with these settings: change run-role.args in $HARNESS (see --help). $CLI said:
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

load_harness

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
the question in your final report instead of waiting.

Do not write to Moodle. Validation is allowed (\`tools/api-check.sh\`,
\`tools/test-question.sh\`, which uses the sandbox). Publishing and previewing
(\`tools/push-live.sh\`, \`tools/push-review.sh\`) and creating or changing quizzes
are not: where your role says to do one, stop there and report what you would
have done, to which quiz.

Your final output is your hand-off report.

Your input: $INPUT"

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
