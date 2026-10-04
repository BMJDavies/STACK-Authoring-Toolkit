#!/usr/bin/env python3
r"""Render every deployed seed of every STACK question through the STACK API and
look for display defects that grade correctly but read wrongly.

Usage:  render_sweep.py <file.xml> [--url http://localhost:3080] [--verbose]

Checks (style-guide.md, "Sign handling in displayed expressions"):

  Source, all CASText fields, including PRT feedback (which /render cannot show):
    FLAG  linebreak   `\\` immediately before `{@...@}`; CASText drops a backslash
    WARN  coeff-src   `{@var@}` written straight before a symbol, e.g. `{@k@}x`;
                      only a problem on seeds where var is 0 or +-1, which the
                      rendered check below decides for the question text and
                      worked solution

  Rendered question text and worked solution, on every deployed seed:
    FLAG  brace       `\{` followed by a number: the visible result of `linebreak`
    FLAG  coeff       0, 1 or -1 printed as a coefficient of a symbol
    FLAG  plusminus   a negative value after +, -, \cdot or \times ("+ -3", "2\cdot{-3}")
    FLAG  notes       two seeds give the same question note (a repeated variant)

Exit 0 = no FLAG (warnings allowed); exit 1 = at least one FLAG; exit 2 = the
API could not render something.
"""
import argparse, json, re, sys, urllib.request, urllib.error
import xml.etree.ElementTree as ET

ap = argparse.ArgumentParser()
ap.add_argument("xmlfile")
ap.add_argument("--url", default="http://localhost:3080")
ap.add_argument("--timeout", type=int, default=180)
ap.add_argument("--verbose", action="store_true")
args = ap.parse_args()

# Same fallback as api_check.py and validate-questions.php.
DEFAULT_SEEDS = [1, 42, 137, 256, 999]

# A symbol a coefficient could sit in front of: a single letter (not part of a
# word) or a common symbol macro.
SYMBOL = r"(?:[A-Za-z](?![A-Za-z])|\\(?:mathbf|mathcal|mathrm|varphi|phi|psi|alpha|beta|gamma|lambda|mu|sigma|theta|omega)\b)"
GAP = r"(?:\s|\\[,;:! ]|\\cdot)*"

SRC_LINEBREAK = re.compile(r"\\\\\{@")
SRC_COEFF = re.compile(r"\{@\s*([A-Za-z_][A-Za-z0-9_]*)\s*@\}" + GAP + SYMBOL)

# The defect leaves "\\{" + number + "}" ("{-4}\\{3}"); set notation "\\{0\\}" ends in "\\}".
R_BRACE = re.compile(r"\\\{\s*-?[\d./]+\s*\}")
# STACK wraps every interpolated value in braces ("{1}\\,v_1"); requiring them
# skips literal mathematics such as "0S = 0" written by the author.
R_COEFF = re.compile(r"\{\s*(\(-1\)|\\left\(-1\\right\)|-1|1|0)\s*\}" + GAP + SYMBOL)
# A negative value straight after +, -, \cdot or \times: "+ -3", "2\cdot{-3}",
# "-3-{-3}". STACK braces interpolated values, so allow "{" (but not "\{").
R_PLUSMINUS = re.compile(r"(?:\+|-|\\cdot(?![a-z])|\\times)\s*(?:(?<!\\)\{\s*)?-")
MATH = re.compile(r"\\\((.*?)\\\)|\\\[(.*?)\\\]", re.S)


def casText_fields(q):
    """(label, text) for every field STACK treats as CASText."""
    out = []
    for tag in ("questiontext", "generalfeedback", "specificfeedback",
                "prtcorrect", "prtpartiallycorrect", "prtincorrect"):
        t = q.find(f"{tag}/text")
        if t is not None and t.text:
            out.append((tag, t.text))
    for prt in q.findall("prt"):
        pname = prt.findtext("name", "")
        for node in prt.findall("node"):
            nname = node.findtext("name", "")
            for br in ("truefeedback", "falsefeedback"):
                t = node.find(f"{br}/text")
                if t is not None and t.text:
                    out.append((f"{pname} node {nname} {br}", t.text))
    return out


