#!/usr/bin/env python3
"""Copy one question out of a section file into a Moodle XML file of its own.

    tools/extract-question.py <file>.xml <question-name> [-o <out>.xml]
    tools/extract-question.py <file>.xml --list

The kit keeps a whole section in one file, but some jobs want a single
question: sharing it, importing it by hand through the question bank, or
Moodle's "Import as new version", which accepts a file holding exactly one
question. With no -o the result goes to standard output.

The question is copied byte for byte, so CDATA, comments inside it and
whitespace are kept exactly. No category entry is written: the importer puts
the question in whatever category you choose. Exit 0 = written, 1 = the name
was not found or the file is not well-formed.
"""
import argparse
import re
import sys
import xml.etree.ElementTree as ET

# Comments and CDATA can hold text that looks like a tag; mask them before
# searching so a "</question>" inside question text cannot end a block.
HIDDEN = re.compile(r"<!--.*?-->|<!\[CDATA\[.*?\]\]>", re.DOTALL)
BLOCK = re.compile(r"<question\b[^>]*>.*?</question>", re.DOTALL)


def questions(source):
    """Yield (name, type, text) for each question, in file order."""
    masked = HIDDEN.sub(lambda m: " " * len(m.group()), source)
    for m in BLOCK.finditer(masked):
        text = source[m.start():m.end()]
        el = ET.fromstring(text)
        name = el.findtext("name/text") or ""
        yield name.strip(), el.get("type", ""), text


def main():
    p = argparse.ArgumentParser(description=__doc__.splitlines()[0])
    p.add_argument("file")
    p.add_argument("name", nargs="?", help="the question's <name>, exactly")
    p.add_argument("-o", "--output", help="file to write (default: stdout)")
    p.add_argument("--list", action="store_true",
                   help="list the question names in the file and stop")
    args = p.parse_args()

    with open(args.file, encoding="utf-8") as f:
        source = f.read()
    try:
        ET.fromstring(source.encode("utf-8"))
        found = list(questions(source))
    except ET.ParseError as e:
        print(f"{args.file}: not well-formed XML: {e}", file=sys.stderr)
        return 1

    if args.list or not args.name:
        for name, qtype, _ in found:
            if qtype != "category":
                print(f"{name}  ({qtype})")
        return 0 if args.list else 1

    matches = [q for q in found if q[0] == args.name]
    if not matches:
        near = [q[0] for q in found if args.name.lower() in q[0].lower()]
        print(f"No question named '{args.name}' in {args.file}.",
              file=sys.stderr)
        if near:
            print("Did you mean: " + ", ".join(near), file=sys.stderr)
        else:
            print("Use --list to see the names.", file=sys.stderr)
        return 1
    if len(matches) > 1:
        print(f"{len(matches)} questions are named '{args.name}'; "
              "writing the first.", file=sys.stderr)

    out = ('<?xml version="1.0" encoding="UTF-8"?>\n<quiz>\n'
           + matches[0][2] + "\n</quiz>\n")
    if args.output:
        with open(args.output, "w", encoding="utf-8") as f:
            f.write(out)
        print(f"Wrote {args.name} to {args.output}", file=sys.stderr)
    else:
        sys.stdout.write(out)
    return 0


if __name__ == "__main__":
    sys.exit(main())
