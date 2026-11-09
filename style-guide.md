# Content rules and style guide

**Audience: the assistant. This is authoritative for content conventions. Edit it to make the rules yours.**

The single source of truth for content conventions in this project. Agents read
this before writing or reviewing anything. Implementation patterns (XML
skeletons, PRT wiring, JSXGraph templates) live in the skill packages under
`.claude/skills/`; this file is about what standards the content must meet.

This file builds on STACK's own essentials file, the technical requirements for
any STACK question. Read it first, verbatim, with
`curl -sL https://raw.githubusercontent.com/maths/moodle-qtype_stack/iss1925/agents/stack_essentials.md`
(not a tool that summarises the page). Where the two disagree, this file wins.
When STACK issue #1925 is merged, change `iss1925` in that URL to `master`.

Almost every rule below was written after something went wrong. Where the reason
is not obvious, it is stated. Treat the "why" as part of the rule: if you
understand why, you will spot the next variant of the same problem.

> **Freeze this file while a book or module is in production.** Changing
> conventions mid-run makes author and reviewer behaviour drift between
> sections. Put new rules discovered during a run into `project/feedback/lessons-learned.md`
> and migrate them in at the next natural boundary.

---

## Quiz settings

- Question behaviour: `immediatefeedback`.
- Attempts allowed: unlimited (this is formative material).
- Correct answer display: hidden (`reviewrightanswer=0`).
- One quiz per section. Split later if a quiz gets very long. `push-live.sh`
  refuses to publish into a quiz that holds questions the file does not, so
  two sections cannot share a quiz.
- Group related descriptions and questions on the same page. Be mindful of
  mobile: do not make pages enormous. Never put a single item alone on a page.
  Merge consecutive descriptions, and attach a trailing summary to the previous
  page.
- Full defaults and `quiz-admin.php` usage: `quiz-settings` skill.

---

## Question requirements

- Everything must be automatically graded. No essay questions.
- Every STACK question must have:
  - Randomised numbers unless that is impossible or silly (see Randomisation).
  - A worked solution in `<generalfeedback>`, unless the question is trivial.
  - Question tests, as STACK's essentials file ("Test every question") and
    "Question tests" below require.
  - Number of random variants, and `<deployedseed>` selection, must follow the
    Deployed seeds policy below.
- Figure-only and worked-example slots use "skinny STACK", not
  `type="description"`. A skinny STACK question is `type="stack"` with no
  `<input>`, no `<prt>` and no `<qtest>`: just the question text (which may
  include `[[jsxgraph]]`), general feedback, and the standard boilerplate.
  Why: `[[jsxgraph]]` only renders when STACK's CASText filter runs, and that
  only happens inside `type="stack"`. A description shows the `[[jsxgraph]]`
  block as literal text on the page. Skinny STACK triggers a "missing tests or
  variants" warning on the authoring dashboard, which students never see. That
  is an accepted cost. Skeleton in the `jsxgraph` skill.
- Interactive figures use JSXGraph only, for consistency and maintenance.
- If the source has a figure for a question (a labelled rectangle, a sign
  diagram, a graph), the question needs a JSXGraph equivalent. A text
  description of a picture is not a substitute for the picture.
- Never silently skip a source question. If one seems unadaptable, find a
  creative STACK format or escalate to a human. Silent skipping is how content
  gaps get shipped.

### Stamp the target STACK version

STACK's essentials file gives the rule and why it matters. In this kit the
value is the STACK version stamp in `project/AGENTS.md`. Never copy it from a
template, the showcase or an older question.

The showcase depends on 4.13 features (route expectations), so claiming an older version misleads anyone who maintains
these questions later.

When STACK is upgraded, update `project/AGENTS.md`, then re-stamp a file the next
time you touch it.

### Record where each question comes from

Every STACK question states its provenance in `<questiondescription>`.
Students never see this field. Write it as plain text. The field is evaluated
as CASText, so no `{@...@}` and no `[[...]]`, apart from the review notes in
the next subsection. Use exactly one of these four forms:

- **Direct adaptation.** The same task as a source item, possibly randomised,
  reworded, or split into parts:
  `This question is directly adapted from Exercise 8 in Section 3A of <citation>.`
- **Substantial adaptation.** The source item's task has changed in kind, for
  example a proof turned into a Parsons problem or a computational scaffold:
  `This question is adapted from Exercise 17 in Section 3A of <citation>: <one clause saying how it was changed>.`
- **From the text.** It tests a definition, result or example in the section's
  text rather than an exercise:
  `This question is based on <Example 3.3 / the definition of a linear map> in Section 3A of <citation>.`
- **Original.** Proposed by the planner, with no source item:
  `This question is original to this project and does not come from <citation>.`

`<citation>` is the project's fixed citation string, given in `project/AGENTS.md`.
In kit reference material (the showcase and prototypes), write "original to
this kit" instead.

A question built from several source items names them all, using the strongest
form that applies (direct, then substantial, then text). Cite the book's
**published** numbering, never position in the source file: commented-out or
omitted items shift the file's order. Skinny STACK questions carry a note too.
`type="description"` items have no such field.

Why: authors and reviewers need to see at a glance which questions are the
book's and which are ours, both to check fidelity to the source and for rights.
The fixed wording makes them searchable: `grep "directly adapted"` lists every
direct adaptation.

