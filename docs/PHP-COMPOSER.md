# PHP-COMPOSER.md — PHP Dependency Manager Cheatsheet

## What Is Composer?

Composer is the standard dependency manager for PHP. It handles installing, updating, and autoloading libraries (packages) that your project depends on. It resolves the full dependency tree, locks exact versions for reproducibility (`composer.lock`), and generates a PSR-4 autoloader so you never need manual `require` statements.

Key files:

- **`composer.json`** — Declares your project's dependencies, autoload rules, scripts, and metadata.
- **`composer.lock`** — Records the exact resolved versions installed. Commit this to version control for reproducible builds.
- **`vendor/`** — Where packages are installed. Always gitignore this directory.

---

## Project Setup

```bash
# Initialize a new composer.json interactively
composer init

# Install dependencies from an existing composer.json/lock
composer install

# Install without dev dependencies (production)
composer install --no-dev
```

---

## Managing Packages

```bash
# Require a package (adds to composer.json and installs)
composer require vendor/package
composer require vendor/package:^2.0

# Require as a dev dependency
composer require --dev vendor/package

# Remove a package
composer remove vendor/package

# Update all packages to latest allowed versions
composer update

# Update a single package
composer update vendor/package

# Update with a temporary constraint override (test upgrade paths without editing composer.json)
composer update --with vendor/package:^3.0

# Regenerate lock file only (no install — useful after merge conflicts in composer.lock)
composer update --lock

# Bump composer.json constraints to match currently locked versions
composer bump

# Reinstall a package from scratch without modifying the lock file
composer reinstall vendor/package
```

---

## Inspecting & Searching

```bash
# List installed packages
composer show

# Show details for one package
composer show vendor/package

# Show installed packages as a dependency tree
composer show --tree

# List outdated packages
composer outdated

# Search Packagist
composer search keyword

# Show why a package is installed (which packages depend on it)
composer why vendor/package

# Show what prevents a package from being installed/updated
composer why-not vendor/package:^3.0

# Show funding links for installed packages
composer fund
```

---

## Autoloading

```bash
# Regenerate the autoloader (e.g. after adding a PSR-4 namespace)
composer dump-autoload

# Optimized autoloader for production (class map)
composer dump-autoload --optimize    # or -o

# Authoritative class map (fail if class not in map)
composer dump-autoload --classmap-authoritative   # or -a
```

Example `composer.json` autoload config (`autoload-dev` follows the same pattern for test namespaces):

```json
{
  "autoload": {
    "psr-4": {
      "App\\": "src/"
    }
  }
}
```

---

## Scripts

```bash
# Run a script defined in composer.json
composer run-script script-name

# List available scripts
composer run-script --list
```

Example script definitions:

```json
{
  "scripts": {
    "test": "phpunit",
    "cs-fix": "php-cs-fixer fix",
    "post-install-cmd": ["@auto-scripts"],
    "post-update-cmd": ["@auto-scripts"]
  }
}
```

---

## Validation & Diagnostics

```bash
# Validate composer.json and composer.lock
composer validate

# Check for security vulnerabilities in dependencies
composer audit

# Diagnose common issues (connectivity, permissions, etc.)
composer diagnose

# Check platform requirements against installed PHP/extensions
composer check-platform-reqs
```

---

## Global Packages

```bash
# Install a package globally
composer global require vendor/package

# Update global packages
composer global update

# List global packages
composer global show
```

> Make sure `~/.composer/vendor/bin` (or `~/.config/composer/vendor/bin`) is in your `$PATH`.

---

## Private Repositories & Auth

```bash
# Add a private Composer repository (e.g. Private Packagist, Satis, Drupal)
composer config repositories.my-repo composer https://packages.example.com

# Add a VCS repository (pull directly from a Git repo)
composer config repositories.my-fork vcs https://github.com/you/forked-package

# Store credentials in auth.json (per-project, gitignored)
composer config http-basic.packages.example.com username token

# Store credentials globally (~/.composer/auth.json)
composer config --global http-basic.packages.example.com username token
```

> `auth.json` supports `http-basic`, `bearer`, `bitbucket-oauth`, `github-oauth`, and `gitlab-oauth` types. Keep it out of version control.

---

## Platform Overrides

Force dependency resolution to target a specific PHP version or extension set, regardless of your local environment:

```bash
# Pin the target PHP version
composer config platform.php 8.3.0

# Pin a specific extension version
composer config platform.ext-gd 2.1.0

# Remove a platform override
composer config --unset platform.php
```

> Useful for ensuring `composer update` resolves against production's PHP version even if your local machine runs a different one.

---

## Version Constraints Quick Reference

| Constraint       | Meaning                                |
| ---------------- | -------------------------------------- |
| `1.2.3`          | Exact version                          |
| `>=1.2`          | Greater than or equal to 1.2           |
| `^1.2`           | >=1.2.0 and <2.0.0 (next major break) |
| `~1.2`           | >=1.2.0 and <1.3.0 (next minor break) |
| `^1.2 || ^2.0`   | Either range (OR)                      |
| `1.2.*`          | Any patch in 1.2.x                     |
| `dev-main`       | Latest commit on the `main` branch     |

---

## Useful Flags

| Flag                      | Purpose                                         |
| ------------------------- | ----------------------------------------------- |
| `--prefer-dist`           | Download zip archives (faster, default)         |
| `--prefer-source`         | Clone from VCS (useful for debugging)           |
| `--no-dev`                | Skip dev dependencies                           |
| `--dry-run`               | Preview what would change without doing it      |
| `-v` / `-vv` / `-vvv`    | Increase output verbosity                       |
| `--no-plugins`            | Disable plugins during command                  |
| `--no-scripts`            | Skip script execution                           |
| `--ignore-platform-reqs`  | Ignore PHP/extension version mismatches         |
| `--with vendor/pkg:^3.0`  | Temporary constraint override during update     |

