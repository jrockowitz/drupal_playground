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
- Custom DDEV services and commands must use `$DDEV_SITENAME`,
  never hard-coded project or container names.

# Agents

- All commits made by an AI agent should end with the note `AI-assisted by {code agent name}`,
  and all tickets and comments made by an AI agent should begin with the same note.
- Do not create Git worktrees. Work directly in the current repository
  or worktree.
- Review all changes before committing or pushing code, and all tickets
  or comments before saving or submitting, unless a skill or prompt
  permits skipping review.
- When cloning repositories hosted on `git.drupalcode.org`, always use the SSH
  clone URL (`git@git.drupalcode.org:...`). Do not clone Drupal.org repositories
  over HTTPS.
- When an in-app /browser is available, use it instead of Playwright.
- Wrap Markdown prose at 80-100 characters when practical; keep lines at
  100 characters or fewer.

# Commands

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

- `web/modules/custom` modules that are under development via the main repo.
- `web/modules/sandbox` modules checked out from https://git.drupalcode.org/