### Notes for the human: `[[todo]]` blocks

A decision that needs the user goes into the question as well as into the
agent's report, as a STACK `[[todo]]` block. STACK lists every question with
one at `<moodle>/question/type/stack/adminui/todo.php`, grouped by tag, so the
user gets a working list inside Moodle. `docs/WORKFLOW.md` ("Your to-do list in
Moodle") is the user's side of this.

- **Where:** at the end of `<questiondescription>`, after the provenance
  sentence, never in the question text, the feedback or a PRT. Students never
  see the question description.
- **Tags:** `tags="<raised-by>, <section-id>"`. The first tag is `kit-review`
  for the reviewer and `kit-enrich` for the enricher; the second is the section
  id, for example `Ch5A`.
- **One block per issue.** Each says where the issue is (question part, PRT
  node, test case, sentence of the worked solution), what is wrong or
  undecided, and the proposed action. Make it short enough to read in the edit
  form.
- **Valid CASText.** The block's content is evaluated. Wrap anything that
  quotes code, `{@...@}`, `[[...]]` or a backslash in `[[escape]]`:
  `[[todo tags="kit-review, Ch5A"]][[escape]]prt2 node 1 tests ans3 against {0} …[[/escape]][[/todo]]`.
  Remember that `{@ex@}` keeps its braces when reading code you quote.
- **Only for a human decision:** an editorial or pedagogic choice, a source
  ambiguity, a defect flagged but not fixed. Anything the agent can and should
  fix itself is fixed, not noted.
- **Keep the list in step with the report.** Every "for your review" item that
  belongs to a question has a block, and every block appears in the report.
  Items about a whole quiz or section, with no single question, stay in the
  report only.
- **Removing.** A block is deleted when it is resolved: by the user in Moodle
  (then the edits are pulled back into `project/content/approved/`), or by the
  agent when the user answers in the conversation. A resolved block is never
  left in place.

Approved files may carry open blocks. They do not affect students, and the
question can be published while the user decides. List them with
`grep -l '\[\[todo' project/content/approved/*.xml`.

---

## Content formatting

- Work in HTML (`<p>`, `<ol>`, `<div>`), not the rich-text editor.
- If you are using JSXGraph you must set the plain text editor as your default
  in Moodle's Editor Preferences, or the editor will mangle the code.

### CSS classes

The bundled Moodle theme (`moodle-theme/textbook/`) styles these. If you rename
the prefix, rename it here, in the theme SCSS, and in the reference XML together.

| Element | Class | Notes |
|---------|-------|-------|
| Key point box | `<div class="tb-keypoint">` | No numbers. "Key Point", not "Key Point 1". |
| Example heading | `<h4 class="tb-example">Example</h4>` | Separated by `<hr>`. No numbers. |
| Solution heading | `<h4 class="tb-solution">Solution</h4>` | |
| Parts list | `<ol class="tb-parts">` or `tb-parts-inline` | |
| Exercise heading | `<p class="tb-exercise">Exercise</p>` | |
| Table | `<table class="tb-table">` | For tables containing inputs. Otherwise use Maxima's `table`. |

---

## Typography and display

- Fractions: `\dfrac{}{}` (displaystyle). Never `\tfrac`, which renders too
  small to read. For Maxima output: `\displaystyle{@a1@}`.
- Units: `\(\theta = 1\,\mathrm{radian}\)`. Thin space before an upright unit.
- Vectors: `\vec{AB}` for point-to-point, bold otherwise.
- Derivatives and integrals: Roman d (`\mathrm{d}`), small space before dx
  (`\,\mathrm{d}x`).
- Multiplication sign: `<multiplicationsign>none</multiplicationsign>`. Never
  `dot` or `cross`. Dots produce ugly output like `3 · x² + 5 · x`.
- Square root symbol: `<sqrtsign>1</sqrtsign>`.

### Sign handling in displayed expressions

CAS output like `x² + -5` or `0x` is mathematically correct and reads terribly.
When displaying randomised expressions:

- Suppress zero-coefficient terms entirely.
- Display negative constants as subtraction: `x² - 5`, not `x² + -5`.
- **Never insert a negative-capable variable into a LaTeX template.** Writing
  `\(x^2 + {@b@}x + {@c@}\)` gives you `x² + -3x` whenever `b` is negative.
  Instead define a display expression in `questionvariables`, for example
  `disp_q: x^2 + b*x + c;`, and interpolate `\({@disp_q@}\)`. Maxima renders the
  signs properly. This applies everywhere: question text, feedback, general
  feedback, worked solutions.
- **Never put a randomised coefficient directly before a symbol**, as in
  `{@k@}x`, `{@k@}\,v_1` or `{@k@}e_1`. On the seeds where `k` is 0, 1 or −1
  this prints "0 v₁", "1 e₁" or "(−1) v₂". Interpolate the product instead,
  `{@k*x@}` or `{@b1*v1+b2*v2@}`, with the symbol added to `ordergreat` so the
  terms stay in order, or name the scalar in the prose ("where \(k = {@k@}\)").
- **`\\` before `{@...@}`, and the braces of `{@ex@}`:** STACK's essentials
  file, "Write the text fields in CASText". Neither `test-question.sh` nor
  `render-check` catches a lost backslash, so only rendering the seeds and
  looking at the result will find it. Do not flag `\frac{@a@}{@b@}` as missing
  braces.

### Currency: always use a rounded display variable

Never display a currency amount straight from a value built with `1+p/100` or
any other rational. It stays an exact rational and renders as a vulgar fraction:
`\frac{5832}{5}` instead of `1166.40`, or an enormous compound-interest
fraction. Define a display variable, `bal_d: float(round(bal*100)/100);`, and
interpolate that in the stem, the feedback and the question note. Keep the exact
value for grading only.

Neither the validator nor the render check catches this. It needs a human or a
Maxima check.

### XML and entity rules

STACK's essentials file gives the structure of a question's XML and the
escaping rules. This kit differs from STACK's minimal form as follows.

- **Write every element.** Copy a question from `reference/showcase.xml`, which
  writes every element as a Moodle export does, rather than relying on STACK's
  defaults. Why: STACK fills omitted options from the site's settings, which
  differ between sites, so explicit values keep a question the same everywhere.
- **The kit's values are style, not STACK's defaults.** In particular
  `<multiplicationsign>none</multiplicationsign>` (STACK: `dot`),
  `<mustverify>0</mustverify>` and usually `<showvalidation>0</showvalidation>`
  (STACK: `1` and `1`), a `<boxsize>` sized to the answer (STACK: `15`), and
  `format="moodle_auto_format"` on most question notes (STACK: `html`). Take
  them from the showcase and the rules in this file, not from STACK's defaults.
- **No named HTML entities anywhere**, even inside CDATA, where STACK would
  allow them. Write `≥`, `≤`, `≠` directly, or a numeric entity (`&#8805;`).

---

## Inputs

STACK's essentials file, "Choose and configure inputs", gives the general rules:
validity before correctness, each placeholder once, `<div>` not `<p>` around an
input line, `numerical` for a single number, `checkvars` and validators,
`consolidatesubscripts`, forbidden words and syntax hints. This kit adds:

- `mustverify`: `0` on all inputs.
- `showvalidation` depends on how complex the expected answer is:
  - `0` (hidden) for single integers, single numbers, dropdowns and multiple
    choice, even where the input type is algebraic.
  - `3` (compact) for polynomials, surds, fractions with variables, expressions
    with several terms.
  - `1` (full, with variable list) for genuinely tricky syntax such as surds or
    set notation, where the student needs to confirm how their typing parsed.
- `boxsize`: match the expected answer length. 4 for a single digit, 15 or more
  for a polynomial.
- Always leave a space between the LaTeX and the input: `\(x =\) [[input:ans1]]`.
- A single number: `numerical` with `forbidfloat` 1, `requirelowestterms` 0 and
  no extra options.
- A matrix of numbers carries `validator:validate_numeric`, defined as in the
  essentials file. Because `mustverify` is 0, on STACK 4.14.0 and earlier its
  invalid-entry test must also fill in a non-matrix input, or it fails as
  "Empty question tests are not permitted!".
- Prefer `checkvars:1` where the allowed variables are those of the teacher's
  answer; otherwise write a variable-set validator, for example
  `validate_x3(ex) := if every(lambda([v], member(v, [x1, x2, x3])), listofvars(ex)) then "" else "Use only the variables x1, x2, x3.";`
  Where a question's variable set depends on the seed, the validator can read
  it from a question variable.
- Every `algebraic` input whose expected answer uses atoms such as `x1`, `y2`
  carries `consolidatesubscripts`, after any validator:
  `<options>validator:validate_x3, consolidatesubscripts</options>`. Each such
  question carries one test entering the answer with underscores (for example
  `subst([x1=x_1,x2=x_2,x3=x_3], ta1)`) and expecting full marks.
- Where bespoke validators recur across questions, suggest adding them to
  STACK.
- Tooling caveat: `render-check` reads the database, not the file on disk.
  After editing XML, re-import before re-running it, or you are checking the
  old version.

### Forbidden words (`forbidwords`)

The rule is in STACK's essentials file. The usual list:

| Question asks for | Forbid |
|-------------------|--------|
| Derivative, "differentiate" | `diff` |
| Indefinite or definite integral | `integrate`, `int` |
| Factorisation | `factor` |
| Expansion | `expand` |
| Solution of an equation | `solve` |
| Roots of a polynomial | `solve`, `allroots` |

### Syntax hints

The rule is in STACK's essentials file. In this kit, any input expecting `sqrt`,
`pi`, `inf` or other non-obvious notation has a generic hint (`a+sqrt(b)`, never
`-2+sqrt(5)`) with `syntaxattribute` 1, and any section that introduces new
input notation opens with a short description explaining how to type it
("Type `sqrt(5)` for √5").

---

## Dropdowns and multiple choice

- **Validity rule (mandatory).** Dropdowns and radio inputs are for
  classification, interpretation, or selection from a small fixed set of
  distinguishable categories ("real roots / no real roots / repeated root",
  "convergent / divergent"). They are not for routine procedures that have many
  valid written forms ("which of these is the translated equation"). A multiple
  choice question for a procedural skill tests pattern-matching against a list,
  not whether the student can do the procedure. Use a free-response algebraic
  input with a PRT instead.
- Use for: true/false, simple classification with two to four fixed options.
- Avoid for: anything needing randomised options, partial credit, or complex
  logic.
- **Radio versus dropdown, by option length.** If the options are full sentences
  (typical of "what do you notice" or "which rule applies" questions), author
  them as radio. In a `<select>`, long options are cramped and cannot be compared
  side by side. LaTeX does not render inside a dropdown either. Reserve dropdowns
  for short categorical tokens. Grading is identical.
- **Audit every distractor for accidental truth.** Check that each wrong option
  is actually false under the full net effect, not just superficially different.
  Invariance and "what do you notice" distractors are the usual trap: two
  different translations really can produce the same image, so a distractor
  saying "both move it up equally" is true, and a student who reasons correctly
  is marked wrong. Reword such options to target the genuine misconception.
  A general claim is not false enough if its only counterexample is a case the
  question excludes: "identical rows give a range of dimension 1" fails only for
  the zero matrix, so it is true for a question whose matrix is all ones.
- **Pattern:** define the option list in question variables using
  `mcq_correct()` / `mcq_incorrect()`, then reference it with `[[input:ansN]]`.
  Never define options inline.
- **Option ordering:** natural logical order (numbers ascending, classifications
  in standard order). Never put the correct option first, or pattern-spotters
  will pick it without engaging. When several inputs share one option list (for
  example, justification dropdowns for the steps of a proof), do not order the
  list by step: the positions of the correct options must not climb with the
  steps, or the answers can be read off the pattern.
- **PRT `tans`:** use `first(mcq_correct(options))`, not `mcq_correct(options)`.
  The latter returns a single-element list, but the student's answer is a
  scalar, so `AlgEquiv(scalar, list)` fails with `ATAlgEquiv_SA_not_list`.
- **Test case inputs:** `first(mcq_correct(options))` for the correct case and
  `first(mcq_incorrect(options))` for the wrong case. Passing a raw option pair
  `[value, true]` makes input verification fail silently, leaving an empty score
  and answer note.
- **Option values must be static literal strings.** Never build an option value
  with `sconcat`, `string()`, interpolation, or a parametric expression like
  `a*x+b`. When the value is assembled by the CAS, STACK's matcher rejects the
  student's submission with "is not listed by the teacher as an option" and the
  question fails on most seeds. Keep the underlying value a plain literal token
  (`ruleA`, `manyone`). If you need parametric text, put it in the LaTeX display
  string shown over that literal value, never in the value itself. This surfaced in
  three independent sections in a single session before it was understood.

---

### Conclusion plus reason pairs: author the reason as radio

Where a question pairs a short conclusion input (over or underestimate,
increasing or decreasing) with a "because ..." reason, author the reason input as
radio from the start rather than converting it later. Reason options are full
sentences, so they fall under the option-length rule, and LaTeX does not render
inside a dropdown.

## PRTs and feedback

### Standard PRT feedback uses commonstring

Write `<prtcorrect>`, `<prtpartiallycorrect>` and `<prtincorrect>` with STACK's
`[[commonstring]]` defaults, as STACK's essentials file says ("Standard PRT
feedback") and as `reference/showcase.xml` does.

### Where the PRT feedback tag goes

- **One PRT:** put `[[feedback:prt1]]` in `<specificfeedback>`, and nowhere in
  the question text.
- **Several PRTs:** put each `[[feedback:prtN]]` inline, after the inputs for
  its part, and leave `<specificfeedback>` empty.
- Each tag appears exactly once (STACK's essentials file, "The feedback tag").
  The fast validator does not catch a repeat.
- Never put a feedback tag inside a hidden element, such as the
  `<div style="display:none;">` that holds a JSXGraph-bound input. Students
  would never see the feedback.

Why: Moodle's quiz settings control when specific feedback is shown (the
review options and the question behaviour). A tag in the question text bypasses
that control. A multi-part question needs each part's feedback next to the part
it is about, which only an inline tag can do.

### Parametric references everywhere

Anywhere a value depends on a randomised question variable, reference the
variable, not a number: `{@ta1@}`, `{@a@}` in CASText, and in test inputs as
STACK's essentials file says ("Test inputs").

Why: a literal that happens to match seed 1 silently breaks on every other seed.
In feedback it produces an incorrect worked solution on most seeds, and nothing
in the XML shows it.

Applies to `<testinput>`, `<truefeedback>`, `<falsefeedback>`,
`<generalfeedback>`, `<questionnote>`, and PRT `<tans>` / answer-test arguments.

Exception: genuinely non-randomised questions, or a variable that is a fixed
constant (`ta: 0` for "the derivative of a constant"). The audit script flags
these as false positives. Triage by checking whether the referenced variable
actually varies with the seed.

Tooling: `tools/audit-test-inputs.sh` flags hardcoded test inputs in randomised
questions. Run it before approving anything.

### Careful use of answertest-generated feedback

The rule is in STACK's essentials file, "Choose the answer test" ("Quiet"):
decide for each node whether the test's own feedback is meaningful, and set
`<quiet>1</quiet>` where it is not, typically when `<sans>` is built from
several inputs.

### False branch feedback is mandatory

Every false branch needs non-empty feedback: a hint towards the correct method,
not a worked solution. For example:

- "Try factorising by finding two numbers that multiply to give {@c@} and add to
  give {@b@}."
- "Check the sign of your coefficient. Completing the square gives \(-(b/2)^2\)."

Generic "Incorrect" is not acceptable, and neither is nothing.

Routing nodes are the exception. If a node's false branch continues to another
node (`<falsenextnode>` is not -1), its false feedback must stay empty, because
the node it routes to will supply the feedback.

### General feedback must contain a worked solution

Every STACK question except trivial arithmetic needs a `<generalfeedback>` with
a full worked solution. **Write the verbal explanation first** (what method,
what to look for, why each step follows), then support it with the calculation.
If the feedback is equations with no connecting prose, it needs rewriting.
Full sentences, full stops.

Why: a student who got it wrong should be able to learn the method from the
feedback alone, not just check an answer.

### Name the concept in the prose

The verbal explanation should name the topic's key concept or theorem, not
describe steps generically. Build a table of prose anchors for your own topics
and keep it here. It is the fastest way to stop feedback drifting into "first do
this, then do that". Examples of the form:

| Topic | Prose anchor |
|-------|--------------|
| Quadratics | "Completing the square rewrites x² + bx as (x + b/2)² − (b/2)²." |
| Polynomials | "By the factor theorem, (x − a) is a factor of f(x) if and only if f(a) = 0." |
| Integration | "Integration reverses differentiation. The constant of integration is needed because differentiation loses any constant." |

Prose goes before or alongside the equations, not after them as an afterthought.

### Diagnostic coverage (mandatory for randomised questions)

Every scored PRT in a randomised question must include at least one diagnostic
node targeting a named common error, not just generic false feedback. A PRT with
no plausible misconception is exempt, but must carry a short code comment saying
so. This is built in by the author at first draft, not bolted on later.

### Diagnostic nodes for common errors

Where a question type has predictable errors, add one or two extra PRT nodes to
catch them. Structure: node 0 checks the correct answer, node 1 checks a
specific common error and gives tailored feedback, the final false branch gives a
generic hint.

Errors worth diagnosing, as a starting list:

| Question type | Common errors |
|---------------|---------------|
| Factorising quadratics | Right product wrong sum, right sum wrong product, sign error |
| Completing the square | Wrong sign on the constant, halved coefficient not squared |
| Simultaneous equations | Swapped x and y, subtracted instead of added |
| Differentiation | Forgot to reduce the power, coefficient error |
| Surds | Did not simplify, rationalised the wrong denominator |
| Inequalities | Did not flip the sign when dividing by a negative |
| Indefinite integration | Forgot +c, wrong power, coefficient error |
| Integration with a condition | Integrated correctly but did not substitute the point to find c |

Not every question needs one. A simple one-step calculation can get by with a
good hint on the false branch.

Three traps when adding a diagnostic:

1. **The diagnostic variable must live in `<questionvariables>`, not
   `<feedbackvariables>`.** Feedback variables are only evaluated when the PRT
   runs, so they are not in scope when STACK parses
   `<testinput><value>wrong_ans</value></testinput>`. Put it in feedback
   variables and the test input evaluates to the symbolic name, and the
   diagnostic test fails. Rule of thumb: if a variable is named in a qtest, it
   goes in question variables.
2. **Check for collisions with existing test cases.** A generic wrong-answer
   test using `ta+1` may now hit your new diagnostic by accident on some seed,
   if `ta+1` happens to equal your diagnostic target there. After adding a
   diagnostic, re-read every qtest in that question.
3. **Make the matched branch terminal.** Set the diagnostic's true-branch
   `nextnode` to -1. It is better pedagogy to stop once the misconception is
   identified. Then add the diagnostic to the route of every test that passes
   through it (see "Question tests" below). Inserting the node changes the route of the existing wrong-answer
   test too.

### The diagnostic must be reachable and distinct on every seed

STACK's essentials file ("Test inputs") requires every diagnostic to be
reachable on every deployed variant. In this kit, constrain the randomisation
so a misconception value can never equal the correct answer, and drive both
the node `<tans>` and the qtest `<value>` from the same question variable so
they cannot drift apart.

### Question tests

STACK's essentials file, "Test every question", gives the rules for tests:
which tests every question needs, how to build test inputs, and how STACK
matches the expected answer note against the full route through the PRT. This
kit follows it, and adds the following.

- **Scores are `1.0000000` or `0.0000000`.** `<expectedscore>` is the raw PRT
  score, and in this kit a PRT scores 1 or 0, never a fraction. Write scores
  and penalties with seven decimal places, as in `reference/showcase.xml`.
- **Full routes: the worked example.** In Showcase 3, prt1 checks the
  x-coordinate, then the y-coordinate, and both branches of the x node lead to
  the y node. "x right, y wrong" and "both wrong" both end at `prt1-2-F`; only
  `prt1-1-T | prt1-2-F` and `prt1-1-F | prt1-2-F` tell them apart, and each
  needs its own test.
- **The route comes from the plan**, never from the validator's output. When a
  route test fails, the output shows `note=<actual trail> (exp <your route>)`.
  Decide which one is wrong (the test or the tree) before changing either.
- **Older questions** with single terminal notes: upgrade them to full routes
  when you next touch the question.
- **Integration exception to offsets.** When the teacher's answer contains
  `+c`, a wrong test uses `x^99`, not `ta+1`. The `+1` vanishes on
  differentiation, so `diff(ta+1, x)` still equals the integrand and the
  "forgot +c" diagnostic fires on what should be a completely wrong answer.
- **A derivation test: an example** of the essentials file's rule. Where
  `val2: k2^2*n` defines the displayed value and `ta2: k2*sqrt(n)` is the
  simplified form the student types, the correct test uses the derivation:

  ```xml
  <!-- BAD: tautological, never catches a val/ta inconsistency -->
  <testinput><name>ans2</name><value>ta2</value></testinput>

  <!-- GOOD: tests the derivation -->
  <testinput><name>ans2</name><value>sqrt(val2)/sqrt(n)</value></testinput>
  ```

  We found a live question where `val2` and `ta2` were inconsistent. The
  tautological test passed while students typing the correct answer were
  marked wrong.
- **Invalid-input tests:** one invalid entry, such as a symbol in a numerical
  input, with every other input correct. The PRT that reads it expects `NULL`
  (essentials file, "Expected answer note"); the others their normal route.

### Form checks: when "correct value" is not enough

STACK's essentials file, "Choose the answer test", explains why `AlgEquiv` is
form-blind and what a form test sees. This kit's recipes:

| Task wording | Recipe |
|---|---|
| "Expand", "write in the form ax^n + …", "simplify" | `AlgEquiv` value node plus an `EqualComAss` form node on the input itself. `EqualComAss` treats `-x^3/8` and `-1/8*x^3` as different forms; use `EqualComAssRules [oneMul]` if coefficients can be fractional. |
| "Factorise", "as a product of factors" | Swap the scoring node to `FacForm`, `testoptions x`. `FacForm` demands every common factor pulled out and rejects partial factorisations, so it cannot grade "linear × quadratic" hence-steps; those need a bespoke structural check. |
| Indefinite integral with +c | `Int`, `testoptions x`, `quiet 1` (essentials, "Integration"). |

Include a qtest that submits the unexpanded bracket and expects score 0.

Write the teacher's answer for a form node so that question-level
simplification leaves it in the required form, or set `questionsimplify` 0 and
wrap every `rand` call, list index and computed value in `ev(..., simp)`.

Form guards should test an inequality on structure, not an equality. A guard
like `degree(denom) == 1` silently rejects legitimate equivalent forms at the
boundary (`1+q/x` has denominator degree 0). Use `degree(denom) < 2`.

### Reject-qtests for "write in completed-square form"

Do not build the reject case by probing `ev(expand(ta),simp)`. On a seed where the
vertex sits at zero, `(x-0)^2+q` *is* `x^2+q`, so the expanded probe equals the
completed-square form, the form check correctly accepts it, and the reject test
fails for the wrong reason. Probe with a form that is value-equal but never in
completed-square form, such as the factored `(x-r1)(x-r2)` for distinct roots.

### Integration: use `Int`, and `tans` is the antiderivative

The rules are in STACK's essentials file, "Choose the answer test"
("Integration"). Prototype:
`reference/prototypes/integration_with_antidiff_prototype.xml`.

### Numerical answers: prefer tolerance over significant figures

Unless the significant-figure form is the skill being assessed, grade "to N
s.f." questions with `NumAbsolute` or `NumRelative` and a magnitude-appropriate
tolerance, not `NumSigFigs`. Reserve `NumSigFigs` for questions that assess the
form, and keep it on the correct-answer node only: use `NumRelative`
(tolerance around 0.05) on any diagnostic node.

Test values with trailing zeros: STACK's essentials file, "Choose the answer
test" ("Numbers").

### Sets

STACK's essentials file, "Choose the answer test" ("Sets", "Numbers"), covers
the input type and float-against-rational comparison. In particular, if a PRT
rounds with `round(x*10)/10` it returns exact rationals such as `989/10`, which
`Sets` treats as different from the float `98.9`: wrap the rounding in
`float(...)`.

### Matrices: never use `addrow`

This project runs STACK 4.13.1, where any question containing the text
`addrow` is reported as an upgrade error and its tests do not run (STACK's
essentials file, "Write the question variables"). For a row operation use
`rowadd`; to stack rows, build the matrix from lists:
`matrix(list_matrix_entries(r1), list_matrix_entries(r2))`.

### Never interpolate the graded answer into the stem

STACK's question review checklist covers this. Use a neutral instruction
("Write down the value of …") rather than "Show that … = {@ta1@}".

### Question names are immutable

`push-live.sh` matches questions to live quiz slots BY NAME. This is the kit's
rule, not Moodle's: Moodle never matches by name (STACK's essentials file,
"Update a question already in use"). Changing a `<name>`
during an edit pass breaks the match: the re-push refuses, because the quiz
still holds the old name and the file does not. Question names are admin-only
and never student-facing, so prose style rules do not apply to them. If a name
genuinely must change, remove the old one from the quiz with
`remove-from-quiz.php` first. That deletes its slot, so do it before students
have attempts on it.

