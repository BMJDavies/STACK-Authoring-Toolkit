# STACK authoring kit: standing instructions

**Audience: the assistant. The human reader wants README.md.**

This file is read automatically at the start of every session in this folder. It
belongs to the kit, is the same for every project, and is never rewritten. The
project's own instructions are in `project/CLAUDE.md`, imported here:

@project/CLAUDE.md

## Before anything else

If `project/CLAUDE.md` does not exist, no project has been configured. Do not
author, validate or publish anything. Read `ONBOARDING.md` and follow it.

## How this folder is organised

This folder is the kit: a pipeline for writing STACK questions for Moodle with an
AI assistant. Everything about one particular textbook or course lives in
`project/`, which is a separate git repository that the kit ignores.

| Kit (generic, shared) | Project (`project/`, specific to one course) |
|---|---|
| `.claude/`: agents, commands, skills | `CLAUDE.md`: what the project is, sources, locale, Moodle site, STACK version stamp, citation |
| `tools/`: shell and PHP tools | `project.conf`: Moodle ids and courses |
| `style-guide.md`, `reviewing-checklist.md` | `style-guide.md`: optional rules for this project only |
| `reference/`: the showcase of every question pattern | `content/plans/`, `content/drafts/`, `content/approved/`, `content/progress.log` |
| `WORKFLOW.md`, `SETUP.md`, `ONBOARDING.md`, `LESSONS.md` | `feedback/`: section reviews |
| `moodle-theme/` | `sources/`: the source material |

Never put project-specific content in a kit file. A rule for one course goes in
`project/style-guide.md`; a rule that would hold for any textbook goes in the
kit's `style-guide.md`.

`STACK_PROJECT` may name a different project folder (for example
`STACK_PROJECT=ladr`) when several projects sit side by side. Everything below
says `project/`; read it as that folder.

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
