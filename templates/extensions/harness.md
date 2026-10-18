# Harness binding

<!-- Template from the kit (templates/extensions/harness.md). Onboarding Stage 0
fills it in: the assistant tests what it can, asks the user the two decisions,
and replaces every <...>. Until then the kit treats the binding as missing. -->

How this kit runs in the assistant tool on this machine. It overrides the kit's
defaults for dispatch, reasoning tiers and unattended runs.

Tool and version: <from the tool's own --version, e.g. Claude Code 2.1.302>.
Recorded <date>.

## Tested here

Each line is the result of a test run in Stage 0, not the tool's description of
itself. Re-run Stage 0 after upgrading the tool.

- Layer files loaded automatically: <yes: their contents were in context before
  any file was read / no: the assistant reads them each session, as the kit's
  `AGENTS.md` tells it to>
- Subagents: <yes: a trivial subagent task ran / no: roles run as separate
  sessions or with `tools/run-role.sh`>
- `/develop-section` as a command: <yes / no: the user asks in words and the
  assistant follows `.claude/commands/develop-section.md`>
- `tools/run-role.sh --check`: <passed / failed: what it said / not set up>

## Dispatching the roles

<The route to use by default, from the results above: native subagents,
`tools/run-role.sh`, or separate sessions the user starts.>

## The user's decisions

These are choices, not facts about the tool, and only the user can make them.
The assistant proposes; the user confirms.

**Models for each reasoning tier.** Each role declares a tier in its body. A
stronger model costs more per section. Use only model names and settings shown
by the tool's own `--help` or model list; a guessed name fails, or silently falls
back to a default. Many tools have no reasoning-effort setting, so the tiers come
down to which model each role gets.

| Tier | Roles | Model and setting here |
|---|---|---|
| `deepest` | planner | <...> |
| `rigorous` | reviewer, enricher | <...> |
| `standard` | author | <...> |

<On Claude Code, a role dispatched as a subagent takes its model from the YAML
header of its role file, so this table binds only `tools/run-role.sh`. Delete
this paragraph on other tools.>

**Unattended runs.** <not used: roles run only in a session the user is
watching, which asks before each risky action / used: settings below>.

When used, a role runs with nobody to approve anything, so it must be allowed in
advance to edit files and run `tools/*`, `docker`, `pdftotext`, `git` and
`grep`. Two questions for the user:

- May an unattended role write to Moodle (publish, create quizzes)? <yes / no>.
  With no, the role stops before publishing and reports what it would have
  published. The kit's standing rule is to ask first, so "no" is the default.
- Narrowest permission that allows the above, or everything? <narrow /
  everything>. Everything is simpler, and lets a confused role run any command.

## Settings for `tools/run-role.sh`

The script reads these lines. `run-role.cli` is one of `claude`, `copilot`,
`codex`, `gemini`. The `args` lines are extra command-line arguments, split on
spaces and passed as they are: no shell quoting, so write `Bash(tools/*)`, not
`"Bash(tools/*)"`. `run-role.args` goes to every role, then the tier's line is
added. Leave a line empty rather than guess.

```
run-role.cli: <claude|copilot|codex|gemini>
run-role.moodle-writes: <yes|no>
run-role.args: <permission flags>
run-role.args.deepest: <model flags>
run-role.args.rigorous: <model flags>
run-role.args.standard: <model flags>
```

Permission flags, as a starting point. Only the Claude Code line has been tested
with `--check`; verify the others against the tool's own help:

| Tool | Narrow | Everything |
|---|---|---|
| Claude Code (tested) | `--permission-mode acceptEdits --allowedTools Bash(tools/*) Bash(docker:*) Bash(pdftotext:*) Bash(git:*) Bash(grep:*)` | `--permission-mode bypassPermissions` |
| Copilot CLI | `--allow-tool=write --allow-tool=shell(tools/*) --allow-tool=shell(docker:*) ...` | `--allow-all-tools` |
| Codex CLI | `--sandbox workspace-write` (may block `docker`) | `--sandbox danger-full-access` |
| Gemini CLI | `--approval-mode=auto_edit` (still asks for shell) | `--approval-mode=yolo` |

## Known degradations

<Anything above that failed or works less well here, in a line each, or "none
found".>
