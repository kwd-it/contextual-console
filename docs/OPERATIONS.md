# Contextual Console operations guide

Practical notes for operators and developers running Contextual Console in production or staging. For first-time VPS setup, see [`DEPLOYMENT.md`](DEPLOYMENT.md). For release history and project handover context, see [`contextual-console-handover.md`](contextual-console-handover.md) and [`CHANGELOG.md`](../CHANGELOG.md).

---

## Documentation map

| Topic | Where to read |
|-------|----------------|
| First private VPS deploy, Nginx, cron, mail env | [`DEPLOYMENT.md`](DEPLOYMENT.md) |
| Day-to-day monitoring, sources, issues, notifications | This file |
| Current features, scheduler times, handover expectations | [`contextual-console-handover.md`](contextual-console-handover.md) |
| HTTP ingest field reference (dev-oriented examples) | [`README.md`](../README.md) (HTTP ingest section) |
| Cursor workflow and UI text rules | [`.cursor/skills-catalogue.md`](../.cursor/skills-catalogue.md) |

---

## Production deployment checklist

Use this on **every** production deploy after code is on the server. For the first install (clone, `.env`, Nginx, cron), follow [`DEPLOYMENT.md`](DEPLOYMENT.md) section 3 first.

1. **Install PHP dependencies** (production):

   ```bash
   composer install --no-dev --optimize-autoloader
   ```

2. **Run migrations** when the release includes them:

   ```bash
   php artisan migrate --force
   ```

3. **Clear stale caches**, then rebuild safe caches:

   ```bash
   php artisan optimize:clear
   php artisan route:cache
   php artisan view:cache
   ```

4. **Do not run** `php artisan config:cache`. Monitored sources read HTTP auth header **values** from `.env` at runtime via `env(auth_token_env_key)`. With `config:cache`, those `env()` lookups return empty and scheduled or manual HTTP runs fail authentication until you run `php artisan config:clear`. See [`DEPLOYMENT.md`](DEPLOYMENT.md) for detail.

5. **Smoke test** (no external HTTP, no mail sent):

   ```bash
   php artisan contextual-console:smoke-test
   ```

6. **Optional manual checks** after config or source changes:

   ```bash
   php artisan contextual-console:run-http-plot-source {sourceKey}
   php artisan contextual-console:source-status
   ```

7. **Confirm the scheduler cron** is still installed (`* * * * * cd /path/to/app && php artisan schedule:run`).

8. **Sign in to the UI** and spot-check dashboard, sources, and Profile (daily summary warning if applicable).

---

## Add a monitored HTTP source

Sources are rows in `monitored_sources`. Create or update them with a database client, tinker, or your own admin workflow. The app does not yet provide a source admin UI.

### Required and common fields

| Field | Purpose |
|-------|---------|
| `key` | Stable identifier used by Artisan (e.g. `hb:example`). |
| `name` | Internal name (shown when `display_name` is empty). |
| `display_name` | Optional label in the UI (e.g. `Example Homes`). |
| `endpoint_url` | Full HTTPS URL for a read-only GET JSON endpoint. |

### Authentication (env only, not in the database)

| Field | Purpose |
|-------|---------|
| `auth_header_name` | HTTP header **name** (e.g. `Authorization`, `X-ContextualWP-Token`). |
| `auth_token_env_key` | Name of a `.env` variable whose **value** is sent as the full header value (not `Header-Name: ...`). |

On the server, set the env var to the complete header value. Examples (placeholders only):

```env
CONTEXTUALWP_TOKEN_HB_EXAMPLE=your-token-here
WYATT_CONTEXTUALWP_AUTH=Basic <base64-placeholder>
```

For WordPress Application Passwords with `Authorization`, the env value is typically `Basic ` plus Base64 of `username:application_password`. See [`DEPLOYMENT.md`](DEPLOYMENT.md) section 9.

If `auth_token_env_key` is set but the env var is missing or empty, HTTP ingest and **Run now** fail before any request is sent.

### JSON shape and adapters (optional)

| Field | Purpose |
|-------|---------|
| `http_json_items_key` | Property holding the plot array when the body is a wrapped object. |
| `http_plot_payload_adapter` | e.g. `contextualwp_list_contexts` for ContextualWP-style lists. |

### Pagination (optional)

| Field | Purpose |
|-------|---------|
| `http_pagination_mode` | Set to `page_per_page` to fetch multiple pages. |
| `http_page_param` | Query param for page number (default `page`). |
| `http_per_page_param` | Query param for page size (default `per_page`). |
| `http_per_page` | Items per page (default `100`). |
| `http_max_pages` | Safety cap (default `20`). |

### Verify the source

**CLI:**

```bash
php artisan contextual-console:run-http-plot-source hb:example
php artisan contextual-console:source-status
```

**UI:** open `/sources/{id}` (database id). If `endpoint_url` is set, use **Run now** to trigger a live fetch without SSH.

---

## Investigate an issue from the UI

Work from summary to detail. Issue links appear on the dashboard, sources list, source detail, run detail, development drilldown, Issues list, and issue detail pages.

### 1. Dashboard (`/dashboard`)

