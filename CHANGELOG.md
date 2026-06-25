# Changelog

All notable changes to this project are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [1.2.1] - 2026-06-25

Patch release that corrects how **development intro media** asset completeness is reported. Plot-level floor plan checks, ingest, comparison, change detection, and all other plot dataset issue rules are unchanged.

### Fixed

- **Stops development intro media being raised as duplicate plot-level warnings.** Intro media (`intro_media_completeness_status` / `intro_media_type`) describes the linked **development**, not the individual plot. Because the source repeats that development-level information on every plot row, the previous behaviour produced one **missing linked development intro media** warning per plot assigned to the same development (and surfaced a false positive where the intro video actually existed). Console no longer creates a plot-level issue for missing intro media.

### Unchanged

- **Missing floor plan warnings remain plot-level.** A required floor plan still warns when `floor_plan_required === true` and `floor_plan_completeness_status === "missing"`.
- **Intro media fields are still preserved** on stored **`DatasetSnapshot`** plot payloads (and still normalised to lowercase during HTTP ingest); only the plot-level issue is removed.

### Notes

- **Development-level completeness is deferred, not lost.** Detecting and reporting development-level completeness (such as missing intro media) once per development needs a dedicated **development ingest/snapshot/issue path**. Until that exists, intro media is intentionally ignored for issue detection and is documented as such in `PlotDatasetIssueDetector`.

## [1.2.0] - 2026-06-23

Adds **Housebuilder Pack asset completeness** support on top of the **v1.1.x** admin and monitoring baseline. Admin user management, profile account controls, ingest/comparison flow, and existing plot field change detection are unchanged.

### Asset completeness (Housebuilder Pack plot payloads)

- **Preserves** new asset completeness fields on stored **`DatasetSnapshot`** plot payloads when the upstream HTTP source returns them: `has_floor_plan`, `floor_plan_required`, `floor_plan_completeness_status`, `has_intro_video`, `has_intro_image`, `intro_media_type`, and `intro_media_completeness_status`.
- **Normalises** asset completeness string fields during HTTP ingest (`floor_plan_completeness_status`, `intro_media_completeness_status`, `intro_media_type`) to lowercase for consistent issue checks.
- **Warns** when a **required floor plan is missing**: `floor_plan_required === true` and `floor_plan_completeness_status === "missing"`.
- **Warns** when **linked development intro media is missing**: `intro_media_completeness_status === "missing"`. Intro media fields are exposed on each plot row but the warning wording reflects that the content is development-related.
- **Does not warn** just because `has_floor_plan`, `has_intro_video`, or `has_intro_image` is `false`.
- **Treats `unknown` as non-actionable**: completeness statuses of `unknown` (or absent/other values) are stored when present but do not create issues.
- **Asset completeness fields are not plot change-detection fields**: they do not create change logs or affect comparison summaries when they change alone.
- **`last_modified_by` remains display-only** snapshot metadata (unchanged from earlier releases).

### Notes

- **Upstream dependency**: live HTTP payloads need **ContextualWP Housebuilder Pack v0.4.6 or later** for these asset completeness fields to appear. Console cannot invent them from older endpoints.
- **Actionable upstream statuses only**: Console raises missing-asset warnings only when the source payload sends completeness statuses of **`missing`**. If upstream sends **`unknown`**, Console deliberately does not warn. Whether a given site produces live **`missing`** warnings still depends on Housebuilder Pack mapping/rules and real source data; deploying **v1.2.0** alone does not guarantee new warnings on any particular monitored source (for example Wyatt/Wired) until upstream data is actionable.
- Admin user management, profile account settings, and existing plot validation rules (`id`, `status`, status-aware `price`, and so on) are unchanged.

## [1.1.1] - 2026-06-22

Patch release completing the admin user management UI introduced in **v1.1.0**. Ingest, comparison, change detection, and plot dataset issue detection rules are unchanged.

### Fixed

