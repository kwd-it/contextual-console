# Changelog

All notable changes to this project are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

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
