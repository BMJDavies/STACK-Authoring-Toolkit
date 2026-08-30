# Running several projects from one kit

**Audience: you, the human, and only if you run more than one project.**

**Skip this unless you already have more than one authoring project.** The normal
way to use the kit is the one SETUP.md describes: this folder *is* your project,
`.claude/` sits inside it, and nothing outside the folder is touched. That is
self-contained, obvious, and undone by deleting the folder.

This document covers the other case: you run two or more authoring projects and
do not want to maintain a separate copy of the pipeline in each.

## The model

One copy of the kit is the source of truth. Each project holds only its own
delta: its content rules, its source material, and any agent it genuinely needs
to override.

A working copy of the agents, commands and skills is deployed into `~/.claude/`,
which makes them available in every project on the machine. Where a project needs
different behaviour, it puts a file of the same name in its own `.claude/`, and
that takes precedence over the home copy.

When the shared copy and a project disagree about a generic rule, the shared copy
wins. A rule that turns out to be generic is promoted here rather than copied
sideways between projects. That single convention is what stops the pipeline
fragmenting into parallel versions.

## Deploying

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
there is overwritten on the next sync. Edit the kit and re-run the script.

## Why the agents are prefixed

`textbook-author`, `textbook-planner` and so on. Once the agents are deployed to
`~/.claude/` they are visible in every project on the machine, including ones that
have nothing to do with textbooks. Names like `author` and `reviewer` are
plausible picks in unrelated work, which is how the wrong specialist gets handed
a job.

If you only ever install the kit per-project, the prefix earns less. It is kept
for consistency, and because it makes each agent self-describing.
