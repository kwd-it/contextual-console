# Contextual Console skills catalogue

Project-level Cursor skills live under `.cursor/skills/`. Reference them explicitly in prompts so the agent loads the right guidance.

## Skills

### no-mojibake-ui-text

| | |
|---|---|
| **When to use** | Editing UI text, Blade templates, tests, command output, emails, Markdown, seed data, or any user-visible strings |
| **Prompt reference** | Use the `no-mojibake-ui-text` skill. |

### contextual-console-workflow

| | |
|---|---|
| **When to use** | General Contextual Console development: inspect first, small diffs, Laravel conventions, reporting results |
| **Prompt reference** | Use the `contextual-console-workflow` skill. |

### monitoring-investigation

| | |
|---|---|
| **When to use** | Investigating monitoring issues, source run failures, plot discrepancies, Housebuilder Pack/Wyatt data, or unexpected dashboard/email output |
| **Prompt reference** | Use the `monitoring-investigation` skill. |

## Combining skills

For UI copy changes during a feature:

```
Use the contextual-console-workflow skill.
Use the no-mojibake-ui-text skill.
```

For a production data mismatch before coding:

```
Use the monitoring-investigation skill.
```
