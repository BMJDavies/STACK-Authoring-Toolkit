#!/usr/bin/env bash
# Deploy the kit's agents, commands and skills into ~/.claude so the pipeline is
# available in every project. Copies, never symlinks: a symlinked .claude has
# broken twice in this project's history.
#
# Project-level .claude/ definitions take precedence over these, so a project
# overrides any of them by name.
#
# Removes only what a previous run of this script installed, recorded in
# ~/.claude/.kit-manifest. Your own agents, commands and skills are never
# touched, so personal commands like /wrap survive.
set -euo pipefail
KIT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
DEST="${HOME}/.claude"
MANIFEST="${DEST}/.kit-manifest"

# 1. remove what the last run installed (handles renames cleanly)
if [ -f "$MANIFEST" ]; then
    while IFS= read -r rel; do
        [ -n "$rel" ] && rm -rf "${DEST:?}/${rel}"
    done < "$MANIFEST"
fi

# 2. install, recording what we put there
: > "$MANIFEST"
for d in agents commands skills; do
    mkdir -p "${DEST}/${d}"
    for item in "${KIT}/.claude/${d}"/*; do
        [ -e "$item" ] || continue
        base="$(basename "$item")"
        cp -R "$item" "${DEST}/${d}/"
        echo "${d}/${base}" >> "$MANIFEST"
    done
    echo "synced ${d}"
done

echo "done. Source: ${KIT}"
echo "Installed $(wc -l < "$MANIFEST" | tr -d ' ') items, listed in ${MANIFEST}"
