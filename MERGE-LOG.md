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

## Not yet verified

The two slash commands and the five skills were surveyed structurally but not
merged line by line. Both are rewrites rather than copies, so a heading-level
comparison is too noisy to conclude from and they need a genuine pass:

- `commands/develop-section.md`, `commands/develop-chapter.md`
- `skills/jsxgraph`, `skills/moodle-operations`, `skills/quiz-settings`,
  `skills/stack-question-types`, `skills/source-material` against SMP's
  `pdf-extraction`

The four agents and the style guide are done.
