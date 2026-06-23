# Contextual Console

## 1. Title and purpose

**Contextual Console** monitors website and housebuilder data. It ingests configured HTTP (and related) sources, stores **dataset snapshots**, runs **dataset comparison runs**, records **change logs** and **dataset issues**, and presents results on an authenticated web dashboard. A scheduled **daily summary** command can print or email a monitoring summary. Optional **database backup** archives the production SQLite database to S3-compatible object storage.

The first product vertical is housebuilders (`app/Domains/Housebuilder`). Shared monitoring platform code lives under `app/Core`.

For deployment steps and env var names (not values), see `docs/DEPLOYMENT.md`. For day-to-day operator tasks (sources, issues, notifications), see `docs/OPERATIONS.md`. For release history, see `CHANGELOG.md`.

## 2. How to use this handover

This file is the **version-controlled project handover** for Contextual Console.

- Update it **before release/version bump commits** when project state, behaviour, or operational expectations have materially changed.
- Use it at the start of a new ChatGPT or Cursor session to understand the current system and continue work safely.
- **Repeatable Cursor behaviour** (workflow, UI text rules, monitoring investigation order) lives in `.cursor/skills/` and `.cursor/skills-catalogue.md`. Do not duplicate those skills here; reference them in prompts instead.

## 3. Current project state

**Current release: v1.2.0** (see `CHANGELOG.md`).

**v1.2.0** adds **Housebuilder Pack asset completeness** support on the **v1.1.x** baseline. Console preserves the new plot payload fields, normalises completeness string values during HTTP ingest, and raises conservative warnings when upstream data reports actionable **`missing`** statuses (required floor plan missing; linked development intro media missing). It treats **`unknown`** as non-actionable, does not warn just because boolean `has_*` flags are false, and does not track asset completeness fields for plot change detection. **`last_modified_by`** remains display-only metadata.

Live HTTP payloads need **ContextualWP Housebuilder Pack v0.4.6 or later** for the asset completeness fields to appear. Whether a monitored source actually produces live missing-asset warnings still depends on Housebuilder Pack mapping/rules and real source data; deploying Console **v1.2.0** alone does not guarantee new warnings on any particular site until upstream payloads send actionable **`missing`** statuses.

**v1.1.1** completed the `/admin/users` UI actions from the **v1.1.0** admin and profile work: create user with an initial password (no invite emails or self-service password reset), immutable login email, reset password for other users, safe delete for eligible users, and existing self/last-admin protections.

Implemented today:

| Area | Notes |
|------|--------|
| **Authenticated dashboard** | Summary counts, recent activity, drilldown links, daily summary subscription warning when applicable |
| **Monitored sources** | List with **source health** badges; per-source status |
| **Source detail pages** | Latest run, developments, plots; **Run now** when `endpoint_url` is set; **failed run diagnostics** when the latest run failed |
| **Comparison run detail pages** | Any past run for a source; issue links to issue detail |
| **Issue detail pages** | `/issues/{issue}` with summary, context, suggested checks, status updates |
| **Changes page** | Cross-source change log with filters |
| **Issues page** | Review statuses; individual and **bulk** status updates; links to issue detail |
| **Development detail / drilldown** | Per-development plots, changes, and context; issue links |
| **Issue links** | Dashboard, sources, runs, developments, Issues list, emails (where applicable) |
| **Scheduled source runs** | Daily `contextual-console:run-scheduled-sources` |
| **Daily summary command and email** | `contextual-console:daily-summary` with optional `--email` |
| **HTML daily summary email** | Source cards, latest run, labelled changes and active-issue tables, issue detail where present; plain text body from the same report |
| **Local daily summary email preview** | Authenticated route in local/development only (see section 4) |
| **Profile daily summary preference** | Per-user opt-in on Profile page (`daily_summary_enabled`); **Send test email** to login address only |
| **Profile account settings** | Signed-in users can update **name** and **password** (current password required); login email is read-only |
| **User roles** | **Admin** and **operator**; admin-only routes protected by middleware |
| **Admin Users page** | `/admin/users` (admin only): **Create user** form (name, login email, role, initial password, daily summary subscription). Login email is immutable after creation. Initial password is set manually because invite emails and self-service password reset are not implemented. User list supports edit (name, role, subscription), **Reset password** for other users (compact details control), and **Delete** for eligible users. Lists name, login email, role, subscription, and last sign-in. Self-deletion, self-demotion, last-admin deletion, and last-admin demotion are blocked; an admin's own role is read-only on the page |
| **Last sign-in tracking** | Updated on successful login; shown on the Admin Users page in the display timezone |
| **Admin safety protections** | Blocks self-deletion, self-demotion, last admin deletion, and last admin demotion; an admin's own role is read-only on the Users page |
| **User provisioning CLI** | `contextual-console:create-admin-user`, `contextual-console:promote-user-to-admin` |
| **UI timestamps** | Stored UTC; displayed in `APP_SCHEDULE_TIMEZONE` (default Europe/London) |
| **Database backup command** | `contextual-console:backup-database` (SQLite file DB, S3-compatible disk) |
| **Production smoke test command** | `contextual-console:smoke-test` (config checks, no external HTTP; verifies each `auth_token_env_key` resolves via `env()` at runtime) |
| **Operator documentation** | `docs/OPERATIONS.md` (deploy checklist, sources, issue investigation, notifications) |

