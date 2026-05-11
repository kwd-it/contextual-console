# Contextual Console

**Contextual Console** is an early-stage Laravel application for recording and reviewing changes in structured datasets. It aims to help teams answer: what changed, what looks inconsistent, and what might need investigation.

The first vertical is **housebuilders**; domain-specific logic lives alongside shared platform code.

## Product direction

The long-term focus is operational visibility across many properties: change history and consistency signals—not a single-site CMS replacement.

## Current status

This project is early-stage but usable for manually ingesting and reviewing changes in Housebuilder plot datasets:

The **first deployment-ready monitoring release** was **v0.4.0**; **v0.5.0** builds on that baseline with dashboard and navigation polish, explicit scheduler timezone defaults, and broader passive plot field tracking—see `CHANGELOG.md`.

- **Monitored sources** (`MonitoredSource`): identify feeds by stable `key`, with a configured endpoint and optional HTTP ingest settings.
- **HTTP JSON plot ingest**: read-only GET via `php artisan contextual-console:run-http-plot-source`.
- **Auth header values from env** (optional): `auth_header_name` + `auth_token_env_key` send a full header value from environment (nothing secret stored in DB).
- **Wrapped JSON list payloads** (optional): unwrap list responses via `http_json_items_key`.
- **ContextualWP list-context adapter** (optional): `http_plot_payload_adapter=contextualwp_list_contexts` normalises common WordPress / ACF field shapes onto Console plot fields (`id`, `price`, `status`).
- **Optional paginated source fetching** (optional): when enabled, fetch multiple pages from an endpoint, combine all items into one dataset, then proceed with normalisation/snapshot/compare.
- **Dataset snapshots** (`DatasetSnapshot`): persisted payload captures per source.
- **Comparison runs** (`DatasetComparisonRun`): baseline then compare-to-previous summaries (`added`, `removed`, `changed`, `unchanged`) and change logs.
- **Issue detection**: dataset-level validation (invalid/missing ids, duplicates, missing/invalid `status`, and status-aware `price` rules). See `PlotDatasetIssueDetector`.
- **Source status**: CLI summary via `php artisan contextual-console:source-status`.
- **Read-only dashboard pages**: `/sources` and `/sources/{source}` (login required); `/` redirects to `/sources`.
- **Admin login**: session login, bootstrap with `php artisan contextual-console:create-admin-user`.
- **SQLite backups** (optional): `php artisan contextual-console:backup-database` — scheduled daily; configure S3-compatible storage and env vars per `docs/DEPLOYMENT.md`.

For release history, see `CHANGELOG.md`.

## Implemented foundation

| Area | What exists today |
|------|-------------------|
| **Model** | `App\Core\Models\ChangeLog` — stores entity type/id, field name, old/new values, and `changed_at`. |
| **Database** | `change_logs` table (see `database/migrations/2026_04_09_052942_create_change_logs_table.php`). |
| **Models (run flow)** | `App\Core\Models\MonitoredSource`, `DatasetSnapshot`, `DatasetComparisonRun` — persisted per-source snapshots and comparison runs. |
| **Services** | `ChangeDetectionService` (`record`, `recordDomainField`, `recordPlotPrice`), `PlotDatasetComparisonService`, `PlotChangeDetector` (whitelisted fields), `PlotDatasetPresenceChangeLogger`, `PlotDatasetRunService` (snapshot + compare + persist summary), `HttpJsonSourceFetcher` (HTTP GET + JSON unwrap + env header values), `PlotHttpIngestNormalizer` (optional `contextualwp_list_contexts` adapter). |
| **Command (internal)** | `php artisan contextual-console:run-plot-source {sourceKey} --file=/path/to/payload.json`; `php artisan contextual-console:run-http-plot-source {sourceKey}`. |

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

## Deployment (private VPS)

See `docs/DEPLOYMENT.md` for a first private VPS deployment guide (Ubuntu LTS + Nginx + PHP-FPM, HTTPS, environment settings, Laravel scheduler cron, daily summary email env, creating the first admin user, configuring a real HTTP source, and running a manual ingest).

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

