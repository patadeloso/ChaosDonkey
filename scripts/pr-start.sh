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

if [[ $# -lt 1 || $# -gt 2 ]]; then
  echo "Usage: scripts/pr-start.sh <feature-branch> [base-branch]" >&2
  exit 1
fi

branch_name="$1"
base_branch="${2:-main}"

repo_root="$(git rev-parse --show-toplevel)"
cd "$repo_root"

if ! git diff --quiet || ! git diff --cached --quiet; then
  echo "Error: working tree has staged or unstaged tracked changes. Commit or stash first." >&2
  exit 1
fi

if git rev-parse --verify "$branch_name" >/dev/null 2>&1; then
  echo "Error: branch '$branch_name' already exists." >&2
  exit 1
fi

git switch "$base_branch"
git pull --ff-only origin "$base_branch"
git switch -c "$branch_name"
git push -u origin HEAD

gh pr create --fill
