---
name: moodle-operations
description: Runbook for the validate, preview, publish and sync workflow. Wraps every script under tools/ so agents know what to run when.
---

# Moodle operations runbook

Every operation runs inside the Moodle Docker container. Container names, ids
and URLs all come from `project/project.conf`. The shell wrappers read that file
for you: do not hardcode an id into a command.

## Fast check, during authoring

```bash
tools/api-check.sh path/to/file.xml
```

Posts each question to a standalone STACK API. Seconds per question, no Moodle
import, safe to run while other work is happening. This is the inner loop.

It has two known false-failure modes, both documented in the style guide: some
multiple choice items whose test inputs use `first(mcq_correct(opts))`, and
questions whose test input is a teacher expression rather than a literal. If a
failure makes no sense after one honest look, confirm with `test-question.sh`
before changing anything.

The first run brings up two Docker containers and takes a minute. After that it
is fast.

## Display check, before hand-off

```bash
tools/render-sweep.sh path/to/file.xml
tools/render-sweep.sh path/to/file.xml -- --verbose   # list every source warning
```

Renders every deployed seed of every STACK question through the same STACK API
and flags defects that grade correctly but display wrongly. Neither
`api-check.sh` nor `test-question.sh` looks for these:

- `\\` straight before `{@...@}`, which shows a column vector as `{-5}\{3}`;
- 0, 1 or −1 printed as the coefficient of a symbol ("1 v₁", "(−1) e₂");
- `+ -` inside mathematics;
- two deployed seeds with the same question note (a repeated variant).

It also scans the source of every CASText field, including PRT feedback, which
the API cannot render. Missing `<deployedseed>` is reported as a warning, and
the default seeds are used. Exit 0 means no flags; exit 1 means at least one
flag; exit 2 means the API failed to render something.

## Full validation, the real gate

```bash
tools/test-question.sh path/to/file.xml
tools/test-question.sh path/to/file.xml --sandbox   # second stack, if configured
```

Three stages: XML import, then Maxima checks on the question variables, then
test the question's own `<deployedseed>` values (5 defaults if it has none) and
run every embedded `<qtest>` against every seed. Exit 0 means
everything passed, exit 1 means something failed.

Nothing is published until this returns 0. Run it yourself rather than trusting
an agent's report that it passed.

Run it in the FOREGROUND and let it block. It can run for a long time under
load, and much longer on a big file. Backgrounding it and polling is the single
most common way an agent loses its work: the poll gets murky, the agent gives
up, and the run is wasted. Just wait.

The script produces no streamed output while it runs, so silence is not a hang.
If you want to confirm progress, check for Maxima processes in the container.

## Auditing test inputs

```bash
tools/audit-test-inputs.sh path/to/file.xml
```

Flags `<testinput>` values that are hardcoded literals inside randomised
questions. Those silently mark students wrong on every seed except the one the
literal happens to match, while the validator reports a pass. This is a triage
report, not a gate: some hits are legitimate. Check whether the referenced
`<tans>` actually varies with the seed.

## Previewing a draft as a student

```bash
tools/push-review.sh path/to/draft.xml
```

Imports into the review question bank and appends any new questions to the
preview quiz. Dedupes by question name, so re-pushing after edits does not stack
duplicate pages. No Maxima or PRT validation happens here: that is
`test-question.sh`'s job.

The script prints the preview URL when it finishes.

## Cleaning up the sandbox

Every validation run re-imports the same questions, so the sandbox category
fills with duplicates. Periodically:

```bash
docker cp tools/cleanup-duplicates.php <container>:/tmp/cleanup.php
docker exec <container> php /tmp/cleanup.php --categoryid=<DEV_CATEGORYID>
```

Keeps the highest id of each name, which is the most recent import.

## Creating a quiz

See the `quiz-settings` skill for the full option list.

```bash
docker cp tools/quiz-admin.php <container>:/tmp/qa.php
docker exec <container> php /tmp/qa.php create \
    --courseid=<id> --name="<quiz name>" --section=<N>
```

**Always pass `--section=N`.** Omitting it drops the quiz into section 0
(General), which is wrong, and moving it afterwards is fiddly.

## Publishing

```bash
tools/push-live.sh path/to/approved.xml <quiz-id> --course=<key>
```

Imports into the target course's question bank. For questions already in the
quiz, matched BY NAME, it updates in place: it repoints the quiz slot at the new
import and deletes the old bank entry. New questions are added on fresh pages.
It also reorders slots to match the XML sequence, regroups pages, deploys 5
variants per STACK question, deletes stale in-progress attempts, and runs a
verification pass checking slot order, lonely pages and orphaned descriptions.

Safe to re-run on the same file. Create the target quiz first if it does not
exist.

Two things to know:

