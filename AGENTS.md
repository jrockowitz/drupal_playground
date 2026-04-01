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

## Python

- Use `python3` instead of `python` when invoking Python.

## Code Style & Standards

- Never use abbreviations in names. Write the full word every time — `$definition` not `$def`, `$configuration` not `$config`, `$identifier` not `$id`, `$parameters` not `$params`, `$temporary` not `$tmp`. Exceptions for widely accepted conventions: `$io`, `src`, `href`, `url`, `id` (when it is literally an ID/primary key), `html`, `csv`, `api`, `sql`, `php`, language codes like `$langcode`.
