---
name: stack-question-types
description: Reference for STACK question types, XML skeletons, PRT patterns and test-case conventions. The single authoring reference for how to build each question type.
---

# STACK question types and PRT patterns

> Content rules (what standards to meet) are in `style-guide.md` (and
> `project/style-guide.md`). This skill
> covers implementation patterns (how to build each type).

## Type picker

| Task | Type | Notes |
|------|------|-------|
| Narrative, key point, figure only | `description` | No inputs, no PRTs. But see the figure caveat below. |
| "Which of these…" with fixed options | `stack` + dropdown | `mcq_correct` PRT |
| "Compute / simplify to an expression" | `stack` + algebraic | `AlgEquiv` PRT |
| "Compute a number to N dp" | `stack` + numerical | `NumDecPlaces` PRT |
| Multi-part with several answers | `stack` + several inputs and PRTs | One PRT per part |
| Table with entries to fill | `stack` + `tb-table` inputs | Compact validation |
| "Show that…" (procedural) | `stack` + faded example | Pattern 6 |
| "Show that…" (reasoning order) | `stack` + parsons | Pattern 7 |
| Proof analysis / comprehension | `stack` + mixed inputs | Pattern 8 |
| Calculation whose working matters | `stack` + freetext working, answers graded automatically | Pattern 9 (STACK patterns A, B) |
| Proof the student writes in full | `stack` + freetext, 0 marks, model proof to compare | Pattern 9 (STACK pattern C) |
| Classify objects into categories (type 3) | `stack` + one dropdown per object | Copy `reference/prototypes/classify_objects_prototype.xml`. A Parsons grouping layout (`[[parsons columns="n"]]`, `matchlib.mac`) also works: STACK's `agents/stack_parsons.md` and its `Drag_and_drop-1-grouping.xml` example. |
| "Give an example with property P" (type 7) | `stack` + algebraic, property-checking PRT | Copy `construct_instance_prototype.xml`. The PRT checks a computed property of the answer, not equivalence to one model answer. |
| Indefinite integral, antidifferentiation | `stack` + algebraic, `Int` PRT | Copy `integration_with_antidiff_prototype.xml`. Rules: STACK's essentials file, "Integration". |
| Figure or worked example with no input | `stack` with no input, PRT or qtest | "Skinny STACK". A `description` renders `[[jsxgraph]]` as literal text. |

When in doubt, copy a question of the same shape out of `reference/showcase.xml`.

**Question-type variety.** Most routine practice is Pointon and Sangwin type 1
or 2 (recall, routine calculation), which is fine. But every self-check quiz
should include at least one type 3 (classify), 7 (construct an example) or 8
(criticise a fallacy). Prototypes exist for 3 and 7. Type 8 has no prototype
yet, so flag it if a section wants one.

## Minimum skeleton: description

```xml
<question type="description">
  <name><text>Section X: Heading</text></name>
  <questiontext format="html"><text><![CDATA[<h3>...</h3><p>...</p>]]></text></questiontext>
  <generalfeedback format="html"><text></text></generalfeedback>
  <defaultgrade>0</defaultgrade>
  <penalty>0</penalty>
  <hidden>0</hidden>
</question>
```

## STACK question skeleton

Do not hand-write this from scratch. Copy a `<question type="stack">` block from
`reference/showcase.xml`, then change `<name>`, `<questiontext>`,
`<questionvariables>`, the input `tans` values and the PRT nodes. All the
surrounding boilerplate stays exactly as it is.

What every question needs is in STACK's essentials file and `style-guide.md`
("Question requirements"). The kit-specific elements to fill in after copying:

