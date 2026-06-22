# Contextual Console

**Contextual Console** is a Laravel application for recording and reviewing changes in structured datasets. It helps operators answer: what changed, what looks inconsistent, and what might need investigation.

The first vertical is **housebuilders**; domain-specific logic lives alongside shared platform code.

## Product direction

The long-term focus is operational visibility across many properties: change history and consistency signals, not a single-site CMS replacement.

## Current status

**v1.1.1** is the current release, building on **v1.1.0**, **v1.0.0** (first **production-ready** release candidate), and **v0.10.x** daily monitoring stabilisation.

**v1.1.1** is a patch release that completes the `/admin/users` UI actions introduced in **v1.1.0**: create user, admin password reset for other users, safe delete, and initial-password wording (invite emails and self-service password reset are not implemented). **v1.1.0** added **admin user management** (admin/operator roles, admin-only **Users** page, last sign-in tracking, daily summary subscription management, and admin safety protections) and **profile account controls** (name and password updates; login email is read-only).

Earlier releases introduced deployment-ready monitoring (**v0.4.0**), scheduler timezone defaults and broader plot field tracking (**v0.5.0**), optional SQLite backups (**v0.6.x**), dashboard and cross-source Issues/Changes pages (**v0.7.0**), display metadata and UI polish (**v0.8.x**), issue review workflow and pagination (**v0.9.0**), improved daily summary email plus per-user Profile opt-in (**v0.10.0**), and **v1.0.0** issue detail pages, source health overview, manual **Run now**, failed-run diagnostics, and production operations documentation (`docs/OPERATIONS.md`).

Ingest, comparison, change detection, and plot dataset issue detection rules are unchanged. See `CHANGELOG.md` and `docs/contextual-console-handover.md` for operator detail.

- **Monitored sources** (`MonitoredSource`): identify feeds by stable `key`, with a configured endpoint and optional HTTP ingest settings. Optional `display_name` provides a cleaner UI label (for example **Wyatt Homes**); when unset, the UI falls back to `name`. Source keys and ingest behaviour are unchanged.
- **HTTP JSON plot ingest**: read-only GET via `php artisan contextual-console:run-http-plot-source`.
- **Auth header values from env** (optional): `auth_header_name` + `auth_token_env_key` send a full header value from environment (nothing secret stored in DB).
- **Wrapped JSON list payloads** (optional): unwrap list responses via `http_json_items_key`.
- **ContextualWP list-context adapter** (optional): `http_plot_payload_adapter=contextualwp_list_contexts` normalises common WordPress / ACF field shapes onto Console plot fields (`id`, `price`, `status`).
- **Optional paginated source fetching** (optional): when enabled, fetch multiple pages from an endpoint, combine all items into one dataset, then proceed with normalisation/snapshot/compare.
- **Dataset snapshots** (`DatasetSnapshot`): persisted payload captures per source.
- **Comparison runs** (`DatasetComparisonRun`): baseline then compare-to-previous summaries (`added`, `removed`, `changed`, `unchanged`) and change logs.
- **Issue detection**: dataset-level validation (invalid/missing ids, duplicates, missing/invalid `status`, and status-aware `price` rules). See `PlotDatasetIssueDetector`.
- **Source status**: CLI summary via `php artisan contextual-console:source-status`.
- **Web UI** (session **login** required for pages below; `/login` and POST `/logout`): **dashboard** at `/dashboard` with active-issue summaries (open or acknowledged, with severity breakdown) and source failure summaries that separate **current** failures from **recovered** ones; Recent Changes and Development overview sections (compact recent lists); **development drilldown** pages per source for plots, recent changes and recent issues; monitored **sources** at `/sources` with **source health** overview (display labels in the list; source keys on detail pages) and `/sources/{source}` (`{source}` is the monitored source **database id** in URLs), including **Run now** when `endpoint_url` is set and **failed-run diagnostics** when the latest run failed; **comparison run detail** at `/sources/{source}/runs/{run}` (`{run}` is the comparison run id), including **historic** runs and failed-run diagnostics where applicable; cross-source **Changes** at `/changes` and **Issues** at `/issues` with **basic query filters** and **server-rendered pagination** (100 per page; filters preserved; total matches and visible range shown). On **Issues**, mark each detected issue or **bulk-update** all issues matching the current filters to open, acknowledged, ignored, or resolved without changing how issues are detected; issue rows can show **old -> new** values where context provides them and link to **issue detail** at `/issues/{issue}` (summary, context, **suggested issue checks**, status updates). Issue rows on the dashboard, sources, runs, and development views also link through to issue detail. **Profile** at `/profile` (update name and password; read-only login email; daily summary email opt-in, **Send test email**, and a warning when nobody is subscribed). **Admin Users** at `/admin/users` for admin role only. **UI timestamps** are shown in `APP_SCHEDULE_TIMEZONE` (default **Europe/London**); values are stored in UTC. Development labels can fall back from plot URLs when snapshot development fields are missing. Plot entity blocks can show **Last modified by: {label}** when snapshot payloads include Housebuilder Pack **`last_modified_by`** (display-only; not tracked for changes or issues). Shared dashboard styling (design tokens, icons, badges, automatic dark mode, System / Light / Dark theme selector). The site **root (`/`)** redirects to `/dashboard`; successful login redirects there as well.
- **Users and roles**: **admin** and **operator** roles. Admins use the admin-only **Users** page (`/admin/users`) to **create** users (name, login email, role, **initial password**, daily summary subscription), edit name/role/subscription, **reset another user's password**, and **delete** eligible users. Login email is set at creation and is read-only in the UI. Initial passwords are set manually because invite emails and self-service password reset are not implemented. Self-deletion, self-demotion, last-admin deletion, and last-admin demotion are blocked. The list shows name, login email, role, subscription, and last sign-in. Operators use the monitoring UI only. Bootstrap the first account with `php artisan contextual-console:create-admin-user`; use `contextual-console:promote-user-to-admin` to promote an existing user. Do not commit or document real passwords.
- **Scheduled monitoring** (Laravel scheduler; times use `config/app.php` **schedule timezone**, default **Europe/London**): `contextual-console:run-scheduled-sources` daily at **06:00**; **daily summary email** via `contextual-console:daily-summary --email` at **06:30** (HTML plus plain text with links to relevant Console pages; subscribed users at login email, otherwise `CONTEXTUAL_CONSOLE_DAILY_SUMMARY_TO`; mail env in `docs/DEPLOYMENT.md`; operator setup in `docs/OPERATIONS.md`). Local/dev **email preview** at `/dev/daily-summary-email-preview` when not in production.
- **SQLite backups** (optional): `php artisan contextual-console:backup-database`, scheduled daily at **06:45**; configure S3-compatible storage and env vars per `docs/DEPLOYMENT.md`.
- **Production smoke test** (no external HTTP): `php artisan contextual-console:smoke-test`, checks key production configuration and is documented in `docs/DEPLOYMENT.md`.

