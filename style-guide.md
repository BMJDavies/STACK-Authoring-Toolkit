# Content rules and style guide

**Audience: the assistant. This is authoritative for content conventions. Edit it to make the rules yours.**

The single source of truth for content conventions in this project. Agents read
this before writing or reviewing anything. Implementation patterns (XML
skeletons, PRT wiring, JSXGraph templates) live in the skill packages under
`.claude/skills/`; this file is about what standards the content must meet.

Almost every rule below was written after something went wrong. Where the reason
is not obvious, it is stated. Treat the "why" as part of the rule: if you
understand why, you will spot the next variant of the same problem.

> **Freeze this file while a book or module is in production.** Changing
> conventions mid-run makes author and reviewer behaviour drift between
> sections. Put new rules discovered during a run into `feedback/lessons-learned.md`
> and migrate them in at the next natural boundary.

---

## Quiz settings

- Question behaviour: `immediatefeedback`.
- Attempts allowed: unlimited (this is formative material).
- Correct answer display: hidden (`reviewrightanswer=0`).
- One quiz per section. Split later if a quiz gets very long.
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
  - At least **two** `<qtest>` cases (correct and wrong). Without them the PRT
    logic is untested. Multi-input questions also need a mixed case.
  - At least one `<deployedseed>`, so STACK stops warning about missing
    tests or variants.
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

- **Escape `<` and `>` inside Maxima code** (`<value>`, `<tans>`, `<sans>`).
  A raw `<` causes `XML_ERR_NAME_REQUIRED` on import. Use `&lt;` / `&gt;` or
  wrap in CDATA.
- **No named HTML entities** like `&ge;`, `&le;`, `&ne;`. Moodle's XML parser
  only defines `&lt; &gt; &amp; &apos; &quot;`. Use the unicode character
  directly (`≥`, `≤`, `≠`) or a numeric entity (`&#8805;`).

---

## Inputs

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
- **Never use the same `[[input:ansN]]` placeholder twice** in one question.
  STACK deduplicates placeholders with `array_unique()` before `format_text()`
  but not after, which causes a mismatch and throws a coding exception. If you
  need the same input in two places, restructure the question.
- **Never repeat an `[[input:ansN]]` placeholder.** STACK's renderer removes
  duplicate placeholders with `array_unique()` before formatting the text but not
  after, and the resulting mismatch throws a coding exception. If the same input
  needs referring to twice, restructure the question or refer to it in prose.

- **Wrap input lines in `<div>`, never `<p>`.** Any HTML line containing
  `[[input:ansN]]` must be in a `<div>`. STACK renders `[[validation:ansN]]` as a
  block-level `<div>`, and HTML5 auto-closes an open `<p>` when it meets a block
  element, so the paragraph terminates early and the input plus the text after it
  end up stacked on separate lines. Wrapping in `<div>` gives valid nested divs,
  and the empty validation divs are `display:none` in STACK's CSS so they cost
  nothing. This applies to every input type: matrix, algebraic, numerical,
  dropdown, multiple choice. Keep prose in a `<p>` and put the
  input/validation/feedback line in its own `<div>`.
  - Tooling caveat: `render-check` reads the database, not the file on disk.
    After editing XML, re-import before re-running it, or you are checking the
    old version.

### Forbidden words (`forbidwords`)

When a question asks the student to do by hand something Maxima could do in one
call, add that function name to the input's `<forbidwords>`. Otherwise a student
can type the CAS command instead of doing the work.

| Question asks for | Forbid |
|-------------------|--------|
| Derivative, "differentiate" | `diff` |
| Indefinite or definite integral | `integrate`, `int` |
| Factorisation | `factor` |
| Expansion | `expand` |
| Solution of an equation | `solve` |
| Roots of a polynomial | `solve`, `allroots` |

Only forbid the function for the operation actually being assessed. A question
that asks for a derivative and then evaluates it forbids `diff`, not the
evaluation.

### Syntax hints

When the expected answer uses `sqrt`, `pi`, `inf` or other non-obvious notation:

- Set `<syntaxhint>sqrt()</syntaxhint>` (or `pi`, etc.).
- Set `<syntaxattribute>1</syntaxattribute>`, which makes the hint a greyed-out
  placeholder that disappears on focus.
