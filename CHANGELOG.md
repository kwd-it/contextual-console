# Changelog

All notable changes to this project are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [0.1.0] - 2026-04-09

### Added

- Initial Laravel application scaffold (framework, default migrations, tests, Vite frontend stub).
- `ChangeLog` model in `app/Core/Models` for field-level change records (`entity_type`, `entity_id`, `field`, `old_value`, `new_value`, `changed_at`).
- `change_logs` database migration.
- `ChangeDetectionService` in `app/Domains/Housebuilder/Services` with `record()` to persist changes via `ChangeLog`.
- Root project documentation: product-focused `README`, this changelog, and MIT `LICENSE`.
