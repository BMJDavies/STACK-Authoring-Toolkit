# Changelog

What changed in each release of the kit, newest first. The version in `VERSION`
matches the newest entry here, and each release is tagged in git as `v<version>`.
When a release needs you to change an existing project, `UPGRADING.md` says how.

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
