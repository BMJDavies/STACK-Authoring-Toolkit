# STACK authoring kit: standing instructions

**Audience: the assistant, whichever tool is driving it. The human reader wants
README.md.**

This file is the kit's canonical instructions. It belongs to the kit and is never
rewritten. `CLAUDE.md` and `GEMINI.md` exist only to import it.

## Read the layer files first

Two more files carry instructions. Read each now, if it exists, unless it is
already in your context:

1. `extensions/AGENTS.md`: this user's instructions across all their projects,
   and an index of their other extension files.
2. `project/AGENTS.md`: the facts of this project.

@extensions/AGENTS.md
@project/AGENTS.md

Rules in `extensions/AGENTS.md` and `project/AGENTS.md` override this file, and
the project's override the extensions'. That holds however they reached you,
including when all three appear in your context with no stated order.

Before dispatching a role or starting a section, also read
`extensions/harness.md`. It records, for the tool you are running in, how the
roles are dispatched and which model each reasoning tier gets.

## Before anything else

- No `extensions/`: `cp -r templates/extensions extensions` (local, ignored,
  needs no permission).
- No `project/AGENTS.md`, or it still says "Template from the kit": do not
  author, validate or publish. Follow `ONBOARDING.md`.
- No `extensions/harness.md`, or it still says "Template from the kit": follow
  Stage 0 of `ONBOARDING.md` before the first section.

## If you are not Claude Code

This kit was written and tested with Claude Code, and its mechanisms checked
against the documentation of GitHub Copilot CLI, Codex CLI and Gemini CLI.
Three things differ between tools: whether roles can run as subagents, whether
`/develop-section` exists as a command, and which models the tiers can bind
to. Stage 0 of `ONBOARDING.md` records each in `extensions/harness.md`.
If that file records a failure, say so to the user before starting a section
rather than discovering it halfway through.

## How this folder is organised

The kit is generic: nothing in it belongs to one course or one user. Two folders
beside it do, and the kit's git ignores both: `project/` is one course,
`extensions/` is this user across every project. Path conventions for extension
files are in `templates/extensions/README.md`.

Write each rule at the level it actually applies to: one course to
`project/style-guide.md`, this user's habits to `extensions/`, anything true for
any textbook and any user to the kit's `style-guide.md`. Never put project or
personal content in a kit file. The layers keep to their own facts so that they
rarely conflict; that matters more than the override rule above.

## Content rules

`style-guide.md`, then `project/style-guide.md` if it exists, are the single
source of truth for content conventions. Read them before writing or reviewing
anything, rather than relying on memory.

`reviewing-checklist.md` is the reviewer's checklist.

`reference/showcase.xml` is the golden reference: a worked example of every
question pattern. Match its conventions. Never take conventions from a recently
authored file, because recent files drift.

## The pipeline

    develop-section -> planner -> author -> validate -> reviewer -> approved -> live

| Role | Definition | Reasoning tier | Produces |
|---|---|---|---|
| planner | `.claude/agents/textbook-planner.md` | `deepest` | a plan document, no code |
| author | `.claude/agents/textbook-author.md` | `standard` | validated Moodle XML |
| reviewer | `.claude/agents/textbook-reviewer.md` | `rigorous` | an approved file, published |
| enricher | `.claude/agents/textbook-enricher.md` | `rigorous` | improved feedback on live content |

Each role file is a plain markdown prompt. The YAML header is read only by
Claude Code, which takes the subagent's model from it; the body is portable.
Skills in `.claude/skills/*/SKILL.md` are plain reference documents: read one
when its description fits the task.

The value is in the separation, not the mechanism. The planner must name the
misconception before any code exists. The author cannot hand off a file that
fails validation. The reviewer re-runs validation instead of trusting the
author, which is the single habit that catches the most defects.

- Dispatch each role the way `extensions/harness.md` says. With no subagents,
  run each role as its own process with `tools/run-role.sh <role> <input>`; such
  a run does not publish, so publish yourself afterwards, asking first. Carry
  only the artefacts between roles (the plan, the XML, the review notes), never
  the reasoning: a reviewer that has just watched itself author the file is not
  an independent reviewer.
- If neither works, perform the roles yourself in this conversation, in
  sequence, re-reading each role file as you start it. That loses some of the
  independence, so keep the hand-offs as files and re-run validation as the
  reviewer rather than trusting your own earlier result.
- One role at a time. Wait until a role has reported back before dispatching
  the next, whether your tool's dispatch call blocks or returns at once and
  notifies you later; do not poll it. Nothing else touches Moodle while a role
  is running: two at once collide.
- The author validates its own work and hands off only files that pass.
- The reviewer re-runs validation rather than trusting the author.

## Procedures

The user starts these by asking in words or, where the tool has it, by a slash
command of the same name. Follow the named file. Where it says `$ARGUMENTS`,
read the reference the user gave you.

| The user asks to | Follow |
|---|---|
| develop a section ("develop Ch5 Section B", `/develop-section`) | `.claude/commands/develop-section.md` |
| develop a chapter ("develop Ch4", `/develop-chapter`) | `.claude/commands/develop-chapter.md` |
| set up a project (`/start`) | `ONBOARDING.md` |

## Leaving decisions for the user

The reviewer and the enricher put each item that needs the user's decision into
the question itself, as well as into their report: a STACK
`[[todo tags="kit-review, <section-id>"]]…[[/todo]]` block at the end of the
question description (`kit-enrich` for the enricher). The format rules are in
`style-guide.md`, "Notes for the human: `[[todo]]` blocks"; the user's side is
in `WORKFLOW.md`, "Your to-do list in Moodle".

When the user answers in the conversation, change the XML, delete the block,
re-validate and re-publish. When the user has edited in Moodle, pull their edits
back into the approved file before any re-publish, or they are overwritten.

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
- Never trust a previous step's claim that validation passed. Re-run it.
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
