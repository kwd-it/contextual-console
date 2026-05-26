<script>
    (function () {
        var storageKey = 'cc-theme';
        var stored = localStorage.getItem(storageKey);
        var theme = stored === 'light' || stored === 'dark' || stored === 'system' ? stored : 'system';
        var root = document.documentElement;

        if (theme === 'light' || theme === 'dark') {
            root.dataset.theme = theme;
        } else {
            root.removeAttribute('data-theme');
        }
    })();
</script>
<style>
    :root {
        /* Page & surfaces */
        --cc-bg: #eef1f8;
        --cc-surface: #ffffff;
        --cc-surface-muted: #f8fafc;
        --cc-surface-subtle: #fafbfc;
        --cc-surface-hover: #f1f5f9;
        --cc-table-header-bg: #f8fafc;

        /* Borders */
        --cc-border: #e2e8f0;
        --cc-border-strong: #cbd5e1;

        /* Text */
        --cc-text: #0f172a;
        --cc-muted: #64748b;
        --cc-heading: #334155;
        --cc-tech: #94a3b8;
        --cc-on-accent: #ffffff;
        --cc-nav-active-text: #312e81;

        /* Links & accent */
        --cc-link: #4f46e5;
        --cc-link-hover: #4338ca;
        --cc-accent: #4f46e5;
        --cc-accent-soft: #eef2ff;
        --cc-accent-border: rgba(79, 70, 229, 0.2);
        --cc-accent-shadow: rgba(79, 70, 229, 0.06);
        --cc-accent-gradient-mid: rgba(79, 70, 229, 0.35);
        --cc-accent-gradient-end: rgba(59, 130, 246, 0.12);
        --cc-bg-glow-indigo: rgba(99, 102, 241, 0.07);
        --cc-bg-glow-blue: rgba(59, 130, 246, 0.05);

        /* Focus */
        --cc-focus-ring-color: var(--cc-link);
        --cc-focus-ring-width: 2px;
        --cc-focus-ring-offset: 2px;

        /* Status: success */
        --cc-success-bg: #ecfdf5;
        --cc-success-text: #14532d;
        --cc-success-border: #86efac;

        /* Status: error */
        --cc-error-bg: #fef2f2;
        --cc-error-text: #991b1b;
        --cc-error-border: #fca5a5;

        /* Status: warning */
        --cc-warning-bg: #fffbeb;
        --cc-warning-text: #92400e;
        --cc-warning-border: #fcd34d;

        /* Status: info */
        --cc-info-bg: #eff6ff;
        --cc-info-text: #1e3a8a;
        --cc-info-border: #93c5fd;

        /* Status: neutral */
        --cc-neutral-bg: #f1f5f9;

        /* Layout & elevation */
        --cc-radius: 12px;
        --cc-radius-sm: 8px;
        --cc-shadow: 0 1px 2px rgba(15, 23, 42, 0.04), 0 4px 16px rgba(15, 23, 42, 0.07);
        --cc-shadow-inset: inset 0 1px 0 rgba(255, 255, 255, 0.75);
    }

    /* System mode: follow OS appearance unless light is forced. */
    @media (prefers-color-scheme: dark) {
        :root:not([data-theme="light"]) {
            /* Page & surfaces */
            --cc-bg: #0c1017;
            --cc-surface: #151b26;
            --cc-surface-muted: #1c2433;
            --cc-surface-subtle: #181f2c;
            --cc-surface-hover: #252f42;
            --cc-table-header-bg: #1a2230;

            /* Borders */
            --cc-border: #2a3548;
            --cc-border-strong: #3b4a63;

            /* Text */
            --cc-text: #e8edf5;
            --cc-muted: #94a3b8;
            --cc-heading: #cbd5e1;
            --cc-tech: #64748b;
            --cc-on-accent: #f8fafc;
            --cc-nav-active-text: #c7d2fe;

            /* Links & accent */
            --cc-link: #818cf8;
            --cc-link-hover: #a5b4fc;
            --cc-accent: #818cf8;
            --cc-accent-soft: rgba(99, 102, 241, 0.18);
            --cc-accent-border: rgba(129, 140, 248, 0.35);
            --cc-accent-shadow: rgba(0, 0, 0, 0.25);
            --cc-accent-gradient-mid: rgba(129, 140, 248, 0.45);
            --cc-accent-gradient-end: rgba(96, 165, 250, 0.15);
            --cc-bg-glow-indigo: rgba(99, 102, 241, 0.12);
            --cc-bg-glow-blue: rgba(59, 130, 246, 0.08);

            /* Focus */
            --cc-focus-ring-color: #a5b4fc;

            /* Status: success */
            --cc-success-bg: #052e1a;
            --cc-success-text: #86efac;
            --cc-success-border: #166534;

            /* Status: error */
            --cc-error-bg: #3f1515;
            --cc-error-text: #fca5a5;
            --cc-error-border: #991b1b;

            /* Status: warning */
            --cc-warning-bg: #3d2808;
            --cc-warning-text: #fcd34d;
            --cc-warning-border: #b45309;

            /* Status: info */
            --cc-info-bg: #172554;
            --cc-info-text: #93c5fd;
            --cc-info-border: #1d4ed8;

            /* Status: neutral */
            --cc-neutral-bg: #1e293b;

            /* Layout & elevation */
            --cc-shadow: 0 1px 2px rgba(0, 0, 0, 0.35), 0 4px 16px rgba(0, 0, 0, 0.45);
            --cc-shadow-inset: inset 0 1px 0 rgba(255, 255, 255, 0.06);
        }
    }

    /* Explicit dark mode (also when OS prefers light). */
    [data-theme="dark"] {
        /* Page & surfaces */
        --cc-bg: #0c1017;
        --cc-surface: #151b26;
        --cc-surface-muted: #1c2433;
        --cc-surface-subtle: #181f2c;
        --cc-surface-hover: #252f42;
        --cc-table-header-bg: #1a2230;

        /* Borders */
        --cc-border: #2a3548;
        --cc-border-strong: #3b4a63;

        /* Text */
        --cc-text: #e8edf5;
        --cc-muted: #94a3b8;
        --cc-heading: #cbd5e1;
        --cc-tech: #64748b;
        --cc-on-accent: #f8fafc;
        --cc-nav-active-text: #c7d2fe;

        /* Links & accent */
        --cc-link: #818cf8;
        --cc-link-hover: #a5b4fc;
        --cc-accent: #818cf8;
        --cc-accent-soft: rgba(99, 102, 241, 0.18);
        --cc-accent-border: rgba(129, 140, 248, 0.35);
        --cc-accent-shadow: rgba(0, 0, 0, 0.25);
        --cc-accent-gradient-mid: rgba(129, 140, 248, 0.45);
        --cc-accent-gradient-end: rgba(96, 165, 250, 0.15);
        --cc-bg-glow-indigo: rgba(99, 102, 241, 0.12);
        --cc-bg-glow-blue: rgba(59, 130, 246, 0.08);

        /* Focus */
        --cc-focus-ring-color: #a5b4fc;

        /* Status: success */
        --cc-success-bg: #052e1a;
        --cc-success-text: #86efac;
        --cc-success-border: #166534;

        /* Status: error */
        --cc-error-bg: #3f1515;
        --cc-error-text: #fca5a5;
        --cc-error-border: #991b1b;

        /* Status: warning */
        --cc-warning-bg: #3d2808;
        --cc-warning-text: #fcd34d;
        --cc-warning-border: #b45309;

        /* Status: info */
        --cc-info-bg: #172554;
        --cc-info-text: #93c5fd;
        --cc-info-border: #1d4ed8;

        /* Status: neutral */
        --cc-neutral-bg: #1e293b;

        /* Layout & elevation */
        --cc-shadow: 0 1px 2px rgba(0, 0, 0, 0.35), 0 4px 16px rgba(0, 0, 0, 0.45);
        --cc-shadow-inset: inset 0 1px 0 rgba(255, 255, 255, 0.06);
    }

    /* Explicit light mode when OS prefers dark (:root defaults + media :not above). */
    [data-theme="light"] {
        color-scheme: light;
    }

    body {
        font-family: ui-sans-serif, system-ui, -apple-system, Segoe UI, Roboto, Helvetica, Arial, "Apple Color Emoji", "Segoe UI Emoji";
        margin: 0;
        padding: 24px 20px 48px;
        color: var(--cc-text);
        background-color: var(--cc-bg);
        background-image:
            radial-gradient(1000px 520px at 8% -8%, var(--cc-bg-glow-indigo), transparent 55%),
            radial-gradient(800px 420px at 96% 0%, var(--cc-bg-glow-blue), transparent 48%);
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
        background: linear-gradient(90deg, var(--cc-accent) 0%, var(--cc-accent-gradient-mid) 42%, var(--cc-accent-gradient-end) 100%);
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
        display: inline-flex;
        align-items: center;
        gap: 0.4em;
        padding: 7px 14px;
        border-radius: var(--cc-radius-sm);
        color: var(--cc-link);
        text-decoration: none;
        font-weight: 500;
        border: 1px solid transparent;
        transition: background-color 0.12s ease, border-color 0.12s ease, color 0.12s ease;
    }
    .cc-nav__link:hover {
        background: var(--cc-surface-hover);
        text-decoration: none;
    }
    .cc-nav__link--current {
        color: var(--cc-nav-active-text);
        background: var(--cc-accent-soft);
        font-weight: 600;
        cursor: default;
        border-color: var(--cc-accent-border);
        box-shadow: 0 1px 2px var(--cc-accent-shadow);
    }
    .cc-nav__link--current:hover {
        background: var(--cc-accent-soft);
        color: var(--cc-nav-active-text);
    }
    .cc-nav__logout-form {
        display: contents;
    }
    button.cc-nav__logout {
        font: inherit;
        cursor: pointer;
        background: none;
    }
    .cc-theme {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        margin-left: 4px;
        padding-left: 10px;
        border-left: 1px solid var(--cc-border);
    }
    .cc-theme__label {
        font-size: 0.8125rem;
        font-weight: 600;
        color: var(--cc-muted);
        white-space: nowrap;
    }
    .cc-theme__select {
        min-width: 6.5rem;
        padding: 7px 10px;
        font: inherit;
        font-size: 0.8125rem;
        font-weight: 500;
        color: var(--cc-text);
        background: var(--cc-surface);
        border: 1px solid var(--cc-border-strong);
        border-radius: var(--cc-radius-sm);
        cursor: pointer;
    }
    .cc-page-header {
        margin-bottom: 24px;
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
    a:focus-visible,
    button:focus-visible,
    select:focus-visible,
    input:focus-visible {
        outline: var(--cc-focus-ring-width) solid var(--cc-focus-ring-color);
        outline-offset: var(--cc-focus-ring-offset);
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
        background: linear-gradient(180deg, var(--cc-surface-muted) 0%, var(--cc-surface-subtle) 100%);
    }
    .cc-card-title {
        display: inline-flex;
        align-items: center;
        gap: 0.45em;
        margin: 0;
        font-size: 0.9375rem;
        font-weight: 600;
        color: var(--cc-heading);
        letter-spacing: -0.01em;
    }
    .cc-page-title {
        display: flex;
        align-items: center;
        gap: 0.5em;
        flex-wrap: wrap;
        margin: 0 0 6px;
        font-size: 1.5rem;
        font-weight: 600;
        letter-spacing: -0.025em;
        color: var(--cc-text);
        line-height: 1.2;
    }
    .cc-icon {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
        line-height: 0;
        color: currentColor;
    }
    .cc-icon svg {
        display: block;
        width: 1em;
        height: 1em;
    }
    .cc-icon-label {
        display: inline-flex;
        align-items: center;
        gap: 0.4em;
    }
    .cc-page-title .cc-icon {
        width: 1.35rem;
        height: 1.35rem;
        color: var(--cc-accent);
        opacity: 0.92;
    }
    .cc-card-title .cc-icon {
        width: 1rem;
        height: 1rem;
        color: var(--cc-muted);
    }
    .cc-nav__link .cc-icon {
        width: 0.9375rem;
        height: 0.9375rem;
        opacity: 0.88;
    }
    .cc-stat-icon {
        width: 0.875rem;
        height: 0.875rem;
        color: var(--cc-muted);
        opacity: 0.9;
    }
    .cc-badge__icon {
        width: 0.75rem;
        height: 0.75rem;
        opacity: 0.9;
        flex-shrink: 0;
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
        background: var(--cc-table-header-bg);
        border-bottom: 1px solid var(--cc-border-strong);
        white-space: nowrap;
    }
    .cc-table tbody tr:last-child td {
        border-bottom: none;
    }
    .cc-table tbody tr:hover td {
        background: var(--cc-surface-muted);
    }
    .cc-table--compact th,
    .cc-table--compact td {
        padding: 9px 14px;
        font-size: 0.8125rem;
    }
    .cc-table--compact thead th {
        font-size: 0.6875rem;
    }
    .cc-details {
        margin-top: 6px;
        font-size: 0.75rem;
        line-height: 1.5;
        color: var(--cc-muted);
    }
    .cc-context-value {
        margin: 0;
        white-space: pre-wrap;
        word-break: break-word;
        font-family: inherit;
        font-size: inherit;
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
        text-align: left;
        background: var(--cc-table-header-bg);
    }
    .cc-kv td {
        background: var(--cc-surface);
    }
    .cc-source-meta {
        margin: 0 0 18px;
        font-size: 0.8125rem;
        padding: 10px 14px;
        background: var(--cc-surface-muted);
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
        gap: 0.35em;
        padding: 3px 10px 3px 8px;
        border-radius: 999px;
        font-size: 0.75rem;
        font-weight: 600;
        letter-spacing: 0.02em;
        border: 1px solid var(--cc-border);
        background: var(--cc-neutral-bg);
        color: var(--cc-heading);
        line-height: 1.25;
        white-space: nowrap;
    }
    .cc-badge--ok {
        border-color: var(--cc-success-border);
        background: var(--cc-success-bg);
        color: var(--cc-success-text);
    }
    .cc-badge--fail {
        border-color: var(--cc-error-border);
        background: var(--cc-error-bg);
        color: var(--cc-error-text);
    }
    .cc-badge--warn {
        border-color: var(--cc-warning-border);
        background: var(--cc-warning-bg);
        color: var(--cc-warning-text);
    }
    .cc-badge--neutral {
        border-color: var(--cc-border);
        background: var(--cc-surface-muted);
        color: var(--cc-muted);
    }
    .cc-badge--info {
        border-color: var(--cc-info-border);
        background: var(--cc-info-bg);
        color: var(--cc-info-text);
    }
    .cc-sev {
        display: inline-flex;
        align-items: center;
        gap: 0.35em;
        padding: 3px 10px 3px 8px;
        border-radius: 999px;
        font-size: 0.6875rem;
        font-weight: 600;
        letter-spacing: 0.04em;
        text-transform: uppercase;
        line-height: 1.25;
        white-space: nowrap;
    }
    .cc-sev--error {
        background: var(--cc-error-bg);
        color: var(--cc-error-text);
        border: 1px solid var(--cc-error-border);
    }
    .cc-sev--warning {
        background: var(--cc-warning-bg);
        color: var(--cc-warning-text);
        border: 1px solid var(--cc-warning-border);
    }
    .cc-sev--info {
        background: var(--cc-info-bg);
        color: var(--cc-info-text);
        border: 1px solid var(--cc-info-border);
    }
    .cc-sev--default {
        background: var(--cc-neutral-bg);
        color: var(--cc-heading);
        border: 1px solid var(--cc-border);
    }
    .cc-count-pill {
        display: inline-block;
        min-width: 1.5rem;
        padding: 3px 8px;
        border-radius: 6px;
        background: var(--cc-surface-muted);
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
        margin-top: 4px;
        font-size: 0.625rem;
        font-weight: 400;
        letter-spacing: 0.01em;
        color: var(--cc-tech);
        opacity: 0.88;
    }
    .cc-entity-display__tech.muted {
        color: var(--cc-tech);
    }
    .cc-filter-form {
        padding: 16px 18px;
        border-bottom: 1px solid var(--cc-border);
        background: var(--cc-surface-subtle);
    }
    .cc-filter-form__fields {
        display: flex;
        flex-wrap: wrap;
        gap: 12px 18px;
        align-items: flex-end;
    }
    .cc-filter-form label {
        display: flex;
        flex-direction: column;
        gap: 5px;
        font-size: 0.6875rem;
        font-weight: 600;
        letter-spacing: 0.04em;
        text-transform: uppercase;
        color: var(--cc-muted);
    }
    .cc-filter-form select,
    .cc-filter-form input[type="date"] {
        min-width: 11rem;
        padding: 7px 10px;
        font-size: 0.8125rem;
        font-weight: 500;
        color: var(--cc-text);
        background: var(--cc-surface);
        border: 1px solid var(--cc-border-strong);
        border-radius: var(--cc-radius-sm);
        box-sizing: border-box;
    }
    .cc-filter-form__actions {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        gap: 10px 14px;
        margin-top: 14px;
    }
    .cc-filter-form button {
        padding: 8px 16px;
        font-size: 0.8125rem;
        font-weight: 600;
        color: var(--cc-on-accent);
        background: var(--cc-accent);
        border: 1px solid var(--cc-accent);
        border-radius: var(--cc-radius-sm);
        cursor: pointer;
    }
    .cc-filter-form button:hover {
        background: var(--cc-link-hover);
        border-color: var(--cc-link-hover);
    }
    .cc-filter-form__clear {
        font-size: 0.8125rem;
        font-weight: 500;
    }
    .cc-filter-form--issues .cc-filter-form__fields {
        display: grid;
        grid-template-columns: repeat(5, minmax(0, 1fr));
        gap: 12px 16px;
        align-items: end;
    }
    .cc-filter-form--issues .cc-filter-form__fields label {
        min-width: 0;
    }
    .cc-profile-account-summary .cc-kv th {
        text-align: left;
    }
    .cc-profile-form__fields {
        display: flex;
        flex-direction: column;
        gap: 16px;
        align-items: flex-start;
    }
    .cc-profile-form__fields label:not(.cc-profile-form__checkbox) {
        width: 100%;
        max-width: 28rem;
        text-transform: none;
        letter-spacing: normal;
        font-size: 0.8125rem;
    }
    .cc-profile-form__fields input[type="email"] {
        width: 100%;
        padding: 7px 10px;
        font-size: 0.8125rem;
        font-weight: 500;
        color: var(--cc-text);
        background: var(--cc-surface);
        border: 1px solid var(--cc-border-strong);
        border-radius: var(--cc-radius-sm);
        box-sizing: border-box;
    }
    .cc-filter-form.cc-profile-form label.cc-profile-form__checkbox {
        display: inline-flex;
        flex-direction: row;
        align-items: center;
        gap: 10px;
        width: auto;
        max-width: none;
        text-transform: none;
        letter-spacing: normal;
        font-size: 0.875rem;
        font-weight: 500;
        color: var(--cc-text);
    }
    .cc-filter-form.cc-profile-form label.cc-profile-form__checkbox input[type="checkbox"] {
        width: 1rem;
        height: 1rem;
        margin: 0;
        flex-shrink: 0;
    }
    .cc-profile-form__error {
        margin: 0;
        font-size: 0.8125rem;
        color: var(--cc-danger, #b91c1c);
    }
    .cc-issues-bulk-panel {
        margin: 0;
        padding: 16px 18px;
        background: var(--cc-surface-muted);
        border-top: 1px solid var(--cc-border);
        border-bottom: 1px solid var(--cc-border);
    }
    .cc-issues-bulk-panel__title {
        margin: 0 0 8px;
        font-size: 0.875rem;
        font-weight: 600;
        color: var(--cc-heading);
    }
    .cc-issues-bulk-panel__caution {
        margin: 0 0 12px;
        font-size: 0.8125rem;
        line-height: 1.45;
    }
    .cc-issues-bulk-panel__caution strong {
        color: var(--cc-text);
        font-weight: 600;
    }
    .cc-issues-bulk-panel__controls {
        display: flex;
        flex-wrap: wrap;
        align-items: flex-end;
        gap: 12px;
    }
    .cc-issues-bulk-panel__controls label {
        display: flex;
        flex-direction: column;
        gap: 4px;
        margin: 0;
        font-size: 0.6875rem;
        font-weight: 600;
        letter-spacing: 0.04em;
        text-transform: uppercase;
        color: var(--cc-muted);
    }
    .cc-issues-bulk-panel__controls select {
        min-width: 11rem;
        padding: 7px 10px;
        font-size: 0.8125rem;
        font-weight: 500;
        color: var(--cc-text);
        background: var(--cc-surface);
        border: 1px solid var(--cc-border-strong);
        border-radius: var(--cc-radius-sm);
        text-transform: none;
        letter-spacing: normal;
    }
    .cc-issues-bulk-panel__controls button {
        padding: 7px 14px;
        font-size: 0.8125rem;
        font-weight: 600;
        color: var(--cc-on-accent);
        background: var(--cc-accent);
        border: 1px solid var(--cc-accent);
        border-radius: var(--cc-radius-sm);
        cursor: pointer;
    }
    .cc-issues-bulk-panel__controls button:hover {
        background: var(--cc-link-hover);
        border-color: var(--cc-link-hover);
    }
    .cc-issues-result-summary,
    .cc-changes-result-summary {
        margin: 0 0 14px;
        font-size: 0.8125rem;
        padding: 14px 18px 0;
    }
    .cc-pagination {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        justify-content: center;
        gap: 10px 14px;
        padding: 16px 18px;
        border-top: 1px solid var(--cc-border);
    }
    .cc-pagination__group,
    .cc-pagination__pages {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        gap: 6px;
    }
    .cc-pagination__link {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-width: 2.25rem;
        padding: 6px 10px;
        font-size: 0.8125rem;
        font-weight: 600;
        color: var(--cc-link);
        text-decoration: none;
        background: var(--cc-surface);
        border: 1px solid var(--cc-border-strong);
        border-radius: var(--cc-radius-sm);
        line-height: 1.2;
    }
    .cc-pagination__link:hover {
        color: var(--cc-link-hover);
        border-color: var(--cc-accent);
    }
    .cc-pagination__link--active {
        color: var(--cc-on-accent);
        background: var(--cc-accent);
        border-color: var(--cc-accent);
        cursor: default;
    }
    .cc-pagination__link--active:hover {
        color: var(--cc-on-accent);
        border-color: var(--cc-accent);
    }
    .cc-pagination__link--disabled {
        color: var(--cc-muted);
        cursor: default;
        opacity: 0.6;
    }
    .cc-pagination__link--disabled:hover {
        color: var(--cc-muted);
        border-color: var(--cc-border-strong);
    }
    .cc-pagination__ellipsis {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-width: 1.5rem;
        padding: 6px 2px;
        font-size: 0.8125rem;
        user-select: none;
    }
    .cc-filter-form--issues .cc-filter-form select,
    .cc-filter-form--issues .cc-filter-form input[type="date"] {
        width: 100%;
        min-width: 0;
    }
    @media (max-width: 1100px) {
        .cc-filter-form--issues .cc-filter-form__fields {
            grid-template-columns: repeat(3, minmax(0, 1fr));
        }
    }
    @media (max-width: 640px) {
        .cc-filter-form--issues .cc-filter-form__fields {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }
    }
    .cc-flash {
        margin: 0;
        padding: 12px 18px;
        font-size: 0.8125rem;
        font-weight: 500;
        color: var(--cc-text);
        background: var(--cc-surface-subtle);
        border-bottom: 1px solid var(--cc-border);
    }
    .cc-flash--page {
        margin-bottom: 22px;
        border: 1px solid var(--cc-border);
        border-radius: var(--cc-radius-sm);
        background: var(--cc-accent-soft);
    }
    .cc-btn-primary {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        margin: 0;
        padding: 8px 18px;
        font-family: inherit;
        font-size: 0.8125rem;
        font-weight: 600;
        line-height: 1.4;
        color: var(--cc-on-accent);
        background: var(--cc-accent);
        border: 1px solid var(--cc-accent);
        border-radius: var(--cc-radius-sm);
        box-shadow: 0 1px 2px var(--cc-accent-shadow);
        cursor: pointer;
    }
    .cc-btn-primary:hover {
        background: var(--cc-link-hover);
        border-color: var(--cc-link-hover);
    }
    .cc-btn-primary:active {
        transform: translateY(1px);
    }
    .cc-run-now-form {
        margin: 0;
    }
    .cc-issue-status-form {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        gap: 6px;
        min-width: 9rem;
    }
    .cc-issue-status-form__label {
        margin: 0;
        font-size: inherit;
        font-weight: inherit;
        letter-spacing: normal;
        text-transform: none;
        color: inherit;
    }
    .cc-issue-status-form select {
        min-width: 7.5rem;
        padding: 5px 8px;
        font-size: 0.75rem;
        font-weight: 500;
        color: var(--cc-text);
        background: var(--cc-surface);
        border: 1px solid var(--cc-border-strong);
        border-radius: var(--cc-radius-sm);
    }
    .cc-issue-status-form button {
        padding: 5px 10px;
        font-size: 0.75rem;
        font-weight: 600;
        color: var(--cc-on-accent);
        background: var(--cc-accent);
        border: 1px solid var(--cc-accent);
        border-radius: var(--cc-radius-sm);
        cursor: pointer;
    }
    .cc-issue-status-form button:hover {
        background: var(--cc-link-hover);
        border-color: var(--cc-link-hover);
    }
    .cc-issue-review-form .cc-kv th,
    .cc-issue-review-form .cc-kv td {
        vertical-align: middle;
    }
    .cc-issue-review-form__controls {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        gap: 12px;
    }
    .cc-issue-review-form__controls select {
        min-width: 11rem;
        padding: 7px 10px;
        font-size: 0.8125rem;
        font-weight: 500;
        color: var(--cc-text);
        background: var(--cc-surface);
        border: 1px solid var(--cc-border-strong);
        border-radius: var(--cc-radius-sm);
        box-sizing: border-box;
    }
    .cc-issue-review-form__controls button {
        padding: 7px 14px;
        font-size: 0.8125rem;
        font-weight: 600;
        color: var(--cc-on-accent);
        background: var(--cc-accent);
        border: 1px solid var(--cc-accent);
        border-radius: var(--cc-radius-sm);
        cursor: pointer;
    }
    .cc-issue-review-form__controls button:hover {
        background: var(--cc-link-hover);
        border-color: var(--cc-link-hover);
    }
    .visually-hidden {
        position: absolute;
        width: 1px;
        height: 1px;
        padding: 0;
        margin: -1px;
        overflow: hidden;
        clip: rect(0, 0, 0, 0);
        white-space: nowrap;
        border: 0;
    }

    .cc-stat-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
        gap: 14px;
        margin-bottom: 22px;
    }
    .cc-stat-card {
        background: var(--cc-surface);
        border: 1px solid var(--cc-border);
        border-radius: var(--cc-radius);
        box-shadow: var(--cc-shadow), var(--cc-shadow-inset);
        padding: 16px 18px;
    }
    .cc-stat-card__label {
        display: flex;
        align-items: center;
        gap: 0.4em;
        margin: 0 0 10px;
        font-size: 0.6875rem;
        font-weight: 600;
        letter-spacing: 0.04em;
        text-transform: uppercase;
        color: var(--cc-muted);
    }
    .cc-stat-card__value {
        margin: 0 0 8px;
        font-size: 1.625rem;
        font-weight: 600;
        letter-spacing: -0.02em;
        color: var(--cc-text);
        font-variant-numeric: tabular-nums;
        line-height: 1.15;
    }
    .cc-stat-card__value--text {
        font-size: 0.9375rem;
        font-weight: 500;
        letter-spacing: normal;
    }
    .cc-stat-card__hint {
        margin: 0;
        font-size: 0.75rem;
        line-height: 1.45;
    }
    .cc-stat-card__action {
        margin: 10px 0 0;
        font-size: 0.8125rem;
        font-weight: 500;
    }
    .cc-stat-card__breakdown {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        gap: 0.35em 0.5em;
    }
    .cc-stat-card__breakdown-sep {
        color: var(--cc-border-strong);
        font-weight: 400;
    }
    .cc-stat-card__action--split {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        gap: 0.35em 0.5em;
    }
    .cc-stat-card__action-sep {
        color: var(--cc-muted);
        font-weight: 400;
        user-select: none;
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
