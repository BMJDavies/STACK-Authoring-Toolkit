# Project instructions

CLAUDE.md is the file Claude Code reads automatically at the start of every
session in this folder. Everything in it is treated as a standing instruction.
Keep it short and factual: it is loaded into context every single time, so long
rambling notes here cost you on every request.

**Fill in every `<...>` placeholder below before your first authoring run.**
Delete this paragraph and the one above once you have.

---

## What this project is

<One or two sentences. What are you building, for whom, at what level?
Example: "Interactive STACK question banks for a first-year undergraduate
linear algebra module, delivered through Moodle to about 200 students a year.">

## Where the questions come from

<Pick ONE and delete the other.>

**Source-led.** Questions are adapted from `<source material>` in `sources/`.
Every item in the source appears in the output as a question, a description, or
an explicitly flagged item. Nothing is silently skipped.

**From scratch.** There is no source text. `<the syllabus / module spec /
learning outcomes doc>` in `references/` is the only ground truth. Questions are
invented against it, so every question must be checked against the spec for
level and scope, and a human signs off the maths before it goes live.

## Content rules

`style-guide.md` is the single source of truth for all content conventions
(PRTs, inputs, display, randomisation, editorial standards). Agents read it
before writing or reviewing anything. Do not rely on memory of it.

`reviewing-checklist.md` is the reviewer's checklist.

`content/reference/showcase.xml` is the golden reference: worked examples of
every question pattern. Agents match its conventions. Never use a recently
authored file as the convention reference, because recent files drift.

## The pipeline

```
/develop-section  ->  planner  ->  author  ->  validate  ->  reviewer  ->  approved/  ->  live
                                                                 |
                                                                 +-> feedback/<section>.md
```

- Agents are defined in `.claude/agents/`. Skills are in `.claude/skills/`.
- All agents run in the foreground, never in the background. The pipeline is
  sequential: each agent's output decides the next step, and two agents touching
  Moodle at once collide.
- Author validates its own work and only hands off files that pass.
- Reviewer re-runs validation itself rather than trusting the author's word.

## Validation

```bash
tools/api-check.sh path/to/questions.xml      # fast inner loop, seconds per question
tools/test-question.sh path/to/questions.xml  # the real gate, before anything goes live
```

Every STACK question must embed at least two `<qtest>` cases (one correct, one
wrong). Without them the marking logic is untested and a broken question looks
exactly like a working one.

## Moodle

Site: `<http://localhost:8000>`
All ids (courses, question banks, quizzes) live in `tools/project.conf`. Scripts
read them from there. Never hardcode an id into a command.

| Content prefix | Course | `--course=` key |
|----------------|--------|-----------------|
| `<C1_>` | `<Course name>` | `<key>` |

Publish with `tools/push-live.sh <xml> <quiz-id> --course=<key>`.
Full runbook for every script: `.claude/skills/moodle-operations/`.

## Working preferences

<Tell Claude how you want to be worked with. Examples, keep what fits:>
- I am new to Claude Code. Explain technical steps rather than just doing them.
- Give me direct question preview URLs, not just quiz links.
- Show me a plan as a short table before writing any XML.
- Never commit. I stage and commit myself.
- Flag editorial decisions (adapting a word problem, converting "explain why"
  into multiple choice) before implementing them, not after.

## Planning

The rolling to-do list, open decisions, and pipeline notes live in
`PROJECT-PLAN.md`. Create it on your first real session.