- `endpoint_url`: full URL to a JSON endpoint (read-only GET).
- `auth_header_name` + `auth_token_env_key` (optional): the **name** of the HTTP header (for example `Authorization`) and the **name** of an environment variable whose value is sent as that header’s **value only**—not a `Header-Name: …` line (for example use `Basic …` in env when `auth_header_name` is `Authorization`). Nothing secret is stored in the database.
- `http_json_items_key` (optional): when the JSON body is an **object** wrapping the list (not a top-level array), set this to the property that holds the array of plot records (for example `contexts` on ContextualWP `list_contexts` responses).
- `http_plot_payload_adapter` (optional): `contextualwp_list_contexts` maps common ContextualWP / WordPress-style rows (for example `post_id`, `acf.price`) onto the plot fields the console compares (`id`, `price`, `status`). When this adapter is set and `http_json_items_key` is empty, the fetcher defaults the wrapper key to `contexts`.

### Pagination (added in v0.3.0)

- `http_pagination_mode` (optional): when set, enables paginated fetching from the endpoint before ingest:
  - `page_per_page`: request page 1..N using `http_page_param` + `http_per_page_param`, combining all returned items into a single array before normalisation/snapshot/comparison.
- `http_page_param` (optional, default `page`): query param name for page number.
- `http_per_page_param` (optional, default `per_page`): query param name for page size.
- `http_per_page` (optional, default `100`): number of items to request per page.
- `http_max_pages` (optional, default `20`): safety cap to prevent unbounded paging.

**Expected plot records** (after unwrap and optional adapter): a JSON array of objects. Each object should include a stable `id` for comparison. **`status`** must be one of `available`, `coming_soon`, `reserved`, or `sold` (case-insensitive). Missing **`status`** is warned. **`price`**: a missing or empty price triggers a warning **only** when `status` is `available`; for other valid statuses, omitting price is normal. If `price` is present and non-empty, it must be numeric and ≥ 0 regardless of status. A **ContextualWP Housebuilder Pack** plots endpoint that already returns a **top-level array** of objects with these Console-aligned fields needs no `http_json_items_key` or `http_plot_payload_adapter`. See `PlotDatasetIssueDetector`.

Very large responses may hit the HTTP client default timeout; prefer smaller responses (for example server-side `limit`) or enable Console pagination on the monitored source when supported by the endpoint.

### Example: Wyatt Housebuilder Pack plots endpoint (paginated)

```php
\App\Core\Models\MonitoredSource::where('key', 'wyatt:housebuilder')->update([
    'endpoint_url' => 'https://www.wyatthomes.co.uk/wp-json/contextualwp-housebuilder/v1/plots',
    'http_pagination_mode' => 'page_per_page',
    'http_page_param' => 'page',
    'http_per_page_param' => 'limit',
    'http_per_page' => 100,
    'http_max_pages' => 10,
]);
```

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

### Local live ContextualWP (HTTPS, placeholders only)

Your laptop can call a **public** WordPress HTTPS URL; the site does **not** need to reach your local Laravel app.

1. In `.env`, define a placeholder env var holding the **value** that will be sent on the configured header (for Application Password Basic auth that is typically `Basic <base64-placeholder>`—without an `Authorization:` prefix). Example names only:

   ```env
   WYATT_CONTEXTUALWP_AUTH="Basic <base64-placeholder>"
   ```

2. Insert or update a `monitored_sources` row with (example placeholders):

   - `endpoint_url`: `https://example.com/wp-json/mcp/v1/list_contexts?post_type=plots&limit=10`
   - `auth_header_name`: `Authorization` (separate from the env value above)
   - `auth_token_env_key`: `WYATT_CONTEXTUALWP_AUTH`
   - `http_plot_payload_adapter`: `contextualwp_list_contexts` (unwraps `contexts` by default and normalises common field shapes)

3. Run:

```bash
php artisan contextual-console:run-http-plot-source hb:your-source-key
```

If the env var referenced by `auth_token_env_key` is missing or empty, the command fails with an explicit error before any HTTP request is made.

## Source status (internal/dev)

- **CLI**: `php artisan contextual-console:source-status`
- **Page**: visit `/sources` for a simple read-only status overview of monitored sources.
- **Source detail**: visit `/sources/{source}` for a read-only view of recent runs and latest dataset issues for a monitored source.

Dashboard pages now require session login (minimal admin auth).

To create an admin user locally (no real credentials):

```bash
php artisan contextual-console:create-admin-user --name="Admin" --email="admin@example.com" --password="a-long-secure-password"
```

## License

This project is released under the [MIT License](LICENSE).
