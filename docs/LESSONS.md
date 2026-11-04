# Lessons from production use

**Audience: your assistant, and you. Worth reading once yourself.**

These are the things that cost real time and that are not easily discovered by reading
documentation. The technical ones are already encoded in `style-guide.md`; this
file is the reasoning behind them, plus the process lessons that do not belong
in a style guide.

Read it once now. Come back to it when something baffling happens.

---

## The expensive class of bug

The worst bugs in STACK authoring share a shape: **the question validates
cleanly, and marks students wrongly.**

A hardcoded test input in a randomised question is the canonical example. The
test value matches seed 1. The validator deploys seed 1 among others, the test
passes, everything is green. In production, students get seeds 2 through 400,
type the correct answer, and are marked wrong. Nothing anywhere reports a
problem. You find out when a student complains, or you never find out.

The general lesson: **your validator tells you a question is self-consistent,
not that it is correct.** Everything that follows is a variation on that.

Practical consequences:

- Run `tools/audit-test-inputs.sh` before approving anything.
- Never trust a single seed. If something works, ask which seeds you tested.
- Make the reviewer re-run validation rather than believing the author. This
  costs minutes and catches drift.
- A separate adversarial pass (the enricher's grading-defect sweep) is worth its
  cost. Ask "which answer would this mark wrongly?", in both directions: a wrong
  answer accepted, and a right answer in a legitimate equivalent form rejected.

---

## Answer tests are blind in ways you will not expect

`AlgEquiv` tests mathematical equality. It does not test form. So a question
saying "factorise x² + 5x + 6" is marked correct if the student types
`x² + 5x + 6` back at you, because that is equal to `(x+2)(x+3)`.

We shipped a lot of these before noticing. The fix is a second PRT node testing
form, and the recipes are in the style guide. But the meta-lesson is: read what
your answer test actually asserts, not what you assume it asserts.

Two more in the same family:

- `Int` (integration) requires `tans` to be the antiderivative, not the
  integrand, because it differentiates the student's answer and compares. Feed
  it the integrand and it compares against the derivative of the integrand and
  fails everything.
- `NumSigFigs` miscounts significant figures on any value with a trailing zero
  at the target precision. `90.0` reads as 2 s.f. and the correct answer is
  rejected. Use a tolerance test unless significant-figure form is the skill.

---

## `simp` runs earlier than you think

`questionsimplify` is evaluated at input time, before the marking tree sees the
answer. So with simplification on, a form check for "did they simplify this"
becomes a silent no-op: Maxima already simplified it, so the check passes and
full marks are awarded regardless.

Turning it off then has a cascade of consequences, because the whole question,
including the variables block, now runs under `simp:false`. List indexing like
`klist[rand(6)+1]` throws, because `rand(6)+1` never collapses to a number. That
aborts the variables block, leaving everything unbound, and the symptom is a test
returning an EMPTY score rather than a wrong one.

**An empty score means the question never set up.** That is the single most
useful diagnostic signal in the system, and it points at the variables, not at
the marking tree. That reading takes a while to acquire.

---

## Concurrency will corrupt your content quietly

We had a push import a different session's file. Two pushes ran at once, both
staging to the same temporary filename inside the container, and one overwrote
the other before the import ran.

What made it nasty was that the slot count was still correct. Everything looked
fine. The wrapper scripts now stage to a unique filename per run, which fixes
the mechanism, but the habit matters more than the fix:

**After every push, verify the imported question NAMES, not the count.**

Same family: Maxima contention. Run more than about three heavy operations at
once and they queue, then time out. A timed-out agent tends to skip its baseline
check and proceed on the assumption that it would have passed, which is how a
broken file gets edited. Cap concurrency at three.

---

## Backgrounding a validation loses the work

Validation produces no output while it runs, and can run for hours on a large
file under load. It is very tempting to background it and poll.

Do not. We lost three agents out of three in one batch to this. The pattern is
always the same: the poll gets ambiguous, the agent cannot tell whether anything
is still happening, it gives up or starts a second run, and the completed work is
thrown away. The agents that simply blocked in the foreground succeeded every
time, including on the eight-hour run.

Silence is not a hang.

---

## Deployed state drifts from your repository

We built a custom Moodle theme, edited it inside the container, and never
committed it. Months later a fresh install did not look right and nobody could
work out why, because whatever made it look right existed only inside a
container.

If you change something inside a container, either put it in the repo or accept
that it will vanish. Recreating containers wipes Maxima and any hand-installed
plugin. Restarting them does not.

Corollary: `project/content/approved/` is the master copy of the questions. Moodle is a
rendering target. Commit the XML, and be able to rebuild the site from it.

---

## Bulk edits across XML are a bad idea

An early attempt used regex sweeps across the question bank to fix a systematic
issue. It produced silent corruption: valid XML, questions that imported fine,
subtly broken content.

Default to per-question edits through the pipeline. If a bulk edit is genuinely
mechanical (renaming a CSS class, say), do it with a dry run and a tag-count
integrity check before and after, and validate the result. That is the safe procedure
to produce this kit's reference files.

---

## Reproduce a claimed bug before fixing it

Bug reports from a previous session, or from another agent, are often
misdiagnosed or overgeneralised. We have "fixed" things that were not broken and
introduced real problems doing it.

Reproduce first. The two false-failure patterns to recognise before sending
anyone off to fix a phantom:

- The fast API check false-fails some multiple choice items, because it uses its
  own seeds rather than the question's deployed ones. The signature is "all
  inputs fail together on every seed but the first".
- CAS timeouts leave a blank score on scattered seeds. A real defect fails the
  same test on every seed with an actual wrong score.

Two agents stalled chasing the first of those before it was understood.

---

## Process lessons

**Plan quality is the whole game.** A plan saying "use an algebraic input"
produces a question nobody can review, because there is nothing to check against.
A plan naming the variables, the ranges, the marking tree structure, the exact
test inputs and the specific misconception to diagnose produces a question that
can be checked mechanically. Spend the time at the plan stage. It is by far the
cheapest place to catch a misunderstanding.

**Name the misconception before writing the code.** Forcing the planner to say
what a student will actually get wrong is what makes the feedback worth having.
Without it you get "Incorrect, try again", which teaches nothing. This is the
difference between a question bank and a textbook.

**Freeze the style guide during a production run.** Changing conventions
mid-chapter makes the output subtly inconsistent in ways that are miserable to
unpick later. Collect new rules in a lessons file and migrate them at a
boundary.

**Use a different model for the independent pass.** Our enrichment pass is run
by a different model from the one that authored and reviewed, and it finds things
the first model was structurally blind to. A second opinion from the same model
is not a second opinion.

**Let the run finish before you intervene.** Our default is a single approval
gate at the plan, then a straight run to first draft, with every editorial flag
batched into one list at the end. Stopping to ask about each small decision makes
a chapter take days instead of hours, and most of those decisions have an obvious
right answer that the style guide already covers.

**Never silently skip a source question.** If something cannot be adapted, say
so explicitly with a reason. Silent skipping is how content gaps ship, and you
find them when a student asks where question 7 went.

---

## Things automation cannot check

Be clear-eyed about the boundary. The pipeline can tell you a question imports,
sets up, and grades its own test cases correctly. It cannot tell you:

- Whether a JSXGraph figure is mathematically right, or even the right picture
- Whether the feedback actually teaches anything
- Whether the difficulty progression makes sense
- Whether a distractor is accidentally true
- Whether a currency value renders as `1166.40` or as a vulgar fraction
- Whether the question is the right question to be asking

That is why the reviewer's pedagogy audit is a named duty rather than an
afterthought, and why some things get flagged for a human to look at rather than
resolved automatically. Build the human spot-check into the process rather than
treating it as a failure of automation.
