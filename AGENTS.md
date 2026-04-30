# Environment

- PHP: 8.3
- DDEV project name: `drupal-playground`
- DDEV URL: https://drupal-playground.ddev.site
- Docroot: `web/`

# Commands

## DDEV

```bash
# Open site and log in as admin (one-time login link)
ddev uli

# Runs PHPUnit
ddev phpunit <file|directory>

# Runs all lint utilities (phpcs, phpstan, cspell, eslint, stylelint)
ddev code-review <file|directory>

# Runs all fix utilities (phpcbf, eslint, stylelint)
ddev code-fix <file|directory>
```

# Architecture

## Directories

- `recipes/` — Custom Drupal Recipes (each has `recipe.yml` + `composer.json`)
- `web/` — Drupal docroot (managed by Composer scaffolding)
- `.ddev/` — DDEV configuration, custom commands, PHP/Nginx overrides
- `docs/` — Project documentation (DDEV setup, PHPStorm config)

# Drupal

- Simple Config
  - Use `#config_target` when extending `ConfigFormBase`. @see https://www.drupal.org/node/3373502
- Services
  - Always use `autowire`
  - Always create an interface
  - Only use public methods when absolutely necessary
  - For injected service order them from general to specific services.
    (i.e. `ConfigFactoryInterface` before `EntityTypeManagerInterface` before `EntityRepositoryInterface)
- Hooks
  - Use OOP hooks instead of procedural hooks with legacy support. @see https://www.drupal.org/node/3442349
- PHPUnit
  - `::setUp` and `::test` methods should come before protected helper methods.
  - For Functional and Kernel tests, try to have a single test method per class.
  - Tests for focus on checking expected behavior, instead of exact labels or markup, since these can easily change.
- Markup
  - Use render arrays over Markup::create().
    - Use `['#markup' => t('Some text'), '#prefix' => '<h2>', '#suffix' => '</h2>']`
      or `['#markup' => '<h2>'. t('Some text') . '</h2>']`
      over `Markup::create('<h2>' . t('Some text') . '</h2>')`
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

- Never commit or push code unless explicitly asked to do so.
- All commits made by AI should end with a note that says: `AI-assisted by {code agent name}`.

## PHP

- For ternary operator always use parentheses around the condition.
- Don't use `private` with methods, use `protected` so that a method can be overridden.
- Don't use `final` with classes and allow a class to be extended.
- Don't use $strict with in_array() calls.
- In PHPDoc, use plain `array` instead of shaped array annotations like `array<...>`, `int[]`, or `string[]`.
- Don't try to align array keys and values.

```php
$array = [
  'key' => 'value'
  'key1' => 'value1',
];
```

### PHPUnit

- For Browser and Kernel test, try to use one test method with assertion blocks to improve the test performance.
- Assertion blocks should have comments that typically begin with `// Check that ...`.
  - Do not use `/* * {comments} * */` comments.
  - Look at existing tests in the module for the expected style.
- Avoid using Nullsafe Operator '?->' in tests and allow the test to fail if the property/method is not set.

## HTML

- Only add returns after block tags. (<p>, <div>, <ul>, <li>, <br/>, etc...)

## CSS

- For Drupal, always namespace module-specific CSS selectors.

## Python

- Use `python3` instead of `python` when invoking Python.
