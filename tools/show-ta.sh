#!/bin/bash
# show-ta.sh: print the teacher's answer for one input of every question in an
# XML file, on each seed, as STACK evaluates it. For free-text questions this is
# the model working exactly as the general feedback's [[ascii]] block receives
# it, which neither test-question.sh nor render-check.sh shows.
#
#   tools/show-ta.sh <xml> [input] [seeds]
#
#   input   default ans1
#   seeds   comma-separated, used for every question; default each question's
#           own <deployedseed> values, else 1,42,137,256,999 (test-question.sh's
#           defaults)
#
# Run from the kit's folder. It reads the database (the highest id of each
# question name), so run tools/test-question.sh on the file first.
#
# Read every line: look for a lone negative product or constant such as -(2*x)
# or -(1/2), a joined sign such as "- -3" or "+-", a coefficient 1 ("1 I"),
# reversed term order, and anything a student would not have typed.

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
# shellcheck source=/dev/null
source "$SCRIPT_DIR/lib/project.sh"
load_project_conf

XML="${1:?Usage: $0 <xml> [input] [seeds]}"
INPUT="${2:-ans1}"
SEEDS="${3:-}"

NAMES="$(mktemp)"
trap 'rm -f "$NAMES"' EXIT
# One line per STACK question: its name, a tab, and the seeds to use.
python3 - "$XML" "$NAMES" "$SEEDS" <<'PY'
import sys, xml.etree.ElementTree as ET
root = ET.parse(sys.argv[1]).getroot()
with open(sys.argv[2], "w") as out:
    for q in root.findall("question"):
        if q.get("type") == "stack":
            own = [s.text.strip() for s in q.findall("deployedseed")]
            seeds = sys.argv[3] or ",".join(own) or "1,42,137,256,999"
            out.write(q.findtext("name/text") + "\t" + seeds + "\n")
PY

docker cp "$SCRIPT_DIR/show-ta.php" "$LIVE_CONTAINER:/opt/show-ta.php" >/dev/null
docker cp "$NAMES" "$LIVE_CONTAINER:/tmp/show-ta-names.txt" >/dev/null
docker exec -e "MOODLE_ROOT=${MOODLE_ROOT:-/var/www/html}" "$LIVE_CONTAINER" \
    php /opt/show-ta.php /tmp/show-ta-names.txt "$INPUT"
