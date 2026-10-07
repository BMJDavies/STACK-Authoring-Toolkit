#!/usr/bin/env bash
# migrate-to-project-folder.sh: upgrade a kit configured before the kit/project
# separation, where project files lived in the kit's own folders, to the layout
# with the project in project/ (its own git repository). See UPGRADING.md.
#
# Run it from the root of your kit folder, BEFORE pulling the update. The usual
# way is to run the new version straight from the remote, without pulling:
#
#   git fetch origin
#   git show origin/main:tools/migrate-to-project-folder.sh | bash -s -- --dry-run
#   git show origin/main:tools/migrate-to-project-folder.sh | bash
#
# Options:
#   --dry-run          say what would happen; change nothing
#   --checkout BRANCH  after moving the project out, switch the kit to BRANCH
#                      (default: stay on the current branch and fast-forward it)
#   --no-update        move the project out, but do not update the kit
#   --no-commit        create project/ as a git repository, but do not commit
#   --backup-dir DIR   where to write the backup archive (default: $HOME)
#
# What it does, in order:
#   1. Checks this is a kit folder with the old layout, and that project/ does
#      not exist yet.
#   2. Writes a backup archive of every file it will move, copy or stash.
#   3. Creates project/ and moves the project into it:
#        CLAUDE.md (if you rewrote it) or CLAUDE_loc.md  -> project/CLAUDE.md
#        style-guide_loc.md (if present)                 -> project/style-guide.md
#        tools/project.conf                              -> project/project.conf
#        content/ (except content/reference/)            -> project/content/
#        feedback/                                       -> project/feedback/
#        sources/                                        -> project/sources/
#      Files the kit tracks are copied; untracked and ignored files are moved.
#   4. Stashes any local changes to files the kit tracks (git stash), so the
#      update cannot conflict. Nothing is discarded: see "git stash list".
#   5. Updates the kit (fast-forward only), unless --no-update.
#   6. Adds any skeleton files project/ is missing, from templates/project/,
#      and rewrites old paths in project/CLAUDE.md and project/style-guide.md.
#   7. Runs git init in project/ and makes a first commit (unless --no-commit).
#   8. Checks that the tools can load project/project.conf.
#
# It never touches Moodle: question names and quiz ids do not change.

set -euo pipefail

DRY=0; CHECKOUT=""; UPDATE=1; COMMIT=1; BACKUP_DIR="$HOME"
while [ $# -gt 0 ]; do
    case "$1" in
        --dry-run) DRY=1 ;;
        --checkout) CHECKOUT="${2:?--checkout needs a branch}"; shift ;;
        --no-update) UPDATE=0 ;;
        --no-commit) COMMIT=0 ;;
        --backup-dir) BACKUP_DIR="${2:?--backup-dir needs a folder}"; shift ;;
        -h|--help) sed -n '2,40p' "${BASH_SOURCE[0]:-/dev/null}" 2>/dev/null || true; exit 0 ;;
        *) echo "Unknown option: $1" >&2; exit 2 ;;
    esac
    shift
done

say()  { printf '%s\n' "$*"; }
step() { printf '\n== %s\n' "$*"; }
die()  { printf 'STOP: %s\n' "$*" >&2; exit 1; }
run()  { if [ "$DRY" = 1 ]; then printf '  would run: %s\n' "$*"; else "$@"; fi; }

# --- 1. Checks -------------------------------------------------------------

step "Checking this folder"
TOP="$(git rev-parse --show-toplevel 2>/dev/null)" || die "not inside a git repository. Run this from your kit folder."
[ "$TOP" = "$(pwd -P)" ] || die "run this from the root of the kit folder ($TOP), not from a subfolder."
[ -d .claude ] && [ -f style-guide.md ] || die "this does not look like the STACK authoring kit (no .claude/ or style-guide.md)."
if [ -e project ]; then die "project/ already exists. If the migration ran before, there is nothing to do; otherwise move project/ aside first."; fi

NOTICE="This project has not been set up yet"
CLAUDE_SRC=""
if [ -f CLAUDE_loc.md ]; then
    CLAUDE_SRC="CLAUDE_loc.md"