- **Hints must be generic placeholders, never answer-shaped.** Use `a+sqrt(b)`
  or `(a+sqrt(b))/c`, never the actual values (`-2+sqrt(5)`). Students read an
  answer-shaped hint as the answer.
- Any section that introduces new input notation should open with a short
  description explaining how to type it ("Type `sqrt(5)` for √5").

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
- **Pattern:** define the option list in question variables using
  `mcq_correct()` / `mcq_incorrect()`, then reference it with `[[input:ansN]]`.
  Never define options inline.
- **Option ordering:** natural logical order (numbers ascending, classifications
  in standard order). Never put the correct option first, or pattern-spotters
  will pick it without engaging.
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

`<prtcorrect>`, `<prtpartiallycorrect>` and `<prtincorrect>` take STACK's
language-string defaults, exactly as in `content/reference/showcase.xml`:

    [[commonstring key="symbolicprtcorrectfeedback"/]] [[commonstring key="defaultprtcorrectfeedback"/]]

and likewise with `partiallycorrect` and `incorrect` in place of `correct`.
Never hardcode text, icons or colours in these three fields.

Why: the strings then follow the site's language and STACK's own styling, and
one change in Moodle updates every question. Hardcoded copies drift and cannot
be translated.

### Where the PRT feedback tag goes

- **One PRT:** put `[[feedback:prt1]]` in `<specificfeedback>`, and nowhere in
  the question text.
- **Several PRTs:** put each `[[feedback:prtN]]` inline, after the inputs for
  its part, and leave `<specificfeedback>` empty.
- Each tag appears exactly once. STACK marks a question broken on import if the
  question text and specific feedback together contain a tag twice, and the
  fast validator does not catch this.
- Never put a feedback tag inside a hidden element, such as the
  `<div style="display:none;">` that holds a JSXGraph-bound input. Students
  would never see the feedback.

Why: Moodle's quiz settings control when specific feedback is shown (the
review options and the question behaviour). A tag in the question text bypasses
that control. A multi-part question needs each part's feedback next to the part
it is about, which only an inline tag can do.

### Parametric references everywhere

Anywhere a value depends on a randomised question variable (test inputs,
feedback HTML, general feedback, question notes), reference the variable, not a
number. Use `ta` / `ta1` / `ta+1` in test inputs and `{@ta1@}`, `{@a@}` in
CASText feedback.

Why: a literal that happens to match seed 1 silently breaks on every other seed.
In a test input it produces a false PASS in the validator while marking real
students wrong in production. In feedback it produces an incorrect worked
solution on most seeds. Neither is visible from reading the XML.

Applies to `<testinput>`, `<truefeedback>`, `<falsefeedback>`,
`<generalfeedback>`, `<questionnote>`, and PRT `<tans>` / answer-test arguments.

Exception: genuinely non-randomised questions, or a variable that is a fixed
constant (`ta: 0` for "the derivative of a constant"). The audit script flags
these as false positives. Triage by checking whether the referenced variable
actually varies with the seed.

Tooling: `tools/audit-test-inputs.sh` flags hardcoded test inputs in randomised
questions. Run it before approving anything.

### Careful use of answertest-generated feedback

Answer tests can produce feedback of their own.  Sometimes this is meaningful to the students, 
but sometimes it is an artefact of the question setup and should not be used.

For each node of each PRT decide if the answertest feedback is meaningful or not. If the answertest feedback is not meaningful then choose the `quiet=yes` option in the PRT node (in the XML, `<quiet>1</quiet>` on that `<node>`).

