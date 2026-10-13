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

    ### stack-<issue number>    (or stack-wa-<slug> until the issue is raised)

    - Issue: https://github.com/maths/moodle-qtype_stack/issues/<n>   (or "not raised yet")
    - Upstream: <$plugin->version on STACK's master when the entry was written>
    - Status: open | fixed upstream | removed
    - Found: <date>, <where>

    **Defect.** What goes wrong, with a minimal reproduction: the call, the
    result expected, the result returned. Name the file and line in STACK.

    **Workaround.** The code the questions use instead.

    **Restore.** The code to put back once STACK is fixed.

`Upstream` comes from GitHub, never from the local install:

    curl -s https://raw.githubusercontent.com/maths/moodle-qtype_stack/master/version.php | grep 'plugin->version'

A release with the fix has a larger version number. An entry is ready to
remove when its issue is closed and the installed STACK's version is larger
than `Upstream`.

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
- Fix: proposed upstream from branch `iss1903`, under review on GitHub (2026-10-13)

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