### Page codes in question names

A quiz's page breaks live in Moodle, not in the XML, so the XML carries them in
the question names. Put `_P<NN>_` in every name, where `<NN>` is the plan's page
number (`E1_Ch1_A_P03_Exercise_A_Q2`). A letter may follow the digits
(`_P04b_`); it does not change the page.

- Every question in the file gets a code, descriptions included, or none does.
  `push-live.sh` uses the codes only when every slot in the live quiz has one,
  and otherwise falls back to grouping by count.
- A page's questions are consecutive in the file. Moodle adds slots in file
  order, so a `_P01_` after a `_P02_` starts a new page.
- `push-review.sh` follows the codes too, so the reviewer sees the planned
  pages.
- Choose the codes before the first publish: they are part of the name, and
  names are immutable.

`tools/audit-draft.py` checks both rules.

### Telling real failures from infrastructure flakes

Not every red result is a bug in your question. Two false-failure patterns are
worth recognising before you send an agent off to fix a phantom:

- **The fast API check false-fails some multiple choice items.** `api-check.sh`
  evaluates against its own seeds rather than the question's `<deployedseed>`
  values, so a multiple choice item whose qtest inputs use
  `first(mcq_correct(opts))` over algebraic option lists can report as failing on
  every seed but the first. Diagnosis rule: if the only failures are such items,
  and the pattern is "all inputs fail together on all-but-the-first seed", run
  `test-question.sh` on that one question before changing anything.
