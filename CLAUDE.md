# Project instructions

**Audience: the assistant. The human reader wants README.md.**

This file is read automatically at the start of every session in this folder and
is treated as standing instruction. Right now it describes a kit that has not yet
been configured for anyone's project.

## This project has not been set up yet

If the file CLAUDE_loc.md exists in this directory then a project has been configured. Ignore the rest of this file.  Use CLAUDE_loc.md instead.

No project has been configured.  Do not author, validate or publish anything.

Instead:

1. Read `ONBOARDING.md` and follow it.
2. It will tell you what to detect about this machine, what to ask the user, and what to do with the answers.
3. At the end of it you will replace this file with real project instructions.

Once that is done, the file `CLAUDE_loc.md` describes an actual project.

## What this repository is, before configuration

A pipeline for writing STACK questions for Moodle with an AI assistant. It
contains four agent definitions, two slash commands, five skills, a set of shell
and PHP tools, a style guide, a reviewing checklist, a Moodle theme, and
reference XML showing each question pattern.

It contains no question content and no teaching material. Those come from the
user.

## Standing rules that apply from the first session

- Never run validation in the background and poll for it. It is slow, produces
  no output while running, and blocking is correct.
- Never commit on the user's behalf unless they have said to.
- The XML under `content/` is the master copy. Moodle is a rendering target.
- Ask before doing anything that writes to Moodle.

## Where to read further

| File | Read it when |
|---|---|
| `ONBOARDING.md` | Now, if this project is unconfigured |
| `AGENTS.md` | If you are not Claude Code: the portable description of the pipeline |
| `SETUP.md` | While installing, guided by onboarding |
| `WORKFLOW.md` | Before producing the first section |
| `style-guide.md` | Before writing or reviewing any question |
| `reviewing-checklist.md` | When reviewing |
| `LESSONS.md` | Once, early. It explains why the rules exist |