def coeff_hits(math):
    """Coefficients 0, 1, -1 in front of a symbol, ignoring digits that are part
    of a longer number, a subscript/superscript, or a fraction."""
    hits = []
    for m in R_COEFF.finditer(math):
        before = math[max(0, m.start() - 6):m.start()]
        if re.search(r"[\d.]$", before):          # part of 21, 0.5, ...
            continue
        if re.search(r"[_^]\{?$", before):        # x_1 v, e^{1} x, ...
            continue
        if re.search(r"\\frac\{?$|\}\{$", before):  # \frac{1}{x}
            continue
        hits.append(math[max(0, m.start() - 12):m.end() + 8])
    return hits


def render(qxml, seed):
    payload = json.dumps({"questionDefinition": qxml, "seed": seed,
                          "renderInputs": "", "readOnly": False}).encode()
    req = urllib.request.Request(args.url + "/render", data=payload,
                                 headers={"Content-Type": "application/json"})
    with urllib.request.urlopen(req, timeout=args.timeout) as r:
        return json.loads(r.read().decode())


root = ET.parse(args.xmlfile).getroot()
questions = [q for q in root.findall("question") if q.get("type") == "stack"]
if not questions:
    print("No STACK questions found in", args.xmlfile)
    sys.exit(1)

nflag = nwarn = nerr = 0
for q in questions:
    name = q.findtext("name/text", "?")
    flags, warns = [], []

    for label, text in casText_fields(q):
        for m in SRC_LINEBREAK.finditer(text):
            flags.append(f"linebreak  {label}: ...{text[max(0, m.start()-25):m.end()+12]!r}")
        for m in SRC_COEFF.finditer(text):
            warns.append(f"coeff-src  {label}: {{@{m.group(1)}@}} before a symbol: "
                         f"...{text[max(0, m.start()-10):m.end()+5]!r}")

    xml_seeds = [int(s.text) for s in q.findall("deployedseed")]
    seeds = xml_seeds or DEFAULT_SEEDS
    if not xml_seeds:
        # The API will not render a randomised question with no deployed
        # variants, so inject the defaults (as api_check.py does).
        warns.append("no-seeds   no <deployedseed> in the XML (style-guide.md, \"Deployed seeds\"); "
                     "rendered with the defaults " + ", ".join(map(str, DEFAULT_SEEDS)))
        for sd in DEFAULT_SEEDS:
            ET.SubElement(q, "deployedseed").text = str(sd)
    qxml = "<quiz>" + ET.tostring(q, encoding="unicode") + "</quiz>"
    notes = {}
    for seed in seeds:
        try:
            r = render(qxml, seed)
        except (urllib.error.URLError, TimeoutError, json.JSONDecodeError) as e:
            flags.append(f"render     seed {seed}: API error {e}")
            nerr += 1
            continue
        notes.setdefault(r.get("questionnote", ""), []).append(seed)
        for label, html in (("question text", r.get("questionrender", "")),
                            ("worked solution", r.get("questionsamplesolutiontext", ""))):
            for m in R_BRACE.finditer(html):
                flags.append(f"brace      seed {seed} {label}: ...{html[max(0, m.start()-20):m.end()+10]!r}")
            for mm in MATH.finditer(html):
                math = mm.group(1) if mm.group(1) is not None else mm.group(2)
                for h in coeff_hits(math):
                    flags.append(f"coeff      seed {seed} {label}: ...{h!r}")
                for m in R_PLUSMINUS.finditer(math):
                    flags.append(f"plusminus  seed {seed} {label}: ...{math[max(0, m.start()-12):m.end()+8]!r}")
    dup = {n: s for n, s in notes.items() if len(s) > 1}
    if dup and len(xml_seeds) > 1:   # default seeds on a fixed question repeat by design
        for n, s in dup.items():
            flags.append(f"notes      seeds {s} share the question note {n[:60]!r}")

    # One line per distinct message, so 10 seeds with the same defect stay readable.
    flags = list(dict.fromkeys(flags))
    status = "FLAG" if flags else ("WARN" if warns else "OK")
    print(f"[{status}] {name}: {len(seeds)} seed(s)")
    for f in flags:
        print("    FLAG", f)
    if args.verbose or not flags:
        for w in warns:
            print("    WARN", w)
    elif warns:
        print(f"    ({len(warns)} source warning(s); rerun with --verbose)")
    nflag += bool(flags)
    nwarn += bool(warns) and not flags

print(f"--- {len(questions)} questions: {nflag} flagged, {nwarn} with warnings only ---")
sys.exit(2 if nerr else (1 if nflag else 0))
