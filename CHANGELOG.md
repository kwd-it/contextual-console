# Changelog

All notable changes to this project are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [0.2.0] - 2026-04-25

### Added

- Dataset issue detection for Housebuilder plot payloads (invalid/missing ids, duplicates, missing/invalid `price` and `status`), persisted per run.
- Source run status summary via `contextual-console:source-status` (latest run status, change counts, issue severity counts).
- Read-only browser UI: `/sources` status page and `/sources/{source}` detail page (latest run summary, recent runs, latest issues).
- Read-only HTTP ingest for monitored sources via `contextual-console:run-http-plot-source`, with auth tokens referenced by env var key (not stored in DB).
- Minimal admin authentication (session login) protecting dashboard pages, plus `contextual-console:create-admin-user` to bootstrap the first user.

### Documentation

- Added private VPS deployment guide (`docs/DEPLOYMENT.md`) including first admin user creation and HTTP source smoke test.

## [0.1.3] - 2026-04-23

### Added

- Added/removed plots are now logged as presence changes using the stable domain change log contract (`entity_type=plot`, `entity_id` = canonical dataset plot `id`, `field=presence`).
- A persisted per-source run flow now exists: `MonitoredSource` → `DatasetSnapshot` → `DatasetComparisonRun`, with stored comparison summaries on completed runs.
- Internal/dev artisan command to run a monitored plot source from a supplied JSON payload file: `contextual-console:run-plot-source {sourceKey} --file=/path/to/payload.json`.

### Changed

- Matched plot comparison is no longer price-only: a small explicit whitelist of comparable fields is supported (currently `price` and `status`), and each changed field is logged separately.
- Dataset comparison runs are isolated per monitored source (each source compares only against its own previous snapshot).

### Notes

- First run for a given monitored source is recorded as `baseline` (no previous snapshot, no comparison summary).

## [0.1.2] - 2026-04-22

### Added

- Dataset-level comparison of two plot datasets keyed by payload `id`, summarising added, removed, changed, and unchanged plots; matched pairs reuse `PlotChangeDetector` (price field only).

### Fixed

- Standardised domain-style change logging: `recordDomainField()` as the shared path; plot price updates log with `entity_type` `plot` and `entity_id` set to the canonical dataset plot `id`. `record()` remains for model-style logging.

### Notes

- Added and removed plots are reflected in the comparison summary only; they are not written as individual `change_logs` rows in this release.

## [0.1.1] - 2026-04-13

### Added

- Automatic detection and logging of plot price changes between two datasets.

### Changed

- Development workflow now includes a git diff review step before testing.

### Notes

- Detection currently supports a single plot comparison (price field only).
- No batch comparison, alerts, or additional fields yet.

## [0.1.0] - 2026-04-09

### Added

- Initial Laravel application scaffold (framework, default migrations, tests, Vite frontend stub).
- `ChangeLog` model in `app/Core/Models` for field-level change records (`entity_type`, `entity_id`, `field`, `old_value`, `new_value`, `changed_at`).
- `change_logs` database migration.
- `ChangeDetectionService` in `app/Domains/Housebuilder/Services` with `record()` to persist changes via `ChangeLog`.
- Root project documentation: product-focused `README`, this changelog, and MIT `LICENSE`.