- Completed the admin user management UI on `/admin/users`, including user creation, admin password reset for other users, and safe deletion of eligible users. The UI now uses an initial password flow because invite emails and self-service password reset are not yet implemented. Existing self-deletion, self-demotion, last-admin deletion, and last-admin demotion protections remain in place.

## [1.1.0] - 2026-06-22

Adds **admin user management** and **profile account controls** on top of the **v1.0.0** monitoring baseline. Ingest, comparison, change detection, and plot dataset issue detection rules are unchanged.

### Admin user management

- **Admin and operator roles**: admins can access account management; operators use the monitoring UI only.
- **Admin Users page** at `/admin/users`: lists name, login email, role, daily summary subscription, and **last sign-in** in the display timezone.
- **Create, edit, and delete users**: admins can add accounts, update name and role, reset another user's password, and delete users safely from the Users page.
- **Login email immutability**: user email is set at creation and cannot be changed from Profile or the Users page.
- **Last sign-in tracking**: successful login records `last_signed_in_at` and shows it on the Users page.
- **Admin safety protections**: blocks self-deletion, self-demotion, deletion of the last admin, and demotion of the last admin; an admin's own role is read-only on the Users page.
- **Daily summary subscriptions**: admins can view and change another user's `daily_summary_enabled` on the Users page.
- **CLI provisioning** still available: `contextual-console:create-admin-user` and `contextual-console:promote-user-to-admin`.

### Profile account settings

- **Profile page** (`/profile`): signed-in users can update their **name** and **password** (current password required).
- **Login email** remains **read-only** on Profile (managed at account creation; admins see it on the Users page).
- **Daily summary subscription** can still be managed on Profile; admins can also view and change subscriptions for other users on the Users page.

### Notes

- No invite emails, forgot-password flow, or per-source notification preferences in this release.
- Role model is **admin** and **operator** only (not full RBAC).

## [1.0.0] - 2026-05-26

First **production-ready** release candidate after the **v0.10.x** daily monitoring stabilisation work. Operator workflows for issue investigation, source and run diagnostics, and daily summary notifications are suitable for production use. Ingest, comparison, change detection, and plot dataset issue detection rules are unchanged.

### Issue review and diagnostics

- **Issue detail pages** at `/issues/{issue}`: summary, context, review status updates, and **suggested issue checks** to make issue explanations more useful for operators.
- **Issue links** from the dashboard, sources list, source detail, comparison run detail, development drilldown, and Issues list through to issue detail.
- **Suggested issue checks** on issue detail (operator hints; for example check failed run details first for `source_run_failed` issues).

### Source and run operations

- **Manual Run now** on source detail when `endpoint_url` is set (browser-triggered HTTP ingest without SSH).
- **Failed-run diagnostics** on source detail and comparison run detail when a run failed (operator-oriented context for investigation).
- **Source health overview** on the sources list (health badges and status-oriented summary per source).

### Daily summary notifications

- **Daily summary emails** include links back to relevant Console pages (sources, runs, issues) where applicable.
- **Subscription warning** when no users are subscribed to the daily summary (visible on Profile and related operator views).
- **Send test email** on Profile: sends the current daily summary report to the signed-in user's login email only (not fallback or other subscribers).

### Deployment, operations and documentation

- **Production deploy guidance**: do **not** run `php artisan config:cache` while monitored sources resolve HTTP auth header **values** from `.env` at runtime via `auth_token_env_key` (see `docs/DEPLOYMENT.md` and `docs/OPERATIONS.md`).
- **Production operations guide** added at `docs/OPERATIONS.md` (deploy checklist, HTTP sources, issue investigation, daily summary setup).
- **Mojibake skill** (`.cursor/skills/no-mojibake-ui-text`) strengthened with blocking grep verification for user-visible strings.

### Fixes

- **Source HTTP auth**: avoid `config:cache` breaking runtime `env()` lookup for auth tokens (deploy and smoke-test guidance updated accordingly).
- **UI timestamps**: displayed in the configured schedule timezone (`APP_SCHEDULE_TIMEZONE`, default **Europe/London**) instead of raw UTC; storage remains UTC.