---

## Recipes

**Locking deps for CI / production:**

```bash
# Developers run:
composer update            # refreshes lock file
git add composer.json composer.lock
git commit -m "Update dependencies"

# CI / production runs:
composer install --no-dev --optimize-autoloader
```

**Resolving merge conflicts in `composer.lock`:**

```bash
# Accept either side of the conflict, then regenerate from composer.json
git checkout --theirs composer.lock
composer update --lock
```

**Testing an upgrade path without editing `composer.json`:**

```bash
composer update --with vendor/package:^3.0 --dry-run   # preview first
composer update --with vendor/package:^3.0              # apply if it looks good
```

**Tightening constraints after updates:**

```bash
composer update
composer bump              # align composer.json constraints to locked versions
git add composer.json composer.lock
git commit -m "Bump dependency constraints"
```

**Adding a Drupal module (Drupal-specific):**

```bash
composer require drupal/module_name
drush en module_name
```

**Patching a package (with cweagans/composer-patches 2.x):**

```bash
composer patches-relock   # re-discover patches, update patches.lock.json
composer patches-repatch  # reinstall patched packages and re-apply patches
```

See [Composer Patches 2.x](#composer-patches-2x) section below for the full reference.

---

## Composer Patches 2.x

[cweagans/composer-patches](https://github.com/cweagans/composer-patches) applies patches to installed Composer dependencies without forking them. Version 2.x introduces a `patches.lock.json` lockfile, richer patch definitions, and dedicated CLI commands.

### Installation

```bash
composer require cweagans/composer-patches:~2.0
```

### Defining Patches

Patches can be defined in three places:

- `composer.json` under `extra.patches`
- A standalone `patches.json` file (path set via `patches-file` config)
- A dependency's own `composer.json` (must use publicly accessible URLs)

#### Compact format

Description is the key, patch path/URL is the value. Quick to write but limited to those two fields.

```json
{
  "extra": {
    "patches": {
      "drupal/core": {
        "Fix config import issue": "patches/drupal-core-config-fix.patch"
      }
    }
  }
}
```

In a standalone `patches.json`:

```json
{
  "patches": {
    "drupal/core": {
      "Fix config import issue": "patches/drupal-core-config-fix.patch"
    }
  }
}
```

#### Expanded format (recommended)

Array of patch objects — supports `sha256` verification, custom `depth`, and arbitrary `extra` metadata.

```json
{
  "extra": {
    "patches": {
      "drupal/core": [
        {
          "description": "Fix config import issue",
          "url": "patches/drupal-core-config-fix.patch",
          "sha256": "abc123...",
          "depth": 1
        }
      ]
    }
  }
}
```

### Configuration

Set in `extra.composer-patches` inside `composer.json`:

```json
{
  "extra": {
    "composer-patches": {
      "patches-file": "patches/patches.json",
      "default-patch-depth": 1
    }
  }
}
```

| Key | Default | Purpose |
|---|---|---|
| `patches-file` | `patches.json` | Path to a standalone patches file |
| `default-patch-depth` | `1` | Global default `-p` depth for all patches |
| `package-depths` | — | Per-package depth overrides |
| `ignore-dependency-patches` | — | Ignore patches defined by listed dependencies |
| `disable-resolvers` | — | Disable `RootComposer`, `PatchesFile`, or `Dependencies` resolvers |
| `disable-patchers` | — | Disable `FreeformPatcher`, `GitPatcher`, or `GitInitPatcher` |

### Commands

| Command | Alias | Purpose |
|---|---|---|
| `composer patches-relock` | `composer prl` | Re-discover all patches; update `patches.lock.json` |
| `composer patches-repatch` | `composer prp` | Delete and reinstall patched packages; re-apply all patches |
| `composer patches-doctor` | `composer pd` | Run system checks and report common setup issues |

Run `patches-relock` + `patches-repatch` together whenever you add, remove, or modify a patch.

### patches.lock.json

The plugin automatically creates `patches.lock.json`, recording every applied patch with its checksum — analogous to `composer.lock` for dependencies.

**Commit `patches.lock.json` to version control.** It guarantees all environments apply the same patches in the same order and catches silently-changing patch sources.

### Typical Workflow

```bash
# 1. Add patch file to patches/ and define it in composer.json or patches.json

# 2. Re-discover patches and update the lockfile
composer patches-relock

# 3. Reinstall affected packages with patches applied
composer patches-repatch

# 4. Commit
git add composer.json patches.lock.json patches/
git commit -m "Add patch for drupal/core config import fix"
```

### Security Note

Avoid mutable patch URLs such as GitHub PR `.patch` links — the content can change silently when new commits are pushed. Use local patch files or pin to a specific commit SHA.

---

*Composer Patches docs: [https://docs.cweagans.net/composer-patches/](https://docs.cweagans.net/composer-patches/)*

---

## Environment Variables

| Variable                  | Purpose                                       |
| ------------------------- | --------------------------------------------- |
| `COMPOSER_HOME`           | Override the Composer home directory           |
| `COMPOSER_CACHE_DIR`      | Override the cache directory                   |
| `COMPOSER_AUTH`            | JSON string with auth credentials              |
| `COMPOSER_MEMORY_LIMIT`   | Override PHP memory limit for Composer         |
| `COMPOSER_NO_INTERACTION` | Set to `1` to run non-interactively           |

---

*Composer docs: [https://getcomposer.org/doc/](https://getcomposer.org/doc/)*
