# Changelog

All notable changes to this project are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [0.8.1] - 2026-05-17

Dashboard and UI refinement: clearer overview sections, development drilldowns, and shared styling tokens. Presentation and navigation only; ingest and detection behaviour unchanged.

### Added

- Added dashboard **Recent Changes** and **Development overview** sections.
- Added **development drilldown** pages showing plots, recent changes and recent issues for a selected development.
- Added **fallback development label** resolution from plot URLs when development fields are missing from snapshot payloads.
- Added dashboard icons, clearer status/severity badges, automatic dark mode and a visible **System / Light / Dark** theme selector.

### Changed

- Refactored dashboard view-data preparation out of the controller.
- Made the dashboard more compact by reducing recent activity/change/issue lists to the latest five items.
- Combined issue, warning and error dashboard summary information into a more compact issues summary card.
- Introduced shared dashboard design tokens to make future UI styling easier.

### Notes

- No ingest, comparison, change detection or issue detection behaviour changed.
- No health scores, risk scores, charts, alerting or workflow features were added.

## [0.8.0] - 2026-05-16

Display support for Housebuilder Pack plot **modified-author** labels supplied by the source endpoint.

### Plot display metadata

- When a Housebuilder Pack plots payload includes **`last_modified_by`**, Console **preserves** it on stored **`DatasetSnapshot`** rows (when present) and shows it on plot entity blocks as **Last modified by: {label}** (for example on **Changes**, **Issues**, source detail, and comparison run detail pages).
- Labels are resolved via **`PlotSnapshotDisplayLookup`** from current or previous snapshot payloads, alongside existing title and development display fields.
- **`last_modified_by` is display-only snapshot metadata**: it is **not** a tracked business field, **does not** create change logs or dataset issues, and changes to **`last_modified_by` alone** do not affect comparison summaries.
- Wording uses **“Last modified by”** (not “Changed by”). Console compares snapshots over time; this label is **not** a full audit trail and **does not** imply the named person made the exact field change shown on the same row.

### Notes

- Requires the upstream Housebuilder Pack / ContextualWP endpoint to return **`last_modified_by`** (a safe display label only). No new Console authentication, roles, staff monitoring, or WordPress-side tracking is introduced in this release.

## [0.7.0] - 2026-05-14

Incremental improvements to the internal monitoring UI: a first cross-source dashboard summary, broader navigation, and light reporting pages for daily monitoring support.

### Dashboard and navigation

- Added a **dashboard** summary page (`/dashboard`) with counts for sources, recent failures, issues, and plot data changes over a rolling window.
- Added **initial drilldown links** from the dashboard into filtered **Issues**, **Changes**, and **Sources** views (query parameters only; no saved views).
- Site **root (`/`)** and **post-login** redirect now go to the dashboard instead of the sources list.
- Polished shared dashboard navigation and wording.

### Source and run inspection

- **Human-readable plot labels** (title and development where present in snapshots) on source dashboards and comparison run detail tables, alongside stable technical plot ids.
- Comparison **run detail** pages (`/sources/{source}/runs/{run}`) remain accurate for **older runs**, not only the latest. Useful when reviewing historic issues and change rows for a given day’s comparison.

### Cross-source pages

- Added **Issues** (`/issues`) and **Changes** (`/changes`) listings across all monitored sources.
- Added **basic filters** on those pages (for example date range and severity on issues).

### Operations note

- Routine deployments can include **additional admin users** for internal testers using the existing bootstrap command; credentials stay in environment and operator channels only. Never in the repository.

## [0.6.1] - 2026-05-11

### Fixed

- Added the S3-compatible filesystem adapter required for DigitalOcean Spaces/S3 backup uploads.

## [0.6.0] - 2026-05-11

### Added

- **`contextual-console:backup-database`**: daily SQLite database backups for production-style file databases (not `:memory:`).
  - Uses SQLite **`VACUUM INTO`** for a consistent snapshot, then compresses the result to **`.sqlite.gz`**.
  - Uploads the archive to a configured **S3-compatible** Laravel filesystem disk (`CONTEXTUAL_CONSOLE_BACKUP_DISK`, plus path/retention env documented in `docs/DEPLOYMENT.md`).
  - Registers a **daily scheduler entry at 06:45** (after the existing 06:00 source run and 06:30 email summary), using the same scheduler timezone as other scheduled commands.

## [0.5.0] - 2026-05-10

Incremental refinements on the v0.4.0 monitoring baseline: clearer navigation, predictable scheduler timezones, and richer passive plot change visibility.

### Dashboard and navigation

- Redirect the site root (`/`) to the sources dashboard (`/sources`).
- Polish source list and source detail dashboard presentation.

### Scheduling

- Run the Laravel scheduler in an explicit timezone (default **Europe/London**), while keeping stored application timestamps in **UTC**.

### Change tracking

- Log matched-plot changes for **title**, **bedrooms**, **development**, **house type** (`house_type`), and **url** in addition to **price** and **status**; dataset issues for matched plots still derive only from presence, price, and status change logs (other fields are recorded without raising plot-level issues).

## [0.4.0] - 2026-05-09

This is the **first deployment-ready monitoring release**.

### Monitoring runs

- Added scheduled monitored source runs.
- Added scheduled source failure recording.
- Added daily monitoring summary command.

### Dashboard visibility

- Linked change logs to comparison runs.
- Added latest run changes on the source detail dashboard.
- Improved failed source run dashboard coverage.

### Issue detection

- Added change-log-driven issues for notable plot changes.

### Email summary

- Added scheduled emailed daily summary.

### Deployment readiness

- Documented production deployment env and scheduler setup.
- Added and documented a production smoke-test command.

## [0.3.0] - 2026-04-30

### Added

- Optional paginated HTTP fetching for monitored Housebuilder plot sources (`HttpJsonSourceFetcher`):
  - When `http_pagination_mode` is `null`, behaviour is unchanged (single request; treated as full dataset).
  - When `http_pagination_mode=page_per_page`, the fetcher requests page 1..N using configured query params, combines all returned items into one array, then passes the combined dataset into the existing normalisation/snapshot/comparison/issue detection flow.
  - Stop conditions: a page returns zero items, a page returns fewer items than `http_per_page`, or `http_max_pages` is reached.

### Database

- Added optional pagination fields on `monitored_sources`:
  - `http_pagination_mode` (supports `page_per_page`)
  - `http_page_param`
  - `http_per_page_param`
  - `http_per_page`
  - `http_max_pages`

### Documentation

- README and deployment docs: documented paginated HTTP plot source fetching and new monitored source pagination fields, with a Wyatt Homes example configuration.

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
