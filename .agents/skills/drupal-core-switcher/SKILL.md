---
name: drupal-core-switcher
description: >-
  Use only when explicitly asked to switch between or test different Drupal
  core major versions on a Composer-managed site in DDEV, repair broken patches,
  or prepare compatibility support for a new Drupal major.
---

# Drupal Core Switcher

Make a Composer-managed DDEV site switchable between its baseline Drupal major
and a target major via
`ddev drupal-core-switcher VERSION [--updb] [--no-interaction]` (`VERSION` is
an integer major), and verify the site works on both.

## Ground rules

- Never commit or push; the user reviews every change. Only the
  underscore-disabled overlay state may be committed — never enabled overlays
  or a target `composer.lock`.
- Act within existing authorization. Ask only for choices outside it: removing
  packages, changing PHP, unapproved prereleases, or other behavior changes.
- The developer owns snapshots, sandbox copies, and database backups. Confirm
  local source work is preserved before any `COMPOSER_DISCARD_CHANGES=true` run.
- Always run Composer as `ddev exec env COMPOSER_DISCARD_CHANGES=true
  /usr/local/bin/composer …`, never `ddev composer` or `vendor/bin/composer`.
- On failure, report the actual overlay, lock, installed-code, and database
  state, and leave it recoverable per the user's preference.

## Inspect

1. Review `ddev describe`/`status`, `ddev drush status`, `.ddev/config.yaml`,
   `composer.json`/`.lock`, existing `composer.drupal_*.json`, merge-plugin
   config, patches, custom repositories, and the existing host command.
2. Baseline = lowest major in the root `drupal/core-recommended` constraint
   unless the existing switcher says otherwise. Ask if ambiguous; never infer
   it from the lock or running site.
3. Ask for the target major if missing.
4. If DDEV's `php_version` is below the target's minimum, propose the exact
   change and ask.
5. For a major not yet validated here, follow
   [Adopting a new Drupal major](references/new-drupal-major.md).

## Overlays

Read [README.md](README.md) for fragment roles (main, sandbox, lenient,
patches) and merge order.

- Require and allow `wikimedia/composer-merge-plugin`; add
  `"_composer.drupal_VERSION*.json"` includes (underscore = disabled) with
  `replace`, `merge-extra`, and `merge-extra-deep` set to `true`. Preserve
  unrelated settings.
- Create only the fragments the target needs. Move the core package family
  together at `^VERSION`, or the least-permissive prerelease constraint (with
  the reason recorded) if no stable release exists.
- Target-only constraints and workarounds go in overlays; never alter root core
  constraints to make the target resolve. Shared changes (`composer.json`,
  `composer.libraries.json`, `composer.recipes.json`,
  `composer.deprecated.sandbox.json`, custom code) are fine if valid on both
  versions.
- For an obsolete root patch, copy
  [templates/obsolete.patch](templates/obsolete.patch) to
  `patches/drupal_VERSION/obsolete.patch`.

## Patch repair

Inspect merged patch definitions (root, recipes, sandbox libraries) against the
resolved lock and installed package; check upstream inclusion, overlap, schema,
and missing distribution files. Sort keys without changing application or
merge order. When hashes change, diff against the previous patch lock and
review upstream changes before installing — pause between relock and install,
checking dependency-provided patches against resolved versions. Record
provenance and validation per patch.

## Host command

Use [.ddev/commands/host/drupal-core-switcher](../../../.ddev/commands/host/drupal-core-switcher),
preserving site hooks; add a target-specific pre/post `case` branch only if
needed, using `$DDEV_SITENAME` for names. It must:

- Accept `--updb` and `--no-interaction` in any order after `VERSION`; reject
  unknown options and extra arguments with a nonzero exit.
- Disable all `composer.drupal_*` references with Perl, enable those for
  `VERSION`, and run Mutagen sync (non-fatal).
- Run `update -W --no-install`, `patches-relock --no-interaction`, `install`
  (appending `--no-interaction` to update/install when given).
- Fail unless `get_drupal_major_version()` of `get_drupal_version()` matches
  `VERSION`; print the full version at the end.
- With `--updb`, run `ddev drush updb -y`, confirm nothing is pending, and
  rebuild caches.

## Verify

1. Switch without `--updb`; resolve Composer failures per the overlay rules.
2. Run available checks (Upgrade Status, `ddev code-review`, PHPStan, Rector,
   tests, smoke tests).
3. Make custom modules, recipes, and tests support both versions; report
   sandbox projects needing equivalent work.
4. Report incompatible contrib, lenient exceptions, failing/obsolete patches,
   removed core modules and their replacements, and custom-code findings.
5. Unless told to stay on the target, switch back to the baseline (after any
   `updb`, establish compatibility or restore the backup first), with all
   overlays disabled. Run `composer validate` and
   `composer update --lock --dry-run`, then confirm no target-only state
   remains. Review the remaining shared changes with the user via `git diff`.
