# Changelog

What changed in each release of the kit, newest first. The version in `VERSION`
matches the newest entry here, and each release is tagged in git as `v<version>`.
When a release needs you to change an existing project, `docs/UPGRADING.md` says how.

## Unreleased

- The kit is now called the STACK Authoring Toolkit, and the README says how
  to get a copy.
- STACK's guidance for agents comes first: its essentials file before any
  question is written, validated or reviewed, and its question review
  checklist for the reviewer and the enricher, each read verbatim with
  `tools/stack-guide.sh`. The style guide now builds on the essentials file
  rather than repeating it.
- A local copy of STACK's source: `tools/update-stack-sources.sh` keeps a
  clone and a checkout of your STACK release in `project/sources/`, and
  `tools/stack-maxima.sh` runs Maxima with STACK loaded. Optional;
  `docs/UPGRADING.md` says how to add it.
- Parsons problems follow STACK's `agents/stack_parsons.md`; the kit keeps only
  its own choices (Pattern 7). Distractors are allowed where the plan names
  them, the wrong-order test is built from the teacher's answer rather than
  the shuffled steps, and the grouping layout is an alternative to dropdowns
  for classification.
- Free-text working and proofs, following STACK's `agents/stack_free_text.md`
  (Pattern 9, Showcase 13, `reference/prototypes/free_text_prototype.xml`).
  By default the answers are graded automatically and the working is not; a
  proof the student writes and compares with a model carries no marks; manual
  grading only where the project says someone marks. `tools/show-ta.sh`
  prints the model working on every seed.
- `tools/stack-guide.sh` reads any file in STACK's `agents/` folder, and, as
  a last resort, a draft in the clone's working tree, which it reports.

## 0.1.0

The first public release, under the GNU GPL v3 or later (`LICENSE`).

- The four-role pipeline (planner, author, reviewer, enricher), with
  `/develop-section` and `/develop-chapter`, and `/start` for setting up a
  project.
- Works from any assistant CLI that reads `AGENTS.md`; tested end to end with
  Claude Code only.
- Your work lives in `project/` and `extensions/`, which the kit never changes.
- Validation: fast checks with `api-check.sh`, the full gate with
  `test-question.sh`, and `audit-test-inputs.sh` for questions that validate but
  mark wrongly.
- Publishing to Moodle as new question versions, with review and live courses,
  quiz administration, render checks and a question export tool.
- The style guide, the reviewing checklist, `reference/showcase.xml` and the
  pattern prototypes.
- A Boost child theme that makes quizzes read as textbook pages.
- Publishing refuses to change a quiz that holds questions the file does not,
  so pushing one section cannot reorder another.
- Needs Moodle 5.0 or later and STACK 4.13.0 or later.
