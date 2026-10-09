---
name: drupal-core-switcher
description: >-
  Set up or maintain an opt-in Composer overlay to switch Drupal core version
  on any Composer-managed Drupal site in DDEV, including requests to test
  Drupal 12 or create composer.drupal_VERSION.json without replacing the
  default build.
---

# Drupal Core Switcher

Prepare a Composer-managed DDEV site to toggle between its default Drupal core
major and a target major, then verify that core, contributed modules, custom
modules, recipes, libraries, and development tooling work on both. The
developer invokes the resulting host command as
`ddev drupal-core-switcher VERSION [--updb] [--no-interaction]`, where
`VERSION` is an integer major such as `12`.

Never commit or push. Require the user to review every change. Warn that an
enabled overlay and the resulting `composer.lock` must never be committed; only
the underscore-disabled overlay state may be committed.

## Inspect and decide

1. Run `ddev describe`, `ddev status`, and `ddev drush status`. Inspect
   `.ddev/config.yaml`, `composer.json`, `composer.lock`, existing
   `composer.drupal_*.json` files, merge-plugin configuration, patches, custom
   repositories, and `.ddev/commands/host/drupal-core-switcher`.
2. Read the baseline from the root `drupal/core-recommended` constraint in
   `composer.json`. For a multi-major constraint such as `^10 || ^11`, use the
   lowest allowed major unless the existing switcher explicitly identifies
   another baseline. If those disagree, or the constraint is missing or
   ambiguous, ask the user. Do not use the lock file or running site to choose
   the baseline.
3. Ask for the target major if absent. Accept only the main integer version
   number.
4. Check the target core's PHP minimum against DDEV's configured PHP. If it is
   insufficient, report the exact `.ddev/config.yaml` `php_version` change and
   ask before editing it.
5. Explain the planned file changes and obtain confirmation before editing.

## Composer overlay model

Before creating or changing overlays, read [README.md](README.md) for the roles
and merge order of the main, sandbox, lenient, and patches fragments.

Require and allow `wikimedia/composer-merge-plugin`. Wire each applicable
`"_composer.drupal_VERSION*.json"` fragment into
`extra.merge-plugin.include`; the underscore disables it and missing files are
ignored. Set `replace: true`, `merge-extra: true`, and
`merge-extra-deep: true`. Preserve unrelated merge settings and includes.

Move the Drupal core package family together. Use `^VERSION` for stable core,
or the appropriate dev/alpha constraint and least-permissive stability setting
when no stable release exists. Include only target-specific overrides and
record why any prerelease is required.

Keep target-only dependency constraints and workarounds in the target overlay.
Never change the root Drupal core constraints merely to make the target
resolve. It is valid to update `composer.json`, shared fragments such as
`composer.libraries.json`, `composer.recipes.json`, and
`composer.deprecated.sandbox.json`, and custom project code when the change is
needed and valid on both the default and target versions. Preserve unrelated
formatting and configuration, and verify every shared change on both versions.

## New major compatibility

For an unreleased or newly supported major such as Drupal 12:

1. Prefer a contrib release whose declared core constraint includes the target.
2. If no such release exists, add only that package to
   `composer.drupal_VERSION.lenient.json`. Lenient permits dependency
   resolution; it does not prove that the code is compatible.
3. Search the project's issue queue for the current “Automated Drupal VERSION
   compatibility fixes” issue. Read its full discussion and inspect its patch
   or merge request before using it.
