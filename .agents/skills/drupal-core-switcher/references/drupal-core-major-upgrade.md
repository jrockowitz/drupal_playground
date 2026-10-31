# Adopting a new Drupal major

Use this guide the first time a project is tested on a Drupal major. For
routine switching, use the main skill and the existing overlays.

The agent proposes each step, asks when a decision is needed, and records what
it found. The developer handles snapshots, sandbox copies, and database
backups.

## Step 1: Agree on the goal

**Do**
- Read the baseline major from the root `drupal/core-recommended` constraint.
- Look up the target's PHP and Drush requirements and compare them with DDEV.

**Ask**
- Which major are we targeting?
- Should the target stay installed at the end, or should we switch back?
- May I change `php_version` in `.ddev/config.yaml` if needed?
- Is there a database backup, and may I run database updates later?

## Step 2: Protect local work

**Do**
- Check `git status` in the project and in each sandbox checkout, including
  untracked files.
- Save copies of `composer.lock`, `patches.lock.json`, and the overlay files
  for later comparison.

**Ask**
- Local changes found in these sandbox checkouts: may Composer replace them?
  (`COMPOSER_DISCARD_CHANGES=true` will discard them.)

## Step 3: Review dependencies

**Do**
- Read every merged Composer fragment, including recipes and sandbox
  libraries, not just the root `composer.json`.
- Find extensions with no compatible release, and core modules or themes the
  target removes.
- On the baseline, run `ddev drush upgrade_status:analyze --all
  --ignore-uninstalled`; deprecations cannot be detected after switching.
- Make each blocking extension compatible per
  [drupal-contrib-core-upgrade.md](drupal-contrib-core-upgrade.md).
- Put target-only changes in the overlays (see [README.md](../README.md)).
  Never change the root core constraints.

**Ask**
- For each blocker: use the dev branch, a patch, a lenient exception, or
  remove the extension?
- Accept a prerelease of core or of a dependency?

## Step 4: Review patches

**Do**
- List every patch from all merged fragments.
- Flag duplicates, overlapping fixes, patches already merged upstream, and
  patches for a different branch than the one selected.
- Keep one patch format per package. Sort project names, but keep the order
  of patches within each project.
- For each changed patch, record the package version, issue or MR, reviewed
  hash or commit, local edits, and when it can be removed.

**Ask**
- Drop, replace, or keep each obsolete or conflicting patch?

## Step 5: Resolve, review, then install

Run the phases separately so changes can be reviewed before installing:

```bash
ddev mutagen sync
ddev exec env COMPOSER_DISCARD_CHANGES=true /usr/local/bin/composer update -W --no-install
ddev exec /usr/local/bin/composer patches-relock --no-interaction
# Review here.
ddev exec env COMPOSER_DISCARD_CHANGES=true /usr/local/bin/composer install
ddev mutagen sync
```

**Do**
- Always use `/usr/local/bin/composer`, never `ddev composer`.
- Before installing, compare the new locks with the saved copies. Read any
  patch whose hash changed.
- Check dependency-provided patches against the resolved versions; the
  installed copies may still be older.
- On failure, keep the log, find the cause, and resume from the lock instead
  of running another broad update.

**Ask**
- Here are the lock and patch changes. OK to install?

## Step 6: Check the site before database updates

**Do**
- Read the full core version from `Drupal.php` and confirm it is the target.
- Confirm Drush bootstraps and `drush cr` works.
- Run `ddev code-review`, `ddev phpstan`, and `ddev phpunit` on the target.
- Smoke-test in the browser: admin pages, content editing, affected widgets,
  themes, and integrations.

**Ask**
- Requirements pass. Run `ddev drush updb -y` now?

## Step 7: Update the database

**Do**
- Run `ddev drush updb -y`. If it fails, fix the cause and retry.
- Confirm no updates are pending, then rebuild caches.
- Run `ddev drupal-core-switcher VERSION` once to confirm the normal command
  works, and that it rejects bad arguments.

## Step 8: Finish and report

**Do**
- Follow the final state chosen in Step 1. Switching back after `updb` may
  require restoring the database backup; disabling overlays alone is not a
  rollback.
- Report:
  - installed code and database state
  - checks run and their results
  - patches added, changed, or ignored
  - lenient exceptions and missing contrib releases
  - custom code changes, and sandbox projects needing similar work
  - anything not verified

## If Drupal Git access fails

- Use `git@git.drupal.org:` for SSH sources
  ([instructions](https://new.drupal.org/node/3266856/git-instructions)).
  HTTPS patch and archive URLs are fine for downloads.
- Check DNS, port 22, authentication, then repository access, in that order.
  `ddev auth ssh` fixes keys, not timeouts. Compare the host with the
  container.
- If sources stay unavailable, use a verified Composer cache or an official
  archive of an exact reviewed commit. Keep the SSH source URL and record how
  to remove the workaround.

## References

**Planning (Step 1)**

- [Upgrading Drupal](https://www.drupal.org/docs/upgrading-drupal/upgrading-drupal)
- [Core release schedule](https://www.drupal.org/about/core/policies/core-release-cycles/schedule)
- [PHP requirements](https://www.drupal.org/docs/getting-started/system-requirements/php-requirements)

**Dependencies (Steps 3 and 5)**

- [Updating Drupal core via Composer](https://www.drupal.org/docs/updating-drupal/updating-drupal-core-via-composer)
- [Updating modules and themes using Composer](https://www.drupal.org/docs/updating-drupal/updating-modules-and-themes-using-composer)
- [Composer version constraints and stability](https://getcomposer.org/doc/articles/versions.md)
- [Deprecated and obsolete core modules and themes](https://www.drupal.org/docs/core-modules-and-themes/deprecated-and-obsolete)
- [Project Analysis](https://www.drupal.org/project/project_analysis)

Module-level references are in
[drupal-contrib-core-upgrade.md](drupal-contrib-core-upgrade.md#references).
