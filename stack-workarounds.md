# STACK workarounds

The register of every workaround the kit's questions use for a defect, or a
missing feature, in STACK (core or contrib). The rules, and the todo block
each question carries, are in `style-guide.md`, "Workarounds for STACK
defects". Run `tools/check-workarounds.sh` to see which entries are ready to
remove.

Not workarounds: Maxima behaviour that STACK cannot change (record it as a
style-guide rule), naming rules, and forbidwords.

## Format

Newest last. One `###` section per entry, headed by its id. The field lines
are read by `tools/check-workarounds.sh`, so keep them as written.

    ### stack-<issue number>    (or stack-wa-<slug> when there is no issue)

    - Issue: https://github.com/maths/moodle-qtype_stack/issues/<n>   (or "not raised yet", or "none, fixed in dev")
    - Upstream: <$plugin->version on STACK's master when the entry was written>
    - Status: open | fixed in dev | removed
    - Found: <date>, <where>
    - Fix: <the commit on dev that fixes it, once there is one>
    - Marker: <text that finds the questions, only for a widespread workaround>

    **Defect.** What goes wrong, with a minimal reproduction: the call, the
    result expected, the result returned. Name the file and line in STACK.

    **Workaround.** The code the questions use instead.

    **Restore.** The code to put back once STACK is fixed.

**STACK's branches.** `master` is the current release. `dev` holds the fixes
staged for the next release, which reach `master` when it is released.

`Upstream` is the version on `master`, taken from GitHub, never from the local
install:

    curl -s https://raw.githubusercontent.com/maths/moodle-qtype_stack/master/version.php | grep 'plugin->version'

Any release that contains a fix has a larger version number. Use `master`'s
number even when the fix is already in `dev`: `dev`'s own number is that of a
release not yet made.

**Check `dev` first.** Before drafting an entry, look at the defective code on
`dev`, for example
`https://raw.githubusercontent.com/maths/moodle-qtype_stack/dev/<path>`, and
at `dev`'s recent commits to that file:

    curl -s "https://api.github.com/repos/maths/moodle-qtype_stack/commits?sha=dev&path=<path>&per_page=5"

If `dev` already fixes it, no issue is raised: the entry keeps the id
`stack-wa-<slug>`, with `Issue: none, fixed in dev`, `Status: fixed in dev`
and the commit's URL under `Fix`. The workaround is still needed until a
release with the fix is installed.

**Status.** `open` until the fix is merged into `dev`. When the issue is
closed with a fix in `dev`, set `fixed in dev` and record the commit under
`Fix`. `removed` once no question uses the workaround.

An entry is ready to remove when the fix is in `dev` (its issue is closed, or
its status is `fixed in dev`) and the installed STACK's version is larger than
`Upstream`.

**Widespread workarounds.** Normally each question carries a todo block and
`/* <id> */` comments. Where a workaround is in dozens of questions and is
already found by one fixed text (a validator name, for example), the user may
choose one register entry instead: give it a `Marker:` line with that text,
and add no blocks or comments. `tools/check-workarounds.sh` then lists the
questions containing the marker. The entry's **Restore** says what to change
and which uses to leave alone.

**Raising the issue.** The planner or author drafts the entry, including the
reproduction; the user raises the issue on GitHub (the assistant never posts
there) and gives the number. Then rename the id from `stack-wa-<slug>` to
`stack-<n>` here, in the todo tags and in the code comments of every question
that uses it:

    grep -l 'stack-wa-<slug>' project/content/*/*.xml

## Entries

### stack-1901

- Issue: https://github.com/maths/moodle-qtype_stack/issues/1901
- Upstream: 2026080600
- Status: open
- Found: 2026-10-11, Chapter 5 plans of the Axler project
- Fix: not in dev yet (checked 2026-10-14)

**Defect.** In `stack/maxima/linearalgebra_core.mac`, `make_list_of_lists`
declares and assigns `_op1` but tests `op1`, so for a set it returns its
argument unchanged:
`make_list_of_lists({c(1,2,3),c(4,5,6)})` gives `{c(1,2,3),c(4,5,6)}`, where
`[[1,2,3],[4,5,6]]` is expected. It also gives no `[]` for `{}`. Under the same
issue, `lin_indp` and `subspace_equivp` (`contrib/vectorspaces.mac`) do not
accept an empty collection (`[]`, `{}`, `ntuple()`, `span()`) as the basis of
\(\{0\}\).

**Workaround.** For a set-of-vectors input `ans1`:

    sl: map(lambda([v], list_matrix_entries(vec_convert(v))), listify(ans1)); /* stack-1901 */

