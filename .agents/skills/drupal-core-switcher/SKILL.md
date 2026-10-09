---
name: drupal-core-switcher
description: >-
  Use only when explicitly asked to switch between or test different Drupal
  core major versions on a Composer-managed site in DDEV, repair broken patches,
  or prepare compatibility support for a new Drupal major.
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
5. Explain the planned changes. Continue within existing authorization; ask
   only for choices outside the approved scope.

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

## First adoption of a new major

When the requested major has not been validated on this project, read
[Adopting a new Drupal major](references/new-drupal-major.md). It covers
branch selection, sandbox preservation, merged patch review, Git transport,
installation recovery, runtime requirements, and database updates.

## Routine switching and patch repair

For a known target, use the existing overlay family. Do not repeat first-time
upgrade discovery unless new compatibility blockers appear. The developer is
responsible for snapshots, recoverable sandbox copies, and database backups;
the DDEV command does not create or manage them. Confirm local source work is
preserved before using `COMPOSER_DISCARD_CHANGES=true`.

For a broken patch, inspect the merged definitions from root, recipes, and
sandbox library fragments. Compare the installed package with the resolved
lock, identify the patch's target branch, and check for upstream inclusion,
overlap, schema problems, and missing distribution files before changing it.
Sort project keys while preserving patch application and fragment merge order.
Record provenance and relevant validation for each changed patch.

Compare the previous patch lock and reviewed diff bytes when hashes change.
Review changed upstream contents before installation. Use existing Composer
commands to resolve, relock, review, and install separately when needed; add no
review option or backup machinery to the DDEV command. For new compatibility
blockers, read the new-major guide linked above.

## Files to create or update

1. Wire the applicable disabled overlay family into `composer.json` in the
   order documented in [README.md](README.md).
2. Create only the main, sandbox, lenient, and patches fragments needed for the
   target. Keep each fragment limited to its documented responsibility.
3. Copy [templates/obsolete.patch](templates/obsolete.patch) to
   `patches/drupal_VERSION/obsolete.patch` when an obsolete root patch needs a
   non-applying target.
4. Use [.ddev/commands/host/drupal-core-switcher](../../../.ddev/commands/host/drupal-core-switcher)
   as the command implementation. Preserve its site-specific hooks. Add a
   target-specific pre/post `case` branch only when the site needs extra steps.
   Use `$DDEV_SITENAME` for service or container names.

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

This is the command’s resolve → relock → install sequence. During manual patch
review, pause between relock and install. Check dependency-provided patches
against the resolved versions because installed fragments can still be older.

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

Run `ddev drush updb -y` only when requested and already authorized. The
developer is responsible for the database backup; the command does not manage
backups. Verify runtime and update requirements before invoking `--updb`;
after success, confirm no updates remain pending and rebuild caches. Do not
request authorization again when it was already provided.

## Resolve and verify

Run the switch without `--updb` first. Let Composer fail normally, then read its
resolver output. Resolve failures using the
[overlay rules above](#composer-overlay-model).
Stop and involve the user when resolution requires removing a package,
changing PHP, accepting an unapproved prerelease, or making another choice
outside the authorized scope that changes project behavior.

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
5. Unless the user requested leaving the target installed, switch back to the
   baseline without `--updb` and confirm core reports the baseline major. After
   database updates, establish compatibility or restore the matching backup
   before restoring older code.
6. When restoring the baseline, disable all overlays again. Run
   `composer validate` and `composer update --lock --dry-run` through `/usr/local/bin/composer` in DDEV.
   Verify that target-only state is gone and the default build still resolves
   and works. Intentional shared compatibility changes may remain; review them
   separately from disabled switcher wiring with `git diff` and the user.

If a command fails, preserve and report the actual overlay, lock, installed-code,
and database state. Disabling references alone is not a rollback. Follow the
user’s final-state preference and preserve a recoverable state; never commit
enabled overlays or temporary target locks.