- If the answer test applies directly to the student's answer then the feedback from the answertest is likely to be meaningful.
- If the answer test is applied to a variable constructed from many inputs, then the feedback from the answertest is unlikely to be meaningful.


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
   through it (see "`<expectedanswernote>` is the full route through the
   PRT"). Inserting the node changes the route of the existing wrong-answer
   test too.

### The diagnostic must be reachable and distinct on every seed

On some seeds a misconception value (add instead of multiply, say) can equal the
correct answer. Then the "wrong" test scores full marks, hits the correct
branch, and the diagnostic is pedagogically void on those draws. Constrain the
randomisation so the two can never coincide, and drive both the node `<tans>`
and the qtest `<value>` from the same bound question variable so they cannot
drift apart.

### `<expectedscore>` is the raw PRT score

`<expectedscore>` is what the PRT returns (1.0 or 0.0), not the weighted
contribution to the question total. Correct cases use `1.0000000`, wrong cases
`0.0000000`. Never fractional.

### `<expectedanswernote>` is the full route through the PRT

Since STACK 4.13.0 a test's expected answer note can specify the route through
the tree, not just where it ends (STACK docs: `STACK_question_admin/Testing.md`,
"Answer note expectation construction"). Use this in every test. For each PRT,
list the answer note of **every node the response visits, in order**, separated
by ` | `:

    <expectedanswernote>prt1-1-F | prt1-2-F</expectedanswernote>

Why: a terminal note alone cannot tell routes apart that end at the same leaf.
In Showcase 3, prt1 checks the x-coordinate, then the y-coordinate, and both
branches of the x node lead to the y node. "x right, y wrong" and "both wrong"
both end at `prt1-2-F`. Only the routes `prt1-1-T | prt1-2-F` and
`prt1-1-F | prt1-2-F` distinguish them. A test that pins the whole route also
catches a mis-wired `nextnode`, which a terminal-only test passes silently.

How STACK matches (`questiontestresult.php`, `test_answer_note`):

- The expected notes must all appear in the actual trail **in order**. Extra
  actual notes are condoned.
- The **last** expected note must equal the last actual note. This end anchor
  is the default, written `(...]`.
- Leave off the brackets: write the bare list. **Do not start-anchor** with
  `[...]`. The trail also contains the answer test's own notes
  (`ATAlgEquiv_String | prt1-1-F`, `ATSet_wrongentries. | prt1-1-F`), even
  on quiet nodes. They usually come first, so a start anchor fails.
- **Never put answer-test notes (`AT...`) in the expectation.** They depend on
  the input and are not part of the route you designed.
- Special values: `NULL` means the PRT did not fire (invalid or blank input).
  `prt1-bail` means it bailed out in the feedback variables, and is never
  anchored. `()` accepts anything, so never use it in this kit.
- A single-node PRT has a one-note route (`prt1-1-T`), which is the same as the
  old form.

The route comes from the design, never from the validator's output. Write the
route the plan intends, and let the validator confirm it. Copying the actual
trail back into the test turns it into a snapshot that tests nothing. When a
route test fails, the output shows `note=<actual trail> (exp <your route>)`.
Decide which one is wrong (the test or the tree) before changing either.

Answer notes are matched as exact strings, so every node's `trueanswernote`
and `falseanswernote` must be unique within its PRT. The `prtN-K-T/F`
convention guarantees this.

Re-check every route in a question whenever you insert, remove or re-wire a
node.

Older questions with single terminal notes still pass, because a bare note is
read as `(note]`. Upgrade them when you next touch the question.

### Wrong test cases use offsets, not independent values

Wrong test inputs must be offsets from the teacher's answer (`ta+1`, `ta-1`),
not independent random values that could accidentally equal the correct answer.

Exception for integration: when the teacher answer contains `+c`, use `x^99`
rather than `ta+1`. The `+1` vanishes on differentiation, so `diff(ta+1, x)`
still equals the integrand and the "forgot +c" diagnostic fires on what should
be a completely wrong answer.

### Test every leaf path

If a PRT has a diagnostic node, include a test case that exercises that specific
path, not just correct and completely-wrong. Aim for one test case per distinct
route, not just per leaf. Where two routes reach the same leaf (as in
Showcase 3), each needs its own test, with its own full-route expectation.

### Non-tautological test inputs

When the correct answer is derived from a separately defined question variable
(say `val2: k2^2*n` defines the displayed value and `ta2: k2*sqrt(n)` is the
simplified form the student must type), the correct-answer qtest must use the
**derivation expression**, not the `ta` variable:

```xml
<!-- BAD: tautological, never catches a val/ta inconsistency -->
<testinput><name>ans2</name><value>ta2</value></testinput>

<!-- GOOD: tests the derivation -->
<testinput><name>ans2</name><value>sqrt(val2)/sqrt(n)</value></testinput>
```

We found a live question where `val2` and `ta2` were inconsistent. The
tautological test passed happily while students typing the actually-correct
answer were marked wrong.

### Form checks: when "correct value" is not enough

When the stem demands a specific FORM ("expand", "factorise", "simplify",
"integrate with +c"), `AlgEquiv` alone is form-blind: it gives full marks to an
unexpanded or unfactorised answer, because `(1+2x)^3` really is equal to its own
expansion. Guard the form with a second node.

| Task wording | Recipe |
|---|---|
| "Expand", "write in the form ax^n + …", "simplify" | `AlgEquiv` value node plus an `EqualComAss` form node, with PRT `autosimplify=0`. Use `EqualComAssRules [oneMul]` if coefficients can be fractional, because plain `EqualComAss` rejects `-1/8*x^3`. |
| "Factorise", "as a product of factors" | Swap the scoring node to `FacForm`, `testoptions x`. Do not set autosimplify=0. `FacForm` demands every common factor pulled out and rejects partial factorisations, so it cannot grade "linear × quadratic" hence-steps; those need a bespoke structural check. |
| Indefinite integral with +c | `Int` (`ATInt`), `tans` set to the bare antiderivative and NOT the +c form, `testoptions x`, `quiet 1`. |

Include a qtest that submits the unexpanded bracket and expects score 0.

`questionsimplify` 0 or 1? The trap is that `simp` runs at input-evaluation
time, before the PRT ever sees the answer.

- Expand checks on polynomials: `questionsimplify=1` is safe, because simp never
  multiplies out `(x-a)^2`. Just add the form node and `autosimplify=0`.
- Simplify-collect (`5*x*x^2`) and surds (`sqrt(18)`): `questionsimplify` MUST
  be 0. Otherwise simp collapses the student's answer at input time and your
  form node becomes a silent no-op that still awards full marks. Going to 0 then
  forces you to wrap every rand call, list index and form-node `ta` in
  `ev(..., simp)`, and to force simp on displays.
- Simplest-surd form needs no new recipe: under `simp:false`, `EqualComAss`
  already rejects `sqrt(18)` for `3*sqrt(2)`, because they are different trees.

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

`Int` differentiates the student's answer and compares with `diff(tans, x)`. If
`tans` is the integrand, the test compares against the derivative of the
integrand and always fails. Compute `F_expr: integrate(f_expr, x);` in question
variables and use `F_expr` as `tans`.

`Int` defaults to `constint:true`, so it already requires a constant of
integration and accepts any constant name.

Two hard limits:

- **Never apply `Int` to a definite integral, or to a "find c given a point"
  question.** With `constint:true`, `ATInt(x^2+3, x^2+3, x)` is false: it
  rejects the correct answer. A `+c` in the worked solution is not enough of a
  signal. Read the stem.
- **Do not use `Antidiff` or `AddConst`** if your STACK install behaves like the one this was built on. Both set `simp:false` inside their block without declaring it local,
  which leaks `simp:false` into global CAS state and corrupts every later PRT
  node and CASText. The symptoms are baffling: empty PRT results on most seeds,
  "unevaluated casstring" warnings, and hung Maxima processes. `Int` saves and
  restores `simp` correctly. Prototype:
  `content/reference/prototypes/integration_with_antidiff_prototype.xml`.

### Numerical answers: prefer tolerance over significant figures

Unless the significant-figure FORM is the skill being assessed, grade "to N
s.f." questions with `NumAbsolute` and a magnitude-appropriate tolerance, not
`NumSigFigs`. `NumSigFigs` has three failure modes that cost three fix rounds
on one section:

1. Trailing-zero loss: `90.0` prints as `90` and is counted as 2 s.f., so the
   correct answer is rejected.
2. Values of 100 or more give `dp=0`, producing integer strings that
   `NumSigFigs` will not accept as unambiguous.
3. A `dispdp(...)` STRING fed as a test input to a `numerical` input is a
   string, not a number, so validation fails with an empty score.

Feed rounded numbers, `float(round(v*10^k)/10^k)`, as qtest inputs. Reserve
strict `NumSigFigs` for cases where s.f. form itself is assessed, and then
constrain the pool so no correct value has a trailing zero at the target figure.

Related: for a `NumDecPlaces` question whose answer ends in zeros at the required
precision (`28.8000` to 4 dp), do not write the literal in the qtest. Test inputs
are CAS-evaluated and a float carries no trailing zeros, so `28.8000` collapses
to `28.8`, the test reads 1 dp instead of 4, and the correct answer is marked
wrong on every seed. Feed `dispdp(float(ta),N)` instead.

And for a question graded to N significant figures, keep `NumSigFigs` on the
correct-answer node but use `NumRelative` (tolerance around 0.05) on any
diagnostic node. `NumSigFigs` miscounts on floats with a trailing zero at the
required precision, so the diagnostic misfires on exactly those seeds.

### Sets

- **There is no `set` input type.** `<type>set</type>` throws
  `stack_input_factory: unknown input type set` and crashes validation at the
  first question. Use `<type>algebraic</type>`. Students type `{a, b}`, which
  parses as a set, and `AlgEquiv` / `Sets` compares set to set.
- **Set qtest values must be pre-evaluated bound variables.** An inline computed
  set (`setify(...)`, `makelist(...)`, or arithmetic on elements) does not
  resolve to a validated set answer: input validation fails and the PRT never
  runs, leaving an empty score. Pre-evaluate each qtest set into its own question
  variable and put only the bare name in the test input. Same for any PRT
  `<tans>` that is a computed set. A passing correct case is not evidence that
  the wrong and diagnostic cases pass, because the correct case usually already
  uses a bare variable.
- **Round-then-`Sets` grading: match rational against rational.** If the PRT
  rounds via `round(x*10)/10` it returns exact rationals like `989/10`. Compared
  against float literals `{98.9, 261.1}`, every entry reports as both wrong and
  missing, and the correct answer scores 0. Wrap the rounding in `float(...)`.

### Never interpolate the graded answer into the stem

A stem like `Show that log … = {@ta1@}` prints the very value the student is
meant to produce, so the graded input is free marks. Use a neutral instruction
("Write down the value of …") and let the PRT do the grading. This applies to
any prompt whose target equals a graded input value.

### Question names are immutable

`push-live.sh` matches questions to live quiz slots BY NAME. Changing a `<name>`
during an edit pass breaks the match: the re-push ADDS the renamed question as a
new slot and orphans the old one as a stale, un-updated duplicate. Question names
are admin-only and never student-facing, so prose style rules do not apply to
them. If a name genuinely must change, clean up the duplicate afterwards with
`remove-from-quiz.php`.

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

Implementation for all three: patterns 6, 7 and 8 in the `stack-question-types`
skill, with prototypes in `content/reference/prototypes/`.

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
- **Design backwards:** pick the answer first, then build the question from it.
- **Constrain the random values** so every variant produces a nice answer (an
  integer or a simple fraction).
- Avoid variants where the answer is trivially 0 or 1, or where the numbers are
  big enough that arithmetic becomes the difficulty.
- The same solution method must work for every variant.

### Degenerate variant prevention

When randomising coefficients, check for values that collapse the mathematical
structure:

- Zero coefficients that eliminate terms (a zero linear coefficient makes
  completing the square trivial).
- Perfect squares where a surd answer is expected.
- Negative discriminants where real roots are expected.
- Equal values that make the problem degenerate (two identical points).

Use `rand_with_prohib` or explicit guards.

### Question-variable gotchas

- **`questionsimplify=0` runs the WHOLE question under `simp:false`, including
  the question variables block.** Under `simp:false`, list indexing like
  `klist[rand(6)+1]` throws "subscript must be an integer", because `rand(6)+1`
  never collapses to a number. That aborts the variable block and leaves every
  downstream variable unbound. The symptom in validation is a qtest returning an
  EMPTY score, not a wrong value, because the PRT never ran. Select random
  elements with `rand([...])` instead of `list[rand(n)+1]`, and wrap any numeric
  variable you display or feed to a test in `ev(..., simp)`.
- **`tab` is a forbidden variable name** in STACK even though plain Maxima
  accepts it. STACK's keyval parser rejects it and invalidates the entire
  question variables block, so the question never sets up. If a short variable
  name behaves oddly, test it against the parser before assuming the maths is
  wrong.

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
