# Onboarding

**Audience: the assistant. Follow this when `CLAUDE.md` still carries the
"not set up yet" notice.**

## Who you are working with

An experienced STACK question author who is new to working with an AI assistant.
Assume fluency in STACK, Maxima, potential response trees and Moodle question
banks. Do not explain what a PRT is. Do assume no familiarity with agents, slash
commands, context, or how to work with you effectively.

Two consequences. Explain the AI-side mechanics without being asked. And do not
soften or simplify the mathematics or the STACK detail: they will spot it, and it
will cost you their confidence.

## Detect before you ask

Work out everything you can without asking. Every question you avoid is one they
do not have to answer.

```bash
docker ps --format '{{.Names}}'          # is anything already running
docker ps -a --format '{{.Names}}'       # containers that exist but are stopped
uname -sm                                # OS and architecture
git -C . rev-parse --is-inside-work-tree # is the kit already under version control
ls tools/project.conf                    # has configuration been started
```

Report what you found in one short paragraph before asking anything. If Moodle is
already running, say so rather than asking whether it is.

## Stage A: their environment

This decides how much of `SETUP.md` applies. Ask only what detection did not
answer.

1. Do they have a working Moodle with STACK installed?
2. If yes, is it local Docker, or local by some other means?
3. Do they have command-line access to it? This is the real requirement. The
   tools shell into the container to run PHP. Administrator rights in the web
   interface are not sufficient on their own.
4. Confirm the operating system and architecture you detected. On Apple Silicon
   the standard Maxima package is not the one to install; `SETUP.md` section 4
   covers this.

**A Moodle they do not control is not supported.** If they describe an
institutional or hosted Moodle where they cannot reach a command line, stop and
say so plainly. The pipeline publishes by executing PHP inside the Moodle
installation. Without that access, the authoring and validation half still works
and the publishing half does not. Do not attempt a workaround.

Then send them to the parts of `SETUP.md` they actually need, rather than all ten
sections. Someone with Moodle and STACK already running usually needs only
sections 5, 6, 8 and 9.

## Stage B: their project

1. Subject, level, and who the students are.
2. Roughly how much material: a topic, a module, a book?
3. What is the source material, and what form is it in? PDF, LaTeX, Word,
   or print only? If PDF, check whether the text layer is usable, because
   mathematical notation frequently is not. `.claude/skills/textbook-source-material/`
   covers this.
4. What is the natural unit of delivery: chapter, module, topic? This decides how
   courses, sections and quizzes are laid out.
5. Do they have an existing question bank whose conventions should be matched?

**This release supports source-led authoring only**, where questions are adapted
from existing material. Authoring against a syllabus with no source text is
possible in principle and may be addressed in a future release, but it is not
supported here and has not been validated. If that is what they want, tell them
plainly rather than improvising it.

## Stage C: how they want to work

1. How much explanation do they want at each step while they are learning?
2. Do they want to approve a plan before any XML is written?
3. Who commits to version control?

## What to do with the answers

Replace `CLAUDE.md` entirely, using the template below. Delete the "not set up
yet" notice. Keep it short: it is loaded on every request, so length here costs
them on every interaction.

Then fill in `tools/project.conf` with the ids that `SETUP.md` section 6 prints,
and offer to walk them through their first section using `WORKFLOW.md`.

---

## Template for the new CLAUDE.md

```markdown
# Project instructions

## What this project is

<One or two sentences: what is being built, for whom, at what level.>

## Where the questions come from

Questions are adapted from <source material> in `sources/`. Every item in the
source appears in the output as a question, a description, or an explicitly
flagged item. Nothing is silently skipped.

## Content rules

`style-guide.md` is the single source of truth for content conventions. Agents
read it before writing or reviewing anything, rather than relying on memory.

`reviewing-checklist.md` is the reviewer's checklist.

`content/reference/showcase.xml` is the golden reference: a worked example of
every question pattern. Match its conventions. Never take conventions from a
recently authored file, because recent files drift.

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

Site: <url>. All ids live in `tools/project.conf`; never hardcode one.
Publish with `tools/push-live.sh <xml> <quiz-id> --course=<key>`.
Runbook: `.claude/skills/moodle-operations/`.

## Working preferences

<From stage C.>
```
