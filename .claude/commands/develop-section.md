---
description: Develop a single section of interactive content: plan, author, review, report. For a whole chapter use /develop-chapter. Arguments: a free-form section reference (e.g. "Ch5 Section A").
argument-hint: "<chapter> <section> [source path]"
---

You are coordinating one section through three agents in sequence: planner,
author, reviewer. You run in the main conversation, so you can spawn them
directly. A subagent cannot spawn subagents, so this coordination must stay here
rather than being delegated.

Your job is coordination. You do not read XML, write questions, or make detailed
structural decisions: that is what the agents are for. You review their output at
a summary level, make go or no-go calls, and only involve the user when genuine
editorial judgement is needed.

## Step 1: gather the spec

Ask the user, in a single compact turn, for anything not clear from `$ARGUMENTS`
or the conversation:

1. Topic or book identifier
2. Chapter number and name
3. Section letter, or "Test Yourself" / "Mixed"
4. Source path, or the relevant syllabus outcomes
5. Page range, if source-led
6. Output filename under `content/drafts/` (suggest one from the naming
   convention and let the user accept or override)
7. Target quiz: new or existing? If new, what name and which course section
   number? If existing, what quiz id?
8. Any overrides to quiz defaults or special pedagogic notes
9. Anything unusual about this section: past-paper questions, discussion
   questions, starred exercises, figures needing attention

Do not start until you have 1 to 7. Items 8 and 9 can be "none".

## Step 2: dispatch the planner

Invoke the planner agent with the full spec, any constraints the user gave, and
pointers to relevant feedback documents.

Wait for the plan at `content/plans/<section-id>.md`.

If the planner escalates (garbled source, pedagogic ambiguity), surface it to
the user and pause.

## Step 3: review the plan

Read the plan at a high level. You are checking:

- Does it cover everything in the source? Compare the item count.
- Are there flagged items needing the user's input?
- Do the editorial decisions look reasonable?
- Is at least a third of the section randomised?
- Is anything marked unadaptable? That always needs a justification.

You do not evaluate XML structure, PRT design or variable schemes. That is
between the planner and the author.

Good plan: proceed. Flagged items: surface them, get answers, update the plan
file first. Something wrong: send it back to the planner with specific concerns.

For most sections you should be able to approve without involving the user.

## Step 4: dispatch the author

Invoke the author agent with the plan path, the output filename, and the target
quiz.

The author executes, validates, and hands off a green file. If it escalates:
validation issues get up to three rounds; a genuine plan problem means either
adjusting the plan or explaining why it stands.

## Step 5: validate it yourself

Run `tools/test-question.sh <path>`. The author's loop uses the fast API check,
so this is the first real Moodle-import validation. Do not skip it because the
author said it passed.

Foreground, blocking. Do not background and poll.

## Step 6: dispatch the reviewer

Invoke the reviewer with the draft path, the plan path, the section spec and the
author's hand-off notes.

## Step 7: handle revisions

If the reviewer returns REVISE, forward the requests to the author. Cap at two
rounds, then escalate to the user regardless of state.

## Step 8: report

- Pass: path to the approved file, path to the feedback document, one-line
  summary, minor fixes the reviewer made, any new rules for the style guide.
- Revise or escalate: what is blocked and what the user needs to decide.

## First section of a new chapter

Two setup tasks, done once per chapter, before dispatching the author:

1. **Section summary.** Pull the learning objectives from the source intro and
   set them as the Moodle course section summary.
2. **Source document.** Attach the chapter source to the bottom of the section
   with `add-resource.php`.

Check whether they already exist first.

## Progress log

Keep `content/progress.log` current so the user can check status without
interrupting you.

- At the start of a section: `tools/log.sh --clear "MANAGER [<section-id>]: starting"`
- Before dispatching an agent: `dispatching <agent>`
- After a hand-off: `received <agent> hand-off`
- On completion or escalation: `complete` or `escalating: <reason>`

Always include `[<section-id>]`, so entries stay identifiable when more than one
section is in flight. Use `tools/log.sh`, not a raw `echo` with an inline
`$(date)`: the subshell triggers a permission prompt on every new message.

## Guardrails

- Never skip validation.
- Never commit.
- If Docker is down, or the validator fails for infrastructure reasons rather
  than content reasons, pause and say so.
- Respect the reviewer's revision requests. Do not shortcut by rewriting the
  file yourself.
- Do not read or evaluate detailed XML. Trust the agents and review at summary
  level.
- Only escalate genuine editorial judgement calls.
- **Always run agents in the foreground.** The pipeline is sequential and
  concurrent Moodle operations collide.
- **When creating a quiz, always pass `--section=N`.** The default is section 0,
  which is wrong.

## Partial runs

The user can also invoke pieces of this directly:

- "Run the planner on Ch5 Section B"
- "Run the author on content/plans/Ch5_B.md"
- "Run the reviewer on content/drafts/Ch5_B.xml"

Each is available at any time.
