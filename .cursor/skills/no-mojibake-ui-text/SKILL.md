---
name: no-mojibake-ui-text
description: >-
  Prevents smart punctuation, Unicode arrows, em dashes, and mojibake in
  user-visible strings. Use when editing UI text, Blade templates, tests,
  command output, emails, Markdown, seed data, or any user-visible strings.
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

## Before finishing

1. Check changed or staged files for mojibake or smart punctuation.
2. Replace any hits with safe ASCII before reporting completion.

Suggested check (staged changes):

```bash
git diff --cached | grep -E "ÔÇö|ÔåÆ|├ö|┬À|—|→|“|”|‘|’"
```

For unstaged or all working-tree changes, run the same pattern against `git diff` or specific file paths.

If grep finds anything, fix the source strings (do not leave the bad characters in place) and re-run the check until clean.