- **Active issues** counts (open or acknowledged) link to `/issues` with filters applied.
- **Failed runs** separates **current** failures from **recovered** ones (a later successful run for the same source).
- **Recent issues** rows link to issue detail.
- A **daily summary subscription** banner appears when no user is subscribed (see Daily summary notifications below).

### 2. Sources overview (`/sources`)

- Each source shows a **health** badge: **Healthy**, **Needs review**, **Failing**, or **Not run yet**, based on the latest run and issue severities on that run.
- Open a source from the list for detail.

### 3. Source detail (`/sources/{source}`)

`{source}` is the monitored source **database id**.

- Latest comparison run status, counts, and issues for that run.
- **Run now** (when `endpoint_url` is set) runs a live HTTP ingest from the browser.
- If the latest run **failed**, read **Failed run diagnostics** (error message and link to the related issue when one exists).
- Issue messages link to issue detail; runs link to run detail.

### 4. Run detail (`/sources/{source}/runs/{run}`)

`{run}` is the comparison run id. Works for historic runs, not only the latest.

- Full issue list and plot change rows for that run.
- Issue messages link to issue detail.

### 5. Issue detail (`/issues/{issue}`)

- Summary: severity, review status, source, comparison run, entity, timestamps.
- **What this means** and **Suggested check** (operator hints; for `source_run_failed`, check failed run details first).
- **Context** JSON and **old -> new** values when present.
- Links back to source, run, and development drilldown where applicable.
- Update review status on the page.

### 6. Issues list (`/issues`)

- Filter by source, severity, status, type, and more.
- Bulk status updates for all issues matching current filters.
- **View issue #...** links to issue detail.

### 7. Development drilldown (`/sources/{source}/developments/{development}`)

- Plots and recent issues for one development; issue messages link to issue detail.

### CLI and external checks

- `php artisan contextual-console:source-status` for a terminal summary.
- For data mismatches, follow the investigation order in the `monitoring-investigation` Cursor skill: Console issue -> upstream endpoint -> CMS data -> raw meta.

---

## Daily summary notifications

### Per-user opt-in (preferred)

1. Sign in and open **Profile** (`/profile`).
2. Enable **Send me the daily monitoring summary email** and save.
3. Mail goes to the user's **login email** (`users.email`). There is no separate summary address field.

Each subscribed user receives their own copy when the scheduler runs `contextual-console:daily-summary --email`.

### Fallback recipient

If **no** user has `daily_summary_enabled`, the command sends one email to **`CONTEXTUAL_CONSOLE_DAILY_SUMMARY_TO`** (see `config/contextual_console.php`).

Set this in production `.env` as a bridge until operators opt in. Do not commit the real address.

### Subscription warnings

When no user is subscribed, a banner appears on the **dashboard** and **Profile**:

- **Fallback configured:** warns that email can still go to the fallback, but at least one operator should normally opt in.
- **No fallback:** critical warning that scheduled summary emails will not be sent.

### Test email (Profile)

**Send test email** on Profile sends the **current** daily summary report to the **signed-in user's login email only**. It does not use the fallback recipient and does not email other subscribers. Useful after mail or template changes. Delivery can take a few minutes; check spam.

### Scheduled send

- **06:30** (scheduler timezone, default `Europe/London`): `contextual-console:daily-summary --email`
- Requires working `MAIL_*` env vars.
- HTML and plain text bodies use the same report data.

### Local preview (not production)

Outside production, `/dev/daily-summary-email-preview` shows the HTML email (authenticated). A link appears on Profile when that route exists.

### Smoke test note

`php artisan contextual-console:smoke-test` still expects **`CONTEXTUAL_CONSOLE_DAILY_SUMMARY_TO`** to be set even when users are subscribed. Keep a fallback address configured for smoke tests and as a safety net.

---

## Timestamps in the UI

- Values are stored in **UTC** (`config/app.php` `timezone`).
- User-facing timestamps use **`APP_SCHEDULE_TIMEZONE`** (default `Europe/London`), the same zone as scheduler entries in `routes/console.php`.
- Override `APP_SCHEDULE_TIMEZONE` for non-UK deployments so displayed times match local operator expectations.

---

## Security: do not commit secrets

Never commit or paste into docs:

- Real auth tokens or full `Authorization` / Basic header values
- SMTP passwords or API keys
- WordPress Application Passwords
- Private endpoint secrets
- Populated `.env` files, `database.sqlite` from production, or backup objects containing live data

Use placeholder names in docs (e.g. `CONTEXTUALWP_TOKEN_HB_EXAMPLE`, `ops@example.com`). Store real values only on the server `.env` or in a password manager.

---

## Useful Artisan commands

| Command | Purpose |
|---------|---------|
| `contextual-console:run-http-plot-source {key}` | Manual HTTP ingest for one source |
| `contextual-console:run-scheduled-sources` | Run all due scheduled sources (same as cron window) |
| `contextual-console:source-status` | CLI summary of sources and latest runs |
| `contextual-console:daily-summary` | Print summary to terminal |
| `contextual-console:daily-summary --email` | Email summary (subscribers or fallback) |
| `contextual-console:smoke-test` | Production config checks without external calls |
| `contextual-console:backup-database` | SQLite backup to configured object storage |
| `contextual-console:create-admin-user` | Create an internal login user |
