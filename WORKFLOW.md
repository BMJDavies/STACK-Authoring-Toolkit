# Using the pipeline

**Audience: your assistant, and you if you want to understand what it is doing.**

Setup is done and the showcase publishes. This is how you actually make
questions.

---

## The shape of it

```
  you                planner            author             reviewer
   |                    |                  |                   |
   +-- section spec --->|                  |                   |
   |              plan document            |                   |
   |                    +----------------->|                   |
   |                              validated XML                |
   |                                       +------------------>|
   |                                                    checks, fixes,
   |                                                    publishes
   |<-------------------- report --------------------------------+
```

Four agents, in `.claude/agents/`:

- **textbook-planner** decides what to build. Reads the source, designs each question,
  writes a plan document. Writes no XML.
- **textbook-author** builds it. Executes the plan, validates its own work, hands off
  only files that pass.
- **textbook-reviewer** checks it. Re-runs validation rather than trusting the author,
  works the checklist, fixes small things itself, publishes.
- **textbook-enricher** improves live content later. A separate quality pass, described
  at the bottom of this file.

Two slash commands drive them: `/develop-section` for one section,
`/develop-chapter` for a whole chapter. If your assistant has no slash
commands, ask in words: "develop Ch5 Section B".

## Why it is split up

You could ask one agent to "write me twenty STACK questions on quadratics". It
will produce twenty plausible questions, several of which are subtly wrong in
ways that are invisible until a student hits them. Wrong in interesting ways: a
marking tree that accepts an unfactorised answer to "factorise this", a test case
that only passes on one random seed, feedback that tells the student the answer.

The split exists because each agent catches a different class of that. The
planner is forced to name the misconception before any code exists. The author
cannot hand off anything that fails validation. The reviewer has fresh eyes and
an explicit checklist. None of them is optional, and the reviewer re-running
validation rather than believing the author is the single most valuable habit in
the whole pipeline.

---

## Your first section

Start small. One section, five or six questions.

```
/develop-section Ch1 Section A
```

It asks for what it needs (source, page range, output filename, target quiz),
then runs the three agents. Most of the time goes on validation.

You will be asked to approve the plan. Read it properly, this once. It is the
cheapest place to catch a misunderstanding: fixing "you have misread what this
section is teaching" costs thirty seconds at the plan stage and half a day after
thirty questions are live.

Things worth checking in a plan:

- Is every source item accounted for? Count them.
- Are at least a third of the questions randomised?
- For each question, does the "common error to diagnose" describe something a
  real student would actually do?
- Do the question types make sense, or has everything become multiple choice
  because that is easiest?

---

## A whole chapter

```
/develop-chapter Ch4 Further Equations
```

Same pipeline, run section by section, with one approval gate at the chapter
plan and then a straight run to first draft. Editorial flags get batched into a
single list at the end rather than interrupting you a dozen times.

If you want it to run without even the plan gate, say "run unattended".

This is the mode to use once you trust the setup. A chapter runs mostly
unattended.

---

## Your to-do list in Moodle

When something in a question needs your decision, the assistant leaves a note
in the question itself as well as in its report. The note is a STACK
`[[todo]]` block at the end of the question description:

```
[[todo tags="kit-review, Ch5A"]]The source asks for "the" eigenvector; any
non-zero multiple is accepted here. Keep, or ask for a normalised one?[[/todo]]
```

Students never see it. The question description is not shown to them, and
STACK replaces the block with an HTML comment in any case. The first tag says
who raised the note: `kit-review` for the reviewer, `kit-enrich` for the
enricher. The second tag is the section.

**Finding them.** Log in to Moodle and open

    <your-moodle>/question/type/stack/adminui/todo.php

The page is also linked as "Find [[todo]] blocks" from STACK's admin page at
`<your-moodle>/question/type/stack/adminui/index.php`. It lists every question
with a todo block in every course where you can edit questions, grouped by tag.
So `Ch5A` is your list for one section, and `kit-review` is everything the
reviewer has raised. Each question name opens STACK's question dashboard, and
the preview icon shows the question as a student sees it.

**What to look for.** Open the question for editing and scroll to the question
description: the note says what the issue is, where it is (a PRT node, a test
case, a sentence of the worked solution) and what the assistant suggests.
While a note remains, STACK's edit form shows a warning at the top, "You have
un-resolved todo blocks in "Question description"", so a note is hard to
overlook. Decide, then either:

- **fix it yourself in Moodle:** make the change, delete the whole
  `[[todo]]…[[/todo]]` block, save, and tell the assistant which quiz you
  edited, so it can pull your edits back into `project/content/approved/`
  ("Pulling hand edits back out of Moodle" in the moodle-operations skill); or
- **reply to the assistant:** delete nothing, and tell it what you decided.
  It changes the XML, removes the note, and re-publishes.

Do one or the other for each question, not both. A re-publish replaces the
question in Moodle with the version on disk, so edits you have made in Moodle
and not yet pulled back are lost.

**Keeping count.** A question is finished when the page no longer lists it.
To see the outstanding notes on disk:

```bash
grep -l '\[\[todo' project/content/approved/*.xml
```

STACK's bulk tester reports any question with a todo block as needing
attention. That is deliberate, and it is one more reminder.

---

## Doing it by hand

The agents are available individually, without the slash commands:

- "Run the planner on Ch5 Section B"
- "Run the author on project/content/plans/Ch5_B.md"
- "Run the reviewer on project/content/drafts/Ch5_B.xml"

