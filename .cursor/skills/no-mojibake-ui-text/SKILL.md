---
name: no-mojibake-ui-text
description: >-
  Blocking check for smart punctuation, Unicode arrows, em dashes, and
  mojibake in user-visible strings. Use when editing UI text, Blade templates,
  tests, command output, emails, Markdown, seed data, or any user-visible
  strings. Do not report task completion until the grep check is clean.
---

# No Mojibake UI Text

## Purpose

Keep user-visible text in plain ASCII punctuation so copy-paste, editors, and encodings do not introduce mojibake or smart punctuation into the codebase.

## When to use

- UI labels, help text, and page copy
- Blade templates and Laravel views
- Tests that assert on displayed or emailed text
- Artisan command output
- Email bodies and subjects
- Markdown and documentation meant for the repo
- Seed data and fixtures with human-readable strings

## Rules

Prefer plain ASCII punctuation:

- Use `-` instead of em dash
- Use `->` instead of Unicode arrows
- Use straight quotes (`"` and `'`) instead of smart quotes

Avoid mojibake strings (often from UTF-8 misread as Latin-1):

- `ÔÇö`
- `ÔåÆ`
- `├ö`
- `┬À`

Also avoid inserting these directly:

- Em dash `—`
- Unicode arrow `→`
- Smart quotes `“` `”` `‘` `’`

## Blocking verification (required before completion)

This skill is a **blocking** step, not advisory guidance. Cursor must actively run the grep check on changed user-visible files before reporting a task complete. **Do not say the task is done** if mojibake, smart punctuation, Unicode arrows, em dashes, or unsafe separators remain.

**Touched files must be clean** in Blade, Markdown, emails, tests, command output, and UI copy -- even when the bad text was already present before the current edit.

### Check command

Use the same pattern for both cases:

```bash
grep -E "ÔÇö|ÔåÆ|├ö|┬À|—|→|“|”|‘|’"
```

- **Staged changes:** pipe `git diff --cached` into that grep before finishing.
- **Unstaged or mixed changes:** pipe `git diff` (or grep specific touched paths) when nothing is staged.

**Any grep match is a blocking issue.** Fix the source strings (do not leave bad characters in place), then re-run the check until it exits with no output. Only then report completion.
