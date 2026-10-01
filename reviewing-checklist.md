# Reviewing checklist

**Audience: the assistant, when reviewing.**

The `textbook-reviewer` agent works through this. It is deliberately a checklist rather
than prose: the point is that nothing gets skipped because it felt fine.

Blocking items are marked. Everything else is a judgement call the reviewer
records in the feedback document.

---

## Question settings

- [ ] `<stackversion>` equals the STACK version stamp in `CLAUDE_loc.md`, not
      a value copied from a template or an older question
- [ ] **Provenance note.** Every STACK question's `<questiondescription>`
      uses one of the four forms in the style guide ("Record where each
      question comes from"), with the project's citation verbatim. It matches
      the plan's source inventory, and any exercise number is the book's
      published number, not a file position.
- [ ] `multiplicationsign` is `none`, not `dot` or `cross`
- [ ] `<prtcorrect>`, `<prtpartiallycorrect>`, `<prtincorrect>` use the
      `[[commonstring]]` defaults, with no hardcoded text or icons (style guide,
      "Standard PRT feedback uses commonstring")
- [ ] **Feedback tag placement.** A single-PRT question has `[[feedback:prt1]]`
      in `<specificfeedback>` and not in the question text. A multi-PRT
      question has each tag inline after its part. Every tag appears exactly
      once, and never inside a hidden element (style guide, "Where the PRT
      feedback tag goes")
- [ ] `syntaxhint` with `syntaxattribute=1` on any input expecting `sqrt`, `pi`,
      `inf` or other non-obvious notation, and the hint is a generic placeholder
      rather than the shape of the answer
- [ ] No degenerate variants: no `rand` call can produce a zero coefficient, a
      zero denominator, a perfect square where a surd is expected, a negative
      discriminant where real roots are expected, or any other value that
      collapses the mathematical structure

## Deployed variants

- [ ] At least 10 variants deployed in most cases
- [ ] All variants of similar difficulty. Check specifically:
  - [ ] Every answer is nice (an integer or a simple fraction)
  - [ ] No variant is accidentally trivial (answer is 0 or 1)
  - [ ] No variant uses numbers big enough to make arithmetic the difficulty
  - [ ] The same solution method works for every variant
  - [ ] Signs render conventionally: no `+ -5`, no `0x`

---

## Test cases

- [ ] Correct answer
- [ ] Every incorrect branch
- [ ] A mixed case for multi-input questions
- [ ] Predictable errors (for example, repeated inputs for quadratic solutions)
- [ ] One case per distinct route where the PRT has diagnostic nodes, including
      routes that end at the same leaf
- [ ] **Every `<expectedanswernote>` is the full route**: the node notes in
      order (`prt1-1-F | prt1-2-T`), with no `AT...` notes and no `[` start
      anchor. Check each route against the PRT's design, not against the
      validator's output.
- [ ] **No hardcoded literals in randomised questions.** Run
      `tools/audit-test-inputs.sh <file>`. Any flagged `<testinput>` must
      reference `ta` / `ta1` / `ta+1`, not a value that only matches one seed.
      Triage false positives by inspecting the referenced `<tans>`: if it is a
      fixed constant, the literal is fine.

You cannot test every combination. Test at least a couple of examples per path.

## Run the tests

- [ ] **BLOCKING.** Tests have actually been executed and passed, not just
      written. Re-run validation yourself rather than trusting the author.

---

## Worked solutions

- [ ] Full sentences, full stops
- [ ] Pitched at the right level for the audience
- [ ] Formatting consistent with the style guide
- [ ] Faithful to the source in notation and in method

---

## PRTs

- [ ] **Answer-test feedback is quiet where it would mislead.** A node testing a
      value built from several inputs, or anything other than the student's
      raw answer, has `<quiet>1</quiet>` (style guide, "Careful use of
      answertest-generated feedback")
- [ ] **Every false branch has non-empty feedback**, and it hints at the method
      rather than giving the answer. Routing nodes are the exception: their
      false feedback must stay empty.
- [ ] **Diagnostic coverage.** Every scored PRT in a randomised question has at
      least one diagnostic node targeting a named common error. A scored PRT
      with only generic false feedback is a defect unless it carries a code
      comment explaining that no plausible misconception exists.
- [ ] **Diagnostic soundness.** The diagnostic value cannot coincide with the
      correct answer on any seed, and the diagnostic's true branch is terminal
      (`nextnode` = -1).
- [ ] **Dropdown validity.** No dropdown or radio used for a routine procedure
      with several valid written forms. Dropdowns are for classification and
      interpretation only. Flag as a defect if violated.
- [ ] **Distractors are actually false**, including under net-effect reasoning
- [ ] **Randomisation audit.** If `<questionvariables>` defines symbolic
      variables that are then given fixed values, with no `rand()` call and no
      curated list, either randomise them or require a code comment explaining
      why not (for example, "past paper, original values preserved"). An unfired
      randomisation skeleton with no comment is a defect.
- [ ] **General feedback contains a full worked solution** for every question
      except trivial arithmetic, with prose that names the concept
- [ ] Points awarded on each branch are numerically correct

---

## Formatting and style

- [ ] Every applicable rule in `style-guide.md` followed
- [ ] Explanatory comments in the code
- [ ] Alt text on every image and figure
- [ ] Conventions checked against `content/reference/showcase.xml`, NOT against
      a recently authored file. Recent files drift. Flag any drift you find.

---

## Plan faithfulness

- [ ] Every planned item is present
- [ ] Question types match the plan
- [ ] Page grouping is as specified
- [ ] The author's reported deviations are the only differences. Undocumented
      deviations may be fine, but they should have been deliberate.

---

## Pedagogy

This is a core duty, not an afterthought.

- [ ] Does the question pose the right question?
- [ ] Is the difficulty progression sensible?
- [ ] Does a student learn something, or is this a rote drill?

---

## Rendered output, after publishing

- [ ] Run `tools/render-check.sh <file>` and clear every flag
- [ ] Load every quiz page in a browser
- [ ] LaTeX: no raw `\(...\)`, no broken MathJax
- [ ] Inputs: every box appears, correct size, in the right position
- [ ] CSS: no unstyled boxes, missing backgrounds or broken layout
- [ ] JSXGraph: figures load, axes and labels correct, interactive elements
      respond
- [ ] Dropdowns: sensible default, all options present, correct option accepted
- [ ] Page grouping: descriptions sit with their questions, no lonely items

---

## Proof and "show that" questions

- [ ] **Faded examples:** are the right blanks faded? Too many is trivial, too
      few is impossible. Flag for a human to look at.
- [ ] **Faded examples follow a full static worked example** in the same quiz
- [ ] **Parsons problems:** zero mark (`defaultgrade=0`), no distractors,
      feedback shows one valid ordering and notes that others may be valid
- [ ] **Reading comprehension:** do the questions test understanding, or just
      surface matching?
- [ ] **Sign handling:** negative-coefficient variants render correctly, no
      `x + -3`, no `(x + -2)^2`
- [ ] **Editorial decisions:** any "explain why" converted to multiple choice
      comes with its distractors and rationale listed for human review