elif [ -f CLAUDE.md ] && ! grep -q "$NOTICE" CLAUDE.md; then
    CLAUDE_SRC="CLAUDE.md"
fi
OLD_CONF=""; if [ -f tools/project.conf ]; then OLD_CONF="tools/project.conf"; fi
HAS_WORK=0
for d in content/plans content/drafts content/approved feedback sources; do
    if [ -d "$d" ] && [ -n "$(find "$d" -type f ! -name .gitkeep ! -name README.md ! -name lessons-learned.md 2>/dev/null | head -1)" ]; then HAS_WORK=1; fi
done
if [ -z "$CLAUDE_SRC" ] && [ -z "$OLD_CONF" ] && [ "$HAS_WORK" = 0 ]; then
    die "found no configured project (no project instructions, no tools/project.conf, no work in content/). Nothing to migrate: just update the kit and run /start."
fi
say "Project instructions: ${CLAUDE_SRC:-none found (project/CLAUDE.md will be the template)}"
say "Project settings:     ${OLD_CONF:-none found (project/project.conf will be the template)}"
if [ -f style-guide_loc.md ]; then say "Project style rules:  style-guide_loc.md"; fi

BRANCH="$(git symbolic-ref --short -q HEAD || true)"
say "Kit branch:           ${BRANCH:-(detached HEAD)}${CHECKOUT:+, will switch to $CHECKOUT}"
has_upstream() { git rev-parse --abbrev-ref '@{upstream}' >/dev/null 2>&1; }
if [ "$UPDATE" = 1 ] && [ -z "$CHECKOUT" ] && ! has_upstream; then
    die "branch ${BRANCH:-HEAD} has no upstream to update from. Use --checkout <branch> or --no-update."
fi

# Every path that will move or be copied.
MOVE_LIST=()
if [ -n "$CLAUDE_SRC" ]; then MOVE_LIST+=("$CLAUDE_SRC"); fi
if [ -f style-guide_loc.md ]; then MOVE_LIST+=(style-guide_loc.md); fi
if [ -n "$OLD_CONF" ]; then MOVE_LIST+=("$OLD_CONF"); fi
for d in content feedback sources; do if [ -d "$d" ]; then MOVE_LIST+=("$d"); fi; done

# --- 2. Backup -------------------------------------------------------------

STAMP="$(date +%Y%m%d-%H%M%S)"
BACKUP="$BACKUP_DIR/$(basename "$TOP")-pre-migration-$STAMP.tgz"
step "Backing up to $BACKUP"
BACKUP_PATHS=("${MOVE_LIST[@]}")
while IFS= read -r f; do
    case " ${BACKUP_PATHS[*]} " in *" $f "*) ;; *) BACKUP_PATHS+=("$f") ;; esac
done < <(git diff --name-only HEAD)
if [ "$DRY" = 1 ]; then
    say "  would archive: ${BACKUP_PATHS[*]}"
else
    tar czf "$BACKUP" --ignore-failed-read "${BACKUP_PATHS[@]}" 2>/dev/null || true
    [ -s "$BACKUP" ] || die "could not write the backup $BACKUP."
    say "  done ($(tar tzf "$BACKUP" | wc -l) entries)"
fi

# --- 3. Move the project out -----------------------------------------------

is_tracked() { git ls-files --error-unmatch -- "$1" >/dev/null 2>&1; }

# Copy a file into project/; remove the original unless git tracks it.
transfer() {
    local src="$1" dst="project/$2"
    if [ "$DRY" = 1 ]; then
        if is_tracked "$src"; then say "  copy  $src -> $dst"; else say "  move  $src -> $dst"; fi
        return
    fi
    mkdir -p "$(dirname "$dst")"
    cp -p "$src" "$dst"
    is_tracked "$src" || rm -f "$src"
}

