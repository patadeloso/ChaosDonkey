#!/bin/bash
if [ -z "${CLAUDE_PLUGIN_ROOT:-}" ] || [ ! -f "${CLAUDE_PLUGIN_ROOT}/core/dist/index.js" ]; then
  printf '%s\n' 'CLAUDE_PLUGIN_ROOT is unset or invalid' >&2
  exit 1
fi
node "${CLAUDE_PLUGIN_ROOT}/core/dist/index.js" pre-tool
