# Harness binding

<!-- Template from the kit (templates/extensions/harness.md). Onboarding Stage 0
fills it in and replaces every <...>. Until then the kit treats the binding as
missing. -->

How this kit runs in the assistant tool on this machine. Re-run Stage 0 of
`ONBOARDING.md` after changing or upgrading the tool.

Tool and version: <from the tool's own --version>. Recorded <date>.

## Tested here

- Subagents: <yes / no>
- `/develop-section` as a command: <yes / no: the user asks in words>

## Dispatching the roles

<Subagents, one role at a time / no subagents: `tools/run-role.sh`, or all four
roles in this one conversation if that fails.>

## Models

| Tier | Roles | Model here |
|---|---|---|
| `deepest` | planner | <...> |
| `rigorous` | reviewer, enricher | <...> |
| `standard` | author | <...> |

## `tools/run-role.sh`

Only for a tool without subagents; otherwise leave as it is. The lines and their
flags are described by `tools/run-role.sh --help`.

```
run-role.cli: <...>
run-role.args: <...>
```
