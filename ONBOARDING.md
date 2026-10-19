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
the kit". It takes a minute and needs nothing from the user.

Record which assistant tool is running the kit, so every later session
dispatches the roles the same way. Test rather than describe: your account of
your own tool is not reliable.

1. Create `extensions/` if needed (`[ -d extensions ] || cp -r templates/extensions
   extensions`), and copy `templates/extensions/harness.md` there if it is
   missing.
2. Fill it in. Take the version from the tool's own `--version`. For "Tested
   here": can a subagent do a trivial task ("read the first line of
   `style-guide.md`"); is `/develop-section` among your commands. For the
   models, use only names your tool lists: the strongest for `deepest`, and
   your judgement for the rest. If your tool takes a subagent's model from the
   role file's header (Claude Code does), record what the headers say.
   Otherwise the header is ignored or rejected, so every dispatch must name the
   recorded model itself: say so under "Dispatching the roles", and run the
   trivial subagent task again with the `deepest` model named, to prove it.
3. Without subagents, fill in the `run-role.` lines as `tools/run-role.sh
   --help` describes and run `tools/run-role.sh --check` until it passes. If it
   will not, record that roles run in this one conversation instead.
4. Delete the comment at the top. Tell the user in two or three sentences what
   you found and which models the roles will use. If you chose them, say they
   can ask you to change them; never edit the role files, which belong to the
   kit.

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
prints, and run `tools/check-stack-version.sh`. If it fails, stop: a STACK older
than 4.13.0 fails every route-style question test, so nothing can be validated
until it is upgraded. Say which service is too old and point to the `SETUP.md`
section the script names. Only when it passes, offer to walk them through their
first section using `WORKFLOW.md`.

If they want you to commit, make the first commit in `project/`, never in the
kit.
