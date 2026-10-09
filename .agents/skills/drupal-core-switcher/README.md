# Drupal Core Switcher

This skill adds an opt-in Composer overlay for toggling a DDEV project between
its default Drupal core major and another major. The goal is not merely to make
Composer resolve: it is to confirm that Drupal core, contributed and custom
modules, recipes, libraries, tests, and development tools continue to work on
both versions.

The normal build remains the default because every overlay is wired into
`composer.json` with an underscore-prefixed filename, such as
`_composer.drupal_12.json`. The DDEV command temporarily removes that
underscore for the requested major and runs Composer.

## Where compatibility changes belong

Use a version-specific `composer.drupal_XX*.json` overlay when a constraint,
patch, lenient exception, conflict, or repository definition is needed only for
that Drupal major.

It is also valid to update shared project files when a change is correct for
both the default and target versions. Examples include:

- `composer.json` merge wiring, plugin configuration, scripts, repositories,
  or dependencies shared by both builds.
- `composer.libraries.json`, `composer.recipes.json`,
  `composer.deprecated.sandbox.json`, and other non-version-specific Composer
  fragments.
- Custom module and recipe metadata, PHP code, services, configuration, and
  tests.

For example, a custom module may need its `core_version_requirement` expanded
to include the target major and its deprecated API calls replaced with code
that works on both majors. Keep target-only workarounds out of shared files,
and do not change the root Drupal core constraints simply to force the target
to resolve. Shared compatibility changes remain after switching back, so test
them on both versions and review them separately from the overlay wiring.

## Overlay files

Treat `composer.drupal_XX*.json` as one overlay family, where `XX` is a Drupal
major. Splitting the family by responsibility makes compatibility work easier
to review and remove later.

### `composer.drupal_XX.json`

The main overlay contains the target's ordinary Composer overrides:

- Drupal core package constraints and compatible development tools.
- Contrib or Drush constraints that differ from the default build.
- Target-specific conflicts, plugin permissions, or stability settings.
- A repository override only when an existing repository hides a required
  release.

Keep only values that differ from the root build. Do not copy the entire root
`composer.json`, and never change its baseline constraints to resolve the
target version.

### `composer.drupal_XX.lenient.json`

The lenient overlay is an explicit list of temporary compatibility exceptions
for Drupal extensions whose declared core constraint excludes the target even
though the extension still needs to be tested there.

It may require and allow `mglaman/composer-drupal-lenient`, then list only the
necessary packages under `extra.drupal-lenient.allowed-list`. Lenient changes
Composer's constraint handling; it does not make incompatible PHP code safe.
Every listed package therefore needs manual testing and should be removed when
a compatible release becomes available.

### `composer.drupal_XX.patches.json`

The patches overlay contains patch behavior that differs for the target major:

- Add a patch needed only on that Drupal major.
- Replace an obsolete root patch with
  `patches/drupal_XX/obsolete.patch` so it cannot apply.
- Ignore an obsolete dependency-provided patch with
  `extra.composer-patches.ignore-dependency-patches`.

Use the patch schema already used by the project's installed Composer Patches
version. Report every patch added, replaced, or ignored.

### `composer.drupal_XX.sandbox.json`

The sandbox overlay defines Composer repositories for Drupal sandbox projects
or development branches that are not discoverable as suitable releases from
the normal repositories. It is commonly used for modules checked out under
`web/modules/sandbox`.

Use Drupal.org SSH source URLs and define only packages needed by that target.
Keep ordinary package constraints in the main overlay unless the constraint is
part of the sandbox package definition itself.

## Merge order

Wire the family into `extra.merge-plugin.include` in this order:

```json
[
  "_composer.drupal_XX.json",
  "_composer.drupal_XX.sandbox.json",
  "_composer.drupal_XX.lenient.json",
  "_composer.drupal_XX.patches.json"
]
```

Configure the merge plugin with `replace: true`, `merge-extra: true`, and
`merge-extra-deep: true`. Later fragments can refine earlier configuration.
The switcher enables or disables every `composer.drupal_XX*.json` entry as a
group, so optional variants may be absent. It first resolves the target
dependency set with `composer update -W --no-install`, then regenerates
`patches.lock.json`, and finally installs the resolved packages. This ensures
the patch lock is based on the target major's dependencies rather than the
previously installed version.

## Usage

```bash
ddev drupal-core-switcher 12
ddev drupal-core-switcher 12 --no-interaction
ddev drupal-core-switcher 12 --updb
```

Run without `--updb` first and resolve Composer failures in the overlay family.
Use `--updb` only after confirming an appropriate database backup exists.

An enabled overlay and the resulting `composer.lock` are temporary test state.
Never commit them. Switch back to the default major and verify every overlay
include has its underscore before committing the disabled wiring and support
files.
