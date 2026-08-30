# Source material

Put your source material here: the textbook chapters, lecture notes or
worksheets the questions will be adapted from.

The pipeline reads from this folder. A plan refers to a file and a page range,
for example `sources/chapter5.pdf` pages 64 to 77, so it helps to keep filenames
short and predictable rather than descriptive.

## Before you rely on a PDF

Check that its text layer is usable, because mathematical notation frequently is
not. Surds, fractional indices and subscripts are the usual casualties, and they
fail silently: the extraction produces plausible text that is quietly wrong.

```bash
pdftotext -layout sources/yourfile.pdf - | head -60
```

If the mathematics comes out mangled, render the pages as images instead and work
from those. `.claude/skills/textbook-source-material/` covers both routes and is
what the planner uses.

## A note on what is in here

This folder is where copyright material tends to accumulate. If you ever publish
or share the repository, check what is in it first. Consider adding `sources/*`
to `.gitignore` if the material is not yours to distribute, keeping only this
file.
