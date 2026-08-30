---
description: Develop a full chapter: plan, approve, then plan/author/review each section, then a final summary. Arguments: a free-form chapter reference (e.g. "Ch4 Further Equations").
argument-hint: "<chapter> [source path]"
---

You are running a full chapter. You work in the main conversation so that you
can spawn the planner, author and reviewer agents directly. A subagent cannot
spawn subagents, so this coordination cannot itself be delegated.

Follow the protocol below. The supervision is the point: verify each step
yourself rather than trusting an agent's report of success.

## Step 1: gather the chapter spec

From `$ARGUMENTS` or by asking:

1. Topic or book identifier
2. Chapter number and name
3. Source path and page range
4. Which sections are already done, if resuming
5. Any special notes (questions to skip, editorial preferences, figures needing
   attention)

Do not start without at least 1 to 3.

Course ids, question bank ids and `--course=` keys are in `tools/project.conf`
and summarised in `CLAUDE.md`. Read them rather than assuming.

## Step 2: session startup

Make sure the container has current copies of the scripts that get copied in:

```bash
docker cp tools/quiz-admin.php <container>:/tmp/qa.php
docker cp tools/add-resource.php <container>:/tmp/add-resource.php
```

The push and validate wrappers ship their own copies each run, so they need
nothing here.

## Step 3: plan the chapter

1. **Read the source.** Identify every section, its page range, and a rough item
   count (key points, examples, development questions, exercises).
2. **Read the style guide** and any feedback documents from earlier sections of
   this chapter.
3. **Produce a chapter plan.** For each section: letter and topic name, source
   range, estimated item count, output filename, quiz (new or existing, with id),
   and any flags.
4. **Plan the Mixed and Test Yourself quizzes** if the user wants them.
5. **Present the plan and wait for approval.** Show it as a compact table. The
   user may adjust section boundaries, flag questions to skip, or add notes.

## Cadence: run to first draft

After approval, run every section (plan, author, validate, review) to completion
without pausing, then hand back one consolidated sign-off list. The plan
approval is the single human checkpoint.

- **Decide routine editorial calls yourself:** figure adaptation, question type
  substitutions within the plan, page grouping, minor wording, diagnostic
  choices. Apply the style guide, pick the sensible default, keep moving. Do not
  stop to ask about these.
- **Batch every editorial flag into ONE end-of-run list**, as numbered "for your
  review" items in the final summary. Not a mid-run interruption.
- **Only stop mid-run for a genuine blocker:** an irreversible and ambiguous
  editorial choice that changes correctness, a source ambiguity where guessing
  wrong corrupts the mathematics, or a hard failure the protocol already
  escalates on (a spawn failing twice, validation failing twice, the reviewer
  exceeding two rounds). Never silently skip a source question.
- If the user says "run unattended", waive even the plan gate and surface the
  plan inside the final summary.

Print a short status line before each checkpoint regardless, so the user can
watch if they want to.

## Step 4: section by section

### Canary check, first section only

Spawn a trivial agent task ("read the first line of `style-guide.md` and report
it"). If spawning is broken, you want to know now, not three sections in.

### Checkpoint 1: the plan

Spawn the planner with the section spec, plus any lessons from the previous
section's feedback document.

On return, verify: no source items skipped (compare against your own estimate),
question type choices are reasonable, flags make sense, at least a third
randomised.

If the spawn fails, retry once, then escalate.

### Checkpoint 2: authoring and validation

Spawn the author with the plan path, output path and target quiz.

On return, verify independently:

- The file exists where it should be.
- Run `tools/test-question.sh <path>` yourself. The author's loop uses the fast
  API check, so this is the first and only Moodle-import validation. It stays
  mandatory. If a second Moodle stack is configured, use `--sandbox` so
  validation does not contend with a push on the main stack.
- If validation fails, send the error back for one fix attempt, then escalate.

Foreground, blocking. Do not background it and poll: that is the most common way
a completed run gets lost.

### Checkpoint 3: review and publish

Create the quiz if it does not exist:

```bash
docker exec <container> php /tmp/qa.php create \
    --courseid=<n> --name="<name>" --section=<N>
```

Spawn the reviewer with the draft path, section spec, quiz id, `--course=` key
and the author's hand-off notes.

On return, verify: the file moved to `content/approved/`, the feedback document
exists, the push succeeded with variants deployed, and the render check came
back clean. If any of those are missing, say what was skipped.

If the reviewer returns REVISE, forward the requests to the author as a new
spawn. Cap at two rounds, then escalate.

### Checkpoint 4: after deployment

Print the quiz URL. Record the section as complete.

### Between sections

- Print a one-line summary.
- List accumulated issues.
- **Carry feedback forward:** read the feedback document the reviewer just
  wrote, and include its "lessons for next section" items in the next author
  spawn.
- **Promote recurring issues:** if the same issue appears in two or more
  sections' feedback, add it to `feedback/lessons-learned.md`. One-off quirks
  stay per-section; patterns get promoted.
- **Compact your context.** You do not need the detailed checkpoint output from
  finished sections, and a long chapter will exhaust your context if you keep it.

## Step 5: wrap up

```
CHAPTER COMPLETE: Chapter <N>: <name>

Sections produced:
- <letter>: <topic>, quiz <id>, <N> items
- ...

For your review:
1. <items needing a decision or a spot check>

Feedback docs written:
- feedback/<section-id>.md
```

Do not commit. That is the user's call.

## Hard rules

- Never skip the canary check.
- Never proceed past planning without approval.
- Independently verify every checkpoint. Do not trust subagent claims.
- Print status updates before each checkpoint.
- Retry a failed spawn once, then escalate. Do not loop.
- Never author XML yourself. Always use the `textbook-author` agent.
- Never commit.
- Run agents in the foreground. The pipeline is sequential and two agents
  touching Moodle at once collide.
- If a subagent appears to have failed, check the disk before concluding
  anything. Agents reported as failed have often carried on and written their
  file successfully.
