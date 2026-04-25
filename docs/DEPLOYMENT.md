# Deployment guide (first private VPS)

This guide is for an **early private deployment** of Contextual Console on a small VPS so you can test real HTTP sources safely.

Scope and non-goals for this branch:

- This is **not** CI/CD automation.
- No scheduling, queues, alerts, Docker, or provider tooling (Forge/Ploi/etc).
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

### ContextualWP endpoint tokens (HTTP ingest)

For HTTP sources that use token auth, the monitored source references an env var name (for example `CONTEXTUALWP_TOKEN_HB_EXAMPLE`).

- Define the token value in `.env` using that key name
- Do **not** store tokens in the database
- Do **not** commit tokens

Example (placeholder only):

```env
CONTEXTUALWP_TOKEN_HB_EXAMPLE=
```

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
- optionally `auth_token_env_key` (the env var name that holds the token)

11. **Run a first HTTP ingest manually**:

```bash
php artisan contextual-console:run-http-plot-source hb:example
```

12. **Visit the dashboard**

- Visit `/login`
- Then `/sources`
- Then `/sources/{source}`

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
  - scheduled polling (future),
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
- ContextualWP tokens stored in **`.env`**, not DB
- Tokens can be **rotated**
- Only **read-only endpoints** are used for ingest
- Do not expose unnecessary client/customer data in logs, source payloads, or the dashboard

---

## 8) Manual real-source smoke test (HTTP)

Example flow for one real source:

1. Configure one `MonitoredSource` with:
   - `endpoint_url`
   - `auth_header_name` (optional)
   - `auth_token_env_key` (optional, but recommended if auth is required)

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
