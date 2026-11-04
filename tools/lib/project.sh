# project.sh: locate the project folder and load its settings.
#
# Sourced by the kit's shell tools after they set SCRIPT_DIR. It sets:
#   KIT_DIR      the kit's root folder (the parent of tools/)
#   PROJECT_DIR  the project folder: $STACK_PROJECT if set (a path relative to
#                the kit root, or absolute), otherwise project/
# and provides load_project_conf, which sources $PROJECT_DIR/project.conf or
# stops with a clear message.

KIT_DIR="$(cd "$SCRIPT_DIR/.." && pwd)"
_stack_project="${STACK_PROJECT:-project}"
case "$_stack_project" in
    /*) PROJECT_DIR="$_stack_project" ;;
    *)  PROJECT_DIR="$KIT_DIR/$_stack_project" ;;
esac
unset _stack_project

load_project_conf() {
    if [ ! -f "$PROJECT_DIR/project.conf" ]; then
        echo "No project settings at $PROJECT_DIR/project.conf." >&2
        if [ -f "$KIT_DIR/tools/project.conf" ]; then
            echo "This kit still has the old layout (tools/project.conf). See docs/UPGRADING.md." >&2
        else
            echo "Set up a project first: see docs/ONBOARDING.md (or run /start)." >&2
        fi
        exit 1
    fi
    # shellcheck source=/dev/null
    source "$PROJECT_DIR/project.conf"
}
