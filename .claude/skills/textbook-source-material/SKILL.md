---
name: textbook-source-material
description: How to turn source material (a PDF chapter, or a syllabus with no source text) into a structured work order for authoring STACK questions.
---

# Turning source material into a work order

Input: either a section of an existing document, or a topic from a syllabus.
Output: a numbered work order listing every item the author should produce.

There are two paths. Pick the one that matches your project and say which in
`CLAUDE.md`, so agents do not have to guess.

---

## Path A: source-led (you have a textbook, notes or worksheets)

### Step 1: extract the text

```bash
pdftotext -layout sources/chapter5.pdf - > /tmp/ch5.txt
```

`-layout` preserves two-column layout where it can.

If the output looks scrambled, do not guess at what it meant. Common failures
and what to do:

- Scattered digits where exponents should be: try `pdftotext -raw`, or read the
  page visually.
- Greek letters lost: read that region visually.
- Matrices and multi-row displays: always check visually.

For anything with surds or fractional indices, `pdftotext` reliably mangles the
glyphs. Render the pages as images instead and read those:

```bash
pdftoppm -r 220 -png -f 64 -l 77 sources/chapter5.pdf /tmp/ch5page
```

Use the images as the truth source for the planner and the author. Text
extraction is a convenience, not an authority.

### Step 2: identify the items

Go through and classify every item. Your source will have its own structural
markers; write your project's map into this skill once you know it. A typical
school textbook maps like this:

| Marker | What it is | Becomes |
|--------|------------|---------|
| "A Linear inequalities" | Section header | Quiz, plus the first `description` question |
| Key points panel | Key point | `description` with a `tb-keypoint` div |
| Worked example | Worked example | `description` (or skinny STACK if it has a figure) with a `tb-example` heading |
| A1, A2, B1, B2 | Development question | STACK interactive question |
| Discussion marginalia | Discussion prompt | STACK with an explicit "discuss with a partner" prompt, or a `description` |
| "Exercise" plus numbered questions | Exercise | STACK, compact validation, `tb-exercise` heading |
| Starred questions | Harder exercise | Same, flagged for difficulty |
| "Mixed questions" | End-of-chapter mixed | Separate quiz |
| "Test yourself" | Self-assessment | Separate quiz |

### Step 3: check nothing was dropped

Count the items in the source and count the items in your work order. They
should match. Every source item appears as a question, as a description, or as
an explicitly flagged item that cannot be adapted with a stated reason. Silently
skipping is not an option: it is how content gaps ship unnoticed.

---

## Path B: from scratch (a syllabus, no source text)

With no source document, the curriculum specification is the only ground truth,
and questions are invented rather than adapted. That changes two things: there is
no item count to check against, and nobody has already vetted the mathematics.

So the work order is built from the learning outcomes instead:

### Step 1: pin down the outcomes

Extract the specific learning outcomes for the topic from the specification,
verbatim. Not your paraphrase. Quote them in the work order.

### Step 2: decide the shape of the topic before the questions

For each outcome, decide:

- What a student who has met this outcome can do that one who has not, cannot.
- What the worked example looks like.
- What the common misconception is.

That third question is the one worth spending time on. It determines the
diagnostic PRT nodes, which is where most of the pedagogic value in a STACK
question lives.

### Step 3: build a progression

An invented topic has no built-in difficulty ordering, so you have to impose
one. A workable default: one or two description items setting up the idea, a
worked example, three to six routine practice questions rising in difficulty,
then one question of Pointon and Sangwin type 3, 7 or 8 (classify, construct an
example, criticise a fallacy) to test understanding rather than execution.

### Step 4: vet before authoring

Because nothing is adapted from a vetted source, add a human gate that
source-led work does not need. Before any XML is written, a subject expert
checks: is the mathematics correct, is the level right for the specification, is
the notation the one students will meet elsewhere?

Getting this wrong is cheap to fix at the plan stage and expensive to fix once
30 questions are live.

---

## Work order format (both paths)

```markdown
# Work order: <topic> <section>

Source: <sources/file.pdf pages X-Y>  |  Spec outcomes: <quoted>
Output file: content/drafts/<filename>.xml
Target quiz: <name, or "new">

## Items (in order)

1. **Description**: section header "A. <title>"
2. **Description**: intro paragraph plus key point
3. **STACK (algebraic)**: question A1, "<short description>"
   - Input: algebraic, tans = …
   - Randomisation: coefficients 2 to 9
   - Likely error to diagnose: <one named misconception>
4. **STACK (dropdown)**: discussion prompt, "<prompt>"
5. **Description**: worked example
6. …

## Notes
- Figures needed, and which JSXGraph template fits
- Cross-references to strip ("see Example 3 on p72")
- Past exam questions, marked so they stay deterministic
- Anything that could not be adapted, with the reason
```

## Step 4: hand it to the author

The author reads the work order, picks up the `stack-question-types` and
`jsxgraph` skills as needed, and produces XML. If the work order is vague, the
XML will be too: "use an algebraic input" is not a specification. Name the
variables, give the ranges, state the PRT structure and the test inputs.
