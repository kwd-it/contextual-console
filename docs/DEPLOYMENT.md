# Deployment guide (first private VPS)

This guide is for an **early private deployment** of Contextual Console on a small VPS so you can test real HTTP sources safely.

Scope and non-goals for this branch:

- This is **not** CI/CD automation.
- No queues-as-primary-ingest requirement, Docker, or provider tooling (Forge/Ploi/etc) is assumed here.
- Scheduled jobs use Laravel’s **scheduler** plus a single **cron** entry on the server (see section 9).
- Keep it small, practical, and easy to follow.

---

## 1) Recommended first-test hosting shape

For a first private deployment (single operator / small team):

- **Separate small VPS** (not shared with unrelated apps at first)
- **Ubuntu LTS**
- **Nginx**
- **PHP 8.4** (with PHP-FPM)
- **Composer**
- **Database**:
  - **SQLite** is acceptable for earliest private testing, or
  - **MySQL/Postgres** if you prefer from day one before adding scheduled ingestion
- **Private domain or subdomain**
- **HTTPS required**
- Dashboard pages are protected by **login** (no public registration)

---

## 2) Required environment settings

Contextual Console is a normal Laravel app. These are the key environment values you must set in production.

### Core Laravel

- **`APP_NAME`**: a human-friendly name (e.g. `Contextual Console`)
- **`APP_ENV=production`**
- **`APP_KEY`**: must be set (generate once on the server)
- **`APP_DEBUG=false`**
- **`APP_URL`**: your HTTPS URL (e.g. `https://console.example.com`)

### Database

Choose one:

- **SQLite**
  - **`DB_CONNECTION=sqlite`**
  - **`DB_DATABASE=/absolute/path/to/database.sqlite`** (recommended in production)
- **MySQL**
  - `DB_CONNECTION=mysql`
  - `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`
- **Postgres**
  - `DB_CONNECTION=pgsql`
  - `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`

### Session cookie security

If you are using HTTPS (you should):

- **`SESSION_SECURE_COOKIE=true`**

### Mail (daily summary email)

The `contextual-console:daily-summary --email` command sends mail through Laravel’s mail stack. Set at least:

- **`MAIL_MAILER`**: e.g. `smtp` (or your provider’s mailer)
- **`MAIL_HOST`**, **`MAIL_PORT`**
- **`MAIL_USERNAME`**, **`MAIL_PASSWORD`** (if your provider requires them)
- **`MAIL_SCHEME`**: e.g. `tls` or `ssl` when required by the provider
- **`MAIL_FROM_ADDRESS`**, **`MAIL_FROM_NAME`**

Align values with your provider’s documentation. Do not commit real credentials.

### Daily summary recipient

- **`CONTEXTUAL_CONSOLE_DAILY_SUMMARY_TO`**: recipient email address for the scheduled daily summary (used when the scheduler runs `contextual-console:daily-summary --email`).

### ContextualWP / HTTP ingest auth (env only)

For HTTP sources, `monitored_sources` stores only `auth_header_name` and `auth_token_env_key`. The environment variable named by `auth_token_env_key` must hold the **header value only** (for example a raw bearer token, or `Basic …` for Application Passwords). The header **name** (for example `Authorization`) is stored in `auth_header_name`; do not prefix the env value with `Authorization:`.

- Define that value in `.env` on the server
- Do **not** store secrets in the database, code, docs, fixtures, or seeders
- Do **not** commit real credentials

**Wyatt / WordPress example:** when the monitored source uses `auth_token_env_key` `WYATT_CONTEXTUALWP_AUTH`, set that variable on the server to the full header **value** (for Basic auth, typically `Basic <base64>`). See section 9 for generating the value from a WordPress Application Password without putting secrets in the repo.

Examples (placeholders only):

```env
CONTEXTUALWP_TOKEN_HB_EXAMPLE=
WYATT_CONTEXTUALWP_AUTH=
CONTEXTUAL_CONSOLE_DAILY_SUMMARY_TO=ops@example.com
```

---

### Production `.env` checklist (concise)

