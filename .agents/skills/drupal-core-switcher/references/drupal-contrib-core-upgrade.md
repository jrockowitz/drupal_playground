# Making a module or recipe compatible with a new Drupal core major

Use for each extension or recipe blocking the target: contrib, custom, or
sandbox.
Project-level steps live in
[drupal-core-major-upgrade.md](drupal-core-major-upgrade.md).

## Tools

- [ddev-module-developer](https://github.com/mandclu/ddev-module-developer):
  `ddev phpstan` (with deprecation rules), `ddev rector`, `ddev phpunit`, and
  `ddev checks`.
- `drupal/upgrade_status` (shared dev dependency):
  `ddev drush upgrade_status:analyze PROJECT`.
- `rector.php` lists Drupal Rector sets only up to the newest release; add the
  target's set when it ships.

## Order of work

1. On the baseline, run Upgrade Status, `ddev phpstan`, and
   `ddev rector process --dry-run` against the module, then fix the
   deprecations. They cannot be detected once the target removes the APIs.
2. Switch to the target and fix remaining fatals and removed APIs.
3. Widen `core_version_requirement` (for example `^11 || ^12`) and the
   module's `composer.json` only after the code works on both majors.
4. Run the module's tests on both majors.

Support both majors in one codebase where possible, for example with
`DeprecationHelper::backwardsCompatibleCall()`. Ask before resorting to a
target-only patch or branch.

## Custom, sandbox, and recipes

- **Custom:** edit in place; changes are shared and must work on both majors.
- **Sandbox:** local checkouts under `web/modules/sandbox`. Ask before editing
  and report what changed.
- **Recipes:** update the `composer.json` constraints and any config actions
  or modules the target removes or renames, then apply the recipe on both
  majors.

## Contrib

**Do**
1. Check for a compatible release, then a development branch, then an
   existing issue or MR.
2. Otherwise draft an issue and an MR from an issue fork.
3. Pin the MR diff or a local patch in `composer.drupal_XX.patches.json`. Add
   a lenient entry if the declared core constraint also blocks resolution.
4. Record the issue, MR, reviewed commit or hash, and removal condition.

**Ask**
- Use a dev branch, a patch, a lenient exception, or remove the extension?
- May I post the drafted issue or MR upstream? Never post without approval.

## References

- [`.info.yml` and `core_version_requirement`](https://www.drupal.org/docs/develop/creating-modules/let-drupal-know-about-your-module-with-an-infoyml-file)
- [How to deprecate (deprecation policy)](https://www.drupal.org/about/core/policies/core-change-policies/how-to-deprecate)
- [`DeprecationHelper`](https://api.drupal.org/api/drupal/core!lib!Drupal!Component!Utility!DeprecationHelper.php/class/DeprecationHelper/11.x)
- [Upgrade Status](https://www.drupal.org/project/upgrade_status)
- [Drupal Rector](https://github.com/palantirnet/drupal-rector)
- [Issue forks](https://www.drupal.org/docs/develop/git/using-gitlab-to-contribute-to-drupal/creating-issue-forks)
