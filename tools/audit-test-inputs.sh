#!/usr/bin/env bash
# audit-test-inputs.sh: flag <testinput> values that are hardcoded
# inside RANDOMISED questions. These silently mark students wrong on every
# seed except the one the literal happens to match (the D9/D12/D13 bug class).
#
# Usage: tools/audit-test-inputs.sh [path-or-glob...]
#        defaults to the project's content/approved/*.xml
#
# A testinput is flagged if BOTH:
#   1. the enclosing <question> has rand(...) in <questionvariables>, AND
#   2. its <value> contains none of:
#        - a teacher-answer reference (\bta[0-9]*\b)
#        - an MCQ helper (mcq_correct / mcq_incorrect)
#        - a sentinel keyword (wrong_ans, true, false)
#
# Pure literals (e.g. "0", "5", "x+1", "x^2-1") therefore get flagged.
# Some hits will be legitimate (e.g. test sentinels for deliberately-wrong
# answers, or questions whose ta is itself a constant like 0). Triage by
# eye. This is a triage report, not a guard rail.
#
# Output: TSV with columns: file<TAB>line<TAB>question<TAB>input<TAB>value
# Exit 0 always.

set -u

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
# shellcheck source=lib/project.sh
source "$SCRIPT_DIR/lib/project.sh"

shopt -s nullglob
files=("$@")
if [ ${#files[@]} -eq 0 ]; then
  files=("$PROJECT_DIR"/content/approved/*.xml)
fi

# Two-pass approach per file: scan for randomised questions, then for each
# <qtest> check whether it has any expected-correct PRT. If so, flag any
# hardcoded testinput within that qtest.
for f in "${files[@]}"; do
  awk -v FILE="$f" '
    function is_hardcoded(v,    s, tok) {
      if (v == "") return 0
      if (v ~ /mcq_(correct|incorrect)/) return 0
      # extract every identifier token; accept value if any token is a known
      # question variable (defined in <questionvariables>). Those vary with
      # seed, so referencing them makes the testinput seed-aware.
      s = v
      while (match(s, /[A-Za-z_][A-Za-z0-9_]*/)) {
        tok = substr(s, RSTART, RLENGTH)
        if (tok in qvars) return 0
        if (tok == "true" || tok == "false" || tok == "wrong_ans") return 0
        s = substr(s, RSTART + RLENGTH)
      }
      return 1
    }
    /<question type/ {
      in_q = 1; in_qv = 0; has_rand = 0; q_name = "(unnamed)"
      delete buf; buf_n = 0; delete qvars
    }
    in_q { buf[++buf_n] = $0; buf_line[buf_n] = NR }
    /<name><text>/ && in_q && q_name == "(unnamed)" {
      if (match($0, /<name><text>[^<]*<\/text><\/name>/)) {
        n = substr($0, RSTART, RLENGTH)
        gsub(/<\/?name>|<\/?text>/, "", n)
        if (n ~ / /) q_name = n
      }
    }
    /<questionvariables>/ { in_qv = 1 }
    in_qv && /rand[a-z_]*\(/ { has_rand = 1 }
    in_qv {
      # capture `name:` and `name(args) :=` assignments (these are question vars)
      s = $0
      while (match(s, /[A-Za-z_][A-Za-z0-9_]*[ \t]*(\([^)]*\))?[ \t]*:[=]?/)) {
        m = substr(s, RSTART, RLENGTH)
        sub(/[ \t]*(\([^)]*\))?[ \t]*:[=]?$/, "", m)
        qvars[m] = 1
        s = substr(s, RSTART + RLENGTH)
      }
    }
    /<\/questionvariables>/ { in_qv = 0 }
    /<\/question>/ {
      if (has_rand) {
        # walk buf in <qtest>...</qtest> chunks
        i = 1
        while (i <= buf_n) {
          if (buf[i] ~ /<qtest>/) {
            j = i; any_correct = 0
            delete tlines; delete tvals; delete tnames; delete tline_nos; tn = 0
            while (j <= buf_n && buf[j] !~ /<\/qtest>/) {
              if (buf[j] ~ /<testinput>/) {
                iname = ""; val = ""
                if (match(buf[j], /<name>[^<]+<\/name>/)) {
                  iname = substr(buf[j], RSTART+6, RLENGTH-13)
                }
                if (match(buf[j], /<value>[^<]*<\/value>/)) {
                  val = substr(buf[j], RSTART+7, RLENGTH-15)
                  gsub(/^[ \t]+|[ \t]+$/, "", val)
                }
                tn++; tnames[tn] = iname; tvals[tn] = val; tline_nos[tn] = buf_line[j]
              }
              if (buf[j] ~ /<expectedscore>/) {
                if (match(buf[j], /<expectedscore>[^<]+<\/expectedscore>/)) {
                  sc = substr(buf[j], RSTART+15, RLENGTH-31) + 0
                  if (sc > 0) any_correct = 1
                }
              }
              j++
            }
            if (any_correct) {
              for (k = 1; k <= tn; k++) {
                if (is_hardcoded(tvals[k])) {
                  printf "%s\t%d\t%s\t%s\t%s\n", FILE, tline_nos[k], q_name, tnames[k], tvals[k]
                }
              }
            }
            i = j
          }
          i++
        }
      }
      in_q = 0
    }
  ' "$f"
done
