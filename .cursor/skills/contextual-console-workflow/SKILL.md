---
name: contextual-console-workflow
description: >-
  Guides general Contextual Console development: inspect first, small diffs,
  Laravel conventions, no unsolicited commits/tags/version bumps. Use for
  feature work, fixes, chores, and routine changes in this repository.
---

# Contextual Console Workflow

## Purpose

Default workflow for developing Contextual Console without scope creep or unsafe repo practices.

## When to use

- General development tasks in this repository
- Features, bug fixes, refactors, docs, and maintenance
- When no more specific project skill applies

## Workflow

1. **Inspect existing code first** - read related commands, services, models, tests, and config before changing behaviour.
2. **Keep changes small and practical** - solve the stated problem; avoid drive-by edits.
3. **Follow existing Laravel and project patterns** - match naming, structure (`app/Core`, `app/Domains/Housebuilder`), and test style already in the tree.
4. **Avoid broad refactors** unless explicitly requested.

## Repository rules

- **Do not commit** unless the user explicitly asks.
- **Do not tag** unless explicitly asked.
- **Do not bump versions** unless explicitly asked.
- **Do not store live credentials** in code, docs, commits, or chat output. Use env vars and operator channels per `docs/DEPLOYMENT.md`.

## Branch naming

- `feature/...` - new functionality
- `fix/...` - bug fixes
- `chore/...` - maintenance, docs, release housekeeping

## Commit messages (when asked to commit)

- `feat(scope): ...`
- `fix(scope): ...`
- `chore(release): ...`
- `chore(deps): ...`
- `chore(docs): ...`

## When done

Report:

- **Changed files** (paths)
- **Tests run** (command and outcome), or note if PHP was not touched and tests were skipped
- **Concise summary** of what changed and why

For UI-visible string edits, also apply the `no-mojibake-ui-text` skill.
