#!/usr/bin/env python3
"""Static audit of a draft before hand-off: catches an unfinished file that
still grades its own tests.

    tools/audit-draft.py project/content/drafts/<file>.xml

api-check.sh and test-question.sh prove the XML can mark its own test cases.
They cannot tell that the question text still says "TODO", that a student who
answers wrongly sees nothing, or that the page codes are half filled in. This
script looks for those, without Maxima or Moodle, in a second or so.

FAIL lines stop the hand-off. WARN lines are for a human look. Exit 0 = no
FAIL, 1 = at least one.

Checks:
  - placeholder text (TODO, TBC, FIXME, "[insert ...", lorem ipsum ...) in
    anything a student can see; the question description is not student-facing
    and may hold [[todo]] blocks
  - a marked STACK question (defaultgrade above 0) with fewer than two test cases
  - page codes (_P<NN>_ in the name, see style-guide.md "Page codes in question
    names"): all questions or none, and each page's questions consecutive
  - WARN: a terminal false branch (falsenextnode -1) with empty feedback. The
    style guide forbids it ("False branch feedback is mandatory"), but a few
    patterns in the showcase use it, so it needs a human look, not a block
  - WARN: a marked STACK question with no worked solution, or no provenance note
"""
import re
import sys
import xml.etree.ElementTree as ET

PLACEHOLDER = re.compile(
    r"\b(TODO|TBC|TBD|FIXME|XXX)\b|lorem ipsum|placeholder|\[insert|"
    r"to be (written|added|completed)|coming soon",
    re.IGNORECASE)
# Moodle XML tags whose text a student can see.
VISIBLE = ("questiontext", "generalfeedback", "specificfeedback",
           "truefeedback", "falsefeedback", "prtcorrect",
           "prtpartiallycorrect", "prtincorrect", "hint")
PAGE_CODE = re.compile(r"_P(\d+)[a-z]?_")


def text_of(el):
    t = el.find("text") if el is not None else None
    return (t.text or "") if t is not None else ""


def bare(html):
    return re.sub(r"<[^>]+>|&nbsp;|\s", "", html)


def main(path):
    try:
        root = ET.parse(path).getroot()
    except ET.ParseError as e:
        print(f"FAIL {path}: not well-formed XML: {e}")
        return 1
    fails = warns = 0
    codes = []

    def report(level, name, msg):
        nonlocal fails, warns
        print(f"{level} {name}: {msg}")
        if level == "FAIL":
            fails += 1
        else:
            warns += 1

    for q in root.iter("question"):
        qtype = q.get("type")
        if qtype == "category":
            continue
        name = text_of(q.find("name")) or "(unnamed)"
        m = PAGE_CODE.search(name)
        codes.append((name, m.group(1) if m else None))

        for tag in VISIBLE:
            for el in q.iter(tag):
                hit = PLACEHOLDER.search(text_of(el) or (el.text or ""))
                if hit:
                    report("FAIL", name, f'placeholder "{hit.group(0)}" in <{tag}>')

        if qtype != "stack" or float(q.findtext("defaultgrade") or 0) == 0:
            continue
        for prt in q.iter("prt"):
            prtname = (prt.findtext("name") or "?").strip()
            for node in prt.iter("node"):
                if (node.findtext("falsenextnode") or "").strip() == "-1" \
                        and not bare(text_of(node.find("falsefeedback"))):
                    report("WARN", name, f"{prtname} node {node.findtext('name')}: "
                           "terminal false branch has no feedback")
        if len(q.findall("qtest")) < 2:
            report("FAIL", name, f"{len(q.findall('qtest'))} test case(s); at least two needed")
        if not bare(text_of(q.find("generalfeedback"))):
            report("WARN", name, "no worked solution in <generalfeedback>")
        if not bare(text_of(q.find("questiondescription"))):
            report("WARN", name, "no provenance note in <questiondescription>")

    coded = [c for _, c in codes if c is not None]
    if coded and len(coded) != len(codes):
        missing = [n for n, c in codes if c is None]
        report("FAIL", path, f"{len(missing)} of {len(codes)} questions have no page code, "
               f"so the live push ignores all of them: {', '.join(missing[:5])}")
    elif coded:
        seen, prev = set(), None
        for n, c in codes:
            if c != prev and c in seen:
                report("FAIL", n, f"page P{c} resumes after another page; a page's questions must be consecutive")
            seen.add(c)
            prev = c

    print(f"--- {fails} FAIL, {warns} WARN, {len(codes)} questions"
          + (f", {len(set(coded))} pages by code" if coded else "") + " ---")
    return 1 if fails else 0


if __name__ == "__main__":
    if len(sys.argv) != 2:
        print(__doc__.strip().splitlines()[2].strip())
        sys.exit(1)
    sys.exit(main(sys.argv[1]))
