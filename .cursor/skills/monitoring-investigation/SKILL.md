---
name: monitoring-investigation
description: >-
  Investigates Console monitoring issues, source run failures, plot data
  discrepancies, Wyatt/Housebuilder Pack data, and unexpected dashboard or
  email results. Use when debugging ingest, comparison, issues, or summaries
  before changing application code.
---

# Monitoring Investigation

## Purpose

Structured debugging for monitoring and housebuilder data problems without assuming Console is wrong.

## When to use

- Console monitoring issues or unexpected issue counts
- Failed or partial source runs
- Plot field discrepancies between Console and upstream data
- Wyatt Homes or Housebuilder Pack endpoint behaviour
- Unexpected dashboard, source detail, or daily summary email content

## Investigation order

Follow this sequence before proposing code changes:

1. **Console issue / run details** - issue type, context JSON, comparison run status, snapshots, change logs
2. **Housebuilder Pack endpoint output** - raw HTTP/JSON from the configured monitored source endpoint
3. **WordPress / admin data** - what operators see in WP admin for the same plots
4. **Raw post meta** - underlying stored meta when admin UI and endpoint disagree

## Failed source runs

For failed scheduled or manual runs:

- Inspect the stored **`source_run_failed`** issue and its **context** first
- Confirm whether a later run recovered (superseded issues may be resolved)
- Only change ingest/retry/error handling after the failure reason is understood

## Plot field discrepancies

When a plot field differs between Console and expectations:

- Compare **endpoint JSON** to **admin-visible values**
- If those disagree, check **raw post meta**
- Do not add Console code to paper over unsaved or default admin UI values unless that compensation is an explicit product requirement

## Credentials

- Keep auth headers, app passwords, API keys, SMTP secrets, Spaces keys, and server passwords **out of** prompts, docs, logs, and commits
- Refer to env var **names** only (see `docs/DEPLOYMENT.md`)

## Code changes

- Prefer fixing upstream data or configuration when evidence points there
- If Console behaviour is wrong, keep fixes minimal and covered by existing test patterns where practical
- Use `contextual-console-workflow` for implementation discipline
