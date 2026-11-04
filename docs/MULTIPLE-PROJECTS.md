# Running several projects

**Audience: you, the human, and only if you run more than one project.**

**Skip this unless you already have more than one authoring project.** The normal
layout is the one `docs/SETUP.md` describes: one kit folder, with your project inside
it in `project/` as a separate git repository.

## Recommended: one kit clone per project

Clone the kit once for each project, and give each clone its own `project/`:

```
~/stack/algebra/      a kit clone; its project/ is the algebra project
~/stack/calculus/     another kit clone; its project/ is the calculus project
```

Each clone is self-contained: the assistant, the tools and the project
instructions all line up, and updating one kit with `git pull` cannot affect the
other project. Kit clones are small, so the duplication costs little.

## Possible: several project folders in one kit

The tools accept a `STACK_PROJECT` setting naming a folder other than
`project/`, relative to the kit root or absolute:

```bash
STACK_PROJECT=calculus tools/test-question.sh calculus/content/approved/Ch1_A.xml
```

Add each such folder to the kit's `.gitignore`, as `project/` is.

The limitation: `STACK_PROJECT` changes where the **tools** look. The assistant
always reads `project/AGENTS.md`, so it does not follow the setting. Either
tell it explicitly which folder you are working on and which instructions to
read, or keep the project you are actively working on in `project/` and rename
folders to switch. For regular work on two projects, separate clones are less
error-prone.

**If you are the assistant and the user names a project folder other than
`project/`:** the kit's `AGENTS.md` has given you the wrong project's
instructions. Read that folder's `AGENTS.md` instead, and take
every path from it — `<folder>/content/`, `<folder>/sources/`,
`<folder>/project.conf` — rather than from `project/`. Pass the same folder to
the tools as `STACK_PROJECT`, so you and they agree on which project this is.

## Your extensions across projects

`extensions/` belongs to the kit folder, not to a project. With several project
folders in one kit, every project shares it, which is the point. With one kit
clone per project, each clone has its own `extensions/`: make yours a git
repository and clone it into each kit clone, so that a change made in one can be
pulled into the others.

## Shared agents, commands and skills in `~/.claude/`

With several clones you may prefer one copy of the agents, commands and skills
for every project on the machine:

```bash
tools/sync-to-home.sh
```

Copies `.claude/agents`, `.claude/commands` and `.claude/skills` into `~/.claude/`.
Copies, never symlinks: a symlinked `.claude` is a well-known way to end up with
links pointing at directories that no longer exist.

It records what it installed in `~/.claude/.kit-manifest`, and on the next run
removes exactly those entries before reinstalling. Two consequences worth knowing:

- Renaming an agent is clean. The old file is removed rather than left stranded
  alongside the new one, which would otherwise leave both live at once.
- Your own agents, commands and skills are never touched, because the script can
  only delete names it wrote down. Personal commands like `/wrap` are safe
  structurally, not by luck.

`~/.claude/` is a deployment target, not a place to edit. Anything you change
there is overwritten on the next sync. Edit the kit and re-run the script. A
kit clone's own `.claude/` takes precedence over the home copy while you work in
that clone.

When the kit and a project disagree about a generic rule, the kit wins. A rule
that turns out to be generic is promoted into the kit's `style-guide.md` rather
than copied sideways between projects' `project/style-guide.md` files. That
single convention is what stops the pipeline fragmenting into parallel versions.

## Why the agents are prefixed

`textbook-author`, `textbook-planner` and so on. Once the agents are deployed to
`~/.claude/` they are visible in every project on the machine, including ones that
have nothing to do with textbooks. Names like `author` and `reviewer` are
plausible picks in unrelated work, which is how the wrong specialist gets handed
a job.

If you only ever use the kit's own `.claude/`, the prefix earns less. It is kept
for consistency, and because it makes each agent self-describing.
