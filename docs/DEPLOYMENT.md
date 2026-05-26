# Deployment guide (first private VPS)

This guide is for an **early private deployment** of Contextual Console on a small VPS so you can test real HTTP sources safely.

Scope and non-goals for this branch:

- This is **not** CI/CD automation.
- No queues-as-primary-ingest requirement, Docker, or provider tooling (Forge/Ploi/etc) is assumed here.
- Scheduled jobs use Laravel's **scheduler** plus a single **cron** entry on the server (see section 9).
- Keep it small, practical, and easy to follow.

For day-to-day monitoring (adding sources, investigating issues, daily summary setup), see [`OPERATIONS.md`](OPERATIONS.md).

---

## Production deployment checklist (every release)

Run on the server after deploying new code (first-time VPS steps are in section 3):

```bash
composer install --no-dev --optimize-autoloader
php artisan migrate --force
php artisan optimize:clear
php artisan route:cache
php artisan view:cache
```

**Do not run** `php artisan config:cache` while HTTP sources resolve `auth_token_env_key` values via runtime `env()` (see step 8 in section 3).

Then verify:

```bash
php artisan contextual-console:smoke-test
```

Sign in to the dashboard and Profile; confirm sources and daily summary warnings look correct. Full operator steps: [`OPERATIONS.md`](OPERATIONS.md).

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

The `contextual-console:daily-summary --email` command sends mail through Laravel's mail stack. Set at least:

