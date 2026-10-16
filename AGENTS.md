# Environment

- Run the following command to inspect the DDEV environment:

  ```bash
  ddev describe -j | jq '.raw | {
    name,
    approot,
    status,
    primary_url,
    docroot,
    type,
    php_version,
    webserver_type,
    database: "\(.database_type):\(.database_version)",
    nodejs_version
  }'
  ```
- Custom DDEV service and command logic must use `$DDEV_SITENAME`; do not
  hard-code project or container names.

# Agents

- Do not create Git worktrees. Work directly in the current repository or
  worktree.
- All commits made by an AI agent should end with a note that reads:
  `AI-assisted by {code agent name}`.
- Require my review before commits and pushes, except when a
  skill or prompt says you can skip the review.
- Require me to review all tickets or comments and allow me to click
  save/submit.
- All tickets and comments made by an AI agent should start with a note that
  reads: `AI-assisted by {code agent name}`.
- When an in-app /browser is available, use it instead of Playwright.
- Wrap Markdown prose at 80-100 characters when practical; keep lines at
  100 characters or fewer.

# Commands

## DDEV

```bash
# Runs PHPUnit
ddev phpunit <file|directory>

# Runs all lint utilities (phpcs, phpstan, cspell, eslint, stylelint)
ddev code-review <file|directory>

# Runs all fix utilities (phpcbf, eslint, stylelint)
ddev code-fix <file|directory>

# Reinstall Drupal, replacing the current database, with optional presets
ddev install [preset...]

# Apply a Recipe from a path relative to the Drupal docroot
ddev recipe-apply ../recipes/<recipe>
```

# Architecture

## Directories

- `web/modules/custom` modules that are under development via the main
  repo.
- `web/modules/sandbox` modules checked out from https://git.drupalcode.org/
