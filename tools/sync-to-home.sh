#!/usr/bin/env bash
# Deploy the kit's agents, commands and skills into ~/.claude so the pipeline is
# available in every project. Copies, never symlinks: a symlinked .claude has
# broken twice in this project's history.
#
# Project-level .claude/ definitions take precedence over these, so a project
# overrides any of them by name.
set -euo pipefail
KIT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
DEST="${HOME}/.claude"

for d in agents commands skills; do
    mkdir -p "${DEST}/${d}"
    cp -R "${KIT}/.claude/${d}/." "${DEST}/${d}/"
    echo "synced ${d} -> ${DEST}/${d}"
done
echo "done. Source: ${KIT}"
