# STACK question authoring kit

A working pipeline for writing STACK questions with Claude Code: agents, skills,
tooling, content rules and a Moodle theme. Everything here has been used in
anger to produce around 600 questions across three books.

It is set up to be generic. Nothing in it assumes a particular subject, level or
syllabus. You point it at your own material and your own Moodle.

## This repo is the source of truth

Anything generic lives here and is edited here. Projects that use the kit
(SMP, Kenya, and others) hold only an appendix of their own conventions, and
override an agent by name where they genuinely need to.

When this repo and a project copy disagree about a generic rule, this repo wins.
A rule that turns out to be generic gets promoted into this repo rather than
copied sideways between projects. That single convention is what stops the
pipeline fragmenting into parallel versions again.

A working copy of the agents, commands and skills is deployed into
`~/.claude/` so the pipeline is available in every project on the machine.
Deploy by copying, never by symlink.

---

## Read these in this order

| File | What it is | When |
|------|-----------|------|
| **README.md** | This. What is in the box and how the pieces fit. | Now, 10 minutes |
| **SETUP.md** | Docker, Moodle, STACK, Maxima, courses, theme, from nothing. | First, half a day |
| **WORKFLOW.md** | How to actually make questions once it runs. | Before your first section |
| **LESSONS.md** | What cost us time, and why the rules are the rules. | Once now, again when something baffles you |
| **style-guide.md** | The content rules. The agents' authority. | Skim now, edit as you go |
| **reviewing-checklist.md** | What the reviewer checks. | Skim now |
| **CLAUDE.md** | Project instructions. Has placeholders to fill in. | During setup |

---

## What is in the box

```
README.md                  start here
SETUP.md                   from nothing to a working install
WORKFLOW.md                using the pipeline day to day
LESSONS.md                 what we learned the hard way
CLAUDE.md                  project instructions (fill in the placeholders)
style-guide.md             content rules: the single source of truth
reviewing-checklist.md     the reviewer's checklist

.claude/
  agents/                  planner, author, reviewer, enricher
  commands/                /develop-section, /develop-chapter
  skills/                  question types, JSXGraph, Moodle ops, quiz settings,
                           turning source material into a work order

tools/
  project.conf             the one file you edit: all your Moodle ids
  setup-workspace.php      creates the sandbox and review courses
  create-live-course.php   creates a course to publish into
  api-check.sh             fast validation, seconds per question
  test-question.sh         full validation, the gate before publishing
  audit-test-inputs.sh     catches the silent wrong-marking bug class
  push-review.sh           preview a draft as a student
  push-live.sh             publish
  render-check.sh          does it actually look right on the page
  quiz-admin.php           create and configure quizzes
  ...and a few smaller utilities

content/
  reference/showcase.xml   ten questions, one per pattern: the golden reference
  reference/prototypes/    working templates for the harder patterns
  drafts/ approved/ plans/ where work happens

moodle-theme/textbook/     a Boost child theme that makes quizzes read like
                           textbook pages rather than tests
```

---

## How it works, in one page

You write nothing by hand. You describe a section; four agents produce it.

**planner** reads your source material and designs the section: every question's
type, its variables and ranges, its marking tree, its test cases, and
specifically what misconception each question should diagnose. It writes a plan
document and no code.

**author** executes the plan. It writes the Moodle XML, embeds test cases,
validates its own work, and only hands off files that pass.

**reviewer** checks it. It re-runs validation rather than trusting the author,
works through the checklist, fixes small things itself, sends substantive
problems back, and publishes what passes.

**enricher** goes back over live content later as an independent quality pass:
better feedback, more diagnostics, and an adversarial hunt for questions that
would mark a student wrongly.

Two slash commands drive them: `/develop-section` and `/develop-chapter`.

### Why bother with four agents

You could ask one agent for twenty questions on quadratics. You would get twenty
plausible questions, several subtly wrong in ways nothing surfaces until a
student hits them: a marking tree that accepts an unfactorised answer to
"factorise this", a test case that only passes on one random seed, feedback that
gives the answer away.

Splitting the job is what catches those. The planner has to name the
misconception before any code exists. The author cannot hand off a file that
fails validation. The reviewer has fresh eyes and an explicit list. The single
most valuable habit in the whole thing is the reviewer re-running validation
instead of believing the author.

---

## What this does not do

Worth knowing before you start, so you build the human checks into your process
rather than discovering the gap later.

The pipeline can confirm a question imports, sets up, and grades its own test
cases correctly. It cannot tell you whether a figure is the right picture,
whether the feedback teaches anything, whether the difficulty progression makes
sense, whether a distractor is accidentally true, or whether you are asking the
right question. Those need your eyes. The reviewer flags them; it does not
resolve them.

There is also a specific bug class the tooling exists to catch but cannot catch
alone: a question that validates cleanly and marks students wrongly. LESSONS.md
opens with it. It is worth ten minutes.

---

## Realistic expectations

- Setup: half a day, most of it downloads. Do it with Claude Code alongside you.
- First section: 20 to 40 minutes, most of it validation. Start with one small
  section, not a chapter.
- After that: a chapter is a few hours, largely unattended.
- Validation is slow and produces no output while running. That is normal. Let
  it block; do not background it and poll.

---

## If you are new to Claude Code

You do not need to understand the internals to use this. A few things that make
the difference:

- `CLAUDE.md` is read at the start of every session. It is where standing
  instructions go. Keep it short: it costs context on every request.
- Correct it in the moment. "Stop, do Y instead" mid-task works, and is far
  better than letting it finish something wrong.
- Ask it to explain before acting while you are learning: "tell me what this
  command does before you run it".
- Paste errors verbatim with the command that produced them. The exact text is
  the useful part.
- Do not let it commit. Read the diff and commit yourself.
- Escape interrupts a run. Nothing is precious; the plans and XML are on disk.

Good first move: open Claude Code in this folder and say *"Walk me through
SETUP.md step by step. Explain each command before you run it."*

---

## Adapting it to your project

Most of the kit is subject-neutral. The places where you will want to make it
yours:

- `CLAUDE.md`: what the project is, where questions come from, how you want to
  be worked with. Fill in every placeholder.
- `style-guide.md`: the rules are the interesting part. The prose-anchor table
  and the common-error table are examples of a form you should extend with your
  own topics. There is a section at the end on adapting to a different level.
- `tools/project.conf`: your Moodle ids. The only place ids should live.
- `moodle-theme/textbook/scss/textbook.scss`: colours and typography are SCSS
  variables at the top. The CSS class prefix is `tb-`; rename it if you like, but
  rename it in the theme, the style guide and the showcase together.
- `.claude/skills/source-material/`: the marker map for your own source
  material, if you are working from an existing text.

The one thing worth not changing until you have reason to: the four-agent split
and the reviewer re-validating. That structure is where the reliability comes
from.

---

## Provenance and licensing

The agents, skills, tooling and documentation are ours to pass on freely. The
theme is a Boost child theme and inherits Moodle's GPL v3; the copyright header
is a placeholder for you to fill in.

The reference XML files under `content/reference/` are our own authored
exemplars, included as convention references. The mathematics in them is
standard school and early-undergraduate material.

STACK itself is GPL and maintained by Chris Sangwin and colleagues at Edinburgh.
The pedagogic patterns in the proof and question-type sections draw on Sangwin's
book and on Bickerton and Sangwin (2022) on proof comprehension. Both are worth
reading if you are going to spend serious time on this.
