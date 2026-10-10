# Repository instructions

Contextual Console is a Laravel application that monitors ContextualWP sites: it pulls plot datasets from the Housebuilder Pack plots feed over HTTP, compares runs, raises issues and sends a daily summary email. It makes no AI provider calls. Ecosystem-wide governance lives in [kwd-it/contextual-stewardship](https://github.com/kwd-it/contextual-stewardship). This file covers only what is local to this repository.

## Architecture and boundaries

- `app/Core` holds shared models and services, `app/Domains/Housebuilder` the plot dataset ingest, comparison and issue detection, `app/Support` view data and summary builders, `app/Console/Commands` the artisan commands (ingest, daily summary, smoke test, backups).
- Ingest is pull-only. Source authentication is read from environment variables at runtime, so production must never run `config:cache`; see `docs/OPERATIONS.md`.
- The Console expects the Housebuilder Pack plots feed as documented in that repository (0.4.6 or later for asset-completeness fields); missing fields are treated as `unknown`. Do not change how feed fields are interpreted without checking the Pack.
- The Cursor skills in `.cursor/skills/` are this repository's conventions and apply to Claude sessions as well: inspect first, small diffs, Laravel patterns, plain ASCII punctuation in user-visible text (`no-mojibake-ui-text`), and the investigation order in `monitoring-investigation` before changing monitoring code.

## Platform

- `composer.json` declares PHP ^8.3 and Laravel ^13; SQLite is the default database. The committed `composer.lock` resolves Symfony packages that require PHP 8.4 or later, so tests and local runs need a PHP 8.4 binary. Treat this gap as a documentation inconsistency to raise, not something to fix silently.

## Conventions

- Branch prefixes `feature/`, `fix/`, `chore/`; Conventional Commits with scopes (`feat(scope):`, `fix(scope):`, `chore(release):`, `chore(deps):`, `chore(docs):`).
- The version lives only in `CHANGELOG.md`; new entries go under `[Unreleased]`. `docs/contextual-console-handover.md` is updated before a release when the project state has changed.

## Validation

- `composer test` runs `config:clear` then the Pest suite with SQLite in memory. It must run under a PHP 8.4 binary (see Platform); with `composer` bound to an older PHP, run `php artisan config:clear` and `php artisan test` with that binary directly.
- Run the grep check from `.cursor/skills/no-mojibake-ui-text/SKILL.md` on touched user-visible files before finishing.
- Never read, print or copy `.env`; `.env.example` documents the variable names.

## Git and release boundaries

- Default branch `master`; no staging branch. This repository is public.
- Landing rule for Claude and Codex sessions: work on a dedicated task branch; implement and validate; run the final Codex review of the actual diff; resolve material findings; push the branch and open a pull request against `master`. This rule is the explicit authorisation to commit that the Cursor workflow skill requires; it does not extend to merging.
- Do not merge the pull request. Do not bump the version, tag or publish a release unless separately authorised. Releases are separate `chore(release):` commits and tags by the product owner.
- Deployment and the production environment belong to the product owner. Never deploy, run commands against a production or staging instance, or record hostnames, credentials or client data in this repository.
