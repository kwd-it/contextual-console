# Contextual Console – Development & Release Workflow

This document describes the intended workflow for developing, testing, and releasing **Contextual Console**. It focuses on process, not product-specific behaviour.

### Note on v1.0

When the project reaches a first stable release (for example **v1.0.0**), stability matters as much as new capability. Prefer small, reviewable changes, clear intent on each branch, and explicit consideration of regressions before merge.

### Architecture (high-level)

Contextual Console is a **Laravel** observability platform.

- **`app/Core`** — reusable platform logic (shared models, cross-cutting services, infrastructure that should stay vertical-agnostic).
- **`app/Domains/*`** — domain-specific logic (for example **Housebuilder** today). Additional verticals should follow the same pattern.

Core should remain coherent and usable without pulling domain code into shared abstractions. Domain modules depend on Core (and Laravel), not the other way around. Keep boundaries visible: shared contracts in Core, industry assumptions and workflows in Domains.

---

## A. Routine application development

Treat **README.md** as the authoritative user-facing contract: described behaviour, setup steps, and security-relevant claims there should match the running product.

### Branch strategy

Use **one primary intent per branch**. Name the branch after that intent.

**Prefixes:**

| Prefix      | Use for |
|------------|---------|
| `docs/`    | Documentation only |
| `feat/`    | New or improved user-facing behaviour |
| `fix/`     | Bug fix for behaviour already on `main` |
| `refactor/`| Internal restructuring, no intended behaviour change |
| `test/`    | Tests only |
| `chore/`   | Maintenance or tooling |

Example: `feat/change-log-export`

### 0. Feature definition (before branching)

Capture a short definition so branching and AI-assisted work stay scoped.

| Field | Content |
|--------|---------|
| **Goal** | What problem this solves and for whom. |
| **Input** | What triggers or feeds the change (HTTP routes, jobs, models, external payloads). |
| **Output** | Observable result (API response, UI, DB state, log lines, events). |
| **Out of scope** | Explicit non-goals so scope does not creep during iteration. |

Revise this if discovery changes the shape of the work; keep it honest and small.

### 1. Decide what to build next

Identify the single biggest pain point or improvement opportunity.

When using an AI assistant (ChatGPT, Cursor Agent, etc.), share only what is needed:

- Current **README** (contract and setup).
- The smallest **code excerpt** or file list that is enough to reason about the change (routes, service, migration, test).
- **CHANGELOG** if the change relates to something already shipped.
- A short note on what feels weakest or riskiest right now.

**Prioritise:** clear user-facing value; portfolio-quality delivery over chasing every edge case in one pass.

### 2. Create a branch

Keep one intent per branch (see prefixes above). Rename or split if the branch starts doing two unrelated things.

### 3. Implement the task (AI-assisted)

Use **Cursor** (or similar) with a **focused prompt** that covers only the current task. Include **constraints** explicitly, for example:

- Touch only `app/Core/...` or only `app/Domains/Housebuilder/...`.
- No new dependencies unless justified.
- Match existing patterns (Pest tests, service layout, naming).

**Iterate freely:** prompt, change, run tests or manual checks, refine. Do not worry about commit message polish during iteration.

### 4. Review changes (before testing)

After Cursor completes a task, review the actual file changes before running tests.

Minimum checks:

- `git status`
- `git diff --stat`
- Inspect any unexpected files with `git diff`

Guidance:

- Do not assume all AI-generated changes should be kept.
- Keep meaningful changes (feature logic, relevant tests, docs).
- Remove unrelated or accidental changes before proceeding.

### 5. Test the result

After review:

- Run automated tests (`composer test` / `php artisan test` / Pest as configured in the repo).
- Manually exercise critical paths (browser, API client, queue worker) when the change touches UX, auth, or async behaviour.

**Regression scope:** decide whether a **smoke pass** (happy paths that prove nothing obvious broke) is enough, or whether a **broader pass** is needed for larger or riskier changes (data migrations, auth, billing-like flows, etc.). If you maintain a checklist, use it; otherwise decide explicitly each time.

Fixes found during testing do not change the eventual **commit type** (see below).

### 6. Impact review before merge

Before merging to `main`:

- Summarise **user-facing and API impact** (routes, responses, auth, caching, queues, events).
- Confirm **README** and, when shipping, **CHANGELOG** still match reality.
- Confirm **no unintended breaking changes**, or that they are versioned and called out in release notes.

### 7. Commit the feature or fix

Once behaviour is correct, choose the commit type from the **final** outcome, not iteration history:

- **`feat:`** — capability added or meaningfully improved.
- **`fix:`** — correcting behaviour that already existed on `main`.

One well-scoped commit is perfectly acceptable.

Example:

```text
feat(change-log): include actor id on recorded changes
```

### 8. Decide version bump (on the branch)

Follow **semantic versioning** in spirit:

| Bump   | When |
|--------|------|
| **MAJOR** | Breaking changes to public API, config, or documented behaviour. |
| **MINOR** | New or improved backward-compatible capability. |
| **PATCH** | Bug fixes to released behaviour. |

Document any intentional breakage in **CHANGELOG** and briefly in **README** if users must act.

### 9. Bump version and docs

When cutting a release, update as applicable for this project:

- **CHANGELOG.md** — what shipped, user-visible.
- **README.md** — only if version or upgrade steps are stated there.
- **Version metadata** — wherever the project records it (for example `composer.json` `version` if you use it, or release tags only).

Prefer a **separate** release-style commit, for example:

```text
chore(release): v0.2.2
```

### 10. Merge, tag, clean up

Merge the branch into `main`, **tag** the release on `main` if you version that way, delete the feature branch.

---

## B. Domain-specific work (`app/Domains/*`)

Domain folders hold vertical-specific services, rules, and integrations. **Core** stays free of industry wording and one-off assumptions; those belong under **Domains**.

When working in a domain module, share with an assistant:

- Domain **README** or inline docblocks if you maintain them.
- The **smallest** relevant slice (service class, DTO, migration, request rules).
- **Target user and problem** (who operates this, what failure mode you are preventing).
- **Quality expectations** (validation, idempotency, behaviour when data is missing).

Avoid pasting the entire application or huge dumps unless debugging a specific integration.

---

## Notes

- **CHANGELOG** documents what shipped, not how it was built.
- **Commit types** describe the difference to `main`, not internal iteration.
- **Local debugging:** use Laravel’s usual levers (`APP_DEBUG`, `LOG_LEVEL`, Telescope if installed) during investigation; do not leave verbose logging or debug endpoints enabled in committed code paths meant for production.

This document is intentionally **domain-agnostic** about any one vertical; deep sector rules belong next to the domain code or in dedicated domain docs if you add them later.
