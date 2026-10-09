# Your extensions

**Audience: you, the human.**

This folder holds your own instructions for the assistant: anything you want in
every project but that does not belong in the kit. Examples: guidance for writing
paper exams, your institution's exam class file, a house style for a kind of
question the kit does not cover.

- The kit never tracks this folder (it is in the kit's `.gitignore`), so updating
  the kit never touches it.
- It can be its own git repository if you want your extensions under version
  control: `git -C extensions init`.
- Onboarding copies it from `templates/extensions/`. A fresh copy contains only
  this file and `CLAUDE.md`, and that is a valid state: you need not add anything.

## How the assistant uses it

The kit's `CLAUDE.md` imports `extensions/CLAUDE.md` at the start of every
session, so keep that file short: it costs context on every request. Use it as
an index. List your other files there, each with a line saying when to read it,
and put the substance in those files. The assistant reads them when you ask, or
when the index says to.

Instructions are layered: the kit, then these extensions, then the project.
Where they disagree, the later one wins. Your preferences override the kit's
defaults, and a particular course's facts override your general preferences.

## Paths in extension files

Extension files point into two places, this folder and the project, so the form
of a path says which one it means. Never write `project/` or `../`.

| Written as | Means | Example |
|---|---|---|
| A bare path | Relative to the project folder | `content/exams/2026-resit.tex` |
| The bare name of an instruction file in this folder | That file here | `Exam-guidance.md` |
| A path starting `extensions/` | Anything else here | `extensions/exam-reference/exam-paper.tex` |
| "the project's `X`", "the kit's `X`" | A name that exists in more than one place | the project's `CLAUDE.md` |

Because no path names `project/`, an extension works unchanged with any project,
including one selected with `STACK_PROJECT`.

## General files and specific jobs

Some extension files hold a general method (how to write a good exam question).
Others do one specific job (write the 2026 resit paper, write questions about
one proof). A specific job may name very specific files, always in the project.
Gather every job-specific value (year, output file, plan file, which proof) in a
**This job** block near the top, give each a placeholder such as `<paper>`, and
use the placeholder in the rest of the file. The next job then means editing one
block.

Keep facts about one course (its syllabus, its source) in that project, not
here.
