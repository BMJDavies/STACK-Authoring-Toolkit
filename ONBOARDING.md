# Onboarding

**Audience: the assistant. Follow this when `project/AGENTS.md` does not exist,
or Stage 0 alone when `extensions/harness.md` does not.**

The kit (this folder) is generic and is never edited during onboarding.
Everything you create belongs to the user's project, in `project/`, which is a
separate git repository that the kit ignores.

Onboarding may already have been started and left unfinished: `project/` exists,
or `project/AGENTS.md` is still the template with its `<...>` placeholders. Work
out how far it got and resume from there rather than starting again. Detection
below tells you what is already in place, and the steps are written so that
re-running one is harmless.

**If `project/CLAUDE.md` or `extensions/CLAUDE.md` exists, this is not a new
project: stop.** The project was set up before the kit renamed its instruction
files to `AGENTS.md`. Onboarding would treat it as new and put a blank template
beside the real instructions. Point the user to `UPGRADING.md`,
"Instruction files renamed to `AGENTS.md`", and offer to do the two renames with
them.

## Who you are working with

An experienced STACK question author who is new to working with an AI assistant.
Assume fluency in STACK, Maxima, potential response trees and Moodle question
banks. Do not explain what a PRT is. Do assume no familiarity with agents, slash
commands, context, or how to work with you effectively.

Two consequences. Explain the AI-side mechanics without being asked. And do not
soften or simplify the mathematics or the STACK detail: they will spot it, and it
will cost you their confidence.

## Detect before you ask

Work out everything you can without asking. Every question you avoid is one they
do not have to answer.

```bash
command -v docker || echo NO_DOCKER      # absent: they need SETUP.md section 1
command -v pdftotext || echo NO_POPPLER   # absent: brew install poppler
docker ps --format '{{.Names}}'          # is anything already running
docker ps -a --format '{{.Names}}'       # containers that exist but are stopped
uname -sm                                # OS and architecture
git -C . rev-parse --is-inside-work-tree # is the kit under version control (it should be)
ls project/ 2>/dev/null                  # has a project folder been started
ls project/CLAUDE.md extensions/CLAUDE.md 2>/dev/null   # old names: see above
```

Report what you found in one short paragraph before asking anything. If Moodle is
already running, say so rather than asking whether it is.

## Stage 0: record your harness

Skip this if `extensions/harness.md` exists and no longer says "Template from
the kit".

You know what tool you are running in; the kit does not. Write it down, so that
every later session and `tools/run-role.sh` dispatch the roles the same way.

1. Create `extensions/` if needed (`[ -d extensions ] || cp -r templates/extensions
   extensions`), and copy `templates/extensions/harness.md` there if it is
   missing.
2. Answer each question in it about yourself, from what you know and what you
   can check: run your own `--help` or `--version` rather than recalling flags.
   Where you cannot tell, write "unsure", not a guess.
3. Bind the three reasoning tiers. If you do not know your own model or effort
   settings, use the strongest available for `deepest` and defaults elsewhere.
   Never invent a flag or model name.
4. Fill in the `run-role.` lines only with flags you have confirmed.
5. Delete the comment at the top, and show the user what you wrote. Ask them to
   check the tier bindings and the permission flags in particular: a model's
   report of its own settings is not reliable, and the permission flags decide
   what a role run without them present may do.

If anything in the four joints listed in the kit's `AGENTS.md` ("If you are not
Claude Code") does not work here, record it under "Known degradations" and tell
the user now.

## Stage A: their environment

This decides how much of `SETUP.md` applies. Ask only what detection did not
answer.

1. Do they have a working Moodle with STACK installed?
2. If yes, is it local Docker, or local by some other means?
3. Do they have command-line access to it? This is the real requirement. The
   tools shell into the container to run PHP. Administrator rights in the web
   interface are not sufficient on their own.
4. Confirm the operating system and architecture you detected. On Apple Silicon
   the standard Maxima package is not the one to install; `SETUP.md` section 4
   covers this.

**A Moodle they do not control is not supported.** If they describe an
institutional or hosted Moodle where they cannot reach a command line, stop and
say so plainly. The pipeline publishes by executing PHP inside the Moodle
installation. Without that access, the authoring and validation half still works
and the publishing half does not. Do not attempt a workaround.

Then send them to the parts of `SETUP.md` they actually need, rather than all ten
sections. Someone with Moodle and STACK already running usually needs only
sections 5, 6, 8 and 9.

## Stage B: their project

First create the project folder from the kit's skeleton, so there is somewhere
to put their material. If `project/` already exists (`SETUP.md` section 6
creates it when the ids are first pasted in), keep it and its `project.conf`, and
copy across only what is missing:

```bash
[ -d project ] || { cp -r templates/project project; git -C project init -q; }
cp -rn templates/project/. project/      # adds missing files, overwrites nothing
```

This gives `project/AGENTS.md` (a template to fill in later), `project.conf`,
`style-guide.md`, `content/`, `feedback/` and `sources/`, each with a short
explanation. Explain to the user that `project/` is their own repository: the
kit ignores it, so updating the kit never touches their work.

Then create their extensions folder the same way, if it is not already there:

```bash
[ -d extensions ] || cp -r templates/extensions extensions
cp -rn templates/extensions/. extensions/   # adds missing files, overwrites nothing
```

Explain in a sentence that `extensions/` is for their own instructions across
all projects, that the kit ignores it, and that it can stay empty. Offer, but do
not assume, `git -C extensions init` if they want it under version control.

1. Subject, level, and who the students are.
2. Roughly how much material: a topic, a module, a book?
3. What is the source material, and what form is it in? PDF, LaTeX, Word,
   or print only?
3b. Where in the world are the students? Currency, names, places and units in
   worded questions follow from this, and the agents will otherwise default to
   wherever their training leans without saying so. Record it in
   `project/AGENTS.md`.

4. **Get the material into `project/sources/` before going further.** Ask them
   to put it there, then confirm it has arrived and that you can actually read it:

   ```bash
   ls -la project/sources/
   pdftotext -layout project/sources/<their file>.pdf - | head -60
   ```

   Check the mathematics survived extraction. Surds, fractional indices and
   subscripts are the usual casualties and they fail silently, producing
   plausible text that is quietly wrong. If it is mangled, say so now and plan to
   work from rendered page images instead. Finding this out here costs a minute;
   finding it out when the planner is midway through a section costs the section.
   `.claude/skills/textbook-source-material/` covers both routes.
5. What is the natural unit of delivery: chapter, module, topic? This decides how
   courses, sections and quizzes are laid out.
6. Do they have an existing question bank whose conventions should be matched?

**This release supports source-led authoring only**, where questions are adapted
from existing material. Authoring against a syllabus with no source text is
possible in principle and may be addressed in a future release, but it is not
supported here and has not been validated. If that is what they want, tell them
plainly rather than improvising it.

## Stage C: how they want to work

1. How much explanation do they want at each step while they are learning?
2. Do they want to approve a plan before any XML is written?
3. Who commits to version control? (Their project has its own repository in
   `project/`; the kit's repository is only for changes to the kit itself.)

## What to do with the answers

Fill in `project/AGENTS.md`: replace every `<...>` in the template with their
answers, and delete the comment at the top. Keep it short: it is loaded on every
request, so length here costs them on every interaction. Generic instructions
are already in the kit's `AGENTS.md`; do not copy them across.

Then fill in `project/project.conf` with the ids that `SETUP.md` section 6
prints, and offer to walk them through their first section using `WORKFLOW.md`.

If they want you to commit, make the first commit in `project/`, never in the
kit.