4. Confirm the automated MR targets the branch used by the installed package.
5. Add its live `.diff` URL to `composer.drupal_VERSION.patches.json` using the
   project's expanded Composer Patches schema. For example, Schema.org
   Blueprints issue [#3602690](https://www.drupal.org/project/schemadotorg/issues/3602690)
   currently uses
   `https://git.drupalcode.org/project/schemadotorg/-/merge_requests/304.diff`.
6. Run the switch so Composer resolves the target dependencies, regenerates
   `patches.lock.json`, and only then installs the patched packages.
7. Inspect the applied diff and run compatibility analysis, automated tests,
   and manual smoke tests. Successful resolution or patch application is not
   evidence that the module works on the target major.

Project Update Bot overwrites its branch, so a live MR diff can change between
runs. Whenever its hash changes in `patches.lock.json`, inspect the new MR diff
and review the lock change before continuing. Automated fixes may be incomplete
and may deliberately leave `core_version_requirement` unchanged. Expand that
metadata only after remaining findings and runtime behavior are validated.
Remove lenient entries and compatibility patches when a compatible upstream
release contains the fixes.

## Files to create or update

1. Wire the applicable disabled overlay family into `composer.json` in the
   order documented in [README.md](README.md).
2. Create only the main, sandbox, lenient, and patches fragments needed for the
   target. Keep each fragment limited to its documented responsibility.
3. Copy [templates/obsolete.patch](templates/obsolete.patch) to
   `patches/drupal_VERSION/obsolete.patch` when an obsolete root patch needs a
   non-applying target.
4. If the switcher command is missing, copy
   [templates/drupal-core-switcher](templates/drupal-core-switcher). If it
   exists, preserve its site-specific hooks. Add a target-specific pre/post
   `case` branch only when the site needs extra steps. Never hard-code a project
   or container name.

The command must reject bad arguments with a nonzero exit. It disables every
`composer.drupal_VERSION*.json` reference with Perl, enables every matching
reference for the requested major, and runs Mutagen sync without making its
failure fatal. It must run Composer through the image binary with
`COMPOSER_DISCARD_CHANGES=true`:

```bash
ddev exec env COMPOSER_DISCARD_CHANGES=true /usr/local/bin/composer update -W --no-install
ddev exec env COMPOSER_DISCARD_CHANGES=true /usr/local/bin/composer patches-relock --no-interaction
ddev exec env COMPOSER_DISCARD_CHANGES=true /usr/local/bin/composer install
```

Resolve the target dependency set with `composer update -W --no-install`, then
run `composer patches-relock --no-interaction`, and finally `composer install`.
This rebuilds `patches.lock.json` from the newly resolved target dependencies
before Composer installs and patches them.

When the user supplies `--no-interaction`, append it to the update and install
commands. Parse it and `--updb` as independent options after `VERSION`, allowing
either order. Reject unknown options and extra positional arguments.

Never substitute `ddev composer` or `vendor/bin/composer`; the project Composer
binary may be replaced mid-update.

After Composer finishes, use `get_drupal_version()` to read the full
`Drupal::VERSION` directly from core. Derive its major with
`get_drupal_major_version()` and fail unless it matches the requested version.
This supports any integer major without hard-coding a baseline while preventing
a missing overlay from being reported as a successful switch. After all
optional work completes, use `get_drupal_version()` again to display the full
installed Drupal version without depending on Drush.

Run `ddev drush updb -y` only when `--updb` was supplied. Database updates may
be destructive, so first recommend an appropriate backup and require the user
to confirm before invoking the command with `--updb`.

## Resolve and verify

Run the switch without `--updb` first. Let Composer fail normally, then read its
resolver output. Put target-only constraints, lenient exceptions, obsolete
patch handling, and repository overrides in the target overlay. Put genuine
cross-version compatibility fixes in shared Composer fragments or project code.
Stop and involve the user when resolution requires removing a package,
changing PHP, accepting a prerelease, or making another choice that changes
project behavior.

After the target resolves:

1. Confirm the direct core checks report the target major and the full installed
   Drupal version.
2. Run the site's relevant checks, including Upgrade Status, `ddev code-review`,
   PHPStan, Rector, module tests, and manual smoke tests when available. Do not
   invent unavailable commands.
3. Update custom project modules, recipes, and their tests as needed to support
   both versions. This includes `*.info.yml` `core_version_requirement` values,
   deprecated or removed APIs, service definitions, configuration, and
   version-sensitive tests. Report sandbox projects that need equivalent work
   rather than assuming compatibility.
4. Report contrib packages without a compatible release, all lenient
   exceptions, failing or obsolete patches, deprecated or removed core modules
   and their contrib replacements, and custom-code findings.
5. Switch back to the baseline without `--updb` and confirm core reports the
   baseline major.
6. Disable all overlays again. Run `composer validate` and
   `composer update --lock --dry-run` through `/usr/local/bin/composer` in DDEV.
   Verify that target-only state is gone and the default build still resolves
   and works. Intentional shared compatibility changes may remain; review them
   separately from disabled switcher wiring with `git diff` and the user.

Do not commit or push. If a command failed while an overlay was enabled,
explicitly restore the underscore-disabled references before handing control
back.