- **`MAIL_MAILER`**: e.g. `smtp` (or your provider's mailer)
- **`MAIL_HOST`**, **`MAIL_PORT`**
- **`MAIL_USERNAME`**, **`MAIL_PASSWORD`** (if your provider requires them)
- **`MAIL_SCHEME`**: e.g. `tls` or `ssl` when required by the provider
- **`MAIL_FROM_ADDRESS`**, **`MAIL_FROM_NAME`**

Align values with your provider's documentation. Do not commit real credentials.

### Daily summary email

The scheduler runs `contextual-console:daily-summary --email` daily (see section 9). Delivery uses Laravel mail (`MAIL_*` above).

**Per-user opt-in (preferred):** users enable **Daily summary email** on **Profile** (`/profile`). Each subscribed user receives the summary at their **login email** (`users.email`).

**Fallback recipient:** when **no** user is subscribed, email goes to **`CONTEXTUAL_CONSOLE_DAILY_SUMMARY_TO`**. Set this in production `.env` until operators opt in; do not commit a real address.

**UI warnings:** if nobody is subscribed, the dashboard and Profile show a banner (fallback still possible, or critical if no fallback is configured). See [`OPERATIONS.md`](OPERATIONS.md).

**Test email:** on Profile, **Send test email** sends the current report to the signed-in user only (not the fallback or other subscribers).

**Smoke test:** `contextual-console:smoke-test` still expects `CONTEXTUAL_CONSOLE_DAILY_SUMMARY_TO` to be set even when subscribers exist.

### SQLite database backups (S3 / DigitalOcean Spaces)

When the default connection is **SQLite** (file-based, not `:memory:`), the scheduled command `contextual-console:backup-database` creates a consistent copy using SQLite **`VACUUM INTO`**, gzip-compresses it, uploads it to a Laravel **filesystem disk**, then deletes local temp files. Old objects under the configured prefix can be pruned using a retention window.

**Contextual Console-specific env (no secrets in these names):**

- **`CONTEXTUAL_CONSOLE_BACKUP_DISK`**: name of a disk in `config/filesystems.php` (default `s3`). Point this at any S3-compatible disk you define.
- **`CONTEXTUAL_CONSOLE_BACKUP_PATH`**: object key prefix inside the bucket (default `database`). Leading/trailing slashes are trimmed.
- **`CONTEXTUAL_CONSOLE_BACKUP_RETENTION_DAYS`**: delete remote backup files whose timestamp in the filename is older than this many days (default `30`). Set to `0` to skip pruning.

**Standard Laravel / Flysystem S3 driver env** (set on the server; do not commit real values). For **DigitalOcean Spaces**, treat the space as a bucket and set the Spaces endpoint and region as your provider documents - for example:

- **`AWS_ACCESS_KEY_ID`**, **`AWS_SECRET_ACCESS_KEY`**: Spaces access key and secret.
- **`AWS_DEFAULT_REGION`**: often a region slug such as `lon1` (follow DO's Spaces docs for your region).
- **`AWS_BUCKET`**: Space name.
- **`AWS_ENDPOINT`**: regional endpoint URL, e.g. `https://lon1.digitaloceanspaces.com` (see [DigitalOcean Spaces documentation](https://docs.digitalocean.com/products/spaces/)).
- **`AWS_URL`**: optional public CDN/base URL if you use one.
- **`FILESYSTEM_DISK`**: leave as your app's primary disk (`local` is fine); backups use **`CONTEXTUAL_CONSOLE_BACKUP_DISK`** only.

Ensure the PHP process user can read the live SQLite file and write under `storage/app/tmp/backups` during the run.

### ContextualWP / HTTP ingest auth (env only)

For HTTP sources, `monitored_sources` stores only `auth_header_name` and `auth_token_env_key`. The environment variable named by `auth_token_env_key` must hold the **header value only** (for example a raw bearer token, or `Basic ...` for Application Passwords). The header **name** (for example `Authorization`) is stored in `auth_header_name`; do not prefix the env value with `Authorization:`.

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
| `APP_SCHEDULE_TIMEZONE` | Optional; IANA zone for `routes/console.php` times (defaults to `Europe/London`) |
| `CONTEXTUAL_CONSOLE_BACKUP_*` | Optional; SQLite backup disk, path prefix, retention (see "SQLite database backups" above); requires `AWS_*` (or equivalent) for the chosen S3 disk |
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

This app primarily stores snapshots/runs/issues in the **database**. Only run this if you are using Laravel's `public/storage` convention (uploads/media/etc):

```bash
php artisan storage:link
```

8. **Clear and rebuild caches** (do **not** run `config:cache`):

Monitored sources resolve HTTP auth header **values** from `.env` at runtime via `env(auth_token_env_key)`. After `php artisan config:cache`, Laravel only exposes env vars that were baked into cached config files, so `env('WYATT_CONTEXTUALWP_AUTH')` and similar keys return empty even when `.env` is correct. Scheduled and manual HTTP source runs then fail auth until you run `php artisan config:clear`.

Until auth lookup is redesigned, **skip `php artisan config:cache`** on production deploys. Clear stale caches, then cache routes and views:

```bash
php artisan optimize:clear
php artisan route:cache
php artisan view:cache
```

9. **Create the first admin user**:

```bash
php artisan contextual-console:create-admin-user --name="Admin" --email="admin@example.com" --password="a-long-secure-password"
```

10. **Configure a monitored source**

Create a `MonitoredSource` row (via DB client or your preferred admin workflow). Field-by-field guide: [`OPERATIONS.md`](OPERATIONS.md). Minimum:

- `key` (example: `hb:example`)
- `name`
- optional `display_name` (UI label)
- `endpoint_url`
- optionally `auth_header_name`
- optionally `auth_token_env_key` (the env var name whose value is sent as the full header value; set the value in `.env`)
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

- **06:00** - `contextual-console:run-scheduled-sources` (HTTP source checks for sources that are due)
- **06:30** - `contextual-console:daily-summary --email` (daily monitoring summary by email)
- **06:45** - `contextual-console:backup-database` (SQLite-only: `VACUUM INTO`, gzip, upload to the configured S3-compatible disk, optional retention cleanup)

Those clock times use the **scheduler timezone** (`APP_SCHEDULE_TIMEZONE`, default `Europe/London`), not the PHP app timezone (`timezone` in `config/app.php`, which remains **UTC** for timestamps). This keeps stored times in UTC while running cron windows at the intended UK local time across GMT and BST. For a deployment aimed at another region, set `APP_SCHEDULE_TIMEZONE` to the appropriate IANA zone.

Laravel only runs the schedule when invoked; on the server, install a **single cron** entry as the user that owns the app files (adjust the path):

```cron
* * * * * cd /path/to/contextual-console && php artisan schedule:run >> /dev/null 2>&1
```

### `WYATT_CONTEXTUALWP_AUTH` from a WordPress Application Password (no secrets in git)

WordPress Application Passwords authenticate with HTTP Basic auth. The **header value** Laravel sends is the string `Basic ` followed by Base64 of `wordpress_username:application_password` (the password is the full generated string, often with spaces - paste it exactly).

1. Create an Application Password in WordPress for a suitable user (Users -> your user -> Application Passwords).
2. On the **server** (SSH), build the value **without** storing it in the repository:
   - Prefer a one-off command where you substitute placeholders locally in your session, or pipe from a file you delete immediately after editing `.env`.
   - Example shape only (replace placeholders; do not commit this line):

```bash
php -r "echo 'Basic ' . base64_encode('YOUR_WP_USERNAME:YOUR_APPLICATION_PASSWORD');"
```

3. Put the **entire printed string** (starting with `Basic `) into `WYATT_CONTEXTUALWP_AUTH` in the server's `.env`.
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
- for each distinct `auth_token_env_key` on a monitored source, the named env var is non-empty at runtime (reports the **env var name only**, not the secret; catches missing values and `config:cache` breaking dynamic auth lookup)
- `CONTEXTUAL_CONSOLE_DAILY_SUMMARY_TO` is set
- `MAIL_FROM_ADDRESS` is set

### Manual verification commands

After deploy or config changes, run:

```bash
php artisan migrate --force
php artisan contextual-console:run-scheduled-sources
php artisan contextual-console:daily-summary
php artisan contextual-console:daily-summary --email
php artisan contextual-console:backup-database
```

- Use `--email` only when mail is configured and `CONTEXTUAL_CONSOLE_DAILY_SUMMARY_TO` is set; the scheduled job runs with `--email` at **06:30**.
- **`contextual-console:backup-database`** only runs successfully when the default DB driver is SQLite and `DB_DATABASE` points at an existing file; it uploads `contextual-console-{Y-m-d-His}.sqlite.gz` under `CONTEXTUAL_CONSOLE_BACKUP_PATH`. On success the command prints `Backup complete: disk=... path=...`.

To confirm what Laravel would run and when:

```bash
php artisan schedule:list
```

### SQLite backup: manual run, verify in object storage, restore outline

**Run a backup once (e.g. after configuring Spaces credentials):**

```bash
cd /var/www/contextual-console
php artisan contextual-console:backup-database
```

**Confirm the object exists** (use your provider's UI, or the AWS CLI against the same endpoint/region/bucket), under the prefix set by `CONTEXTUAL_CONSOLE_BACKUP_PATH` (default `database`). Filenames look like `contextual-console-2026-05-11-064512.sqlite.gz`.

**Restore (high level):**

1. Put the app in maintenance mode (or stop PHP-FPM / queue workers) so nothing writes to the database during replacement.
2. Download the chosen `.sqlite.gz` from object storage to the server.
3. Decompress, e.g. `gunzip -c contextual-console-....sqlite.gz > /path/to/restored.sqlite` (adjust paths).
4. Point **`DB_DATABASE`** at the restored file (or replace the existing file with the decompressed file, preserving ownership/permissions for the PHP user).
5. Clear config cache if you changed `.env`: `php artisan config:clear`, then bring the app back up.

Always test a restore on a copy before relying on it in an incident.