- **CAS timeouts leave an empty score.** Under concurrent load, a stack can
  intermittently return a blank `score=` / `note=` for a qtest. That is a
  timeout, not a grading defect. Tell them apart: a real defect fails the SAME
  test on EVERY seed with an actual wrong score; a flake leaves an EMPTY score on
  SCATTERED seeds while the identical test passes elsewhere. Confirm by isolating
  that question into a throwaway file and re-running.

---

## JSXGraph

- **Always use `[[jsxgraph]]` blocks, never raw `<script>` tags.** STACK strips
  `<script>` for security, so a raw script produces a blank figure.
- `[[jsxgraph]]` only renders inside `type="stack"` questions. For a figure-only
  slot use skinny STACK (see Question requirements).
- **Accessibility:** wrap every JSXGraph block in
  `<div role="img" aria-label="Description of figure">`.
- **Labels must not overlap curves.** Use `anchorX`, `anchorY` and `offset` to
  position them. For points near an intersection, use larger offsets.
- **Use `//` line comments, never `/* ... */` block comments** in JSXGraph
  JavaScript, and avoid Maxima-style `/* */` in CASText generally. STACK's
  CASText validator rejects question text containing `/* */` as "no longer
  supported". Moodle import still tolerates it, but it is on a deprecation path.