## 4. Daily summary email behaviour

- The scheduler runs **`contextual-console:daily-summary --email`** daily (after source runs; see section 8).
- The mailable sends **HTML** (Blade template `emails.contextual-console.daily-summary-html`) and **plain text** (report `toPlainText()`).
- HTML layout per source: **source card**, **latest run in period** (and overall latest where relevant), **recovery note** when applicable, **Changes** table (added / removed / changed / unchanged), **Active issues** table (errors / warnings / info / total), and **issue detail** lines (including `old_value -> new_value` where the issue context provides them).
- **Preview route**: `/dev/daily-summary-email-preview` (`dev.daily-summary-email-preview`). Registered only when **not** in the `production` environment, behind `auth` and local/development middleware. Intentionally **unavailable in production**.
- **Per-user subscription**: users with `daily_summary_enabled` receive one email each at their **login email** (`users.email`). No separate summary email field.
- If **one or more** users are subscribed, **`CONTEXTUAL_CONSOLE_DAILY_SUMMARY_TO` is not used** for that run.
- If **no** users are subscribed, **`CONTEXTUAL_CONSOLE_DAILY_SUMMARY_TO`** remains the fallback recipient (see `config/contextual_console.php`).
- **Subscription warnings** on dashboard and Profile when nobody is subscribed (fallback possible or critical if no fallback).
- **Test email** on Profile sends the current report to the signed-in user's login email only (not fallback or other subscribers).
- After deployment, at least one operator should **opt in via Profile** (or a controlled DB update) so production does not rely on the env fallback long term.

## 5. Profile / user preference behaviour

- **Profile page** (`/profile`): signed-in users can update **name** and **password** (current password required). **Login email** is read-only (set when the account is created).
- **Daily summary email** checkbox controls only the signed-in user's `daily_summary_enabled`.
- Saving preferences updates the subscription only; email goes to the **login email**, not a separate address field.
- **Admins** manage accounts on the **Users** page (`/admin/users`): create users with an **initial password** (no invite emails or self-service password reset); edit name, role, and daily summary subscription; reset another user's password; delete eligible users. Login email is set at creation and is read-only in the UI. Self-deletion, self-demotion, last-admin deletion, and last-admin demotion remain blocked.
- **Not implemented**: invite emails, forgot-password or emailed reset links, self-service registration, per-source notification preferences, or login email changes from the UI.

## 6. Monitoring and issue behaviour

