---
name: github
description: "Use when pushing changes to GitHub, fixing rejected pushes, rebasing onto origin/master, or recovering from git conflicts in this repository."
---

# GitHub Push Recovery Skill

Use this skill when you need to push code to GitHub from this repository and the push is rejected because the remote branch is ahead or local changes are blocking a rebase.

## When to Use

- A normal `git push` is rejected with a non-fast-forward error.
- Git reports that rebase cannot proceed because of unstaged changes.
- You need a repeatable recovery workflow for GitHub pushes.

## Core Workflow

1. Check repository state.
2. Fetch the latest remote changes.
3. Rebase local commits onto the remote branch.
4. Push the updated branch to GitHub.

## Commands

From the project root, run:

```bash
git status --short
git remote -v
git fetch origin
git rebase origin/master
git push origin master
```

## If Local Changes Block Rebase

If Git says there are unstaged changes, stash them first:

```bash
git stash push -m "wip: stash local changes before push"
git fetch origin
git rebase origin/master
git push origin master
git stash pop
```

## Verification

A successful push will show output similar to:


## Reference

See [docs/github-push-workflow.md](../../../docs/github-push-workflow.md) for the full reference note.