For release history, see `CHANGELOG.md`.

## Implemented foundation

| Area | What exists today |
|------|-------------------|
| **Model** | `App\Core\Models\ChangeLog`, stores entity type/id, field name, old/new values, and `changed_at`. |
| **Database** | `change_logs` table (see `database/migrations/2026_04_09_052942_create_change_logs_table.php`). |
| **Models (run flow)** | `App\Core\Models\MonitoredSource`, `DatasetSnapshot`, `DatasetComparisonRun`, persisted per-source snapshots and comparison runs. |
| **Services** | `ChangeDetectionService` (`record`, `recordDomainField`, `recordPlotPrice`), `PlotDatasetComparisonService`, `PlotChangeDetector` (whitelisted fields), `PlotDatasetPresenceChangeLogger`, `PlotDatasetRunService` (snapshot + compare + persist summary), `HttpJsonSourceFetcher` (HTTP GET + JSON unwrap + env header values), `PlotHttpIngestNormalizer` (optional `contextualwp_list_contexts` adapter). |
| **Command (internal)** | `php artisan contextual-console:run-plot-source {sourceKey} --file=/path/to/payload.json`; `php artisan contextual-console:run-http-plot-source {sourceKey}`. |

Everything else is default Laravel scaffolding (auth migrations, queue/cache tables, welcome UI, tests).

## Architecture approach

- **`app/Core`**: reusable platform concepts (e.g. shared models like `ChangeLog`).
- **`app/Domains/Housebuilder`**: housebuilder-specific services and future domain code.

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
- Richer alerting and classification of issues beyond today's listings and suggested checks.
- Deeper reporting (saved views, exports, or notifications beyond the existing daily email).

Priorities will shift with production integrations and operator feedback.

## Requirements