and handle the empty set in its own PRT node rather than passing `[]` to
`lin_indp` or `subspace_equivp`.

**Restore.**

    sl: make_list_of_lists(ans1);

and drop any node that exists only to keep `[]` away from the library.

### stack-1903

- Issue: https://github.com/maths/moodle-qtype_stack/issues/1903
- Upstream: 2026080600
- Status: open
- Found: 2026-10-11, Chapter 5 plans of the Axler project (5D convention D5)
- Fix: not in dev yet (checked 2026-10-14); proposed from branch `iss1903`, under review on GitHub (2026-10-13)

**Defect.** In `stack/maxima/contrib/eigenlib.mac` (line 46), the
three-argument form of `eigenvectorp` tests
`ev(is(M.v=first(L)*v),expand,simp)`. `expand` given to `ev` is applied to the
result of `is`, not to the two sides first, so `is` compares unexpanded forms.
With `M: matrix([0,1],[1,1])`, `L: (1+sqrt(5))/2` and
`v: transpose(matrix([1, L]))`, `eigenvectorp(v, M, L)` gives `false`, where
`true` is expected; the two-argument form gives `true`. Integer, rational,
`sqrt(2)`, complex and symbolic cases happened to pass (checked 2026-10-13 under
STACK's Maxima, version 2026080600). A fix that passes all ten checked cases:
`is(ev(zeromatrixp(ratsimp(radcan(M.v - first(L)*v))), simp))`.

**Workaround.**

    is(ratsimp(radcan(M . v - L*v)) = zeromatrix(2,1)) /* stack-1903 */

and reject the zero vector separately, which `eigenvectorp` does itself.

**Restore.**

    eigenvectorp(v, M, L)

### stack-1912

- Issue: https://github.com/maths/moodle-qtype_stack/issues/1912
- Upstream: 2026080600
- Status: open
- Found: 2026-10-05, Section 3F of the Axler project; diagnosed 2026-10-14
- Fix: not in dev yet (checked 2026-10-14)

**Defect.** In `stack/maxima/stackmaxima.mac` (line 882), `stack_matrix_disp`,
the `texput` for `matrix`, raises "stack_matrix_disp: argument must be a
matrix." when given an unevaluated `matrix(w)` whose arguments are not lists,
as in the body of a lambda: `f: lambda([w], matrix(w)); tex1(f);` fails, though
`f([1,2])` gives `matrix([1,2])`. In a question, a variable whose value holds
such a lambda makes every CAS call fail with "CAS failed to return any data
due to timeout", even if it is never called. Inline lambdas (inside `sublist`)
and function definitions `f(w) := matrix(w)` are not affected.

**Workaround.** In a lambda bound to a question variable:

    apply(matrix, [w])  /* stack-1912 */

for `matrix(w)`, and `transpose(apply(matrix, [w]))` for a column.

**Restore.**

    matrix(w)

Restoring is optional, since the two forms are equivalent.

**Questions to mark** (Axler project, not yet marked; to be done in the
Chapter 3 clean-up after Chapter 5). Each binds a lambda using
`apply(matrix, [...])` to a question variable:

- `Ch3_F.xml`: 3F_P02b_DualBasisCompute (`ok4`), 3F_P03b_DualMapCompute
  (`ok6`), 3F_P04b_DualOfProduct (`ok8`)
- `Ch3_F_further.xml`: 3F_P15b_RangeDualCompute (`okc`, `ok31b`),
  3F_P23a_Ex32_DoubleDual (`vals46`)

Twelve other Chapter 3 questions use `apply(matrix, [q])` inside an inline
`sublist` lambda, which is never stored: not a workaround, so leave them
unmarked.

### stack-1911

- Issue: https://github.com/maths/moodle-qtype_stack/issues/1911
- Upstream: 2026080600
- Status: open
- Found: 2026-10-05, Section 3E of the Axler project; reviewed 2026-10-14
- Fix: not in dev yet (checked 2026-10-14); proposed from branch `iss1911` (adds the `realnum` option)
- Marker: `validator:validate_numeric`

**Defect.** A missing feature: the numerical input accepts complex numbers
(`i`, `2*i`, `sqrt(-4)` are valid), and none of its options (`floatnum`,
`intnum`, `rationalnum`) asks for "any real number". In a question set over
\(\mathbf{R}\) and graded by a property, a complex entry can pass the check.

**Workaround.** A bespoke validator on each such input, defined in the
question variables, which also rejects letters with `simp:false`:

    validate_numeric(ex) := block([sx], sx: ev(ex, simp), if listofvars(ex) = [] and freeof(%i, sx) then "" else "Enter a real number, not an expression containing a letter, a variable or i.");

with the extra option `validator:validate_numeric`. One entry, no todo blocks
(user decision, 2026-10-14): in the Axler project it is on 194 inputs in about
60 Chapter 3 questions.

**Restore.** Once a release with the option is installed: on `numerical`
inputs, replace `validator:validate_numeric` with the new option (`realnum` in
the proposed fix; use whatever name is released), and delete the
`validate_numeric` definition where no other input uses it. Leave the
validator where the box must also reject `0*t`, which the numerical input
accepts by design (3B_P09c in the Axler project). Algebraic and matrix inputs
keep the validator. Run `test-question.sh` on each file changed.

### stack-1931

- Issue: https://github.com/maths/moodle-qtype_stack/issues/1931
- Upstream: 2026101500
- Status: open
- Found: 2026-10-14 (complex, 5B of the Axler project) and 2026-10-19 (real surds, 5D); raised 2026-10-19
- Fix: not in dev yet (checked 2026-10-19); proposed as an uncommitted change to `ATSet` in the user's clone

**Defect.** In `stack/maxima/stackmaxima.mac`, `ATSet` (reached from
`ATAlgEquiv` when both arguments are sets) simplifies each element with
`ATSets_prepare` and then compares the sets structurally with `subsetp`. Equal
elements written differently are not identified:
`ATAlgEquiv({1/2+sqrt(5)/2, 1/2-sqrt(5)/2}, {(1+sqrt(5))/2, (1-sqrt(5))/2})`
and `ATAlgEquiv({-4*(1+%i), -4+4*%i, 3}, {-4-4*%i, -4+4*%i, 3})` give
`false` (`ATSet_wrongentries`), where `true` is expected. `rectform` on each
element fixes the complex case only. The Sets answer test (`ATSets`) uses the
same comparison.

**Workaround.** In the feedback variables, compare element by element in both
directions with a length check, and test the boolean in the node:

    smk: lambda([AA, BB], is(length(AA) = length(BB)) and every(lambda([x], some(lambda([y], algebraic_equivalence(x, y)), BB)), AA) and every(lambda([y], some(lambda([x], algebraic_equivalence(x, y)), AA)), BB)); /* stack-1931 */
    esk: smk(listify(ansk), listify(tans)); /* stack-1931 */

with node sans `esk`, tans `true`, and one such boolean per diagnostic set.

**Restore.** Delete the marked lines, and give each node sans `ansk` and tans
the set it compared with, as each question's todo block lists.
