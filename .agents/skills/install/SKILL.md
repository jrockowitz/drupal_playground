---
name: install
description: Install or reinstall Drupal with optional presets (ai, eca, schemadotorg, translation, all). Use when the user wants to (re)install the site or apply a named preset.
disable-model-invocation: true
---

Run `ddev install` with the preset(s) from $ARGUMENTS, or no preset for a base install.

## Available presets

| Preset | What it applies |
|--------|----------------|
| `ai` | `drupal_playground_ai` recipe |
| `eca` | `eca_starterkit` recipe |
| `all` | applies `ai`, `schemadotorg`, `eca`, and `translation` in sequence |
| `schemadotorg` | `drupal_playground_schemadotorg` recipe |
| `translation` | `drupal_playground_translation` recipe + locale sync |

Multiple presets can be combined, e.g. `ddev install ai eca`.

`all` must be used by itself, e.g. `ddev install all`.

## Steps

1. Confirm with the user before running — this wipes the current database.
2. Run: `ddev install $ARGUMENTS`
3. Report the one-time login URL printed at the end.
