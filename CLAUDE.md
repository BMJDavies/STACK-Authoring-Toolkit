# STACK authoring kit: standing instructions

**Audience: the assistant. The human reader wants README.md.**

This file is read automatically at the start of every session in this folder. It
belongs to the kit, is the same for every project, and is never rewritten. The
user's personal instructions, which apply across all their projects, are in
`extensions/CLAUDE.md`. The project's own instructions are in
`project/CLAUDE.md`. Both are imported here, in that order:

@extensions/CLAUDE.md
@project/CLAUDE.md

## Before anything else

If `extensions/` does not exist, copy the kit's template into place. It is
local, ignored by the kit's git, and overwrites nothing, so it needs no
permission:

    [ -d extensions ] || cp -r templates/extensions extensions

If `project/CLAUDE.md` does not exist, no project has been configured. If it
still begins with the comment "Template from the kit", onboarding was started but
not finished. In either case do not author, validate or publish anything: read
`ONBOARDING.md` and follow it, resuming where it left off.

**Old layout.** If `project/CLAUDE.md` does not exist but there are signs of a
project set up with an earlier version of the kit (`tools/project.conf` exists,
`CLAUDE_loc.md` exists, or `content/approved/` holds XML files), do not run
onboarding: that would start a second project beside the real one. Explain the
reorganisation to the user, point them to `UPGRADING.md`, and offer to run
`tools/migrate-to-project-folder.sh --dry-run` with them.

## How this folder is organised

This folder is the kit: a pipeline for writing STACK questions for Moodle with an
AI assistant. Everything about one particular textbook or course lives in
`project/`, and the user's own cross-project additions live in `extensions/`.
Both are folders the kit ignores, and each may be its own git repository. Both
are created by copying a skeleton from `templates/`.

| Kit (generic, shared) | Extensions (`extensions/`, this user, every project) | Project (`project/`, specific to one course) |
|---|---|---|
| `.claude/`: agents, commands, skills | `CLAUDE.md`: an index of the user's extension files and when to read them | `CLAUDE.md`: what the project is, sources, locale, Moodle site, STACK version stamp, citation |
| `tools/`: shell and PHP tools | Any other files the user adds: instructions, guidance, templates | `project.conf`: Moodle ids and courses |
| `style-guide.md`, `reviewing-checklist.md` | | `style-guide.md`: optional rules for this project only |
| `reference/`: the showcase of every question pattern | | `content/plans/`, `content/drafts/`, `content/approved/`, `content/progress.log` |
| `WORKFLOW.md`, `SETUP.md`, `ONBOARDING.md`, `LESSONS.md` | | `feedback/`: section reviews |
| `moodle-theme/`, `templates/` | | `sources/`: the source material |

Never put project-specific or personal content in a kit file. A rule for one
course goes in `project/style-guide.md`; a habit or workflow of this user's that
spans courses goes in `extensions/`; a rule that would hold for any textbook and
any user goes in the kit's `style-guide.md`.

Instructions are layered: kit, then extensions, then project, and where they
disagree the later one wins. Read extension files when the user asks for them or
when `extensions/CLAUDE.md` says to. Paths in extension files are relative to
the kit root.

The tools accept `STACK_PROJECT` to name a different project folder. This file
still imports `project/CLAUDE.md` for the project, so if the user says they are working in
another folder, read that folder's `CLAUDE.md` and use its paths instead of
`project/` (see `MULTIPLE-PROJECTS.md`).

## Content rules

`style-guide.md`, then `project/style-guide.md` if it exists, are the single
source of truth for content conventions. Agents read them before writing or
reviewing anything, rather than relying on memory. Where they disagree, the
project's file wins for that project.

`reviewing-checklist.md` is the reviewer's checklist.

`reference/showcase.xml` is the golden reference: a worked example of every
question pattern. Match its conventions. Never take conventions from a recently
authored file, because recent files drift.

## The pipeline

/develop-section -> planner -> author -> validate -> reviewer -> approved -> live

- Agents are in `.claude/agents/`, skills in `.claude/skills/`.
- Agents run in the foreground, never backgrounded. The pipeline is sequential,
  and two agents touching Moodle at once collide.
- The author validates its own work and hands off only files that pass.
- The reviewer re-runs validation rather than trusting the author.

## Validation

    tools/api-check.sh <file>       fast inner loop, seconds per question
    tools/test-question.sh <file>   the real gate, before anything goes live

Every question embeds at least two test cases, one correct and one wrong.
Without them the marking logic is untested, and a broken question is
indistinguishable from a working one.

## Moodle

All ids live in `project/project.conf`; never hardcode one.
Publish with `tools/push-live.sh <xml> <quiz-id> --course=<key>`.
Runbook: `.claude/skills/moodle-operations/`.

## Standing rules

- Never run validation in the background and poll for it. It is slow, produces
  no output while running, and blocking is correct.
- Never commit on the user's behalf, in either repository, unless they have said
  to.
- The XML under `project/content/` is the master copy. Moodle is a rendering
  target.
- Ask before doing anything that writes to Moodle.

## Where to read further

| File | Read it when |
|---|---|
| `ONBOARDING.md` | Now, if `project/CLAUDE.md` does not exist |
| `WORKFLOW.md` | Before producing a section, and when something goes wrong |
| `style-guide.md` | Before writing or reviewing any question |
| `reviewing-checklist.md` | When reviewing |
| `LESSONS.md` | Once, early. It explains why the rules exist |
| `SETUP.md` | While installing, or when changing the Moodle setup |
| `AGENTS.md` | If you are not Claude Code: the portable description of the pipeline |