### Notes

- Plot-level dataset issue detection rules are unchanged.
- For day-to-day production operations, start with `docs/OPERATIONS.md`; first-time VPS setup remains in `docs/DEPLOYMENT.md`.

## [0.10.0] - 2026-05-25

Daily summary email improvements, per-user summary preferences, clearer dashboard handling of recovered source failures, issue change details in the UI and email, local dev email preview, and updated project handover. Monitoring presentation and email delivery improved; plot dataset issue detection rules are unchanged.

### Added

- **HTML daily summary email** with a plain-text fallback body built from the same monitoring report.
- **Local daily summary email preview** at `/dev/daily-summary-email-preview` (authenticated; registered only outside production).
- **Profile page** (`/profile`) for the signed-in user: read-only name and login email display, plus a **Daily summary email** opt-in checkbox (`daily_summary_enabled`).
- **Per-user daily summary delivery**: subscribed users receive the scheduled summary at their **login email** (`users.email`).
- **Cursor project skills** for general workflow, no-mojibake UI text, and monitoring investigations (see `.cursor/skills-catalogue.md`).
- **Project handover** (`docs/contextual-console-handover.md`) as the version-controlled operator handover; superseded generic workflow docs removed from the repo.

### Changed

- **Dashboard source failures**: distinguishes **current** failed runs from **recovered** failures (a later successful run for the same source). Recovered failures are no longer counted or listed as active dashboard problems.
- **Recovered source run failures**: when a later run succeeds, older open `source_run_failed` issues for that source are resolved automatically and excluded from active dashboard and daily summary error counts.
- **Issue rows**: show **old -> new** change details on Issues and related views where issue context includes those values.
- **Daily summary content**: includes useful issue detail lines, change values, and recovered-failure context (not only counts).
- **Daily summary HTML layout**: clearer labelled sections per source for changes and active issues.
- **Daily summary recipients**: when one or more users are subscribed, email goes to each subscriber only; **`CONTEXTUAL_CONSOLE_DAILY_SUMMARY_TO`** is used only when no users are subscribed (see `docs/DEPLOYMENT.md` and `docs/contextual-console-handover.md`).

### Notes

- Plot-level dataset issue detection rules are unchanged.
- After deploying, operators should opt in via **Profile** (or a controlled DB update) so production does not rely on the env fallback recipient long term.

## [0.9.0] - 2026-05-24

Issue review workflow, active-issue dashboard summaries, bulk issue review, paginated Issues and Changes listings, monitored source display labels, and UI table polish. Review and presentation only; ingest, comparison, change detection, and issue detection rules are unchanged.

### Added

- **Monitored source display labels**: optional `display_name` on `MonitoredSource` rows provides a cleaner UI label (for example **Wyatt Homes** instead of **Wyatt Homes Housebuilder**). When unset, the UI falls back to `name`. Source keys, ingest behaviour, and endpoint configuration are unchanged. Existing environments can set `display_name` via Tinker (see README Wyatt example).

- **Issue review status workflow** on the cross-source **Issues** page: internal users can mark dataset issues as open, acknowledged, ignored, or resolved, filter by review status, and update status from a simple server-rendered form. Newly detected issues default to open; issue detection, severity, and `issue_type` behaviour are unchanged. Repeated detections still create separate issue rows (no deduplication or auto-resolve).

- **Issues bulk review** on `/issues`: when at least one filter is applied, update all matching issues (across the full filtered result set, not only the current page) to a chosen review status (open, acknowledged, ignored, or resolved) in one action, with an explicit target status.

### Changed

