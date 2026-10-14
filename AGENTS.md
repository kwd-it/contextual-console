# Codex review guidance

Repository-specific priorities for reviewing changes to Contextual Console. Universal review behaviour is defined outside this repository; ecosystem governance is in [kwd-it/contextual-stewardship](https://github.com/kwd-it/contextual-stewardship).

## Review priorities

1. **Credentials and configuration.** Source authentication is read from environment variables at runtime; flag any change that moves secrets into config, the database, fixtures, docs or logs, or that would require `config:cache` in production. Flag any hostname, credential or client data in the diff.
2. **Ingest contract.** `app/Domains/Housebuilder` must keep treating missing Housebuilder Pack fields as `unknown` and must not assume fields the Pack does not document. Flag changes to field interpretation, pagination handling or adapters that are not paired with a Pack change or an approved proposal.
3. **Issue semantics.** Plot-level versus development-level signals (for example floor plans are plot-level, intro media is development-level) and issue supersession rules. Flag anything that reintroduces duplicate development-level warnings per plot or changes an issue type's meaning without a changelog entry.
4. **User-visible text.** Blade templates, emails, command output and Markdown use plain ASCII punctuation (`.cursor/skills/no-mojibake-ui-text/SKILL.md`). Flag smart quotes, em dashes, Unicode arrows or mojibake.
5. **Tests and migrations.** Behaviour changes come with Pest coverage in `tests/Feature` or `tests/Unit`; new migrations are additive and the deployment notes name them.

## Normally ignore

`vendor/`, `node_modules/`, `public/build/`, `storage/`, and `composer.lock` or `package-lock.json` beyond their hashes when manifests change.

## Landing

A change is complete when it sits on a task branch with a pull request against `master`. Merging belongs to the product owner, except documentation and test-only maintenance merged by the steward under the stewardship policy D-016 through its `tooling/merge/autonomous-merge.mjs`; flag any pull request presented as eligible for that policy whose diff touches anything outside Markdown, `docs/` and `tests/`, weakens a test, or whose description lacks a `Reviewed head:` line matching the head commit. Version bumps in `CHANGELOG.md`, tags and deployment belong to the product owner; flag any diff that performs them without explicit authorisation in the task.
