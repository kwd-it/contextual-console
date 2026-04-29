# Changelog

All notable changes to this project are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [0.2.2] - 2026-04-29

### Changed

- Housebuilder plot dataset issue detection (`PlotDatasetIssueDetector`): accept `coming_soon` as a valid status; warn on missing `price` only when `status` is `available`; do not warn on missing `price` for `coming_soon`, `reserved`, or `sold`. Missing or invalid `status` still warns. When `price` is present and non-empty, it must still be numeric and ≥ 0.

### Documentation

- README: current status to **v0.2.2**; clarified dataset issue rules and **Expected plot records**; noted that a ContextualWP Housebuilder Pack plots endpoint returning a top-level JSON array aligned with Console plot fields can use HTTP ingest without `http_json_items_key` or `http_plot_payload_adapter`.

### Notes

- Very large Housebuilder Pack responses can exceed the Console HTTP client’s default timeout; addressing `limit`/pagination and timeouts is expected to be handled in the Pack (or follow-up Console work), not in this patch release.

## [0.2.1] - 2026-04-27

### Added

- ContextualWP HTTP source compatibility for monitored plot sources (read-only ingest unchanged in spirit).
- Support for wrapped HTTP JSON list payloads via optional `http_json_items_key` on `MonitoredSource`.
- Optional `http_plot_payload_adapter` with **`contextualwp_list_contexts`**, including default unwrapping of ContextualWP **`contexts`** payloads when that adapter is set and no items key is configured.
- Mapping of common ContextualWP / WordPress / ACF-style fields onto Console plot fields (especially `id`, `price`, and `status`) for ingest and comparison, including safe handling of ACF select-style `value` / `label` shapes for status.
- Support for **full HTTP auth header values** from environment variables for HTTP sources (for example WordPress Application Password **Basic** auth), with header names stored separately in the database—**never** commit live credentials; keep secrets in `.env` only.

### Documentation

- Documented local testing of a live ContextualWP source from a local Contextual Console install (`README.md`, `docs/DEPLOYMENT.md`).
- Clarified HTTP ingest auth: the env var holds the **header value only** (not an `Authorization:` prefix line); `auth_header_name` stores the header name separately.

### Notes

- **ContextualWP core** remains generic. Housebuilder-specific plot dataset richness (for example full `price` / `status` in payloads) belongs in the **ContextualWP Housebuilder Pack**; today’s `list_contexts` responses may only expose summary fields (such as `id`, `title`, `description`, `last_updated`). Missing `price` / `status` **warnings** from the Console issue detector are therefore expected until a richer Housebuilder Pack endpoint is available.

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