- **Matching is by question name.** Renaming a question during an edit pass
  breaks the match, so the re-push adds a new slot and orphans the old one as a
  stale duplicate. Treat `<name>` as immutable.
- **After every push, verify the imported question NAMES**, not just the slot
  count. A wrong-content import keeps the same count, which is exactly why it is
  easy to miss.

Re-pushing a file rebuilds all the question ids. If you are about to send
someone preview links, query for the ids again after the push, not before.

## Checking what the page actually looks like

```bash
tools/render-check.sh path/to/file.xml
tools/render-check.sh --match Ch6
tools/render-check.sh --all
```

Loads each question through Moodle's renderer and greps the resulting HTML for
known bad signatures: raw `[[jsxgraph]]` or `[[input:]]` text that never got
processed, `<p>`-with-validation-`<div>` stacking, substitution failures. Exit 0
is clean.

This reads the DATABASE, not the file on disk. After editing XML you must
re-import before re-running it, or you are checking the old version.

Browser spot-checks are still needed for what this cannot see: visual layout,
whether a JSXGraph figure is mathematically right, dropdown usability.

## Pulling hand edits back out of Moodle

If someone edits a question in the Moodle UI, the repo is now stale:

```bash
docker cp tools/sync-from-moodle.php <container>:/tmp/sync.php
docker exec <container> php /tmp/sync.php <quizid> /tmp/export.xml
docker cp <container>:/tmp/export.xml project/content/approved/<filename>.xml
git diff project/content/approved/<filename>.xml
```

Use this only to reconcile hand edits. Read the diff before accepting it.

## Questions with `[[todo]]` blocks

Notes for the user are `[[todo]]` blocks in the question description (style
guide, "Notes for the human: `[[todo]]` blocks"). STACK lists every question
with one, grouped by tag, at

    <moodle>/question/type/stack/adminui/todo.php

and the same list is on disk:

```bash
grep -l '\[\[todo' project/content/approved/*.xml
```

A todo block does not stop validation, publishing or rendering, and students
never see it. STACK's bulk tester reports each one as needing attention, which
is intended.

## Removing questions from a quiz

```bash
docker cp tools/remove-from-quiz.php <container>:/tmp/rfq.php
docker exec <container> php /tmp/rfq.php <quizid> "<name-pattern>"
```

The pattern uses `fnmatch`, so it needs wildcards: `"%Ch6%"` style patterns will
not work, `"*Ch6*"` will.

## Adding a file to a course page

```bash
docker cp <file>.pdf <container>:/tmp/upload.pdf
docker cp tools/add-resource.php <container>:/tmp/add-resource.php
docker exec <container> php /tmp/add-resource.php \
    --courseid=<n> --section=<N> --name="Source: Chapter 2" --file=/tmp/upload.pdf
```

## The whole pipeline

```
(1) write XML into project/content/drafts/<file>.xml
(2) tools/api-check.sh          -> fast green light while authoring
    tools/render-sweep.sh       -> displayed maths is right on every seed
(3) tools/test-question.sh      -> the real gate
(4) reviewer approves           -> file moves to project/content/approved/
(5) tools/push-live.sh          -> live course
(6) tools/render-check.sh       -> confirm it renders
(7) the file stays in project/content/approved/ as the source of truth
```

## Looking up a quiz id

```bash
docker exec <db-container> mysql -u<user> -p<pass> <db> -e \
  "SELECT id, name FROM m_quiz WHERE course=<n> AND name LIKE '%10A%' ORDER BY id"
```

Two gotchas: the table prefix on a moodle-docker install is `m_`, not `mdl_`;
and the webserver container has no MySQL client, so run the query against the
database container.

A PHP fallback that works from the webserver container, useful if the database
container is not responding:

```bash
docker exec <container> php -r "
define('CLI_SCRIPT', true);
require('/var/www/html/config.php');
global \$DB;
foreach (\$DB->get_records_sql(\"SELECT id, name FROM {quiz} WHERE course=7\") as \$q) {
  echo \$q->id.' - '.\$q->name.PHP_EOL;
}
"
```

Moodle's `$DB` API applies the table prefix itself, so use the brace form
`{quiz}` rather than spelling out `m_quiz`.

## Two things that will bite you

**Concurrency.** Two scripts hitting Moodle at once is how content gets
corrupted. `push-live.sh` and `push-review.sh` protect themselves by staging to a
unique container filename, but validation and pushes still contend for Maxima.
Run one Moodle-touching operation at a time unless you have a second stack.

**Container recreation.** Restarting the containers is safe. Recreating them
(`docker compose down` then `up`, or a rebuild) wipes anything installed by hand
inside them, including Maxima and any custom theme. See SETUP.md for what to
reinstall.
