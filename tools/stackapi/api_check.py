#!/usr/bin/env python3
"""Validate a Moodle-XML question file through the standalone STACK API /test route.

Splits a multi-question quiz file and POSTs each STACK question to /test, once
per deployed seed, which runs the question's embedded qtests on that variant.
No Moodle DB import, no seed-deploy, no cleanup.

One request per seed: on STACK 4.14.0 and earlier, /test generates only the
first deployed seed's variant and runs every seed's tests against it, so a
request holding several seeds tests the first seed only.

Usage:  api_check.py <file.xml> [--url http://localhost:3080] [--retries 2]

Exit 0 = all questions pass (or have no tests); exit 1 = at least one real fail.
Timeouts and transient errors are retried (the amd64 image runs emulated on
Apple Silicon and can time out under load); a question only counts as FAIL once
its retries are exhausted.
"""
import sys, json, re, urllib.request, urllib.error, argparse
import xml.etree.ElementTree as ET

ap = argparse.ArgumentParser()
ap.add_argument("xmlfile")
ap.add_argument("--url", default="http://localhost:3080")
ap.add_argument("--retries", type=int, default=2)
ap.add_argument("--timeout", type=int, default=180)
args = ap.parse_args()

# The /test route only runs tests against seeds already DEPLOYED in the XML.
# A randomised question with no <deployedseed> entries is silently NOT tested
# (results come back empty). Our Moodle validator sidesteps this by force-
# deploying these 5 seeds, so we inject the same set into any question lacking
# deployed seeds, matching validate-questions.php.
INJECT_SEEDS = [1, 42, 137, 256, 999]

root = ET.parse(args.xmlfile).getroot()
questions = [q for q in root.findall("question") if q.get("type") == "stack"]
if not questions:
    print("No STACK questions found in", args.xmlfile)
    sys.exit(1)


def post_test(xmlstr):
    payload = json.dumps({"questionDefinition": xmlstr}).encode()
    req = urllib.request.Request(args.url + "/test", data=payload,
                                 headers={"Content-Type": "application/json"})
    with urllib.request.urlopen(req, timeout=args.timeout) as r:
        return json.loads(r.read().decode())


def get_results(res):
    """Return the results as a seed->object dict. The API returns an empty JSON
    list [] (not {}) for questions with no deployed variants; normalise that."""
    r = res.get("results")
    return r if isinstance(r, dict) else {}


def is_timeout(res):
    """True if any seed reports a CAS/runtime timeout rather than a real mismatch."""
    for seedres in get_results(res).values():
        msg = (seedres.get("messages") or "").lower()
        if "timed out" in msg or "timeout" in msg:
            return True
    return False


def post_with_retries(xmlstr):
    """POST one request, retrying transient errors and emulation timeouts.
    Returns (response, None) or (None, error message)."""
    res = None
    for attempt in range(args.retries + 1):
        try:
            res = post_test(xmlstr)
        except Exception as e:
            if attempt < args.retries:
                continue
            return None, f"{e} (after {args.retries + 1} attempts)"
        # Retry on emulation timeout, not on a genuine score mismatch.
        if is_timeout(res) and attempt < args.retries:
            continue
        break
    return res, None


any_fail = False
n_pass = n_fail = n_notest = 0
for q in questions:
    name_el = q.find("name/text")
    name = name_el.text if name_el is not None else "?"
    seeds = [el.text for el in q.findall("deployedseed")] or [str(s) for s in INJECT_SEEDS]
    for el in q.findall("deployedseed"):
        q.remove(el)

    # One request per seed, each a copy of the question with that one seed.
    res = {"messages": "", "results": {}}
    error = None
    for seed in seeds:
        seed_el = ET.SubElement(q, "deployedseed")
        seed_el.text = seed
        wrapper = ET.Element("quiz")
        wrapper.append(q)
        xmlstr = ET.tostring(wrapper, encoding="unicode")
        q.remove(seed_el)
        # Strip /*...*/ block comments (JS comments in JSXGraph figures, Maxima
        # comments in castext). The API's castext validator rejects them ("no
        # longer supported") and returns zero results, while Moodle import accepts
        # them. They are semantically inert, and this only touches the POST
        # payload, never the content file. New content should use // instead.
        xmlstr = re.sub(r"/\*.*?\*/", "", xmlstr, flags=re.S)

        seedres, error = post_with_retries(xmlstr)
        if seedres is None:
            error = f"seed {seed}: {error}"
            break
        if seedres.get("messages") and not res["messages"]:
            res["messages"] = seedres["messages"]
        res["results"].update(get_results(seedres))
        if res["messages"]:
            break  # A question-level error is the same for every seed.

    if error:
        print(f"[ERROR] {name}: {error}")
        any_fail = True
        n_fail += 1
        continue

    results = get_results(res)
    tp = sum((s.get("passes") or 0) for s in results.values())
    tf = sum((s.get("fails") or 0) for s in results.values())
    qmsg = res.get("messages") or ""
    seedmsgs = [s.get("messages") for s in results.values() if s.get("messages")]

    if qmsg:
        # A question-level error message means the API refused to compile or
        # test the question (e.g. unsupported castext). Must be FAIL even when
        # zero tests ran, otherwise untested questions exit green.
        status = "FAIL"
        any_fail = True
        n_fail += 1
    elif not results or tp == 0 and tf == 0:
        # Description / no-test question: /test runs a default model-answer check.
        status = "NOTEST"
        n_notest += 1
    elif tf or is_timeout(res):
        status = "FAIL"
        any_fail = True
        n_fail += 1
    else:
        status = "PASS"
        n_pass += 1

    detail = ""
    if tf:
        detail = " | " + "; ".join(
            f"seed {k}: {v['fails']} fail" for k, v in results.items() if v.get("fails"))
    if qmsg:
        detail += f" [{qmsg[:120]}]"
    elif seedmsgs and status != "PASS":
        detail += f" [{seedmsgs[0][:120]}]"
    print(f"[{status}] {name}: {tp} pass / {tf} fail across {len(results)} seed(s){detail}")

print(f"--- {n_pass} PASS, {n_fail} FAIL, {n_notest} no-test, {len(questions)} total ---")
sys.exit(1 if any_fail else 0)
