# Lessons learned

The intake log for new rules. The planner and the author read this before
starting a section, so anything here changes behaviour immediately.

## How this file works

- The reviewer writes per-section feedback into `feedback/<section-id>.md`.
- When the same issue turns up in two or more sections, it stops being a
  one-off and gets promoted here.
- At a natural boundary (the end of a chapter or a book), migrate the entries
  here into `style-guide.md` and clear this file.

Why the two-stage process: the style guide should not change mid-production run,
because shifting the conventions while a chapter is being written makes author
and reviewer behaviour drift between sections. This file is where a new rule
waits.

Keep entries short and actionable. State the rule, then why, then where it
applies.

---

## Open entries

### Answer-test notes are in the trail, even on quiet nodes

A route expectation lists node notes only, with no `[` start anchor. The actual
trail interleaves the answer test's own notes, e.g.
`ATAlgEquiv_String | prt1-1-F | ATAlgEquiv_String | prt1-2-F` on string or
MCQ comparisons, and `ATSet_wrongentries. | prt1-1-F` on sets. They usually come
first, and `<quiet>1</quiet>` does not remove them: it hides feedback, not
notes.

Why: found on 2026-10-01 while moving to STACK 4.13 route expectations. A
start-anchored `[prt1-1-F | prt1-2-F]` fails against such a trail, even though
the route is right.
Where: every `<expectedanswernote>`. It is already folded into the style-guide
rule, so this entry records the evidence behind it.

<!--
Format:

### Short name for the rule

The rule, in one or two sentences.

Why: what went wrong that made this a rule.
Where: which question types or situations it applies to.
-->
