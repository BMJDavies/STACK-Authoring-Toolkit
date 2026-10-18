# Upgrading

**Audience: you, the human, if you set up a project with an earlier version of
the kit.** Newest change first. If your kit predates both, do the older one
first.

## Instruction files renamed to `AGENTS.md` (October 2026)

**If you are the assistant, sent here because `project/CLAUDE.md` or
`extensions/CLAUDE.md` exists: do not run onboarding.** Explain the change below
and offer to make it with the user.

The kit's standing instructions moved from `CLAUDE.md` to `AGENTS.md`, the file
most assistant tools read, so that tools other than Claude Code can drive the
kit. The kit's `CLAUDE.md` is now a one-line stub that imports `AGENTS.md`. Your
project's and your extensions' instruction files are renamed to match, and the
kit no longer loads them under the old name.

`git pull` works as usual: the kit tracks neither file. Then rename them:

```bash
git -C project mv CLAUDE.md AGENTS.md      # plain mv if project/ is not a repository
mv extensions/CLAUDE.md extensions/AGENTS.md
```

If `project/AGENTS.md` already exists and still says "Template from the kit"
(the older migration below adds one), delete it before the rename.

Then:

1. Look for any remaining mentions of the old name and change them to
   `AGENTS.md`: `grep -n CLAUDE project/*.md extensions/*.md`.
2. Start a session. The assistant finds `extensions/harness.md` missing and
   fills it in (`ONBOARDING.md`, Stage 0), recording what your tool can do.
   It needs nothing from you.

Nothing in Moodle changes, and nothing else in `project/` moves.

## The project/ layout (October 2026)

Earlier versions kept your project inside the kit's own folders: onboarding
rewrote the kit's `CLAUDE.md`, your Moodle ids were in `tools/project.conf`, and
your work was in `content/`, `feedback/` and `sources/`. Now your project lives in
`project/`, a separate git repository inside the kit, and the kit never changes
it. `README.md` ("The kit and your project") shows the new layout.

**If you are the assistant, sent here because `tools/project.conf` or
`CLAUDE_loc.md` exists at the kit root: do not run onboarding.** It would create
a fresh `project/` from the template, leaving an empty second project beside the
real one while the user's work stayed stranded in the kit's own folders. Explain
the reorganisation and offer to walk them through the migration below, starting
with the dry run.

### Why a plain `git pull` will not work

Your kit folder has local changes to two files the kit tracks, `CLAUDE.md` and
`tools/project.conf`, and the update changes both. `git pull` will stop with
"Your local changes would be overwritten". Do not resolve that by taking the
kit's versions: your project instructions and Moodle ids would be lost.

### Upgrade in three commands

From the root of your kit folder, without pulling first:

```bash
git fetch origin
git show origin/main:tools/migrate-to-project-folder.sh | bash -s -- --dry-run
git show origin/main:tools/migrate-to-project-folder.sh | bash
```

The first fetches the update without applying it. The second runs the new
migration script in dry-run mode: it lists what it would move, copy and stash,
and changes nothing. Read that list. The third does it.

The script:

1. checks that this is a kit folder with the old layout and no `project/` yet;
2. writes a backup archive of everything it touches to your home folder;
3. moves your project into `project/`: your rewritten `CLAUDE.md` becomes
   `project/CLAUDE.md`, `tools/project.conf` becomes `project/project.conf`, and
   your plans, drafts, approved questions, feedback and source material follow;
4. stashes any local changes to kit files (`git stash`), so the update cannot
   conflict, and tells you if there were any;
5. updates the kit with a fast-forward `git pull`;
6. adds any missing files to `project/` from `templates/project/`, and rewrites
   old paths in `project/CLAUDE.md`;
7. makes `project/` a git repository and commits your project to it;
8. checks that the tools can find `project/project.conf`.

It never touches Moodle: question names and quiz ids do not change, so your
published quizzes carry on as they are. The kit folder keeps its path, so your
local settings and your assistant's memory for this folder carry over too.

### Afterwards

- **Read `project/CLAUDE.md`.** It is your old project instructions with the
  paths updated. Sections that repeat the kit's generic instructions (content
  rules, the pipeline, validation commands) can be deleted: the kit's
  `CLAUDE.md` now provides them and imports yours.
- **Run one tool end to end**, for example
  `tools/test-question.sh project/content/approved/<a file>.xml`.
- **If you had changed kit files** (the script lists them), they are in
  `git stash list`. Look with `git stash show -p`. A change that was really about
  your project belongs in `project/style-guide.md` or `project/CLAUDE.md`; a
  change to the kit itself is worth proposing upstream. Drop the stash when you
  are sure.
- **Commit your project work in `project/`** from now on. The kit's own
  repository is only for changes to the kit.

### Options

| Option | Use it when |
|---|---|
| `--dry-run` | Always first: shows the plan and changes nothing |
| `--checkout BRANCH` | Your kit folder is on a branch other than the one to update, for example an experimental branch: the script moves the project out, sets your branch's changes aside, and switches to `BRANCH` |
| `--no-update` | You want to update the kit yourself; the script only moves the project out |
| `--no-commit` | You want to make the project's first commit yourself |
| `--backup-dir DIR` | The backup should go somewhere other than your home folder |

### If something goes wrong

The script stops at the first problem and says what to do. Your project is
always in at least one of: its original place, `project/`, the backup archive,
or the stash. If the update itself does not fast-forward (for example because
your branch has its own commits), the script stops after moving the project out;
update the kit by hand, then re-run with `--no-update` to finish.

If you prefer to stay on the old layout for now, the last version of the kit
before this change is tagged `v1-flat-layout`.

### The extensions folder

Later versions of the kit add `extensions/`, for your own instructions across
projects (`README.md`, "The kit and your project"). There is nothing to migrate:
the assistant creates it from `templates/extensions/` at the start of the next
session, and `git pull` adds the matching line to the kit's `.gitignore`. If you
already keep personal instruction files somewhere else, move them in and list
them in `extensions/AGENTS.md`.
