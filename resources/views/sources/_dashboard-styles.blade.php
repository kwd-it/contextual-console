<style>
    :root {
        --cc-bg: #eef1f8;
        --cc-surface: #ffffff;
        --cc-border: #e2e8f0;
        --cc-border-strong: #cbd5e1;
        --cc-text: #0f172a;
        --cc-muted: #64748b;
        --cc-heading: #334155;
        --cc-tech: #94a3b8;
        --cc-link: #4f46e5;
        --cc-link-hover: #4338ca;
        --cc-accent: #4f46e5;
        --cc-accent-soft: #eef2ff;
        --cc-accent-border: rgba(79, 70, 229, 0.2);
        --cc-radius: 12px;
        --cc-radius-sm: 8px;
        --cc-shadow: 0 1px 2px rgba(15, 23, 42, 0.04), 0 4px 16px rgba(15, 23, 42, 0.07);
        --cc-shadow-inset: inset 0 1px 0 rgba(255, 255, 255, 0.75);
    }
    body {
        font-family: ui-sans-serif, system-ui, -apple-system, Segoe UI, Roboto, Helvetica, Arial, "Apple Color Emoji", "Segoe UI Emoji";
        margin: 0;
        padding: 24px 20px 48px;
        color: var(--cc-text);
        background-color: var(--cc-bg);
        background-image:
            radial-gradient(1000px 520px at 8% -8%, rgba(99, 102, 241, 0.07), transparent 55%),
            radial-gradient(800px 420px at 96% 0%, rgba(59, 130, 246, 0.05), transparent 48%);
        line-height: 1.5;
        -webkit-font-smoothing: antialiased;
    }
    .cc-page {
        max-width: 1180px;
        margin: 0 auto;
    }
    .cc-app-bar {
        display: flex;
        flex-wrap: wrap;
        align-items: flex-end;
        justify-content: space-between;
        gap: 14px 24px;
        margin-bottom: 26px;
        padding: 16px 20px 18px;
        background: var(--cc-surface);
        border: 1px solid var(--cc-border);
        border-radius: var(--cc-radius);
        box-shadow: var(--cc-shadow), var(--cc-shadow-inset);
        position: relative;
    }
    .cc-app-bar::after {
        content: "";
        position: absolute;
        left: 18px;
        right: 18px;
        bottom: 0;
        height: 3px;
        border-radius: 3px 3px 0 0;
        background: linear-gradient(90deg, var(--cc-accent) 0%, rgba(79, 70, 229, 0.35) 42%, rgba(59, 130, 246, 0.12) 100%);
        opacity: 0.9;
    }
    .cc-brand {
        display: flex;
        flex-direction: column;
        gap: 3px;
        min-width: 0;
    }
    .cc-brand__link {
        font-size: 1.0625rem;
        font-weight: 600;
        letter-spacing: -0.025em;
        color: var(--cc-text);
        text-decoration: none;
    }
    .cc-brand__link:hover {
        color: var(--cc-link);
        text-decoration: none;
    }
    .cc-brand__tagline {
        font-size: 0.75rem;
        line-height: 1.4;
    }
    .cc-nav {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        gap: 6px;
        font-size: 0.875rem;
    }
    .cc-nav__link {
        padding: 7px 14px;
        border-radius: var(--cc-radius-sm);
        color: var(--cc-link);
        text-decoration: none;
        font-weight: 500;
        border: 1px solid transparent;
        transition: background-color 0.12s ease, border-color 0.12s ease, color 0.12s ease;
    }
    .cc-nav__link:hover {
        background: #f1f5f9;
        text-decoration: none;
    }
    .cc-nav__link--current {
        color: #312e81;
        background: var(--cc-accent-soft);
        font-weight: 600;
        cursor: default;
        border-color: var(--cc-accent-border);
        box-shadow: 0 1px 2px rgba(79, 70, 229, 0.06);
    }
    .cc-nav__link--current:hover {
        background: var(--cc-accent-soft);
        color: #312e81;
    }
    .cc-page-header {
        margin-bottom: 24px;
    }
    .cc-page-title {
        margin: 0 0 6px;
        font-size: 1.5rem;
        font-weight: 600;
        letter-spacing: -0.025em;
        color: var(--cc-text);
        line-height: 1.2;
    }
    .cc-page-sub {
        margin: 0;
        max-width: 68ch;
        font-size: 0.9375rem;
        color: var(--cc-muted);
        line-height: 1.55;
    }
    .cc-back {
        display: inline-block;
        margin-bottom: 14px;
        font-size: 0.8125rem;
    }
    a {
        color: var(--cc-link);
        text-decoration: none;
    }
    a:hover {
        color: var(--cc-link-hover);
        text-decoration: underline;
    }
    .cc-card {
        background: var(--cc-surface);
        border: 1px solid var(--cc-border);
        border-radius: var(--cc-radius);
        box-shadow: var(--cc-shadow), var(--cc-shadow-inset);
        margin-bottom: 22px;
        overflow: hidden;
    }
    .cc-card-header {
        padding: 16px 18px 14px;
        border-bottom: 1px solid var(--cc-border);
        background: linear-gradient(180deg, #f8fafc 0%, #fafbfc 100%);
    }
    .cc-card-title {
        margin: 0;
        font-size: 0.9375rem;
        font-weight: 600;
        color: var(--cc-heading);
        letter-spacing: -0.01em;
    }
    .cc-card-desc {
        margin: 6px 0 0;
        font-size: 0.8125rem;
        color: var(--cc-muted);
        font-weight: 400;
        line-height: 1.45;
    }
    .cc-card-body {
        padding: 0;
        overflow-x: auto;
    }
    .cc-card-body--padded {
        padding: 18px;
        overflow-x: visible;
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
        min-width: 920px;
        border-collapse: collapse;
    }
    .cc-table th,
    .cc-table td {
        text-align: left;
        padding: 12px 16px;
        border-bottom: 1px solid var(--cc-border);
        vertical-align: top;
        font-size: 0.875rem;
    }
    .cc-table thead th {
        font-weight: 600;
        color: var(--cc-heading);
        font-size: 0.75rem;
        text-transform: none;
        letter-spacing: 0.02em;
        background: #f8fafc;
        border-bottom: 1px solid var(--cc-border-strong);
        white-space: nowrap;
    }
    .cc-table tbody tr:last-child td {
        border-bottom: none;
    }
    .cc-table tbody tr:hover td {
        background: #f8fafc;
    }
    .cc-details {
        margin-top: 6px;
        font-size: 0.75rem;
        line-height: 1.5;
        color: var(--cc-muted);
    }
    .cc-empty {
        margin: 0;
        padding: 32px 22px;
        text-align: center;
        font-size: 0.875rem;
    }
    .cc-empty-title {
        margin: 0 0 8px;
        font-size: 1rem;
        font-weight: 600;
        color: var(--cc-heading);
    }
    .cc-kv {
        width: 100%;
        border-collapse: collapse;
    }
    .cc-kv th,
    .cc-kv td {
        padding: 12px 18px;
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
        background: #f8fafc;
    }
    .cc-kv td {
        background: var(--cc-surface);
    }
    .cc-source-meta {
        margin: 0 0 18px;
        font-size: 0.8125rem;
        padding: 10px 14px;
        background: #f8fafc;
        border: 1px solid var(--cc-border);
        border-radius: var(--cc-radius-sm);
        display: inline-block;
        max-width: 100%;
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
        padding: 4px 11px;
        border-radius: 999px;
        font-size: 0.75rem;
        font-weight: 600;
        letter-spacing: 0.02em;
        border: 1px solid var(--cc-border);
        background: #f1f5f9;
        color: var(--cc-heading);
        line-height: 1.2;
    }
    .cc-badge--ok {
        border-color: #86efac;
        background: #ecfdf5;
        color: #14532d;
    }
    .cc-badge--fail {
        border-color: #fca5a5;
        background: #fef2f2;
        color: #991b1b;
    }
    .cc-badge--warn {
        border-color: #fcd34d;
        background: #fffbeb;
        color: #92400e;
    }
    .cc-badge--neutral {
        border-color: var(--cc-border);
        background: #f8fafc;
        color: var(--cc-muted);
    }
    .cc-badge--info {
        border-color: #93c5fd;
        background: #eff6ff;
        color: #1e3a8a;
    }
    .cc-sev {
        display: inline-flex;
        align-items: center;
        padding: 4px 11px;
        border-radius: 999px;
        font-size: 0.6875rem;
        font-weight: 600;
        letter-spacing: 0.04em;
        text-transform: uppercase;
        line-height: 1.25;
        white-space: nowrap;
    }
    .cc-sev--error {
        background: #fef2f2;
        color: #991b1b;
        border: 1px solid #fca5a5;
    }
    .cc-sev--warning {
        background: #fffbeb;
        color: #92400e;
        border: 1px solid #fcd34d;
    }
    .cc-sev--info {
        background: #eff6ff;
        color: #1e3a8a;
        border: 1px solid #93c5fd;
    }
    .cc-sev--default {
        background: #f1f5f9;
        color: var(--cc-heading);
        border: 1px solid var(--cc-border);
    }
    .cc-count-pill {
        display: inline-block;
        min-width: 1.5rem;
        padding: 3px 8px;
        border-radius: 6px;
        background: #f8fafc;
        border: 1px solid var(--cc-border);
        font-variant-numeric: tabular-nums;
        font-size: 0.75rem;
        font-weight: 500;
        color: var(--cc-heading);
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
        font-weight: 600;
        color: var(--cc-text);
    }
    .cc-entity-display__secondary {
        margin-top: 3px;
        font-size: 0.8125rem;
    }
    .cc-entity-display__tech {
        margin-top: 8px;
        font-size: 0.6875rem;
        color: var(--cc-tech);
    }
    .cc-entity-display__tech.muted {
        color: var(--cc-tech);
    }
    @media (max-width: 720px) {
        body {
            padding: 16px 14px 40px;
        }
        .cc-page-title {
            font-size: 1.3125rem;
        }
        .cc-app-bar {
            padding: 14px 16px 16px;
        }
        .cc-kv th {
            width: 42%;
            min-width: 7.5rem;
        }
    }
</style>
