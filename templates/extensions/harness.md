# Harness binding

<!-- Template from the kit (templates/extensions/harness.md). Onboarding Stage 0
fills it in: the assistant answers each question about itself and replaces every
<...>. Until then the kit treats the binding as missing. -->

How this kit is bound to the assistant tool on this machine. It overrides the
kit's defaults for dispatch and reasoning tiers. The kit itself never names a
model or an effort value; this file is the only place they appear.

Recorded: <date>, by <the assistant, naming itself>. Checked by the user:
<yes / not yet>.

## The tool

1. Tool and version: <e.g. Claude Code 2.1.302, Copilot CLI 1.4>
2. Root instruction file it reads: <AGENTS.md, CLAUDE.md, GEMINI.md, ...>
3. Reads `AGENTS.md` natively: <yes / no / only when ...>
4. `@path` includes: <supported, and where / not supported>
5. Precedence between instruction layers: <defined, which order / none>
6. Walks directories for instruction files: <yes, how / no>
7. Custom role agents: <yes, where they live / no>
8. Custom slash commands: <yes, where they live / no>
9. Non-interactive invocation: <e.g. `copilot -p`>

## Dispatching the roles

<How the four roles run here: native subagents from `.claude/agents/` (Claude
Code), the role file's body handed to a subagent, separate sessions started by
the user, or `tools/run-role.sh`. Name the one to use by default.>

<How the procedures run here: a native `/develop-section` command, or by
following `.claude/commands/develop-section.md` when the user asks.>

## Reasoning tiers

Each role declares a tier in its body. Bind each one to the strongest setting
this tool offers for it. If you do not know your own settings, use the
strongest available for `deepest` and the defaults elsewhere, and do not invent
flag or model names: a guessed name fails silently or not at all. Ask the user
to confirm this section once.

Most tools offer model choice but no reasoning-effort setting, so on them the
tiers come down to which model each role gets. Say so here if that is the case.

| Tier | Roles | Binding here |
|---|---|---|
| `deepest` | planner | <model and effort, or "strongest available"> |
| `rigorous` | reviewer, enricher | <...> |
| `standard` | author | <...> |

## Settings for `tools/run-role.sh`

The script reads these lines. `run-role.cli` is one of `claude`, `copilot`,
`codex`, `gemini`. The `args` lines are extra command-line arguments, split on
spaces (no quoting): `run-role.args` goes to every role, then the tier's line is
added. Put model choice and tool permissions here. Leave a line empty rather
than guess.

```
run-role.cli: <claude|copilot|codex|gemini>
run-role.args: <permission flags every role needs>
run-role.args.deepest: <model and effort flags>
run-role.args.rigorous: <model and effort flags>
run-role.args.standard: <model and effort flags>
```

A role run without a person present must be allowed to edit files and run the
kit's shell tools (`tools/*.sh`, `docker`, `php`). Each tool grants this
differently, and not all have a middle ground between asking every time and
allowing everything. For reference, checked October 2026; verify against your
tool's own help before relying on it:

| Tool | Non-interactive | Permissions |
|---|---|---|
| Claude Code | `claude -p` | `--permission-mode acceptEdits` plus `--allowedTools "Bash(tools/*)" ...`, or `--permission-mode auto`; `--model`, `--effort` |
| Copilot CLI | `copilot -p -s --no-ask-user` | `--allow-tool='shell,write'` or `--allow-all-tools`; `--model=` |
| Codex CLI | `codex exec` | read-only by default; `--sandbox workspace-write` to write files. The sandbox may also block `docker` |
| Gemini CLI | `gemini -p` | `--approval-mode=auto_edit` or `yolo`; `-m` |

## Known degradations

<What does not work, or works less well, on this tool: layering with no defined
precedence, no subagents, no effort setting, tool permissions that block a role.
"None found" is a valid answer once it has been checked.>
