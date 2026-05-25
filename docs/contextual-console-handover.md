# Contextual Console

## 1. Title and purpose

**Contextual Console** monitors website and housebuilder data. It ingests configured HTTP (and related) sources, stores **dataset snapshots**, runs **dataset comparison runs**, records **change logs** and **dataset issues**, and presents results on an authenticated web dashboard. A scheduled **daily summary** command can print or email a monitoring summary. Optional **database backup** archives the production SQLite database to S3-compatible object storage.

The first product vertical is housebuilders (`app/Domains/Housebuilder`). Shared monitoring platform code lives under `app/Core`.

For deployment steps and env var names (not values), see `docs/DEPLOYMENT.md`. For release history, see `CHANGELOG.md`.

## 2. How to use this handover

This file is the **version-controlled project handover** for Contextual Console.

- Update it **before release/version bump commits** when project state, behaviour, or operational expectations have materially changed.
- Use it at the start of a new ChatGPT or Cursor session to understand the current system and continue work safely.
- **Repeatable Cursor behaviour** (workflow, UI text rules, monitoring investigation order) lives in `.cursor/skills/` and `.cursor/skills-catalogue.md`. Do not duplicate those skills here; reference them in prompts instead.

## 3. Current project state

Implemented today:

| Area | Notes |
|------|--------|
| **Authenticated dashboard** | Summary counts, recent activity, drilldown links |
| **Monitored sources** | List and per-source status |
| **Source detail pages** | Latest run, developments, plots |
| **Comparison run detail pages** | Any past run for a source |
| **Changes page** | Cross-source change log with filters |
| **Issues page** | Review statuses; individual and **bulk** status updates |
| **Development detail / drilldown** | Per-development plots, changes, and context |
| **Scheduled source runs** | Daily `contextual-console:run-scheduled-sources` |
| **Daily summary command and email** | `contextual-console:daily-summary` with optional `--email` |
| **HTML daily summary email** | Source cards, latest run, labelled changes and active-issue tables, issue detail where present; plain text body from the same report |
| **Local daily summary email preview** | Authenticated route in local/development only (see section 4) |
| **Profile daily summary preference** | Per-user opt-in on Profile page (`daily_summary_enabled`) |
| **Database backup command** | `contextual-console:backup-database` (SQLite file DB, S3-compatible disk) |
| **Production smoke test command** | `contextual-console:smoke-test` (config checks, no external HTTP) |

## 4. Daily summary email behaviour

- The scheduler runs **`contextual-console:daily-summary --email`** daily (after source runs; see section 8).
- The mailable sends **HTML** (Blade template `emails.contextual-console.daily-summary-html`) and **plain text** (report `toPlainText()`).
- HTML layout per source: **source card**, **latest run in period** (and overall latest where relevant), **recovery note** when applicable, **Changes** table (added / removed / changed / unchanged), **Active issues** table (errors / warnings / info / total), and **issue detail** lines (including `old_value -> new_value` where the issue context provides them).
- **Preview route**: `/dev/daily-summary-email-preview` (`dev.daily-summary-email-preview`). Registered only when **not** in the `production` environment, behind `auth` and local/development middleware. Intentionally **unavailable in production**.
- **Per-user subscription**: users with `daily_summary_enabled` receive one email each at their **login email** (`users.email`). No separate summary email field.
- If **one or more** users are subscribed, **`CONTEXTUAL_CONSOLE_DAILY_SUMMARY_TO` is not used** for that run.
- If **no** users are subscribed, **`CONTEXTUAL_CONSOLE_DAILY_SUMMARY_TO`** remains the fallback recipient (see `config/contextual_console.php`).
- After deployment, **Kirk should opt in** via Profile (or a one-off DB update) so production does not rely on the env fallback long term. Other users should stay **unsubscribed** unless they choose to opt in.

## 5. Profile / user preference behaviour

- **Profile page** (`/profile`): shows **Name** and **Login email** (read-only display); **Daily summary email** checkbox controls only the signed-in user's `daily_summary_enabled`.
- Saving preferences updates subscription only; email goes to the **login email**, not a separate address field.
- **Not implemented**: password change, roles, admin user management UI, per-source notification preferences, or editing name/email from the UI.

## 6. Monitoring and issue behaviour

- Failed ingests/comparisons create **`source_run_failed`** issues with context (error message, run id, etc.). Inspect context before changing code (see `monitoring-investigation` skill).
- When a **later run succeeds**, older open `source_run_failed` issues for that source are **resolved** automatically; recovered failures are **not** counted as active dashboard/email errors.
- Change-driven and other issues may store **`old_value` / `new_value`** in context; issue rows, source detail, and daily emails can show these as **`old -> new`** transitions where relevant.
- **Active** issue counts (dashboard, email, filters) use statuses **open** and **acknowledged**; **ignored** and **resolved** are excluded where appropriate.
- **Review statuses**: open, acknowledged, ignored, resolved. Issues can be updated on the Issues page individually or via **bulk status** POST.

Investigation order when data looks wrong: **Console issue -> Housebuilder Pack endpoint output -> WordPress/admin data -> raw post meta**. Details in the `monitoring-investigation` skill.

## 7. Data / source model

