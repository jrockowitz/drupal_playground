# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

Drupal 11 playground site managed with DDEV and Composer. The project is structured around custom Drupal Recipes that compose modules and configuration into reusable installation presets.

- **DDEV project name:** `drupal-playground` → site at `https://drupal-playground.ddev.site`
- **Docroot:** `web/`
- **PHP:** 8.3, **DB:** MariaDB 10.11, **Webserver:** nginx-fpm

## Commands

Custom DDEV commands for this project:

```bash
# Install Drupal (standard + all recipes)
ddev install

# Install Drupal with AI recipe included
ddev install ai

# Open site and log in as admin (one-time login link)
ddev uli

# Apply a recipe manually
ddev drupal recipe ../recipes/<recipe_name>
```

## Architecture

### Directory Structure

- `recipes/` — Custom Drupal Recipes (each has `recipe.yml` + `composer.json`)
- `web/` — Drupal docroot (managed by Composer scaffolding)
- `.ddev/` — DDEV configuration, custom commands, PHP/Nginx overrides
- `docs/` — Project documentation (DDEV setup, PHPStorm config)

### Patching

The project uses `cweagans/composer-patches` v2. Patches are tracked in `patches.lock.json`.

```bash
# Re-discover patches and write patches.lock.json
ddev composer patches-relock

# Re-install patched deps and re-apply patches
ddev composer patches-repatch

# Diagnose common patching issues
ddev composer patches-doctor
```

## Testing

```bash
ddev exec vendor/bin/phpunit --filter MyTest web/modules/custom/
```

Copy `web/core/phpunit.xml.dist` to `phpunit.xml` and configure:
- `SIMPLETEST_DB=mysql://db:db@db/db`
- `SIMPLETEST_BASE_URL=https://drupal-playground.ddev.site`

## Code Style & Standards

- Never use abbreviations in names. Write the full word every time — `$definition` not `$def`, `$configuration` not `$config`, `$identifier` not `$id`, `$parameters` not `$params`, `$temporary` not `$tmp`. Exceptions for widely accepted conventions: `$io`, `src`, `href`, `url`, `id` (when it is literally an ID/primary key), `html`, `csv`, `api`, `sql`, `php`, language codes like `$langcode`.