- PHP **8.3+**
- [Composer](https://getcomposer.org/)
- Node.js and npm (for Vite/asset build, if you use the default frontend tooling)
- Laravel **13** (pulled in via Composer)

Default local database in `.env.example` is **SQLite**.

## Documentation

| Document | Purpose |
|----------|---------|
| [`docs/DEPLOYMENT.md`](docs/DEPLOYMENT.md) | First private VPS deploy: Nginx, PHP-FPM, HTTPS, `.env`, cron, mail, backups |
| [`docs/OPERATIONS.md`](docs/OPERATIONS.md) | Production deploy checklist, HTTP sources, investigating issues in the UI, daily summary setup |
| [`docs/contextual-console-handover.md`](docs/contextual-console-handover.md) | Version-controlled handover: current features, scheduler, limitations |
| [`CHANGELOG.md`](CHANGELOG.md) | Release history |

Production deploys should run `php artisan optimize:clear`, `route:cache`, and `view:cache`, and should **not** run `config:cache` while source auth tokens are read from `.env` at runtime. See `docs/OPERATIONS.md`.

## Deployment (private VPS)

See `docs/DEPLOYMENT.md` for a first private VPS deployment guide (Ubuntu LTS + Nginx + PHP-FPM, HTTPS, environment settings, Laravel scheduler cron, daily summary email env, creating the first admin user, configuring a real HTTP source, and running a manual ingest). Day-to-day operator tasks are in `docs/OPERATIONS.md`.

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
- `auth_header_name` + `auth_token_env_key` (optional): the **name** of the HTTP header (for example `Authorization`) and the **name** of an environment variable whose value is sent as that header's **value only**, not a `Header-Name: ...` line (for example use `Basic ...` in env when `auth_header_name` is `Authorization`). Nothing secret is stored in the database.
- `http_json_items_key` (optional): when the JSON body is an **object** wrapping the list (not a top-level array), set this to the property that holds the array of plot records (for example `contexts` on ContextualWP `list_contexts` responses).
- `http_plot_payload_adapter` (optional): `contextualwp_list_contexts` maps common ContextualWP / WordPress-style rows (for example `post_id`, `acf.price`) onto the plot fields the console compares (`id`, `price`, `status`). When this adapter is set and `http_json_items_key` is empty, the fetcher defaults the wrapper key to `contexts`.

### Pagination (added in v0.3.0)

- `http_pagination_mode` (optional): when set, enables paginated fetching from the endpoint before ingest:
  - `page_per_page`: request page 1..N using `http_page_param` + `http_per_page_param`, combining all returned items into a single array before normalisation/snapshot/comparison.
- `http_page_param` (optional, default `page`): query param name for page number.
- `http_per_page_param` (optional, default `per_page`): query param name for page size.
- `http_per_page` (optional, default `100`): number of items to request per page.
- `http_max_pages` (optional, default `20`): safety cap to prevent unbounded paging.

**Expected plot records** (after unwrap and optional adapter): a JSON array of objects. Each object should include a stable `id` for comparison. **`status`** must be one of `available`, `coming_soon`, `reserved`, or `sold` (case-insensitive). Missing **`status`** is warned. **`price`**: a missing or empty price triggers a warning **only** when `status` is `available`; for other valid statuses, omitting price is normal. If `price` is present and non-empty, it must be numeric and ≥ 0 regardless of status. Optional **`last_modified_by`** (when returned by the Housebuilder Pack endpoint) is stored on snapshots and shown in the UI as **Last modified by: {label}**; it is display-only metadata and is not compared for change logs or issues. A **ContextualWP Housebuilder Pack** plots endpoint that already returns a **top-level array** of objects with these Console-aligned fields needs no `http_json_items_key` or `http_plot_payload_adapter`. See `PlotDatasetIssueDetector`.

Very large responses may hit the HTTP client default timeout; prefer smaller responses (for example server-side `limit`) or enable Console pagination on the monitored source when supported by the endpoint.

### Example: Wyatt Housebuilder Pack plots endpoint (paginated)

```php
\App\Core\Models\MonitoredSource::where('key', 'wyatt:housebuilder')->update([
    'display_name' => 'Wyatt Homes',
    'endpoint_url' => 'https://www.wyatthomes.co.uk/wp-json/contextualwp-housebuilder/v1/plots',
    'http_pagination_mode' => 'page_per_page',
    'http_page_param' => 'page',
    'http_per_page_param' => 'limit',
    'http_per_page' => 100,
    'http_max_pages' => 10,
]);
```

For an existing local row, set the display label without changing the source key:

```php
\App\Core\Models\MonitoredSource::where('key', 'wyatt:housebuilder')->update(['display_name' => 'Wyatt Homes']);
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

1. In `.env`, define a placeholder env var holding the **value** that will be sent on the configured header (for Application Password Basic auth that is typically `Basic <base64-placeholder>`, without an `Authorization:` prefix). Example names only:

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

## Source status and dashboards (internal/dev)

- **CLI**: `php artisan contextual-console:source-status`
- **Dashboard**: `/dashboard`: high-level counts and recent runs; drilldown links apply **basic filters** on Issues, Changes, or Sources where noted on the page; recent issues link to issue detail.
- **Sources list**: `/sources`: monitored sources with **source health** overview.
- **Source detail**: `/sources/{source}`: recent runs, latest run's issues and changes, **Run now** when configured, **failed-run diagnostics** when applicable, links to each run's detail page and issue detail.
- **Run detail**: `/sources/{source}/runs/{run}`: that run's issues and plot change rows (works for past runs, not only the latest); failed-run diagnostics when the run failed.
- **Issue detail**: `/issues/{issue}`: summary, context, suggested checks, status updates (see `docs/OPERATIONS.md` for investigation workflow).

To create an admin user locally (example placeholders only):

```bash
php artisan contextual-console:create-admin-user --name="Admin" --email="admin@example.com" --password="a-long-secure-password"
```

## License

This project is released under the [MIT License](LICENSE).
