#!/usr/bin/env bash
set -euo pipefail

if ! command -v gh >/dev/null 2>&1; then
  echo "Error: GitHub CLI (gh) is required." >&2
  exit 1
fi

if ! command -v git >/dev/null 2>&1; then
  echo "Error: git is required." >&2
  exit 1
fi

if [[ $# -gt 1 ]]; then
  echo "Usage: scripts/pr-finish.sh [base-branch]" >&2
  exit 1
fi

base_branch="${1:-main}"

repo_root="$(git rev-parse --show-toplevel)"
cd "$repo_root"

gh pr merge --squash --delete-branch --auto

git switch "$base_branch"
git pull --ff-only origin "$base_branch"
