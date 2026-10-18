# Agent instructions

**Audience: the assistant, whichever tool is driving it.**

This kit was built for and tested with Claude Code. The content is not
tool-specific and can be driven by another assistant; the orchestration is. This
file is the portable entry point.

## Start here

If `project/CLAUDE.md` does not exist yet, follow `ONBOARDING.md`
before doing anything else.

## What the pipeline is

Four roles, run in sequence, each with a defined hand-off:

| Role | Definition | Produces |
|---|---|---|
| planner | `.claude/agents/textbook-planner.md` | a plan document, no code |
| author | `.claude/agents/textbook-author.md` | validated Moodle XML |
| reviewer | `.claude/agents/textbook-reviewer.md` | an approved file, published |
| enricher | `.claude/agents/textbook-enricher.md` | improved feedback on live content |

Those four files are plain markdown role prompts. The YAML header at the top of
each is Claude Code metadata; the body below it is portable. An assistant on
another tool can be given the body of a file as its instructions for that step.

The value is in the separation, not the mechanism. The planner must name the
misconception before any code exists. The author cannot hand off a file that
fails validation. The reviewer re-runs validation instead of trusting the author,
which is the single habit that catches the most defects. An assistant working
alone through all four steps in one conversation will approximate this, but the
independence is what makes it work, so preserve the sequence and the hand-offs
even when one model performs every role.

## If your tool has no sub-agents

Run the four roles as four separate conversations rather than four turns of one.
Carry only the artefacts between them: the plan document, the XML file, the
review notes. Do not carry the reasoning. A reviewer that has just watched itself
author the file is not an independent reviewer.

## Leaving decisions for the user

The reviewer and the enricher put each item that needs the user's decision into
the question itself, as well as into their report: a STACK
`[[todo tags="kit-review, <section-id>"]]…[[/todo]]` block at the end of the
question description (`kit-enrich` for the enricher). Students never see it, and
STACK lists every such question, grouped by tag, at
`<moodle>/question/type/stack/adminui/todo.php`, so the user works through them
in Moodle. The format rules are in `style-guide.md`, "Notes for the human:
`[[todo]]` blocks". The user's side is in `WORKFLOW.md`, "Your to-do list in
Moodle".

When the user answers in the conversation, change the XML, delete the block,
re-validate and re-publish. When the user has edited in Moodle, pull their edits
back into the approved file before any re-publish, or they are overwritten.

## What the assistant must not do

- Background a validation run and poll for it. It is slow and silent by design.
- Trust a previous step's claim that validation passed. Re-run it.
- Commit unless the user has asked.
- Write to Moodle without asking.

## Reference

`style-guide.md` is authoritative for content conventions and is where the
hard-won rules live; `project/style-guide.md`, if present, adds rules for one
project. `reviewing-checklist.md` is what the reviewer works through.
`LESSONS.md` explains why the rules exist and is worth reading once, early.
