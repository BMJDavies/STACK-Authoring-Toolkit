# Source material

Put your source material here: the textbook chapters, lecture notes or
worksheets the questions will be adapted from.

The pipeline reads from this folder. A plan refers to a file and a page range,
for example `project/sources/chapter5.pdf` pages 64 to 77, so it helps to keep filenames
short and predictable rather than descriptive.

## Before you rely on a PDF

Check that its text layer is usable, because mathematical notation frequently is
not. Surds, fractional indices and subscripts are the usual casualties, and they
fail silently: the extraction produces plausible text that is quietly wrong.

```bash
pdftotext -layout project/sources/yourfile.pdf - | head -60
```

If the mathematics comes out mangled, render the pages as images instead and work
from those. `.claude/skills/textbook-source-material/` covers both routes and is
what the planner uses.

## A note on what is in here

This folder is where copyright material tends to accumulate. It is part of your
project repository, which is separate from the kit, so the kit never publishes
it. If you ever publish or share the project repository, check what is in here
first. The project's `.gitignore` ignores this folder (apart from this file) by
default; remove those lines if the material is yours to distribute.
