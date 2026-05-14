<style>
    :root {
        --cc-bg: #f9fafb;
        --cc-surface: #ffffff;
        --cc-border: #e5e7eb;
        --cc-text: #111827;
        --cc-muted: #6b7280;
        --cc-heading: #374151;
        --cc-link: #2563eb;
        --cc-radius: 10px;
        --cc-shadow: 0 1px 2px rgba(15, 23, 42, 0.06);
    }
    body {
        font-family: ui-sans-serif, system-ui, -apple-system, Segoe UI, Roboto, Helvetica, Arial, "Apple Color Emoji", "Segoe UI Emoji";
        margin: 0;
        padding: 24px 20px 40px;
        color: var(--cc-text);
        background: var(--cc-bg);
        line-height: 1.45;
    }
    .cc-page {
        max-width: 1120px;
        margin: 0 auto;
    }
    .cc-app-bar {
        display: flex;
        flex-wrap: wrap;
        align-items: flex-end;
        justify-content: space-between;
        gap: 12px 20px;
        margin-bottom: 20px;
        padding-bottom: 14px;
        border-bottom: 1px solid var(--cc-border);
    }
    .cc-brand {
        display: flex;
        flex-direction: column;
        gap: 2px;
        min-width: 0;
    }
    .cc-brand__link {
        font-size: 1rem;
        font-weight: 650;
        letter-spacing: -0.02em;
        color: var(--cc-text);
        text-decoration: none;
    }
    .cc-brand__link:hover {
        color: var(--cc-link);
        text-decoration: none;
    }
    .cc-brand__tagline {
        font-size: 0.75rem;
        line-height: 1.35;
    }
    .cc-nav {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        gap: 4px;
        font-size: 0.875rem;
    }
    .cc-nav__link {
        padding: 6px 12px;
        border-radius: 8px;
        color: var(--cc-link);
        text-decoration: none;
        font-weight: 500;
    }
    .cc-nav__link:hover {
        background: #f3f4f6;
        text-decoration: none;
    }
    .cc-nav__link--current {
        color: var(--cc-text);
        background: #eef2ff;
        font-weight: 600;
        cursor: default;
    }
    .cc-nav__link--current:hover {
        background: #eef2ff;
        color: var(--cc-text);
    }
    .cc-page-header {
        margin-bottom: 22px;
    }
    .cc-page-title {
        margin: 0 0 4px;
        font-size: 1.375rem;
        font-weight: 650;
        letter-spacing: -0.02em;
        color: var(--cc-text);
    }
    .cc-page-sub {
        margin: 0;
        font-size: 0.875rem;
        color: var(--cc-muted);
    }
    .cc-back {
        display: inline-block;
        margin-bottom: 12px;
        font-size: 0.8125rem;
    }
    a {
        color: var(--cc-link);
        text-decoration: none;
    }
    a:hover {
        text-decoration: underline;
    }
    .cc-card {
        background: var(--cc-surface);
        border: 1px solid var(--cc-border);
        border-radius: var(--cc-radius);
        box-shadow: var(--cc-shadow);
        margin-bottom: 20px;
        overflow: hidden;
    }
    .cc-card-header {
        padding: 14px 16px 12px;
        border-bottom: 1px solid var(--cc-border);
        background: linear-gradient(to bottom, #fafafa, #fff);
    }
    .cc-card-title {
        margin: 0;
        font-size: 0.9375rem;
        font-weight: 600;
        color: var(--cc-heading);
    }
    .cc-card-desc {
        margin: 4px 0 0;
        font-size: 0.8125rem;
        color: var(--cc-muted);
        font-weight: 400;
    }
    .cc-card-body {
        padding: 0;
    }
    .cc-card-body--padded {
        padding: 16px;
    }
    .muted {
        color: var(--cc-muted);
    }
    .mono {
        font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", "Courier New", monospace;
        font-size: 0.75rem;
    }
    table.cc-table {
        width: 100%;
        border-collapse: collapse;
    }
    .cc-table th,
    .cc-table td {
        text-align: left;
        padding: 10px 14px;
        border-bottom: 1px solid var(--cc-border);
        vertical-align: top;
        font-size: 0.875rem;
    }
    .cc-table thead th {
        font-weight: 600;
        color: var(--cc-heading);
        font-size: 0.8125rem;
        background: #f9fafb;
    }
    .cc-table tbody tr:last-child td {
        border-bottom: none;
    }
    .cc-table tbody tr:hover td {
        background: #fafafa;
    }
    .cc-details {
        margin-top: 6px;
        font-size: 0.75rem;
        line-height: 1.5;
    }
    .cc-empty {
        margin: 0;
        padding: 28px 20px;
        text-align: center;
        font-size: 0.875rem;
    }
    .cc-empty-title {
        margin: 0 0 6px;
        font-size: 0.9375rem;
        font-weight: 600;
        color: var(--cc-heading);
    }
    .cc-kv {
        width: 100%;
        border-collapse: collapse;
    }
    .cc-kv th,
    .cc-kv td {
        padding: 10px 16px;
        border-bottom: 1px solid var(--cc-border);
        vertical-align: top;
        font-size: 0.875rem;
    }
    .cc-kv tr:last-child th,
    .cc-kv tr:last-child td {
        border-bottom: none;
    }
    .cc-kv th {
        width: 200px;
        font-weight: 600;
        color: var(--cc-muted);
        font-size: 0.8125rem;
        background: #fafafa;
    }
    .cc-kv td {
        background: var(--cc-surface);
    }
    .cc-source-meta {
        margin: 0 0 16px;
        font-size: 0.8125rem;
    }
    .cc-stat-row {
        display: flex;
        flex-wrap: wrap;
        gap: 8px;
        align-items: center;
    }
    .cc-badge {
        display: inline-flex;
        align-items: center;
        padding: 2px 9px;
        border-radius: 999px;
        font-size: 0.75rem;
        font-weight: 600;
        letter-spacing: 0.01em;
        border: 1px solid var(--cc-border);
        background: #f3f4f6;
        color: var(--cc-heading);
    }
    .cc-badge--ok {
        border-color: #bbf7d0;
        background: #ecfdf5;
        color: #166534;
    }
    .cc-badge--fail {
        border-color: #fecaca;
        background: #fef2f2;
        color: #991b1b;
    }
    .cc-badge--warn {
        border-color: #fde68a;
        background: #fffbeb;
        color: #92400e;
    }
    .cc-badge--neutral {
        border-color: var(--cc-border);
        background: #f9fafb;
        color: var(--cc-muted);
    }
    .cc-badge--info {
        border-color: #bfdbfe;
        background: #eff6ff;
        color: #1e40af;
    }
    .cc-sev {
        display: inline-flex;
        padding: 2px 8px;
        border-radius: 4px;
        font-size: 0.75rem;
        font-weight: 600;
        letter-spacing: 0.01em;
    }
    .cc-sev--error {
        background: #fef2f2;
        color: #991b1b;
        border: 1px solid #fecaca;
    }
    .cc-sev--warning {
        background: #fffbeb;
        color: #92400e;
        border: 1px solid #fde68a;
    }
    .cc-sev--info {
        background: #eff6ff;
        color: #1e40af;
        border: 1px solid #bfdbfe;
    }
    .cc-sev--default {
        background: #f3f4f6;
        color: var(--cc-heading);
        border: 1px solid var(--cc-border);
    }
    .cc-count-pill {
        display: inline-block;
        min-width: 1.5rem;
        padding: 2px 7px;
        border-radius: 6px;
        background: #f3f4f6;
        border: 1px solid var(--cc-border);
        font-variant-numeric: tabular-nums;
        font-size: 0.8125rem;
        font-weight: 500;
    }
    .cc-time {
        font-variant-numeric: tabular-nums;
        white-space: nowrap;
    }
    .cc-entity-display {
        font-size: 0.875rem;
        line-height: 1.45;
    }
    .cc-entity-display__primary {
        font-weight: 500;
        color: var(--cc-text);
    }
    .cc-entity-display__secondary {
        margin-top: 2px;
        font-size: 0.8125rem;
    }
    .cc-entity-display__tech {
        margin-top: 6px;
        font-size: 0.75rem;
    }
</style>
