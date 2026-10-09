# STACK question authoring kit

**Audience: you, the human. Everything else is written to be read by your
assistant.**

This repo holds a pipeline for writing STACK questions for Moodle with an AI assistant: agents,
skills, tooling, content rules and a theme. It assumes no particular subject, level or syllabus. 
You point it at your own material and your own Moodle site.

This folder becomes your project. Everything lives inside it, including the agent
definitions in `.claude/`. Nothing is installed elsewhere on your machine, so the
whole thing is undone by deleting the folder. If you later run several authoring
projects and want them to share one pipeline, see `MULTIPLE-PROJECTS.md`.

It was built for and tested with Claude Code. The content, the style guide, the
checklist and the tools are not tool-specific, and `AGENTS.md` explains how to
drive the same pipeline from another assistant. As of late 2026, I am working on
an LLM-agnostic version of this project but for now, you will need to use Claude Code
or manually adapt this infrastructure for your own purposes.

## Getting started

Open your Claude Code Assistant **in this folder** and type `/start`, or ask it
to follow `ONBOARDING.md`. The folder has to be the working directory itself: if
you open a session in the parent, nothing here loads, and you get an ordinary
assistant that has never heard of the pipeline and cannot tell you so. It will work out what you already have installed, ask what it
cannot detect, and configure the kit for your project.

You are well served by opening these files and understanding what is in them, and
you will get more out of the kit if you do. But you do not *need* to read anything beyond
this readme file to get started.

## What this contains, and what it does not

No teaching content of any kind: no publisher material, no source documents, no
authored question banks. The only XMLs here are the showcase and the pattern
prototypes, which exist to demonstrate conventions and were written for that
purpose.

You will need to provide your own source material. This might be a textbook you wish to 'STACKify',
your lecture notes, or something similar that has been carefully designed in a different context.
Like many things in life, the quality of the input (i.e. your source files) is the primary factor
in determining the quality of the output (i.e. your interactive, STACKified textbook).
The old 'garbage in, garbage out' mantra very much applies here.

What it does contain is the method: agents, commands, skills, tooling, the style
guide and the reviewing checklist, plus a Moodle theme.

The project settings template, `templates/project/project.conf`, ships with the
default local development database password used by moodle-docker, so that a
fresh copy works immediately. Repoint your copy, `project/project.conf`, before
using the kit against any server that matters.

---

## The kit and your project

This folder is the kit. Your own work lives inside it, in two folders the kit
ignores: `project/`, a separate git repository for one course, and
`extensions/`, your own instructions that apply across all your projects:

```
stack-authoring-kit/          the kit: clone this, and run the assistant here
├── CLAUDE.md                 standing instructions; imports extensions/CLAUDE.md
│                             and project/CLAUDE.md
├── .claude/  tools/  style-guide.md  reference/  ...
├── extensions/               yours, across projects: optionally its own repository
│   ├── CLAUDE.md             an index of your extension files
│   └── ...                   your own instructions, guidance and templates
└── project/                  your project: its own git repository
    ├── CLAUDE.md             what the project is: sources, locale, Moodle site
    ├── project.conf          your Moodle ids
    ├── style-guide.md        rules for this project only
    ├── content/              plans, drafts, approved questions
    ├── feedback/             section reviews
    └── sources/              your source material
```

Onboarding (`/start`) creates `project/` for you from `templates/project/`, and
`extensions/` from `templates/extensions/`. Updating the kit is a `git pull` in
this folder, and it never touches either. Commit your project's work inside
`project/`.

`extensions/` is for things you want in every project but that are not part of
the kit: for example, how you write paper exams. Instructions are layered: kit,
then extensions, then project, and the later one wins where they disagree.
`templates/extensions/README.md` explains how to write extension files.
 If you already use an
earlier version of the kit, where project files sat in the kit's own folders,
see `UPGRADING.md`.

---

## What is in the box

