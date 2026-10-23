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
| Classify objects into categories (type 3) | `stack` + one dropdown per object | Copy `reference/prototypes/classify_objects_prototype.xml`. Parsons grades ordered proofs only, not bucket-sorting, so classification uses dropdowns. |
| "Give an example with property P" (type 7) | `stack` + algebraic, property-checking PRT | Copy `construct_instance_prototype.xml`. The PRT checks a computed property of the answer, not equivalence to one model answer. |
| Indefinite integral, antidifferentiation | `stack` + algebraic, `Int` PRT | Copy `integration_with_antidiff_prototype.xml`. Do NOT use `Antidiff` / `AddConst`: see style guide. |
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

Required elements:

- `<questionvariables>` with Maxima code, including the teacher's answer `ta1` corresponding to input `ans1`.
- `<specificfeedback>` containing the `[[feedback:prt1]]` placeholder
- `<questionnote>` showing the randomised variant, including ALL random values
- `<stackversion>` set to the project's STACK version stamp in
  `project/CLAUDE.md` (style guide: "Stamp the target STACK version")
- `<questiondescription>` with the provenance note (style guide: "Record where
  each question comes from")
- At least one `<input>` and one `<prt>`
- At least **two** `<qtest>` cases (correct scores 1, wrong scores 0)
- `<generalfeedback>` with the worked solution

Settings, input configuration, display and randomisation rules are all in
`style-guide.md`.

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
   that exercises the diagnostic path. Every expectation is the full route of
   node notes, e.g. `prt1-1-F | prt1-2-T`, with no `AT...` notes and no
   brackets: see the style guide, "Question tests".

Match the structure in `reference/showcase.xml`.

---

## Proof and "show that" patterns

All three involve editorial decisions about scaffolding level. Flag them for human review.

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

Zero-mark formative exercise. `<defaultgrade>0</defaultgrade>`. No distractors.

Question variables:

```maxima
stack_include_contrib("prooflib.mac");

proof_steps: [
    ["key1", "Step 1 text with \\(\\LaTeX\\)."],
    ["key2", "Step 2 text."]
];
proof_steps: random_permutation(proof_steps);

ta: proof("key1", "key2");
ta1:[ta, proof_steps];
```

Question text:

```
[[parsons input="ans1"]]
{"steps": {# parsons_encode(proof_steps) #},
 "headers": ["Your proof:"],
 "available_header": ["Drag from here:"]}
[[/parsons]]
[[input:ans1]] [[validation:ans1]]
```

Input: type `parsons`, tans `ta1`, `mustverify` 0,
`showvalidation` 0.

PRT feedback variables:

```maxima
sa: parsons_decode(ans1);
[pd, saa]: proof_assessment(sa, proof_alternatives(ta));
```

PRT node: `AlgEquiv(pd, 0)`, true scores 1, false scores 0 with feedback
`{@proof_assessment_display(saa, proof_steps)@}`.

Test cases: correct is `apply(parsons_answer, [ta, proof_steps])` expecting 1,
wrong is `apply(parsons_answer, [map(first, proof_steps), proof_steps])`
expecting 0.

Proof tree constructors for steps that can swap order:

- `proof("a","b","c")` fixed order
- `proof_c("a","b","c")` any permutation acceptable
- `proof_iff(proof(...), proof(...))` if and only if, halves commute
- `proof_cases("justify", ...)` first element fixed, rest commute
- `proof_ind(base, case1, case2, conclusion)` induction, 1st and 4th fixed

Sign handling: apply `tex1()` to whole Maxima expressions
(`tex1(x^2 + b*x + c)`) rather than concatenating strings
(`sconcat("x^2 + ", tex1(b), "x")`), or you get `x^2 + -2x`.

Prototype: `reference/prototypes/parsons_prototype.xml`

### Pattern 8: reading comprehension (proof analysis)

Present a complete numbered proof, then ask about its structure: which step does
X, what justifies step Y, what is the key property used, what would break if.

Use it after students have learned a technique, to test whether they understand
why it works rather than how to execute it.

Prototype: `reference/prototypes/reading_comp_prototype.xml`

---

## Debugging a failing PRT

1. Run `tools/test-question.sh` and read the FAIL lines.
2. An unexpected `note` means the branching is wrong. Inspect the graph.
3. A score off by rounding means a PRT node `value` is wrong.
4. One seed passing and another failing means an edge case in a `rand` range.
5. An EMPTY score (rather than a wrong one) usually means the question never set
   up at all: a broken question-variables block, an unresolvable test input, or a
   CAS timeout. Look at the variables, not the PRT.
