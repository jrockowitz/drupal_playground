# Environment

- Run `ddev describe` to find the current name, URL, docroot, PHP version, and more...
- Custom DDEV service and command logic must use `$DDEV_SITENAME`; do not hard-code project or container names.

# Agents

- All commits made by an AI agent should end with a note that says: `AI-assisted by {code agent name}`.
- All tickets and comments made by an AI agent should start with a note that says: `AI-assisted by {code agent name}`.
- Allow human to review all changes before committing and pushing code.
- When creating tickets or comments on Drupal.org, allow the human to review the ticket or comment and click submit.
- When an in-app browser is available, use it instead of Playwright.

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

- `web/modules/custom` modules that are under development via the main repo.
- `web/modules/sandbox` modules checkout of `https://git.drupalcode.org/`
