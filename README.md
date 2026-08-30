# STACK question authoring kit

**Audience: you, the human. Everything else here is written to be read by your
assistant.**

A pipeline for writing STACK questions for Moodle with an AI assistant: agents,
skills, tooling, content rules and a theme. It has been used to produce
approximately 600 questions across three books.

It assumes no particular subject, level or syllabus. You point it at your own
material and your own Moodle.

This folder becomes your project. Everything lives inside it, including the agent
definitions in `.claude/`. Nothing is installed elsewhere on your machine, so the
whole thing is undone by deleting the folder. If you later run several authoring
projects and want them to share one pipeline, see `MULTIPLE-PROJECTS.md`.

It was built for and tested with Claude Code. The content, the style guide, the
checklist and the tools are not tool-specific, and `AGENTS.md` explains how to
drive the same pipeline from another assistant.

## Getting started

Open your assistant in this folder and type `/start`, or ask it to follow
`ONBOARDING.md`. It will work out what you already have installed, ask what it
cannot detect, and configure the kit for your project.

You are well served by opening these files and understanding what is in them, and
you will get more out of the kit if you do. But you do not need to read any of
them to get started.

## What this contains, and what it does not

No teaching content of any kind: no publisher material, no source documents, no
authored question banks. The only XML here is the showcase and the pattern
prototypes, which exist to demonstrate conventions and were written for that
purpose.

What it does contain is the method: agents, commands, skills, tooling, the style
guide and the reviewing checklist, plus a Moodle theme.

`tools/project.conf` ships with the default local development database password
used by moodle-docker, so that a fresh copy works immediately. Repoint it before
using the kit against any server that matters.

---

## What is in the box

```
README.md                  this file, for you
ONBOARDING.md              setup interview, for your assistant
AGENTS.md                  portable entry point for a non-Claude assistant
CLAUDE.md                  project instructions, rewritten during setup
SETUP.md                   from nothing to a working install
WORKFLOW.md                using the pipeline day to day
LESSONS.md                 why the rules are the rules
style-guide.md             content conventions: the agents' authority
reviewing-checklist.md     what the reviewer works through
MULTIPLE-PROJECTS.md       optional: sharing one kit across several projects

.claude/
  agents/                  textbook-{planner,author,reviewer,enricher}
  commands/                /start, /develop-section, /develop-chapter
  skills/                  question types, JSXGraph, Moodle operations,
                           quiz settings, reading source material

tools/
  project.conf             the one file you edit: all your Moodle ids
  setup-workspace.php      creates the sandbox and review courses
  create-live-course.php   creates a course to publish into
  api-check.sh             fast validation, seconds per question
  test-question.sh         full validation, the gate before publishing
  audit-test-inputs.sh     catches the silent wrong-marking defect class
  push-review.sh           preview a draft as a student sees it
  push-live.sh             publish
  render-check.sh          confirms a question renders correctly on the page
  quiz-admin.php           create and configure quizzes
  ...and several smaller utilities

content/
  reference/showcase.xml   ten questions, one per pattern: the golden reference
  reference/prototypes/    working templates for the harder patterns
  drafts/ approved/ plans/ where work happens

moodle-theme/textbook/     a Boost child theme that makes quizzes read as
                           textbook pages rather than as tests
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

Good first move: type `/start`, then ask it to explain each command before it
runs it. Working through setup with it narrating is the best introduction to
working with it on the questions afterwards.

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
- `.claude/skills/textbook-source-material/`: the marker map for your own source
  material, if you are working from an existing text.

The one thing worth not changing until you have reason to: the four-agent split
and the reviewer re-validating. That structure is where the reliability comes
from.

---

## Provenance

This pipeline was built and used in production on the SMP interactive textbook
project, an adaptation of an existing A-level and GCSE textbook series into
interactive STACK content. It has been exercised across three books. A case study
describing that project is forthcoming on the STACK site, at
stack-assessment.org/CaseStudies/. Nothing from that project's content is
included here, and no knowledge of it is needed to use this kit.

The theme is a Boost child theme and inherits Moodle's GPL v3. Its copyright
header is a placeholder to fill in.

The reference XML under `content/reference/` consists of purpose-written
exemplars demonstrating each question pattern. The mathematics in them is
standard school and early-undergraduate material.

STACK is GPL, and is maintained by Chris Sangwin and colleagues at Edinburgh. The
pedagogic patterns in the proof and question-type sections draw on Sangwin's book
and on Bickerton and Sangwin (2022) on proof comprehension. Both are worth
reading before spending serious time on this.
