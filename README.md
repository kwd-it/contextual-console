# Contextual Console

**Contextual Console** is an observability and intelligence platform for structured digital properties. It aims to give teams a clearer picture of what changed, what looks wrong, and where data or behaviour may be inconsistent.

The first vertical is **housebuilders**; domain-specific logic lives alongside shared platform code.

## Product direction

The long-term focus is operational visibility across many properties: change history, data quality signals, and health of feeds or sync paths—not a single-site CMS replacement.

## Current status

**v0.1.0** — early foundation. The app is a standard Laravel project with a small slice of domain and platform structure in place.

## Implemented foundation

| Area | What exists today |
|------|-------------------|
| **Model** | `App\Core\Models\ChangeLog` — stores entity type/id, field name, old/new values, and `changed_at`. |
| **Database** | `change_logs` table (see `database/migrations/2026_04_09_052942_create_change_logs_table.php`). |
| **Service** | `App\Domains\Housebuilder\Services\ChangeDetectionService` — `record($model, $field, $old, $new)` persists a row via `ChangeLog`. |

Everything else is default Laravel scaffolding (auth migrations, queue/cache tables, welcome UI, tests).

## Architecture approach

- **`app/Core`** — reusable platform concepts (e.g. shared models like `ChangeLog`).
- **`app/Domains/Housebuilder`** — housebuilder-specific services and future domain code.

This split is intentional so additional verticals can follow the same pattern later.

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

## License

This project is released under the [MIT License](LICENSE).