| Variable | Notes |
|----------|--------|
| `APP_ENV` | `production` |
| `APP_KEY` | `php artisan key:generate` on the server (once) |
| `APP_URL` | Public HTTPS base URL of the app |
| `APP_DEBUG` | `false` |
| `DB_*` | SQLite **or** MySQL/Postgres (see Database above) |
| `MAIL_*` | Provider-specific; required for emailed daily summary |
| `CONTEXTUAL_CONSOLE_DAILY_SUMMARY_TO` | Inbox for the daily summary email |
| `WYATT_CONTEXTUALWP_AUTH` | Only if a source uses that env key; header **value** only |

---

## 3) First deployment steps (manual)

This is a minimal, manual sequence for a first VPS deployment.

### Server prerequisites (summary)

- Nginx installed and running
- PHP 8.4 + PHP-FPM installed
- Composer installed
- A database available (SQLite file or MySQL/Postgres server)

### App deploy steps

1. **Clone repository** (choose a directory like `/var/www/contextual-console`):

```bash
git clone <your-private-repo-url> contextual-console
cd contextual-console
```

2. **Install dependencies**:

```bash
composer install --no-dev --optimize-autoloader
```

3. **Create `.env`**:

```bash
cp .env.example .env
```

Set production values in `.env` (see section 2).

4. **Generate app key**:

```bash
php artisan key:generate
```

5. **Configure database**

- For SQLite: ensure the database file exists and is writable by the PHP-FPM user.
- For MySQL/Postgres: ensure credentials work and the database exists.

6. **Run migrations**:

```bash
php artisan migrate --force
```

7. **Storage symlink** (only if actually needed)

This app primarily stores snapshots/runs/issues in the **database**. Only run this if you are using Laravel’s `public/storage` convention (uploads/media/etc):

```bash
php artisan storage:link
```

8. **Cache config/routes/views**:

```bash
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

9. **Create the first admin user**:

```bash
php artisan contextual-console:create-admin-user --name="Admin" --email="admin@example.com" --password="a-long-secure-password"
```

10. **Configure a monitored source**

Create a `MonitoredSource` row (via DB client or your preferred admin workflow) with:

- `key` (example: `hb:example`)
- `name`
- `endpoint_url`
- optionally `auth_header_name`
- optionally `auth_token_env_key` (the env var name whose value is sent as the full header value)
- optionally `http_json_items_key` (when the JSON body wraps the list in an object)
- optionally `http_plot_payload_adapter` (set to `contextualwp_list_contexts` for ContextualWP-style list payloads)
- optionally `http_pagination_mode` (set to `page_per_page` to fetch multiple pages and combine before ingest)
- optionally `http_page_param`, `http_per_page_param`, `http_per_page`, `http_max_pages` (pagination configuration; defaults exist when omitted)

11. **Run a first HTTP ingest manually**:

```bash
php artisan contextual-console:run-http-plot-source hb:example
```

12. **Visit the dashboard**

- Visit `/login`
- Then `/sources`
- Then `/sources/{source}`

13. **Scheduler cron** (automated source checks + daily summary email): install the cron line in section 9 and set mail / `CONTEXTUAL_CONSOLE_DAILY_SUMMARY_TO` in `.env`.

---

## 4) Nginx notes (Laravel)

Keep this simple:

- Nginx **document root must point to `public/`**
- PHP-FPM must be configured so `.php` requests are handled by **PHP 8.4 FPM**
- Protect hidden files (especially **`.env`**) from being served
- HTTPS should be enabled (use your preferred certificate method)

If you already have a Laravel Nginx snippet convention in your infra, use that rather than inventing a complex config here.

---

## 5) Database choice notes

- **SQLite is acceptable** for first private testing with a few sources and manual runs.
  - Keep the DB file on persistent disk.
  - Ensure file permissions are correct for the PHP-FPM user.
- **MySQL/Postgres is better** before:
  - heavier scheduled polling volume,
  - larger snapshot history,
  - multi-user use,
  - or if you want established backup tooling and monitoring from day one.

Backups are required either way.

---

## 6) Backup notes

At minimum, you must be able to restore:

- **Database** (required)
  - snapshots, runs, issues, sources, users, change logs all live here
- **`.env`** (securely) or ensure secrets are recoverable from a password manager

Do not rely on the VPS alone as the only copy. Plan for disk loss, accidental deletion, or compromise.

---

## 7) Security checklist (private VPS)

- **`APP_DEBUG=false`**
- Dashboard routes require **login**
- Create a **strong admin password**
- No public registration (admin users are manually provisioned)
- **HTTPS enabled**
- ContextualWP / HTTP ingest secrets stored in **`.env`**, not DB
- Tokens can be **rotated**
- Only **read-only endpoints** are used for ingest
- Do not expose unnecessary client/customer data in logs, source payloads, or the dashboard

---

## 8) Manual real-source smoke test (HTTP)

Example flow for one real source:

1. Configure one `MonitoredSource` with:
   - `endpoint_url`
   - `auth_header_name` (optional)
   - `auth_token_env_key` (optional, but recommended if auth is required; value in `.env` is the entire header string)
   - `http_json_items_key` / `http_plot_payload_adapter` (optional; see project `README.md` HTTP ingest section)

Example values:

- `key`: `hb:example`
- `endpoint_url`: `https://example.com/wp-json/contextualwp/v1/plots`
- `auth_header_name`: `X-ContextualWP-Token`
- `auth_token_env_key`: `CONTEXTUALWP_TOKEN_HB_EXAMPLE`