```
README.md                  this file, for you
ONBOARDING.md              setup interview, for your assistant
AGENTS.md                  portable entry point for a non-Claude assistant
CLAUDE.md                  standing instructions for the assistant (never rewritten)
SETUP.md                   from nothing to a working install
WORKFLOW.md                using the pipeline day to day
LESSONS.md                 why the rules are the rules
style-guide.md             content conventions: the agents' authority
reviewing-checklist.md     what the reviewer works through
MULTIPLE-PROJECTS.md       optional: sharing one kit across several projects
UPGRADING.md               moving a project from an earlier version of the kit

.claude/
  agents/                  textbook-{planner,author,reviewer,enricher}
  commands/                /start, /develop-section, /develop-chapter
  skills/                  question types, JSXGraph, Moodle operations,
                           quiz settings, reading source material

tools/
  setup-workspace.php      creates the sandbox and review courses
  create-live-course.php   creates a course to publish into
  api-check.sh             fast validation, seconds per question
  test-question.sh         full validation, the gate before publishing
  audit-test-inputs.sh     catches the silent wrong-marking defect class
  push-review.sh           preview a draft as a student sees it
  push-live.sh             publish
  render-check.sh          confirms a question renders correctly on the page
  quiz-admin.php           create and configure quizzes
  migrate-to-project-folder.sh  upgrades a kit set up before project/ existed
  ...and several smaller utilities

reference/
  showcase.xml             ten questions, one per pattern: the golden reference
  prototypes/              working templates for the harder patterns

templates/project/         the skeleton onboarding copies to project/
templates/extensions/      the skeleton onboarding copies to extensions/

extensions/                your own cross-project instructions (not part of the kit)
project/                   your project (not part of the kit; see above)

moodle-theme/textbook/     a Boost child theme that makes quizzes read as
                           textbook pages rather than as tests
```

---

## How it works, in one page

You write nothing by hand. You describe a section/chapter/page from your source material; four agents produce it.

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

This system does NOT give you 100% certainty that each and every question meets your
pedagogic aims. This is worth knowing before you start, so you build the human checks into your process
rather than discovering the gap later.

In my experience, the quality of the questions produced is generally very high.
The question text, feedback, and PRTs are all *usually* very good. That said, quality varies,
particularly when authoring questions on a new, unfamiliar, or esoteric topic.

The pipeline will confirm whether a question imports, sets up, and grades its own test
cases correctly. It cannot tell you whether a figure is the right picture,
whether the feedback teaches anything, whether the difficulty progression makes
sense, whether a distractor is accidentally true, or whether you are asking the
right question. Those need your eyes. The reviewer flags them but will only resolve them
in relatively trivial cases.

There is also a specific bug class the tooling exists to catch but does not catch
consistently on its own: a question that validates cleanly and marks students wrongly. LESSONS.md
opens with it. It is worth 5 minutes of your time to read and understand this file.

---

## Realistic expectations

- Setup is mostly downloads. Do it with Claude Code alongside you.
- Start with one small section, not a chapter. Most of the time goes on
  validation.
- Validation is slow and produces no output while running. That is normal. Let
  it block; do not background it and poll.

---

## If you are new to Claude Code

You do not need to understand the internals to use this. A few things that make
the difference:

- You will need to set-up Claude Code on your own before using this toolkit.
  Here is Anthropic's quickstart guide: https://code.claude.com/docs/en/quickstart
- `CLAUDE.md` is read at the start of every session, together with
  `project/CLAUDE.md`. Your project's standing instructions go in
  `project/CLAUDE.md`. Keep it short: it costs context on every request.
- Correct the system in the moment, rather than waiting. "Stop, do Y instead" mid-task works,
  and is far better than letting it finish something wrong.
- Ask it to explain before acting while you are learning: "tell me what this
  command does before you run it".
- Paste errors verbatim with the command that produced them. The exact text is
  the useful part. Uploading screenshots is also useful for the same purpose. I recommend
  choosing a terminal that accepts pasted images.
- Do not let it commit. Read the diff and commit yourself.
- Escape interrupts a run. Nothing is precious; the plans and XML are on disk.

Good first move: type `/start`, then ask it to explain each command before it
runs it. Working through setup with it narrating is the best introduction to
working with it on the questions afterwards.

---

## Adapting it to your project

Most of the kit is subject-neutral. The places where you will want to make it
yours:

- `project/CLAUDE.md`: what the project is, where questions come from, how you
  want to be worked with. Onboarding fills in every placeholder.
- `project/style-guide.md`: rules for your project only. The kit's
  `style-guide.md` holds the rules for every project: the prose-anchor table and
  the common-error table are examples of a form you can extend with your own
  topics, and there is a section at the end on adapting to a different level. A
  rule that would hold for any textbook is worth proposing for the kit.
- `project/project.conf`: your Moodle ids. The only place ids should live.
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
included here, and neither are its conventions: the style guide names no
syllabus, phase or exam board, and has been used unchanged at other levels and
in other countries. No knowledge of that project is needed to use this kit.

The theme is a Boost child theme and inherits Moodle's GPL v3. Its copyright
header is a placeholder to fill in.

The reference XML under `reference/` consists of purpose-written
exemplars demonstrating each question pattern. The mathematics in them is
standard school and early-undergraduate material.

STACK is GPL, and is maintained by Chris Sangwin and colleagues at The University of Edinburgh.