Embedding syntax, board sizing and templates: `jsxgraph` skill.

---

## Proof and "show that" questions

Three approaches, from Bickerton and Sangwin (2022). All three involve editorial
judgement, so flag them for a human before implementing.

### Faded worked examples

A worked solution with algebraic blanks for the student to fill in. It must
**always** follow a full static worked example showing the complete method with
no blanks. The pedagogic premise is that the student has just seen how it is
done and now reproduces the key steps; without that scaffolding the blanks are
guesswork. Blanks should target meaningful steps (the substitution, the
simplification), not trivial intermediate values.

### Parsons problems (drag and drop proof)

**Zero-mark formative exercise.** Set `<defaultgrade>0</defaultgrade>`. Proof
steps often admit several valid orderings, and grading one ordering penalises
correct reasoning. Feedback shows one valid ordering with a note that others may
also be valid.

**No distractors.** Every step is used in the proof.

Feedback tone: never "your proof is not quite right". Use "That was not the
order I was looking for, but other valid solutions may exist. Here is some
feedback based on the solution I was expecting:".

Note that Parsons grades ORDERED proofs only. It cannot grade bucket
classification, so use dropdowns for "sort these into categories" tasks.

### Reading comprehension (proof analysis)

Present a complete numbered proof, then ask questions about its structure:
which step does X, what justifies step Y, what would break if.

