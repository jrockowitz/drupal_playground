## Project Overview

Drupal 11 playground site managed with DDEV and Composer. The project is structured around custom Drupal Recipes that compose modules and configuration into reusable installation presets.

- **DDEV project name:** `drupal-playground` → site at `https://drupal-playground.ddev.site`
- **Docroot:** `web/`
- **PHP:** 8.3, **DB:** MariaDB 10.11, **Webserver:** nginx-fpm

## Commands

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

## Architecture

### Directory Structure

- `recipes/` — Custom Drupal Recipes (each has `recipe.yml` + `composer.json`)
- `web/` — Drupal docroot (managed by Composer scaffolding)
- `.ddev/` — DDEV configuration, custom commands, PHP/Nginx overrides
- `docs/` — Project documentation (DDEV setup, PHPStorm config)

## HTML

- Only add returns after block tags. (<p>, <div>, <ul>, <li>, <br/>, etc...)

## Git

- Never commit or push code unless explicitly asked to do so.
- All commits made by AI should end with a note that says: `AI-assisted by {code agent name}`.

## Python

- Use `python3` instead of `python` when invoking Python.

## PHP

- In PHPDoc, use plain `array` instead of shaped array annotations like `array<...>`, `int[]`, or `string[]`.
- Don't try to align array keys and values.

```php
$array = [
  'key' => 'value'
  'key1' => 'value1',
];
```

### Tests
- Assertion blocks should have comments that typically begin with `// Check that ...`.
  - Do not use `/* *** {comments} *** */` comments.
  - Look at existing tests in the module for the expected style.
- For BrowserTest (aka functional)
  - Try to use one test method with assertion blocks to improve the test performance.

## Code Style & Standards

- Never use abbreviations in names.
  - Write the full word every time
  - Example include:
    - `$definition` not `$def`
    - `$parameters` not `$params`
    - `$temporary` not `$tmp`
  - Exceptions for widely accepted conventions:
    - `$io`, `src`, `href`, `url`, `id`, `$config`
    - `html`, `csv`, `api`, `sql`, `php`, language codes like `$langcode`.
