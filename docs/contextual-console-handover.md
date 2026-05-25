# Contextual Console handover

Lightweight orientation for operators and developers. For deployment steps and env var names, see `docs/DEPLOYMENT.md`. For release history, see `CHANGELOG.md`.

## Project purpose

Contextual Console monitors website and housebuilder data: it ingests monitored sources, stores **dataset snapshots**, runs **dataset comparison runs**, records **change logs** and **dataset issues**, and surfaces results on a dashboard plus optional **daily summary email**. Optional **database backup** archives the SQLite database to S3-compatible storage.

The first vertical is housebuilders (`app/Domains/Housebuilder`); shared platform code lives under `app/Core`.

## Live application

- **URL**: `https://console.example.com` (replace with the real production `APP_URL`; no credentials in this doc)

## Main production concepts

| Concept | Role |
|---------|------|
| **Monitored sources** | Stable `key`, HTTP endpoint, optional ingest settings; scheduled daily ingest |
| **Dataset snapshots** | Persisted payload capture per source run |
| **Dataset comparison runs** | Baseline or completed compare vs previous snapshot; summary counts and IDs |
| **Dataset issues** | Detected problems (e.g. `source_run_failed`, plot warnings); review statuses on Issues UI |
| **Change logs** | Field-level and presence changes between snapshots |
| **Daily summary email** | `contextual-console:daily-summary --email` (recipient via env) |
| **Database backup** | `contextual-console:backup-database` to configured S3-compatible disk |

## Current operations (scheduler)

All scheduled times use the scheduler timezone in config (default **Europe/London**). Stored DB timestamps remain **UTC**; user-facing display should respect configured schedule/display timezone.

| Time | Command |
|------|---------|
| 06:00 | `contextual-console:run-scheduled-sources` |
| 06:30 | `contextual-console:daily-summary --email` |
| 06:45 | `contextual-console:backup-database` |

Manual ingest (non-scheduled): `contextual-console:run-http-plot-source {sourceKey}` or file-based plot source commands (see `README.md`).

## Workflow conventions

- **Cursor skills**: [`.cursor/skills-catalogue.md`](../.cursor/skills-catalogue.md)
- **Branches**: `feature/...`, `fix/...`, `chore/...`
- **Commits** (when committing): `feat(scope):`, `fix(scope):`, `chore(release):`, `chore(deps):`, `chore(docs):`
- **Secrets**: never commit live auth headers, app passwords, private keys, SMTP passwords, Spaces keys, or server passwords; use `.env` on the server only

## Investigation rule

When data looks wrong upstream or in Console:

**Console issue -> Housebuilder Pack endpoint output -> WordPress/admin data -> raw post meta**

Do not assume Console is wrong. For failed runs, read the `source_run_failed` issue context before changing code. See the `monitoring-investigation` skill in `.cursor/skills/`.

## Key paths

| Area | Location |
|------|----------|
| Scheduler | `routes/console.php` |
| Core models | `app/Core/Models/` |
| Housebuilder services | `app/Domains/Housebuilder/` |
| Artisan commands | `app/Console/Commands/` |
| Deployment | `docs/DEPLOYMENT.md` |

## Maintaining this doc

Keep this file short. Update when scheduler times, major concepts, or team conventions change. Put detailed procedures in `docs/DEPLOYMENT.md` or `README.md` instead of duplicating them here.
