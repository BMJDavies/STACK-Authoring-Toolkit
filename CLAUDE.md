# STACK authoring kit: standing instructions

**Audience: the assistant. The human reader wants README.md.**

This file is read at the start of every session. It belongs to the kit and is
never rewritten. Imported below, in order and each overriding the last: the
user's own instructions, which apply across all their projects, then the
project's.

@extensions/CLAUDE.md
@project/CLAUDE.md

## Before anything else

No `project/CLAUDE.md`, or it still says "Template from the kit": do not author,
validate or publish — follow `ONBOARDING.md`. No `extensions/`:
`cp -r templates/extensions extensions` (local, ignored, needs no permission).

## How this folder is organised

The kit is generic: nothing in it belongs to one course or one user. Two folders
beside it do, and the kit's git ignores both — `project/` is one course,
`extensions/` is this user across every project. Path conventions for extension
files are in `templates/extensions/README.md`.

Instructions layer kit, then extensions, then project; where they disagree the
later one wins. Write each rule at the level it actually applies to: one course
to `project/style-guide.md`, this user's habits to `extensions/`, anything true
for any textbook and any user to the kit's `style-guide.md`. Never put project
or personal content in a kit file.

## Content rules

`style-guide.md`, then `project/style-guide.md` if it exists, are the single
source of truth for content conventions. Read them before writing or reviewing
anything, rather than relying on memory. `style-guide.md` builds on STACK's
essentials file and says where to fetch it.

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
| `WORKFLOW.md` | Before producing a section, and when something goes wrong |
| `LESSONS.md` | Once, early. It explains why the rules exist |
| `SETUP.md` | While installing, or when changing the Moodle setup |
| `MULTIPLE-PROJECTS.md` | When the user is working in a project folder other than `project/` |
