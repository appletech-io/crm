#!/usr/bin/env bash

set -euo pipefail

MAIN_BRANCH="main"
DEV_BRANCH="develop"

ORIGINAL_BRANCH=$(git branch --show-current)

cleanup() {
  echo "Returning to $ORIGINAL_BRANCH..."
  git checkout "$ORIGINAL_BRANCH" >/dev/null 2>&1 || true
}
trap cleanup EXIT

echo "Checking git repository..."
git rev-parse --is-inside-work-tree >/dev/null 2>&1 || {
  echo "Error: Not inside a git repository."
  exit 1
}

echo "Checking required branches exist..."

git show-ref --verify --quiet "refs/heads/$MAIN_BRANCH" || {
  echo "Error: branch '$MAIN_BRANCH' does not exist locally."
  exit 1
}

git show-ref --verify --quiet "refs/heads/$DEV_BRANCH" || {
  echo "Error: branch '$DEV_BRANCH' does not exist locally."
  exit 1
}

echo "Checking working directory is clean..."
if ! git diff --quiet || ! git diff --cached --quiet; then
  echo "Error: Working directory is not clean. Commit or stash changes first."
  exit 1
fi

echo "Fetching latest updates..."
git fetch origin

echo "Switching to $MAIN_BRANCH..."
git checkout "$MAIN_BRANCH"

echo "Updating $MAIN_BRANCH..."
git pull --ff-only origin "$MAIN_BRANCH"

echo "Merging $DEV_BRANCH into $MAIN_BRANCH..."
if ! git merge --no-ff "$DEV_BRANCH"; then
  echo "Error: Merge failed (conflicts likely). Aborting merge..."
  git merge --abort || true
  exit 1
fi

echo "Pushing $MAIN_BRANCH..."
git push origin "$MAIN_BRANCH"

echo "Switching back to $DEV_BRANCH..."
git checkout "$DEV_BRANCH"

echo "Done successfully."