Each step a student justifies contains exactly one equality or inference, so it
has exactly one correct reason. Move any other equality into the connecting
prose between steps. A step that chains two equalities with different reasons
marks a defensible answer wrong.

Implementation for all three: patterns 6, 7 and 8 in the `stack-question-types`
skill, with prototypes in `reference/prototypes/`.

---

## Question-type variety

Tag every item with its Pointon and Sangwin type:

1. recall, 2. routine calculation, 3. classify, 4. interpret, 5. prove or show,
6. construct a counterexample, 7. construct an example, 8. criticise a fallacy.

Most routine practice is type 1 or 2, which is fine. But every self-check or
end-of-topic quiz should include at least one type 3, 7 or 8 question. Those are
the types that reveal whether a student understands the object or has only
memorised a procedure. Prototypes exist for types 3 and 7.

---

## End-of-topic review quizzes

If your material has end-of-chapter review, split it into two quizzes with
different jobs, in this order on the course page: section quizzes, then Mixed,
then Test Yourself, then the source document.

- **Mixed**: optional extra practice combining all the chapter's methods. The
  fuller set. Past-paper questions can stay deterministic here; that is the
  recognised exception to the randomisation floor.
- **Test Yourself**: a short self-assessment before moving on. Every wrong-answer
  feedback must name the section to revisit, alongside the usual method hint
  ("Revisit Section B: integrating rational powers"). A failed self-check should
  route the student back to the right place, not just restate the method.