step "Moving the project into project/"
run mkdir -p project
if [ -n "$CLAUDE_SRC" ]; then transfer "$CLAUDE_SRC" CLAUDE.md; fi
if [ -f style-guide_loc.md ]; then transfer style-guide_loc.md style-guide.md; fi
if [ -n "$OLD_CONF" ]; then transfer "$OLD_CONF" project.conf; fi
for d in content feedback sources; do
    [ -d "$d" ] || continue
    while IFS= read -r -d '' f; do
        case "$f" in content/reference/*) continue ;; esac
        transfer "$f" "$f"
    done < <(find "$d" -type f -print0)
done
# Tidy folders left empty by the moves (the kit recreates what it tracks).
[ "$DRY" = 1 ] || find content feedback sources -depth -type d -empty -delete 2>/dev/null || true

# --- 4. Stash local changes to tracked kit files ----------------------------

step "Setting aside local changes to kit files"
if [ -n "$(git status --porcelain --untracked-files=no)" ]; then
    git status --short --untracked-files=no | sed 's/^/  /'
    run git stash push -m "kit changes set aside by migrate-to-project-folder.sh ($STAMP)"
    say "  Your changes are kept in the stash: inspect with 'git stash show -p', drop when sure."
else
    say "  none"
fi

# --- 5. Update the kit ------------------------------------------------------

if [ "$UPDATE" = 1 ]; then
    step "Updating the kit"
    if [ -n "$CHECKOUT" ]; then
        run git checkout "$CHECKOUT"
    fi
    if [ "$DRY" = 1 ]; then
        say "  would run: git pull --ff-only (if the branch has an upstream)"
    elif has_upstream; then
        git pull --ff-only || die "the update did not fast-forward. Your project is safe in project/ and in $BACKUP. Update the kit by hand, then re-run with --no-update to finish."
    else
        say "  $(git symbolic-ref --short -q HEAD || echo HEAD) has no upstream; not pulling."
    fi
else
    step "Not updating the kit (--no-update)"
fi

if [ "$DRY" = 1 ]; then
    step "Dry run finished: nothing was changed"
    exit 0
fi

# --- 6. Complete project/ from the skeleton and fix old paths ---------------

step "Completing project/"
if [ -d templates/project ]; then
    cp -rn templates/project/. project/
    say "  added missing files from templates/project/"
else
    say "  templates/project/ not found: the kit has not been updated yet. Update it, then re-run with --no-update."
fi
for f in project/CLAUDE.md project/style-guide.md; do
    [ -f "$f" ] || continue
    sed -i.bak -E \
        -e 's#CLAUDE_loc\.md#project/CLAUDE.md#g' \
        -e 's#style-guide_loc\.md#project/style-guide.md#g' \
        -e 's#tools/project\.conf#project/project.conf#g' \
        -e 's#(^|[^[:alnum:]_/.-])content/reference/#\1reference/#g' \
        -e 's#(^|[^[:alnum:]_/.-])(content|feedback|sources)/#\1project/\2/#g' \
        "$f"
    rm -f "$f.bak"
done
say "  rewrote old paths in project/CLAUDE.md and project/style-guide.md"

# --- 7. Make project/ a repository -----------------------------------------

step "Creating the project repository"
if [ ! -d project/.git ]; then
    git -C project init -q
fi
if [ "$COMMIT" = 1 ]; then
    git -C project add -A
    git -C project commit -q -m "Project imported from the kit's old layout by migrate-to-project-folder.sh" && say "  first commit made in project/"
else
    say "  git init done; nothing committed (--no-commit)"
fi

# --- 8. Check ---------------------------------------------------------------

step "Checking the tools can find the project"
if [ -f tools/lib/project.sh ]; then
    if ( SCRIPT_DIR="$TOP/tools"; source tools/lib/project.sh; load_project_conf && echo "  project.conf loads from $PROJECT_DIR" ); then :; else die "the tools could not load project/project.conf."; fi
else
    say "  tools/lib/project.sh not found: update the kit, then check again."
fi

cat <<EOF

Done. Next:
  - Read project/CLAUDE.md. Sections that repeat the kit's generic instructions
    (content rules, pipeline, validation) can now be deleted: the kit's
    CLAUDE.md provides them.
  - Run one tool end to end, for example:
      tools/test-question.sh project/content/approved/<a file>.xml
  - Backup: $BACKUP
  - Local kit changes, if any, are in 'git stash list'.
EOF
