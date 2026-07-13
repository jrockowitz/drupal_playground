# Drupal Playground OpenSpec Baseline Design

## Objective

Document the current Drupal Playground architecture in OpenSpec for developers
and AI agents. The baseline explains how one codebase supports parallel Drupal
core versions, contributed-project work, and focused ecosystems through Composer
Merge Plugin, DDEV, Git worktrees, and Drupal Recipes.

This effort documents behavior already present in the repository. It does not
change installation, dependency, worktree, or ecosystem behavior.

## Architecture

The documentation models five foundation capabilities:

1. `foundation-composer` assembles version, Recipe, library, compatibility,
   sandbox, and nested-project dependency manifests.
2. `foundation-ddev` provides a shared but directory-isolated local runtime and
   command surface.
3. `foundation-git-worktree` creates parallel working directories and records
   their responsibilities.
4. `foundation-drupal-recipe` applies shared and optional Recipe compositions.
5. `foundation-drupal-core` defines the clean site and Drupal 10/11 behavior.

Four ecosystem capabilities build on those foundations:

- `ecosystem-ai` for `drupal_ai`
- `ecosystem-webform` for `drupal_webform`
- `ecosystem-schemadotorg` for `drupal_schemadotorg`
- `ecosystem-clinical-trials-gov` for `drupal_clinical_trials_gov`

`main` remains the shared integration and general contributed-module workspace.
`drupal_10` remains a compatibility lane rather than a separate ecosystem. ECA
and translation remain install presets unless dedicated worktrees are created.

## Documentation flow

```text
Composer manifests
       |
       v
Branch-specific codebase -----> Git worktree
                                      |
                                      v
                             Directory-named DDEV
                                      |
                                      v
                         Drupal core + shared Recipes
                                      |
                                      v
                            Optional ecosystem preset
```

`openspec/config.yaml` supplies a concise project orientation and the canonical
worktree/spec registry. Foundation specs own shared behavior. Ecosystem specs
describe worktree purpose, dependency participation, supported install presets,
Recipe chains, operational steps, installed outcomes, and verification scenarios
without restating foundation requirements.

## Error and safety documentation

The baseline calls out behavior that materially affects local state:

- `ddev install` drops the current database before reinstalling Drupal.
- `ddev worktree` validates and confirms its target before creation.
- Worktree bootstrapping stops on the first failed command.
- Recipe application fails when neither supported Recipe runner exists.
- Recipe, Drush, Composer, and indexing failures propagate through commands that
  use strict shell error handling.

## Verification

Every baseline requirement is traced to current tracked scripts, manifests, or
Recipe definitions. OpenSpec validation must pass in strict non-interactive mode.
The review also checks for placeholders, contradictions, future behavior stated
as current behavior, duplicated foundation requirements, and inconsistent
ecosystem branding.