| Concept | Role |
|---------|------|
| **MonitoredSource** | Stable `key`, HTTP endpoint, ingest settings, schedule; `http_plot_payload_adapter`, pagination fields |
| **DatasetSnapshot** | Persisted payload per successful capture |
| **DatasetComparisonRun** | Compare vs previous snapshot; status, summary counts, finished time |
| **DatasetIssue** | Detected problems (e.g. `source_run_failed`, plot warnings); severity, status, context JSON |
| **Change logs** | Field-level and presence changes between snapshots |
| **Housebuilder / plot payloads** | Normalised plot records under `app/Domains/Housebuilder` |

**HTTP ingestion**: `contextual-console:run-http-plot-source` and scheduled runs fetch JSON from configured endpoints. **`contextualwp_list_contexts`** adapter reads the default contexts array from contextualwp-style wrapped list responses when configured on the source. **`page_per_page`** pagination mode is supported via `http_pagination_mode`, `http_per_page_param`, and `http_per_page` on `MonitoredSource` (see `HttpJsonSourceFetcher`).

## 8. Operational notes

| Time (scheduler timezone, default Europe/London) | Command |
|--------------------------------------------------|---------|
| 06:00 | `contextual-console:run-scheduled-sources` |
| 06:30 | `contextual-console:daily-summary --email` |
| 06:45 | `contextual-console:backup-database` |

- **Stored timestamps** are **UTC** (`config/app.php` `timezone`). Scheduler times and user-facing schedule/display copy use **`schedule_timezone`** / display helpers as configured (default Europe/London).
- **Production database**: SQLite file; backups via configured Laravel **filesystem disk** (S3-compatible / Spaces-style). See `docs/DEPLOYMENT.md` for env var **names** only.
- **Never commit**: database files, credentials, `.env` values, auth headers, app passwords, SSH keys, SMTP secrets, S3/Spaces keys, or private server host/password details.

**Key paths**

| Area | Location |
|------|----------|
| Scheduler | `routes/console.php` |
| Core models / services | `app/Core/` |
| Housebuilder domain | `app/Domains/Housebuilder/` |
| Artisan commands | `app/Console/Commands/` |
| Daily summary mail / builder | `app/Mail/`, `app/Support/DailyMonitoringSummaryBuilder.php` |
| Dev preview routes | `routes/dev.php`, `bootstrap/app.php` |

## 9. Local development and preview notes

- **Daily summary HTML preview**: log in locally, open `/dev/daily-summary-email-preview` (link also on Profile when the route exists). Uses the same builder/report as the command.
- For a **realistic preview** against production-like data, download a production **backup** object from object storage, decompress, and point local **`DB_DATABASE`** at the copy. Follow the restore outline in `docs/DEPLOYMENT.md`. Do **not** commit synced database files or production secrets.
- Preview and dev routes are **disabled in production** by design.

## 10. Deployment / release notes

- Run the **test suite** before deploy (`php artisan test` or project CI equivalent).
- Run **`php artisan migrate`** after deploying branches that add migrations. The daily summary preferences work adds **`daily_summary_enabled`** on `users` (`2026_05_25_100000_add_daily_summary_preferences_to_users_table.php`).
- After deploy, verify: **Profile** checkbox save, **preview route** absent in production / present locally, **`contextual-console:daily-summary --email`** with subscriber vs fallback behaviour, and scheduler cron still installed.
- **Version bumps and git tags** should be separate **release** commits/chores, not mixed into feature branches.
- **Handover update** (this file) should land **before** the release/version bump commit when state has changed.

## 11. Known limitations / not yet implemented

- No per-source email preferences or frequency options
- No Slack / Microsoft Teams alerts
- No admin UI to manage other users' notification preferences
- No password or full profile editing beyond name/email display and daily summary opt-in
- **`CONTEXTUAL_CONSOLE_DAILY_SUMMARY_TO`** env fallback still used when no subscribers (temporary operational bridge)
- No formal role-based permissions beyond **authenticated access** to the app
- Email branding is **text-only** (no logo image yet)

## 12. Recommended next work

1. Deploy and migrate current **daily summary / Profile / HTML email** improvements.
2. **Opt Kirk in** to daily summaries after deploy (Profile UI or controlled DB update).
3. **Verify** the next morning's scheduled email content and recipient list.
4. Later: reduce or remove reliance on **`CONTEXTUAL_CONSOLE_DAILY_SUMMARY_TO`** once subscribers are stable.
5. Later: full account/profile editing; per-source notification preferences.
6. If preparing a wider release: improve contributor and open-source documentation (`README.md`, `LICENSE`, public deployment story).

## 13. Cursor skills reference

Load these in prompts instead of pasting long workflow text into chats:

| Skill | Use when |
|-------|----------|
| **`contextual-console-workflow`** | General development: inspect first, small diffs, Laravel conventions, no unsolicited commits/tags/version bumps |
| **`no-mojibake-ui-text`** | UI copy, Blade, tests, emails, Markdown: plain ASCII punctuation (`-`, `->`, straight quotes) |
| **`monitoring-investigation`** | Source run failures, plot discrepancies, unexpected dashboard or daily summary content |

Full index: [`.cursor/skills-catalogue.md`](../.cursor/skills-catalogue.md).