2. Set the token in `.env` (placeholder shown):

```env
CONTEXTUALWP_TOKEN_HB_EXAMPLE=
```

3. Run ingest:

```bash
php artisan contextual-console:run-http-plot-source hb:example
```

4. Check CLI status:

```bash
php artisan contextual-console:source-status
```

5. Check the dashboard:

- `/sources`
- `/sources/{source}`

---

## 9) Production scheduler (cron), Wyatt auth, and verification

The app registers schedule entries in `routes/console.php`:

- **06:00** — `contextual-console:run-scheduled-sources` (HTTP source checks for sources that are due)
- **06:30** — `contextual-console:daily-summary --email` (daily monitoring summary by email)

Laravel only runs the schedule when invoked; on the server, install a **single cron** entry as the user that owns the app files (adjust the path):

```cron
* * * * * cd /path/to/contextual-console && php artisan schedule:run >> /dev/null 2>&1
```

### `WYATT_CONTEXTUALWP_AUTH` from a WordPress Application Password (no secrets in git)

WordPress Application Passwords authenticate with HTTP Basic auth. The **header value** Laravel sends is the string `Basic ` followed by Base64 of `wordpress_username:application_password` (the password is the full generated string, often with spaces—paste it exactly).

1. Create an Application Password in WordPress for a suitable user (Users → your user → Application Passwords).
2. On the **server** (SSH), build the value **without** storing it in the repository:
   - Prefer a one-off command where you substitute placeholders locally in your session, or pipe from a file you delete immediately after editing `.env`.
   - Example shape only (replace placeholders; do not commit this line):

```bash
php -r "echo 'Basic ' . base64_encode('YOUR_WP_USERNAME:YOUR_APPLICATION_PASSWORD');"
```

3. Put the **entire printed string** (starting with `Basic `) into `WYATT_CONTEXTUALWP_AUTH` in the server’s `.env`.
4. Ensure the monitored source row uses `auth_header_name` `Authorization` and `auth_token_env_key` `WYATT_CONTEXTUALWP_AUTH` (header **name** in the database; **value** only in env).

Never commit real usernames/passwords, command history containing them, or a populated `.env`.

### Production smoke test (safe / no external calls)

Before running any manual source checks or email sends, run:

```bash
php artisan contextual-console:smoke-test
```

This checks basic production readiness **without** calling external endpoints, sending mail, or running scheduled jobs:

- `APP_URL` is set
- database connection works
- `migrations` table exists
- an admin user exists
- at least one monitored source exists
- the monitored source has an endpoint URL
- `CONTEXTUAL_CONSOLE_DAILY_SUMMARY_TO` is set
- `MAIL_FROM_ADDRESS` is set

### Manual verification commands

After deploy or config changes, run:

```bash
php artisan migrate --force
php artisan contextual-console:run-scheduled-sources
php artisan contextual-console:daily-summary
php artisan contextual-console:daily-summary --email
```

- Use `--email` only when mail is configured and `CONTEXTUAL_CONSOLE_DAILY_SUMMARY_TO` is set; the scheduled job runs with `--email` at **06:30**.

To confirm what Laravel would run and when:

```bash
php artisan schedule:list
```
