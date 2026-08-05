# Agent Workflow

- `.agents/skills/` is the canonical shared storage for Agent Skills.
- Commit project-owned skills; ignore generated or vendor-managed skills.
- OpenSpec main specs record verified current behavior. Active changes record proposed behavior, design, and tasks.
- Agent Skills and Superpowers guide exploration, implementation, testing, review, and verification. Transient Superpowers output is ignored; durable accepted decisions belong in OpenSpec.
- For nested modules or repositories, follow the closest applicable `AGENTS.md`.

# Environment

- PHP: 8.3
- DDEV derives its project name and URL from the current worktree. Run `ddev describe` to find the current name and URL.
- Docroot: `web/`

## DDEV and worktrees

- Never create a new DDEV project or instance. Always use the DDEV environment
  for the current worktree.
- Custom DDEV service and command logic must use `$DDEV_SITENAME`; do not hard-code project or container names.

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

# Create a Git worktree with its own DDEV identity
ddev worktree <path>
```

## Config

```bash
# Execute partial config import. Use this to test config changes.
ddev drush config:import -y --partial --source=<directory>
```

## Validation

```bash
# Validate all OpenSpec artifacts
openspec validate --all --strict --no-interactive

# Check the Git diff for whitespace errors
git diff --check
```

# Architecture

## Directories

- `recipes/` — Custom Drupal Recipes (each has `recipe.yml` + `composer.json`)
- `web/` — Drupal docroot (managed by Composer scaffolding)
- `.ddev/` — DDEV configuration, custom commands, PHP/Nginx overrides
- `openspec/` — Current specifications and active change artifacts
- `.agents/skills/` — Canonical shared Agent Skills

Recipe-specific guidance belongs beside the Recipe that owns it.

# Drupal

- Config
  - Use `#config_target` when extending `ConfigFormBase`. @see https://www.drupal.org/node/3373502
  - When simple config is schema-validated, trust the schema-backed return type instead of adding defensive casts like `(array) $config->get('query_paths')`.
  - Treat schema-backed `config->get('key')` values as the expected datatype and do not add fallback values for required config contracts.
  - Treat required API payload values the same way: do not add defensive casts or fallback rendering for data that must exist for the feature to function.
  - In tests, only model `NULL` when the contract explicitly allows it.
- Services
  - Always use `autowire`
  - Always create an interface
  - Only use public methods when absolutely necessary
  - Order injected services from general to specific (for example, `ConfigFactoryInterface` before `EntityTypeManagerInterface` before `EntityRepositoryInterface`).
- Hooks
  - Use OOP hooks instead of procedural hooks with legacy support. @see https://www.drupal.org/node/3442349
- PHPUnit
  - `::setUp` and `::test` methods should come before protected helper methods.
  - Prefer a single test method per Kernel, Functional, and Browser test class when scenarios can share one Drupal install/bootstrap.
  - Keep separate test methods only when scenarios require incompatible module sets, exception-driven control flow, or setup/reset sequences that would make one combined test misleading.
  - Tests should focus on checking expected behavior instead of exact labels or markup, since these can easily change.
- Markup
  - Use render arrays over `Markup::create()`.
    - Use `['#markup' => t('Some text'), '#prefix' => '<h2>', '#suffix' => '</h2>']`
      or `['#markup' => '<h2>' . t('Some text') . '</h2>']`
      over `Markup::create('<h2>' . t('Some text') . '</h2>')`
- Comments
  - Make sure every function, method, class, and constructor has a comment.

# Drush

- Always use `autowire`
- Do not create any aliases.

# Programming

## General

- Never use abbreviations in names
  - Write the full word every time
  - Examples include use:
    - `$definition` not `$def`
    - `$parameters` not `$params`
    - `$temporary` not `$tmp`
  - Exceptions for widely accepted conventions:
    - `$io`, `src`, `href`, `url`, `id`, `$config`
    - `html`, `csv`, `api`, `sql`, `php`, language codes like `$langcode`.

## Git

- All commits made by AI should end with a note that says: `AI-assisted by {code agent name}`.

## PHP

- For ternary operator always use parentheses around the condition.
- Don't use `private` with methods, use `protected` so that a method can be overridden.
- Don't use `final` with classes and allow a class to be extended.
- Don't use `$strict` with `in_array()` calls.
- In PHPDoc, use plain `array` instead of shaped array annotations like `array<...>`, `int[]`, or `string[]`.
- Don't try to align array keys and values.

```php
$array = [
  'key' => 'value',
  'key1' => 'value1',
];
```

### PHPUnit

- For Browser, Functional, and Kernel tests, prefer one test method with clear assertion blocks to improve performance when one bootstrap can cover the full scenario.
- Assertion blocks should have comments that typically begin with `// Check that ...`.
  - Do not use `/* * {comments} * */` comments.
  - Look at existing tests in the module for the expected style.
- Avoid using the nullsafe operator (`?->`) in tests and allow the test to fail if the property or method is not set.

## HTML

- Only add returns after block tags. (<p>, <div>, <ul>, <li>, <br/>, etc...)

## CSS

- For Drupal, always namespace module-specific CSS selectors.

## Python

- Use `python3` instead of `python` when invoking Python.
