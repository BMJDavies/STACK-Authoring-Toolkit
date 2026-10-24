# Harness binding

<!-- Template from the kit (templates/extensions/harness.md). Onboarding Stage 0
replaces every <...> with a value and deletes this comment. Until then the kit
treats the binding as missing. -->

How this kit runs in the assistant tool on this machine. Re-run Stage 0 of
`ONBOARDING.md` after changing or upgrading the tool.

Tool and version: <...>. Recorded: <date>.

## Tested here

- Subagents: <yes / no>
- Effort can be set per dispatch: <yes / no>
- `/develop-section` as a command: <yes / no>

## Dispatching the roles

Route: <subagents / tools/run-role.sh / this conversation>

Name the model and effort level from the table below in every dispatch: the role
files do not carry them. Where effort cannot be set per dispatch, the session's
effort applies to every role.

## Models and effort

Chosen by the user at onboarding, from the guidance in `WORKFLOW.md`, "Choosing
models and effort". Reviewed before each chapter.

| Role | Tier | Model here | Effort here |
|---|---|---|---|
| planner | `deepest` | <...> | <...> |
| reviewer | `rigorous` | <...> | <...> |
| enricher | `rigorous` | <...> | <...> |
| author | `standard` | <...> | <...> |

## `tools/run-role.sh`

Only for a tool without subagents; otherwise delete this section. The lines and
their flags are described by `tools/run-role.sh --help`.

```
run-role.cli: <...>
run-role.args: <...>
```
