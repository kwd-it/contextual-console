# Contextual Console

**Contextual Console** is an early-stage Laravel application for recording and reviewing changes in structured datasets. It aims to help teams answer: what changed, what looks inconsistent, and what might need investigation.

The first vertical is **housebuilders**; domain-specific logic lives alongside shared platform code.

## Product direction

The long-term focus is operational visibility across many properties: change history and consistency signals—not a single-site CMS replacement.

## Current status

**v0.1.3** — dataset-level plot comparison and run flow:

- **Change logging contract**: stable domain-style change logging via `ChangeDetectionService::recordDomainField()` with `entity_type=plot` and `entity_id` set to the canonical plot dataset `id`.
- **Presence changes**: added/removed plots are logged as `ChangeLog` rows with `field=presence`.
- **Matched plot changes**: matched plots are no longer price-only; a small explicit whitelist of comparable fields is logged (currently `price` and `status`), and each changed field is logged separately.
- **Run flow persisted**: a per-source snapshot + comparison run flow exists via `MonitoredSource`, `DatasetSnapshot`, and `DatasetComparisonRun`, with persisted run summaries.
- **Manual ingest**: an internal/dev artisan command can run a monitored source from a supplied JSON payload file (uses the same run flow).

## Implemented foundation

| Area | What exists today |
|------|-------------------|
| **Model** | `App\Core\Models\ChangeLog` — stores entity type/id, field name, old/new values, and `changed_at`. |
| **Database** | `change_logs` table (see `database/migrations/2026_04_09_052942_create_change_logs_table.php`). |
| **Models (run flow)** | `App\Core\Models\MonitoredSource`, `DatasetSnapshot`, `DatasetComparisonRun` — persisted per-source snapshots and comparison runs. |
| **Services** | `ChangeDetectionService` (`record`, `recordDomainField`, `recordPlotPrice`), `PlotDatasetComparisonService`, `PlotChangeDetector` (whitelisted fields), `PlotDatasetPresenceChangeLogger`, `PlotDatasetRunService` (snapshot + compare + persist summary). |
| **Command (internal)** | `php artisan contextual-console:run-plot-source {sourceKey} --file=/path/to/payload.json` — run a monitored source from a JSON payload file. |

Everything else is default Laravel scaffolding (auth migrations, queue/cache tables, welcome UI, tests).

## Architecture approach

- **`app/Core`** — reusable platform concepts (e.g. shared models like `ChangeLog`).
- **`app/Domains/Housebuilder`** — housebuilder-specific services and future domain code.

This split is intentional so additional verticals can follow the same pattern later.

## Run flow (high level)

For Housebuilder plot datasets, the current flow is:

- **Monitored source** (`MonitoredSource`): identifies a dataset feed/source by a stable `key` (e.g. `hb:foo`).
- **Snapshot** (`DatasetSnapshot`): a persisted capture of the source payload (array of plots keyed by plot `id` for comparison purposes).
- **Comparison run** (`DatasetComparisonRun`):
  - **Baseline**: the first snapshot for a given source creates a run with `status=baseline` (no comparison summary).
  - **Completed**: subsequent snapshots compare the current payload to the immediately previous snapshot for the same source, write change logs, and persist a summary (`added`, `removed`, `changed`, `unchanged`, plus `added_ids`/`removed_ids`).
- **Change logs** (`ChangeLog`): field-level records for matched plot changes (whitelisted fields) and dataset presence changes (`field=presence`).

## Near-term roadmap

Planned next steps (not yet implemented):

- Broader use of change recording from real domain models and workflows.
- Alerting and severity or classification of issues.
- Checks for completeness and consistency of structured data.
- Monitoring for sync/feed health.
- A focused dashboard or reporting layer.

Priorities will shift with the first production integrations.

## Requirements

- PHP **8.3+**
- [Composer](https://getcomposer.org/)
- Node.js and npm (for Vite/asset build, if you use the default frontend tooling)
- Laravel **13** (pulled in via Composer)

Default local database in `.env.example` is **SQLite**.

## Local development

First-time bootstrap in one shot (see `composer.json` `setup` script): `composer run setup` installs dependencies, creates `.env` if missing, generates the app key, runs migrations, and runs `npm install` plus `npm run build`. With SQLite, create an empty `database/database.sqlite` file first if migrate fails because the file is missing.

Manual steps:

1. Clone the repository and enter the project directory.

2. Install PHP dependencies:

   ```bash
   composer install
   ```

3. Environment file and app key:

   ```bash
   cp .env.example .env
   php artisan key:generate
   ```

4. For SQLite, ensure the database file exists (if it does not already):

   ```bash
   touch database/database.sqlite
   ```

   On Windows PowerShell you can use `New-Item -ItemType File -Path database/database.sqlite -Force` instead of `touch`.

5. Run migrations:

   ```bash
   php artisan migrate
   ```

6. Optional: install JS dependencies and run the dev server (or use `composer run dev` for the combined Laravel + queue + Vite workflow defined in `composer.json`):

   ```bash
   npm install
   npm run dev
   ```

   ```bash
   php artisan serve
   ```

7. Run tests:

   ```bash
   composer test
   ```

## Manual ingest (internal/dev)

Run a Housebuilder plot monitored source from a supplied JSON payload file:

```bash
php artisan contextual-console:run-plot-source hb:foo --file=storage/app/test-payload-1.json
```

Notes:

- The JSON file must be a **top-level array** of plot objects and each plot must include an `id`.
- The monitored source must already exist in the database (`monitored_sources.key=hb:foo`); this command does not create it.

## HTTP ingest (internal/dev)

To ingest real production data from a remote JSON endpoint (read-only), configure the monitored source with HTTP fields:

- `endpoint_url`: full URL to a ContextualWP-style JSON endpoint (or any endpoint that returns a top-level JSON array)
- `auth_header_name`: header name to send a token in (e.g. `Authorization` or `X-ContextualWP-Token`) (optional)
- `auth_token_env_key`: name of the environment variable containing the token (optional)

Example (no real credentials):

1. Create or update a monitored source row (example):

   - `key`: `hb:example`
   - `name`: `Housebuilder Example`
   - `endpoint_url`: `https://example.com/wp-json/contextualwp/v1/plots`
   - `auth_header_name`: `X-ContextualWP-Token`
   - `auth_token_env_key`: `CONTEXTUALWP_TOKEN_HB_EXAMPLE`

2. Add the token to your local `.env` (do **not** commit it):

   ```env
   CONTEXTUALWP_TOKEN_HB_EXAMPLE=your-token-here
   ```

3. Run the ingest:

```bash
php artisan contextual-console:run-http-plot-source hb:example
```

## Source status (internal/dev)

- **CLI**: `php artisan contextual-console:source-status`
- **Page**: visit `/sources` for a simple read-only status overview of monitored sources.
- **Source detail**: visit `/sources/{source}` for a read-only view of recent runs and latest dataset issues for a monitored source.

## License

This project is released under the [MIT License](LICENSE).
