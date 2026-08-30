# Merge log: kit against the live SMP pipeline

The kit was extracted from SMP on 6 August 2026. SMP's agents had not changed
since 23 July, so this was a reconciliation of a deliberate generalisation, not
of drift.

Rule applied: every rule in the live version must be provably present here, or
consciously dropped with a reason. Completeness, not wording preference.

## Carried back into the kit

| File | What | Why |
|---|---|---|
| `agents/author.md` | Explicit progress-log milestone list and example line | The kit compressed five named milestones into one sentence; the list is generic and is what makes concurrent runs legible |
| `agents/author.md` | Prototype mapping, pattern to filename | The kit said "read the relevant prototype" without saying which. Extended to all eight prototypes the kit ships, up from SMP's five |
| `agents/reviewer.md` | Explicit progress-log milestone list and example line | As above |
| `style-guide.md` | Never repeat an `[[input:ansN]]` placeholder | Throws a coding exception in STACK's renderer. Absent from the kit entirely |
| `style-guide.md` | Reject-qtests for completed-square form | The naive `expand` probe passes on vertex-at-zero seeds. Absent from the kit entirely |
| `style-guide.md` | Conclusion plus reason pairs author as radio | Option-length rule plus LaTeX not rendering in dropdowns. Absent from the kit entirely |

## Consciously dropped

| What | Why |
|---|---|
| `SMP/` path prefixes throughout | Project-specific. The kit is relative to its own root |
| `C1_Showcase.xml` as the named reference | Becomes `content/reference/showcase.xml` |
| Reviewer and enricher "update the tracker" steps | The tracker is an SMP artefact; the kit has no equivalent |
| Enricher "triage workflow" section | Describes SMP's PROJECT-PLAN spot-check queue by name |
| `--stack=45` validation flag | SMP's two-stack sandbox setup; the kit ships one stack |
| `manager.md` | The slash commands carry the role, and a subagent cannot spawn the agents a manager exists to dispatch |
| `core1-retrofit.md` | One-off, per its own description |
| GCSE Foundation conventions, Showcase reference, Content character | Project-specific; belong in SMP's appendix |

## Checked and found already present

Two risks were flagged before the merge and neither survived contact.

The session-54 form-check recipes were thought lost because they are absent from
the kit's `stack-question-types` skill. They were moved into the style guide's
"Form checks: when correct value is not enough" section, which is fuller than the
skill version was: it adds the recipe table, the `questionsimplify` trap, the
simplest-surd note and the structural-inequality rule.

A wider list of style-guide rules appeared to be missing on a first pass. That
was a malformed grep, using `\|` for alternation under `grep -E`, where it is a
literal. On a correct check the kit already carried the randomisation floor
(phrased "at least a third"), the div-not-p input rule, forbidwords, syntax
hints, the `simp:false` list-indexing trap, the forbidden-identifier rule,
`EqualComAssRules [oneMul]`, `dispdp` trailing zeros, and terminal answer notes.

## Commands and skills: verified, nothing to carry

Both slash commands and all five skills were checked functionally against the
live SMP versions. All are supersets or equivalents. Nothing needed carrying back.

`develop-section` and `develop-chapter` are longer here (146 and 178 lines against
125 and 151) and add a "validate it yourself" step, encoding the lesson that the
dispatcher owns validation rather than the author, plus guardrails and partial-run
sections that SMP lacks.

`quiz-settings` is better here: it carries the slot-to-page map restore lesson,
that a push regroups pages and undoes a hand-built layout, which the SMP version
does not have.

`jsxgraph`, `moodle-operations` and `stack-question-types` are equivalent in
coverage. `pdf-extraction` became `source-material`, a better name given a PDF is
only one kind of source, and it additionally handles the case of a syllabus with
no source text at all.

Two apparent gaps in this pass were my own measurement errors, not content
differences: searching for `feedback/` with a slash when the text reads "feedback
documents", and for "section headings" when the kit phrases the same rule around
page grouping.

## Outstanding

Nothing in the merge. SMP has not yet been cut over to consume this repo, which
is Phase 4 of `CONSOLIDATION-PLAN.md` in the SMP repo.
