# GitHub Push Recovery Guide

This note documents the exact Git commands used to recover from push/rebase issues and successfully push changes to GitHub.

## Situation

The repository had local changes and the remote branch had newer commits, so a normal `git push` was rejected. The fix was to sync the local branch with the remote before pushing.

## Commands Used

Run these commands from the project root:

```bash
git status --short
git remote -v
git log --oneline --decorate -5
git fetch origin
git rebase origin/master
git push origin master
```

## If local changes block rebase

If Git reports that rebase cannot proceed because of unstaged changes, use:

```bash
git stash push -m "wip: stash local changes before push"
git fetch origin
git rebase origin/master
git push origin master
git stash pop
```

## If the push is rejected because the remote is ahead

Use:

```bash
git fetch origin
git rebase origin/master
git push origin master
```

## Notes

- Always check `git status` first.
- If you have uncommitted work that should not be lost, stash it before rebasing.
- If a commit was already created locally and needs to be pushed, rebase onto the latest remote branch first, then push.

## Example Recovery Flow

```bash
cd c:\xammp\htdocs\sms
git status --short
git fetch origin
git rebase origin/master
git push origin master
```
