# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

Drupal 11 playground site managed with DDEV and Composer. The project is structured around custom Drupal Recipes that compose modules and configuration into reusable installation presets.

- **DDEV project name:** `drupal-playground` → site at `https://drupal-playground.ddev.site`
- **Docroot:** `web/`
- **PHP:** 8.3, **DB:** MariaDB 10.11, **Webserver:** nginx-fpm

## Common Commands

All Drupal/Drush commands run inside the DDEV container.

```bash
# Start/stop environment
ddev start
ddev stop

# Install Drupal (standard + all recipes)
ddev install

# Install Drupal with AI recipe included
ddev install ai

# Open site and log in as admin (one-time login link)
ddev uli

# Drush shortcuts
# e.g. ddev drush cr, ddev drush cex, ddev drush cim
ddev drush <command>

# Generate one-time login URL
ddev drush uli

# Apply a recipe manually
ddev drupal recipe ../recipes/<recipe_name>

# Composer (runs inside DDEV container)
ddev composer require <package>
ddev composer update

# Launch admin pages
ddev launch /admin
```

### Configuration Management

```bash
# Export config to sync dir
ddev drush config:export -y

# Import config from sync dir
ddev drush config:import -y

# Preview pending config changes
ddev drush config:export --diff

# Inspect a specific config object
ddev drush config:get <config.name>

# Show config sync directory path
ddev drush status --field=config-sync
```

### Development Commands

```bash
# List enabled modules
ddev drush pm:list --status=enabled

# Enable a module
ddev drush en <module>

# View recent log entries
ddev drush watchdog:show --count=20

# Clear all logs
ddev drush watchdog:delete all

# Run cron
ddev drush cron

# Execute PHP in Drupal context
ddev drush php:eval "<php code>"

# View fields for an entity bundle
ddev drush field:info <entity> <bundle>

# Run pending database updates
ddev drush updatedb

# Inspect database tables
ddev drush sql:query "DESCRIBE <table_name>;"
```

### Code Quality

```bash
# Check style
ddev exec vendor/bin/phpcs --standard=Drupal web/modules/custom/

# Auto-fix style
ddev exec vendor/bin/phpcbf --standard=Drupal web/modules/custom/

# Static analysis
ddev exec vendor/bin/phpstan analyse web/modules/custom/

# Security advisories
ddev composer audit
```

### Testing

```bash
ddev exec vendor/bin/phpunit --filter MyTest web/modules/custom/
```

Copy `web/core/phpunit.xml.dist` to `phpunit.xml` and configure:
- `SIMPLETEST_DB=mysql://db:db@db/db`
- `SIMPLETEST_BASE_URL=https://drupal-playground.ddev.site`

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

## Debugging

```bash
# Follow web container logs (stdout/stderr)
ddev logs -f web

# Enable/disable Xdebug (no restart needed)
ddev xdebug on
ddev xdebug off
```

## Code Style & Standards

- Never use abbreviations in names — except widely accepted ones (`$io`, `src`, `href`, etc.)
