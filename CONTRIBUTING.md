# Contributing

Thank you for helping. The kit improves mostly through people reporting what got
in their way while they used it, so a clear report is as useful as code.

## Reporting a problem

Open an issue. Say:

- what you were doing, what you expected and what happened instead, with the
  exact error text if there was one;
- the kit version (the `VERSION` file), your Moodle and STACK versions, and your
  operating system;
- which assistant tool you were using, if the problem is in how it followed the
  kit's instructions.

At the end of each run the kit's assistant lists the frictions it met and offers
to draft an issue (`WORKFLOW.md`, "Frictions"). That draft is a good starting
point.

Problems in STACK itself belong at
[maths/moodle-qtype_stack](https://github.com/maths/moodle-qtype_stack), not here.

## Security problems

Do not open a public issue. Use "Report a vulnerability" on the repository's
Security tab, which reaches the maintainers privately.

## Proposing a change

Send a pull request against `main`. Small, focused pull requests are easiest to
review: one problem each. Say in the description what you changed, why, and how
you tested it. If you changed a tool that writes to Moodle, say which Moodle
version you tested on.

Before you send it:

- Keep the kit generic. Nothing in it may belong to one course, one textbook,
  one institution or one user. Rules for a single course go in that project's
  own `project/` folder, and personal habits in `extensions/` (`AGENTS.md`, "How
  this folder is organised"). A rule that would hold for any textbook and any
  user belongs in the kit.
- Include no teaching material you do not have the right to share: no publisher
  content, source documents or question banks. New reference questions should be
  written for the kit.
- If you change a reference question, run it through `tools/test-question.sh`.
- If the change means existing users must do something, add a note to
  `UPGRADING.md`, and add a line to the newest section of `CHANGELOG.md`.

## Licence of contributions

The kit is licensed under the GNU GPL v3 or later (`LICENSE`). By sending a pull
request you agree that your contribution is licensed under the same terms. You
keep the copyright in your own work.
