# Repository instructions

Contextual Console is a Laravel application that monitors ContextualWP sites: it pulls plot datasets from the Housebuilder Pack plots feed over HTTP, compares runs, raises issues and sends a daily summary email. It makes no AI provider calls. Ecosystem-wide governance lives in [kwd-it/contextual-stewardship](https://github.com/kwd-it/contextual-stewardship). This file covers only what is local to this repository.

## Architecture and boundaries

- `app/Core` holds shared models and services, `app/Domains/Housebuilder` the plot dataset ingest, comparison and issue detection, `app/Support` view data and summary builders, `app/Console/Commands` the artisan commands (ingest, daily summary, smoke test, backups).
- Ingest is pull-only. Source authentication is read from environment variables at runtime, so production must never run `config:cache`; see `docs/OPERATIONS.md`.
- The Console expects the Housebuilder Pack plots feed as documented in that repository (0.4.6 or later for asset-completeness fields); missing fields are treated as `unknown`. Do not change how feed fields are interpreted without checking the Pack.
- The Cursor skills in `.cursor/skills/` are this repository's conventions and apply to Claude sessions as well: inspect first, small diffs, Laravel patterns, plain ASCII punctuation in user-visible text (`no-mojibake-ui-text`), and the investigation order in `monitoring-investigation` before changing monitoring code.

## Platform

- `composer.json` declares PHP ^8.3 and Laravel ^13; SQLite is the default database. `config.platform.php` pins Composer resolution to 8.3.0 so `composer update` keeps the lock installable on the declared minimum even when run on a newer PHP; do not remove the pin or raise the minimum without a recorded decision.

## Conventions

- Branch prefixes `feature/`, `fix/`, `chore/`; Conventional Commits with scopes (`feat(scope):`, `fix(scope):`, `chore(release):`, `chore(deps):`, `chore(docs):`).
- The version lives only in `CHANGELOG.md`; new entries go under `[Unreleased]`. `docs/contextual-console-handover.md` is updated before a release when the project state has changed.

## Validation

- `composer test` runs `config:clear` then the Pest suite with SQLite in memory. Run it on the declared minimum (PHP 8.3) and, where a binary is available, on the deployment target (PHP 8.4, see `docs/DEPLOYMENT.md`).
- Run the grep check from `.cursor/skills/no-mojibake-ui-text/SKILL.md` on touched user-visible files before finishing.
- Never read, print or copy `.env`; `.env.example` documents the variable names.

## Git and release boundaries

- Default branch `master`; no staging branch. This repository is public.
- Landing rule for Claude and Codex sessions: work on a dedicated task branch; implement and validate; run the final Codex review of the actual diff; resolve material findings; push the branch and open a pull request against `master` whose description carries `Owner summary:`, `Reviewed head:` and `Merge:` lines as the stewardship development workflow requires. This rule is the explicit authorisation to commit that the Cursor workflow skill requires; it does not extend to merging beyond the policy above.
- Do not merge the pull request, except that a documentation and test-only maintenance pull request meeting every condition of the stewardship autonomous merge policy (D-016, `governance/authority-model.md` in kwd-it/contextual-stewardship) is merged by the steward through that repository's `tooling/merge/autonomous-merge.mjs`, which refuses anything outside Markdown, `docs/` and `tests/`. Merging this default branch deploys nothing: the product owner deploys by hand. Do not bump the version, tag or publish a release unless separately authorised. Releases are separate `chore(release):` commits and tags by the product owner.
- Deployment and the production environment belong to the product owner. Never deploy, run commands against a production or staging instance, or record hostnames, credentials or client data in this repository.