- Failed ingests/comparisons create **`source_run_failed`** issues with context (error message, run id, etc.). Inspect context before changing code (see `monitoring-investigation` skill).
- When a **later run succeeds**, older open `source_run_failed` issues for that source are **resolved** automatically; recovered failures are **not** counted as active dashboard/email errors.
- Change-driven and other issues may store **`old_value` / `new_value`** in context; issue rows, source detail, and daily emails can show these as **`old -> new`** transitions where relevant.
- **Active** issue counts (dashboard, email, filters) use statuses **open** and **acknowledged**; **ignored** and **resolved** are excluded where appropriate.
- **Review statuses**: open, acknowledged, ignored, resolved. Issues can be updated on the Issues page individually or via **bulk status** POST.
- **Asset completeness warnings** (Housebuilder Pack plot payloads, **v0.4.6+**): Console stores `has_floor_plan`, `floor_plan_required`, `floor_plan_completeness_status`, `has_intro_video`, `has_intro_image`, `intro_media_type`, and `intro_media_completeness_status` on snapshots when returned. Warnings are raised only when upstream sends actionable **`missing`** statuses:
  - **Required floor plan is missing** when `floor_plan_required === true` and `floor_plan_completeness_status === "missing"`.
  - **Linked development intro media is missing** when `intro_media_completeness_status === "missing"`.
  - **`unknown`** (and other non-`missing` values) are deliberately non-actionable.
  - **`has_floor_plan` / `has_intro_video` / `has_intro_image` being false alone does not warn.**
  - Asset completeness fields are **not** plot change-detection fields; **`last_modified_by`** remains display-only.
  - Whether a live monitored source produces these warnings depends on Housebuilder Pack rules and real payload data, not Console version alone.

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
| **Asset completeness fields** | When **Housebuilder Pack v0.4.6+** returns them: stored on snapshots; conservative plot warnings for actionable **`missing`** completeness statuses only |
| **Plot display metadata** | **`last_modified_by`** shown as **Last modified by: {label}** when present; display-only (not change detection or issues) |

**HTTP ingestion**: `contextual-console:run-http-plot-source` and scheduled runs fetch JSON from configured endpoints. **`contextualwp_list_contexts`** adapter reads the default contexts array from contextualwp-style wrapped list responses when configured on the source. **`page_per_page`** pagination mode is supported via `http_pagination_mode`, `http_per_page_param`, and `http_per_page` on `MonitoredSource` (see `HttpJsonSourceFetcher`). Asset completeness string fields are lowercased during normalisation. Live payloads need **Housebuilder Pack v0.4.6 or later** for asset completeness fields.

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
- On each production deploy: **`php artisan optimize:clear`**, then **`route:cache`** and **`view:cache`**; **do not** run **`config:cache`** while source auth uses runtime `env()` (see `docs/DEPLOYMENT.md` and `docs/OPERATIONS.md`).
- Run **`php artisan migrate`** after deploying branches that add migrations.
- After deployment, verify: **Profile** name and password and daily summary email checkbox, **Admin Users** page for admin accounts, **preview route** absent in production / present locally, **`contextual-console:daily-summary --email`** with subscriber vs fallback behaviour, **`contextual-console:smoke-test`**, and scheduler cron still installed.
- **Version bumps and git tags** should be separate **release** commits/chores, not mixed into feature branches.
- **Handover update** (this file) should land **before** the release/version bump commit when state has changed.

## 11. Known limitations / not yet implemented

- No invite emails or self-service account registration
- No forgot-password or password-reset email flow (admins set an **initial password** when creating a user and can reset another user's password on the Users page; there is no emailed reset link)
- No per-source email preferences or frequency options
- No Slack / Microsoft Teams alerts
- **`CONTEXTUAL_CONSOLE_DAILY_SUMMARY_TO`** env fallback still used when no subscribers (temporary operational bridge)
- No formal RBAC beyond **admin** and **operator** roles
- Email branding is **text-only** (no logo image yet)

## 12. Recommended next work

1. Deploy using the checklist in **`docs/OPERATIONS.md`** / **`docs/DEPLOYMENT.md`**.
2. Have operators **opt in** to daily summaries on Profile where appropriate; admins can manage subscriptions on the Users page when needed.
3. **Verify** the next scheduled email content and recipient list (and Profile test email after mail changes).
4. Later: reduce or remove reliance on **`CONTEXTUAL_CONSOLE_DAILY_SUMMARY_TO`** once subscribers are stable.
5. Later: per-source notification preferences; invite or forgot-password email flows.
6. If preparing a wider release: improve contributor and open-source documentation (`README.md`, `LICENSE`, public deployment story).

## 13. Cursor skills reference

Load these in prompts instead of pasting long workflow text into chats:

| Skill | Use when |
|-------|----------|
| **`contextual-console-workflow`** | General development: inspect first, small diffs, Laravel conventions, no unsolicited commits/tags/version bumps |
| **`no-mojibake-ui-text`** | UI copy, Blade, tests, emails, Markdown: plain ASCII punctuation (`-`, `->`, straight quotes) |
| **`monitoring-investigation`** | Source run failures, plot discrepancies, unexpected dashboard or daily summary content |

Full index: [`.cursor/skills-catalogue.md`](../.cursor/skills-catalogue.md).
