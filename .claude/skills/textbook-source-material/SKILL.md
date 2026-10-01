---
name: textbook-source-material
description: How to turn source material (a chapter of a textbook, notes or worksheets) into a structured work order for authoring STACK questions.
---

# Turning source material into a work order

Input: a section of an existing document.
Output: a numbered work order listing every item the author should produce.

There are two paths. Pick the one that matches your project and say which in
`CLAUDE.md`, so agents do not have to guess.

---

## Reading a source document (a textbook, notes or worksheets)

### Step 1: extract the text

```bash
pdftotext -layout sources/chapter5.pdf - > /tmp/ch5.txt
```

`-layout` preserves two-column layout where it can.

Page numbers here and in plans are always **PDF** page numbers, which is what
`pdftotext` and `pdftoppm` take. A book with front matter numbers its printed
pages differently, and a range written in printed numbers renders the wrong
pages while still landing on plausible content.

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
markers; write your project's map into this skill once you know it. The table
below is one worked example, not a schema to match:

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

Every source item must appear in the work order: as a question, as a
description, or as an explicitly flagged item that cannot be adapted, with a
stated reason. Silently skipping is not an option: it is how content gaps ship
unnoticed.

Record each item's **published** reference (Exercise 8, Example 3.3, the result
numbered 3.4), not its position in the source file. Commented-out, omitted or
"not in the published book" items shift file order. Note any you find,
because every question's provenance note cites these references (style guide:
"Record where each question comes from").

The counts need not match. A source with exercises tends to map one-to-one; a
source that teaches through worked examples and puts its exercises at the end of
a chapter maps one-to-many, because a sub-topic otherwise yields no interactive
questions at all. Account for every item; do not expect equal totals.

---

## Authoring without a source document

Not supported in this release. Working from a syllabus alone, with no source
text, is possible in principle and may be addressed in a future version, but it
has not been validated here. The item-count check that catches silently dropped
content does not apply, and nothing has pre-vetted the mathematics, so the
safeguards this kit relies on are weaker. If that is the situation, say so rather
than improvising it.


## Work order format

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
