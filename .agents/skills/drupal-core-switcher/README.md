# Drupal Core Switcher

Opt-in Composer overlays that toggle a DDEV project between its baseline Drupal
major and another major, so core, contrib, custom code, recipes, libraries,
and tooling can be validated on both.

Overlays are wired into `composer.json` with an underscore prefix
(`_composer.drupal_12.json`), which disables them. The
[host command](../../../.ddev/commands/host/drupal-core-switcher) removes the
underscore for the requested major, then runs `composer update -W --no-install`,
`composer patches-relock`, and `composer install`.

## Usage

```bash
ddev drupal-core-switcher 12 [--updb] [--no-interaction]
```

Run without `--updb` first. Backups and snapshots are your responsibility, and
`COMPOSER_DISCARD_CHANGES=true` discards local source changes. Enabled overlays
and the resulting `composer.lock` are temporary state: commit only the disabled
wiring, after restoring and verifying the baseline build.

## Where changes belong

- **Target-only** constraints, patches, lenient exceptions, conflicts, and
  repositories: `composer.drupal_XX*.json`.
- **Shared**: changes valid on both majors, such as `composer.json`, the shared
  fragments (`composer.libraries.json`, `composer.recipes.json`,
  `composer.deprecated.sandbox.json`), and custom modules, recipes, and tests.
  They persist after switching back, so test them on both versions.

Never change the root core constraints to make the target resolve.

## Overlay family

`XX` is the Drupal major. Each fragment holds only what differs from the root
build.

| File | Contents |
| --- | --- |
| `composer.drupal_XX.json` | Core package family and dev tools, differing contrib/Drush constraints, conflicts, plugin permissions, stability, and repository overrides only when a repository hides a needed release. |
| `composer.drupal_XX.sandbox.json` | Repositories for sandbox projects and dev branches (often `web/modules/sandbox`) that are target-specific. Use `git@git.drupal.org:` URLs. Shared definitions belong in the shared sandbox fragment. |
| `composer.drupal_XX.lenient.json` | `mglaman/composer-drupal-lenient` and an `extra.drupal-lenient.allowed-list` of extensions whose declared core constraint excludes the target. Remove each one when a compatible release ships. |
| `composer.drupal_XX.patches.json` | Target-only patches, obsolete root patches replaced with `patches/drupal_XX/obsolete.patch`, and obsolete dependency patches listed in `extra.composer-patches.ignore-dependency-patches`. Match the project's Composer Patches schema. |

## Merge order

```json
"extra": {
  "merge-plugin": {
    "include": [
      "_composer.drupal_XX.json",
      "_composer.drupal_XX.sandbox.json",
      "_composer.drupal_XX.lenient.json",
      "_composer.drupal_XX.patches.json"
    ],
    "replace": true,
    "merge-extra": true,
    "merge-extra-deep": true
  }
}
```

Later fragments refine earlier ones. The family is toggled as a group, and
missing fragments are ignored.

For a major not yet validated on this project, see
[Adopting a new Drupal major](references/drupal-core-major-upgrade.md).

## References

For core and contrib upgrade references, see
[Adopting a new Drupal major](references/drupal-core-major-upgrade.md#references).

**Lenient**

- [Using the Drupal Lenient Composer plugin](https://www.drupal.org/docs/develop/using-composer/using-the-drupal-lenient-composer-plugin)
- [mglaman/composer-drupal-lenient](https://github.com/mglaman/composer-drupal-lenient)

**Patches**

- [Composer Patches documentation](https://docs.cweagans.net/composer-patches/)
- [Composer Patches recommended workflows](https://docs.cweagans.net/composer-patches/usage/recommended-workflows/)
- [cweagans/composer-patches](https://github.com/cweagans/composer-patches)

**Merge plugin**

- [wikimedia/composer-merge-plugin](https://github.com/wikimedia/composer-merge-plugin)