- **Dashboard monitoring** now focuses on **active** issues (open and acknowledged): the summary card counts all active issues (not limited to seven days), shows info, warning, and error severity breakdowns, drilldown links go to `issue_status=active` (hidden when the count is zero), and the recent issues list excludes ignored and resolved issues. Removed the **Sources with runs** summary tile to keep the dashboard grid compact.
- **Issues page pagination** on `/issues`: browse matching issues with server-rendered compact numbered page links plus first, previous, next, and last controls (100 per page, newest first; ellipses when page ranges are skipped). Filter query parameters are preserved across pages. The issues list shows total matches and the visible range.
- **Changes page pagination** on `/changes`: browse matching plot data changes with the same compact numbered pagination as Issues (100 per page, newest first; filter query parameters preserved across pages). The change list shows total matches and the visible range.
- **Changes page table** on `/changes`: the change list leads with source, entity, field, and old/new values; source keys are hidden from the default table (still shown on source detail pages). Changed-at timestamps and run links remain at the end for audit context.
- **Sources page table** on `/sources`: the list uses display labels only (no source key column), quieter snapshot context under each source (`Current snapshot` / `Previous snapshot`), and a linked **Run** id with status badge in the latest-run column. Source keys and full run metadata remain on source and run detail pages.
- **Issues page table**: simplified for day-to-day review (status updates and filtering).

### Notes

- No ingest, comparison, change detection, or issue detection behaviour changed.

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
- Wording uses **"Last modified by"** (not "Changed by"). Console compares snapshots over time; this label is **not** a full audit trail and **does not** imply the named person made the exact field change shown on the same row.

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
- Comparison **run detail** pages (`/sources/{source}/runs/{run}`) remain accurate for **older runs**, not only the latest. Useful when reviewing historic issues and change rows for a given day's comparison.

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

- Housebuilder plot dataset issue detection (`PlotDatasetIssueDetector`): accept `coming_soon` as a valid status; warn on missing `price` only when `status` is `available`; do not warn on missing `price` for `coming_soon`, `reserved`, or `sold`. Missing or invalid `status` still warns. When `price` is present and non-empty, it must still be numeric and >= 0.

### Documentation

- README: current status to **v0.2.2**; clarified dataset issue rules and **Expected plot records**; noted that a ContextualWP Housebuilder Pack plots endpoint returning a top-level JSON array aligned with Console plot fields can use HTTP ingest without `http_json_items_key` or `http_plot_payload_adapter`.

### Notes

- Very large Housebuilder Pack responses can exceed the Console HTTP client's default timeout; addressing `limit`/pagination and timeouts is expected to be handled in the Pack (or follow-up Console work), not in this patch release.

## [0.2.1] - 2026-04-27

### Added

- ContextualWP HTTP source compatibility for monitored plot sources (read-only ingest unchanged in spirit).
- Support for wrapped HTTP JSON list payloads via optional `http_json_items_key` on `MonitoredSource`.
- Optional `http_plot_payload_adapter` with **`contextualwp_list_contexts`**, including default unwrapping of ContextualWP **`contexts`** payloads when that adapter is set and no items key is configured.
- Mapping of common ContextualWP / WordPress / ACF-style fields onto Console plot fields (especially `id`, `price`, and `status`) for ingest and comparison, including safe handling of ACF select-style `value` / `label` shapes for status.
- Support for **full HTTP auth header values** from environment variables for HTTP sources (for example WordPress Application Password **Basic** auth), with header names stored separately in the database - **never** commit live credentials; keep secrets in `.env` only.

### Documentation

- Documented local testing of a live ContextualWP source from a local Contextual Console install (`README.md`, `docs/DEPLOYMENT.md`).
- Clarified HTTP ingest auth: the env var holds the **header value only** (not an `Authorization:` prefix line); `auth_header_name` stores the header name separately.

### Notes

- **ContextualWP core** remains generic. Housebuilder-specific plot dataset richness (for example full `price` / `status` in payloads) belongs in the **ContextualWP Housebuilder Pack**; today's `list_contexts` responses may only expose summary fields (such as `id`, `title`, `description`, `last_updated`). Missing `price` / `status` **warnings** from the Console issue detector are therefore expected until a richer Housebuilder Pack endpoint is available.

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
- A persisted per-source run flow now exists: `MonitoredSource` -> `DatasetSnapshot` -> `DatasetComparisonRun`, with stored comparison summaries on completed runs.
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
