# Environment

- Run `ddev describe` to find the current name, URL, docroot, PHP version, and more...
- Custom DDEV service and command logic must use `$DDEV_SITENAME`; do not hard-code project or container names.

# Agents

- All commits and comments made by an AI agent should end with a note that says: `AI-assisted by {code agent name}`.

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