Useful when you are fixing one thing, or when a run died partway and you want to
pick it up rather than start again.

If your assistant has no subagents, it runs each role as a separate process
with `tools/run-role.sh` instead. You do not need to run it yourself.

---

## The commands you will actually type

```bash
tools/api-check.sh project/content/drafts/file.xml      # fast check, seconds
tools/render-sweep.sh project/content/drafts/file.xml   # display check, every seed
tools/test-question.sh project/content/drafts/file.xml  # the real gate, minutes
tools/audit-test-inputs.sh project/content/drafts/file.xml
tools/push-review.sh project/content/drafts/file.xml    # preview as a student
tools/push-live.sh project/content/approved/file.xml <quiz-id> --course=<key>
tools/render-check.sh project/content/approved/file.xml # does it look right on screen
```

Full runbook: `.claude/skills/moodle-operations/SKILL.md`.

---

## Where things live

```
In the kit (generic, shared):
reference/                   showcase.xml and the pattern prototypes
tools/                       scripts
templates/project/           the skeleton a new project is created from

In your project (project/, its own git repository):
project/AGENTS.md            what the project is: sources, locale, Moodle site
project/project.conf         all the Moodle ids
project/style-guide.md       rules for this project only
project/content/drafts/      the author writes here (naming is your project's
                             to choose; record it in project/AGENTS.md)
project/content/approved/    the reviewer promotes here; the source of truth
project/content/plans/       plan documents
project/feedback/            one document per section, plus lessons-learned.md
project/sources/             your source material
```

`project/content/approved/` is the master copy of your work. Commit it, in the
project's repository. Moodle is a
rendering target that can be rebuilt from these files; the reverse is not true.

---

## The feedback loop

The reviewer writes `project/feedback/<section-id>.md` after every section, with a
"lessons for next section" list. Whoever is coordinating reads it and feeds
those lessons into the next author spawn, which is why section 5 comes out
better than section 1.

When the same issue turns up in two or more sections, it stops being a lesson
and becomes a rule: promote it to `project/feedback/lessons-learned.md`, and migrate it
into `style-guide.md` at the next natural boundary.

Do not edit `style-guide.md` mid-chapter. Changing the conventions while a
chapter is in production makes author and reviewer behaviour drift between
sections, and you end up with material that is subtly inconsistent in ways that
are tedious to unpick.

---

## Enrichment: the later quality pass

Once a chapter is live and settled, the enricher goes back over it. Its job is
different from the reviewer's:

- Fill false-branch feedback that is empty or that gives the answer away
- Add diagnostic nodes where a named misconception is worth catching
- Make general feedback name the concept rather than just showing algebra
- Hunt adversarially for grading defects: any answer that would be marked
  wrongly, in either direction

Run it with a different model from the one that authored and reviewed the file.
Independence is the point, and a second opinion from the same model is not a
second opinion. The agent is configured for Sonnet for exactly this reason.

Cap it at three concurrent enrichers on substantive files. Maxima contention
scales badly: three each get a session and finish, four or more queue up and one
times out.

---

## Things that will go wrong, and what they mean

**A question passes validation but marks students wrong.** Almost always a
hardcoded test input in a randomised question: the test value matches seed 1, so
the validator is happy, while every other seed is broken. Run
`tools/audit-test-inputs.sh`.

**A test fails with an EMPTY score rather than a wrong one.** The question never
set up. Look at the question variables block, not the marking tree. A common
cause is `questionsimplify=0` combined with list indexing.

**A question fails on scattered seeds but passes on others, with blank scores.**
That is a CAS timeout under load, not a defect. A real defect fails the same test
on every seed with an actual wrong score. Isolate the question into its own file
and re-run before changing anything.

**A push seems to have worked but the quiz has the wrong content.** Two pushes
ran at once. Verify imported question NAMES after every push, not just the count:
a wrong-content import keeps the count identical, which is what makes it easy to
miss.

**An agent reports that it failed or was rejected.** Check the disk before
believing it. Agents reported as dead have frequently carried on and written
their file successfully.

**Validation seems to have hung.** It produces no output while running, so
silence is normal and can last a long time. Wait. Do not background it and poll:
that is the most reliable way to lose a run that was about to succeed.

Silence is also what a genuinely wedged run looks like, so if you need to tell
them apart, check for a live process rather than guessing from elapsed time:

```bash
docker exec <webserver-container> ps -eo pid,etime,cmd | grep -E 'php|maxima'
```

Work in progress shows a process. A wedged run shows one with a long elapsed
time and nothing to show for it, or none at all.

---

## Working with an AI assistant, if it is new to you

- `AGENTS.md` is read at the start of every session, together with
  `project/AGENTS.md`, which it reads. Your project's standing instructions go
  in `project/AGENTS.md`. Keep it short, because it costs context every time.
- Correct it when it goes wrong, in the moment. "Don't do X, do Y instead" in
  the middle of a task works, and is much better than letting it finish
  something wrong.
- Ask it to explain before it acts, while you are learning: "tell me what this
  command does before running it".
- Paste errors verbatim, with the command that produced them. The exact text is
  the useful part, and paraphrasing throws it away.
- If a long run goes off the rails, stop it. Escape interrupts. Nothing is
  precious: the plan documents and the XML are on disk.
- Do not let it commit. Read the diff and commit yourself. That habit is what
  keeps you in control of a codebase an agent is writing.