Give both a course-page description so students can tell them apart.

---

## Randomisation

- **Every quiz needs at least a third of its items randomised.** Built in from
  the start, not a separate pass later.
- The planner marks each question "randomise" or "fixed", the author implements
  it, the reviewer enforces the threshold.
- **Build backwards**, so every variant has a good answer and takes the same
  method (STACK's essentials file, "Randomise with STACK's functions"). In this
  kit a good answer is an integer or a simple fraction, never trivially 0 or 1,
  and the numbers are never big enough that arithmetic becomes the difficulty.

### Deployed seeds

This section is the single statement of seed policy. STACK's essentials file,
"Deploy variants of a randomised question", gives the checks every seed must
pass: a distinct question note, no runtime errors, every test passing, no
degenerate variant. This kit fixes the numbers, and also deploys seeds where
STACK needs none.

- **Randomised questions:** one seed per distinct variant, up to 10
  `<deployedseed>` values. Replace any seed that duplicates another's note.
- **Fixed questions:** exactly one `<deployedseed>`. STACK needs none here,
  but this kit's tools expect every question to carry its own seeds (below).
- **Parsons problems:** about 3, so students see different shuffles.
- **Descriptions** have none.

Every question carries its own seeds in the XML, so nothing downstream picks
them. `test-question.sh` and `api-check.sh` test the XML's seeds and fall back
to the defaults 1, 42, 137, 256, 999 only when a question has none, which should
not happen. `push-live.sh` deploys exactly the XML's seeds and adds none. Only
a STACK question with no `<deployedseed>` at all gets 5 random seeds, with a
warning, because those seeds have never been validated. Treat that warning as a
defect in the XML.

### Degenerate variant prevention

When randomising coefficients, check for values that collapse the mathematical
structure:

- Zero coefficients that eliminate terms (a zero linear coefficient makes
  completing the square trivial).
- Perfect squares where a surd answer is expected.
- Negative discriminants where real roots are expected.
- Equal values that make the problem degenerate (two identical points).

Use `rand_with_prohib` or explicit guards. This kit is stricter than STACK's
essentials file, which allows 0, 1 and zero coefficients as deliberate boundary
cases: here they are defects.

### Question-variable gotchas

STACK's essentials file, "Write the question variables", covers assignment,
forbidden names such as `tab`, and inputs not existing yet. An EMPTY score in a
qtest, rather than a wrong one, usually means the question variables stopped
with an error and the PRT never ran.

---

## Editorial conventions

- **Strip all source numbering and cross-references.** "Example 3", "see page
  72", "Workbook 16": these mean nothing outside the original document.
- **Multiple choice distractors must be clear standalone statements.** No hybrid
  "answer plus reason" options where the parts can contradict each other. Each
  distractor should target a specific misconception that a real student could
  plausibly hold.
- **Converting "explain why" into multiple choice is an editorial decision.**
  When a source question asks "how can you tell", "why", or "explain", and you
  convert it, list the distractors with your rationale in the hand-off notes so
  the reviewer can audit them.
- **Say when an answer is an example.** When a part asks for any one object
  out of many that satisfy a condition (a vector in a null space, a map with a
  given null space, two non-commuting matrices, constants for which a system
  has no solution), write "Give an example of ...", not "Give ...". Students
  then know that any valid answer earns the marks and that they need not find
  a particular one. A part with a unique answer keep their usual wording.
- **Ask for an exact value, not a bound.** Never place an input after ≤, ≥,
  "at least" or "at most": any weaker bound also makes the statement true, so a
  correct statement would be marked wrong. Ask for the smallest or largest
  possible value, or write the step as an equality
  (\(\dim\operatorname{range}S+\dim\operatorname{range}T\le\dim\operatorname{range}S+\dim\operatorname{null}S=\) [input]).
  Where a bound is unavoidable, say "give the best bound". The exception is
  describing a set exactly ("find the set of values: \(k\le\) [input] or
  \(k\ge\) [input]").
- **Prefer exact form over decimals.** Unless the question is explicitly about
  rounding or estimation, ask for exact answers (`sqrt(2)`, `3/4`, `pi/6`) and
  use `AlgEquiv`. Reserve numerical tolerance for genuinely numerical contexts.
- **Page grouping:** a question that refers to "the figure above" must be on the
  same quiz page as that figure.
- **No lonely pages.** A single question stranded on its own page reads as an
  afterthought.

---

## Adapting this guide to a different level

The rules above were written for late-school and early-undergraduate algebra and
calculus. If your material sits somewhere else, expect these shifts, and record
your decisions here rather than leaving them implicit:

- **Lower level:** more numerical and fewer algebraic inputs, more table-fill
  questions, more classification via dropdowns, a higher proportion of
  description items, and figures for non-algebraic purposes (shapes, grids, bar
  charts, number lines). Expect a lower randomisation ceiling too: "two thirds of
  12" has far fewer good variants than "factorise x² + bx + c", so be realistic
  about which questions can meaningfully vary.
- **Physical or hands-on tasks** (construct with compasses, measure, shade a
  grid) cannot become STACK questions directly. Convert them to test the
  mathematical understanding behind the task: show the result of the
  construction as a figure and ask questions about it. Interactive JSXGraph
  works for plotting points or reading values. Drag and drop can order
  construction steps. Skip a whole topic only when it genuinely cannot work
  online, and then leave a description item saying so and pointing at the source.
- **Higher level:** expect more multi-step questions where a later part depends
  on an earlier answer. Prefer "correct given your part (a)" over "matches the
  teacher's part (b)".