- `<questionvariables>` with the teacher's answer `ta1` for input `ans1`;
- the feedback tags, placed as the style guide says ("Where the PRT feedback
  tag goes");
- `<stackversion>` from `project/AGENTS.md`;
- `<questiondescription>` with the provenance note;
- `<generalfeedback>` with the worked solution.

---

## PRT patterns

### Pattern 1: algebraic (AlgEquiv)

Node 0: `AlgEquiv(ans1, ta1)`, true scores 1, false scores 0.
Set `forbidfloat=1` where the answer must be exact.

Note that this is form-blind. If the stem says "expand" or "factorise", see the form-check recipes in the style guide.

### Pattern 2: numerical (NumDecPlaces)

Node 0: `NumDecPlaces(ans1, ta1, N)` where N is the required decimal places.
Set `forbidfloat=0`, `requirelowestterms=0`.

### Pattern 3: dropdown (mcq_correct)

The teacher's answer is a list of `[option, boolean]` pairs:

```maxima
ta1: [["convergent", false], ["divergent", true], ["oscillating", false]];
```

PRT `<tans>`: `first(mcq_correct(ta1))`.

Test values: correct is `first(mcq_correct(ta1))`, wrong is
`first(mcq_incorrect(ta1))`.

### Pattern 4: multi-input, independent PRTs

One input and one PRT per part. Each PRT `value` is 1, `defaultgrade` is 1, and
Moodle distributes the weight. Name them in aligned pairs: `ans1`/`prt1`, `ans2`/`prt2`.

### Pattern 5: multi-input, dependent PRTs

Rare. The PRT for part (b) reads the student's `ans1` and checks (b) relative to
it. Prefer "correct given your (a)" over "matches the teacher's (b)": it is
fairer and it is what a human marker would do.

### Adding a diagnostic node

1. Define the misconception value in `<questionvariables>`, NOT in
   `<feedbackvariables>` (see the style guide for why).
2. Add a `<node>` with `<answertest>AlgEquiv</answertest>`, `<sans>ans1</sans>`,
   `<tans>wrong_ans</tans>`.
3. Route node 0's `<falsenextnode>` to the new node's index.
4. The new node's true branch gives the specific error feedback and terminates
   (`nextnode` = -1). Its false branch gives the generic method hint.
5. Clear node 0's `<falsefeedback>`: it is a routing node now.
6. Update the route in the existing wrong-answer test's
   `<expectedanswernote>` (it now passes through the new node), and add a test
   that exercises the diagnostic path. Every expectation is the full route,
   e.g. `prt1-1-F | prt1-2-T` (STACK's essentials file, "Expected answer note").

Match the structure in `reference/showcase.xml`.

---

## Proof and "show that" patterns

All four involve editorial decisions about scaffolding level. Flag them for human review.

### Pattern 6: faded worked example

A worked solution with algebraic blanks. Each blank is an `AlgEquiv` input.

**Sequencing rule:** a faded example must always follow a full static worked
example (zero marks) showing the complete method with no blanks. The pedagogic
premise is that the student has just seen how it is done and now reproduces the
key steps. Without that, the blanks are guesswork. The planner pairs them; the
reviewer blocks if the pairing is missing.

Structure: question text shows the method with `[[input:ansN]]` at key algebraic
steps (the substitution, the simplification, not "compute b² − 4ac" in
isolation), general feedback shows the complete solution, one `AlgEquiv` PRT per
blank, styled with `tb-example`.

Prototype: `reference/prototypes/faded_example_prototype.xml`

### Pattern 7: Parsons problem (drag and drop proof)

**The technique is STACK's.** Read `agents/stack_parsons.md` (with
`tools/stack-guide.sh stack_parsons.md`, as for the essentials file) for the
block, the input, the libraries, the proof construction functions, the PRT and
the tests. This pattern adds only the kit's choices for a proof. Copy
`reference/prototypes/parsons_prototype.xml`.

The kit's choices, in addition to "Parsons problems" in `style-guide.md`
(zero marks, no distractors unless the plan asks for them, the feedback
wording):

- **Layout:** proof (`[[parsons input="ans1"]]`), with `"headers": ["Your
  proof:"]` and `"available_header": ["Drag from here:"]`.
- **Library:** `stack_include_contrib("prooflib.mac")`, STACK's recommendation
  for live questions. A project that prefers the server's installed copy,
  `stack_include("contribl://prooflib.mac")`, says so in its own style guide.
- **Model answer:** `[ta, proof_steps]`, written out in `<tans>`, never a
  variable holding that list: STACK builds the teacher's-answer test by
  splitting the unevaluated model answer at commas.
- **Marking:** accept every equivalent order, with automatic feedback:
  `[pd, saa]: proof_assessment(sa, proof_alternatives(ta))`, one node
  `AlgEquiv(pd, 0)`, quiet, and `{@proof_assessment_display(saa,
  proof_steps)@}` in the false branch after the kit's feedback sentence.
- **Tests:** correct `apply(parsons_answer, [ta, proof_steps])`; wrong built
  from `ta`, never from the shuffled `proof_steps` (which may already be in
  the right order on some variant), for example the first two steps swapped.
- **Variants:** about 3, so students see different shuffles (`style-guide.md`,
  seed policy).

### Pattern 8: reading comprehension (proof analysis)

Present a complete numbered proof, then ask about its structure: which step does
X, what justifies step Y, what is the key property used, what would break if.

Use it after students have learned a technique, to test whether they understand
why it works rather than how to execute it.

Prototype: `reference/prototypes/reading_comp_prototype.xml`

### Pattern 9: free-text working or proof

**The technique is STACK's.** Read `agents/stack_free_text.md` (with
`tools/stack-guide.sh stack_free_text.md`, as for the essentials file) for the
four patterns (A to D), the input and its `manualgraded` option, the layout,
the extractors, the model working, the PRTs and the tests. This pattern adds
only the kit's choices. Copy `reference/prototypes/free_text_prototype.xml`
(patterns A, A2, B and C); `reference/showcase.xml`, Showcase 13, is pattern A.

The kit's choices, in addition to "Free-text working and proofs" in
`style-guide.md`:

- **Pattern:** the planner chooses one per item and records it on the
  item's **Type** line, for example `stack + freetext (pattern B)`. A and B,
  auto-graded with the working ungraded, come first.
- **Model working on every seed:** after `tools/test-question.sh` has
  imported the file, run `tools/show-ta.sh <file> ans1` (or the freetext
  input's name) and read every line of every seed. No other kit tool shows
  what the `[[ascii]]` block receives, and the `{#...#}` printing faults
  appear on some seeds only.
- **Preview check:** STACK's file asks for a check in a browser. The
  reviewer does it in the review quiz (`tools/push-review.sh`), or asks the
  user to, and records what was checked, on which seeds, in the feedback
  document.

---

## Debugging a failing PRT

1. Run `tools/test-question.sh` and read the FAIL lines.
2. An unexpected `note` means the branching is wrong. Inspect the graph.
3. A score off by rounding means a PRT node `value` is wrong.
4. One seed passing and another failing means an edge case in a `rand` range.
5. An EMPTY score (rather than a wrong one) usually means the question never set
   up at all: a broken question-variables block, an unresolvable test input, or a
   CAS timeout. Look at the variables, not the PRT.
