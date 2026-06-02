# AI Schema.org JSON-LD Recipe Config Action — Design Spec

**Date:** 2026-04-21
**Module:** `ai_schemadotorg_jsonld`
**Location:** `web/modules/sandbox/ai_schemadotorg_jsonld/`

---

## Overview

Add reusable recipe config action support to `ai_schemadotorg_jsonld` so recipes can attach the Schema.org JSON-LD field to one or more bundles without calling a Drush command from install automation.

The main goal is to replace the imperative AI Schema.org JSON-LD section in `.ddev/commands/web/install` with a recipe such as `drupal_playground_ai_schemadotorg_jsonld` while keeping the feature reusable for other projects.

This work does not try to convert the field, automator, and display setup into static imported YAML. The existing module already has runtime builder logic that is the correct orchestration point, so the recipe action should call module code rather than duplicate it declaratively.

---

## Current Problem

The current install workflow does two different things:

1. Applies recipes.
2. Runs imperative post-install commands for `ai_schemadotorg_jsonld`.

Today the AI section of `.ddev/commands/web/install`:

- Applies the AI recipes.
- Enables:
  - `ai_schemadotorg_jsonld`
  - `json_field_widget`
  - `ai_schemadotorg_jsonld_breadcrumb`
  - `ai_schemadotorg_jsonld_log`
- Runs:
  - `drush ai_schemadotorg_jsonld:add-field node article`
  - `drush ai_schemadotorg_jsonld:add-field node page`

This is not recipe-native and makes the installation workflow harder to reuse in other environments.

---

## Goals

- Add a recipe config action named `addField` to `ai_schemadotorg_jsonld`.
- Allow recipe authors to target:
  - explicit bundles
  - all current bundles for a supported entity type
- Refactor the builder so Drush and recipes share the same service API.
- Keep the new API easy to test with kernel tests.
- Keep the recipe reusable outside Drupal Playground.

## Non-Goals

- Automatically apply the field to bundles created after recipe application.
- Replace all install automation with recipes in this task.
- Export the generated field and automator configuration as static recipe config.

---

## Recommended Approach

Use a shared builder API plus a custom recipe config action plugin.

The config action will accept a structured payload, validate it, and call the builder. The Drush command will become a thin adapter around the same builder API. The recipe will remain declarative and reusable, while the module code remains the single source of truth for field setup behavior.

This is preferred over:

- keeping the Drush command as the canonical implementation and duplicating logic in the config action
- trying to model all side effects as imported config YAML

---

## Public API Changes

Refactor `AiSchemaDotOrgJsonLdBuilderInterface` and `AiSchemaDotOrgJsonLdBuilder` to use a two-level API:

```php
public function addFieldToBundles(string $entity_type_id, array $bundles): void;
```

```php
protected function addFieldToBundle(string $entity_type_id, string $bundle): void;
```

`addFieldToBundle()` is a rename of the current `addFieldToEntity()` method. The renamed method keeps the current per-bundle orchestration behavior:

- ensure `ai_schemadotorg_jsonld.settings:entity_types.{entity_type_id}` exists
- create field storage
- create field instance
- create automator
- add form display component
- add view display component

`addFieldToBundles()` becomes the new public orchestration entry point for callers that may pass multiple bundles or `['*']`.

---

## Responsibilities

### `AiSchemaDotOrgJsonLdBuilder::addFieldToBundles()`

This method is responsible for:

- validating the requested entity type
- validating the bundle list
- normalizing bundle input
- expanding `['*']` to all current bundles
- resolving non-bundle entity types to their synthetic bundle
- iterating through normalized bundles and calling `addFieldToBundle()`

### `AiSchemaDotOrgJsonLdBuilder::addFieldToBundle()`

This method remains responsible for the existing per-bundle side effects:

- ensuring `ai_schemadotorg_jsonld.settings:entity_types.{entity_type_id}` exists by calling a new manager method when needed
- field storage
- field instance
- automator
- form display component
- view display component

If the entity type is not yet present in `ai_schemadotorg_jsonld.settings:entity_types.{entity_type_id}.*`, `addFieldToBundle()` should automatically add it before creating the automator. This requires a small manager API expansion so the builder can ask the manager to add a single entity type on demand without duplicating that logic.

### `AiSchemaDotOrgJsonLdCommands::addField()`

The Drush command becomes a thin adapter that:

- accepts CLI arguments
- converts them into the builder API contract
- catches exceptions
- reports a user-friendly Drush success or error message

### `Plugin\ConfigAction\AddField`

The config action becomes a thin adapter that:

- validates the recipe payload shape
- calls `addFieldToBundles()`
- lets exceptions bubble so recipe application fails clearly

The expected class location is:

- `src/Plugin/ConfigAction/AddField.php`

---

## Config Action Contract

Add a config action plugin named `addField`.

Recipe usage for explicit bundles:

```yaml
config:
  actions:
    ai_schemadotorg_jsonld.settings:
      addField:
        entity_type: node
        bundles: ['article', 'page']
```

Recipe usage for all current bundles:

```yaml
config:
  actions:
    ai_schemadotorg_jsonld.settings:
      addField:
        entity_type: node
        bundles: ['*']
```

Why this contract:

- `entity_type` is explicit and easy to validate.
- `bundles` is always an array, which keeps the API stable.
- `['*']` is a clear, reusable representation of "all current bundles".
- The action name `addField` matches the existing Drush terminology and stays concise.

---

## Validation Rules

`addFieldToBundles()` is the authoritative validation layer.

### Entity type validation

- `entity_type_id` must resolve to a supported content entity type.
- Unsupported entity types throw `\InvalidArgumentException`.

### Bundle list validation

- `bundles` must not be empty.
- `bundles` must always be an array.
- Duplicate bundle names are de-duplicated silently.
- `['*']` means all current bundles that exist when the method runs.
- `['*', 'page']` is invalid and throws `\InvalidArgumentException`.

### Bundle entity types

For entity types that have bundles:

- every explicit bundle must exist
- any missing bundle throws `\InvalidArgumentException`

### Non-bundle entity types

For entity types without bundles, such as `user`:

- `['*']` resolves to `[$entity_type_id]`
- `[$entity_type_id]` is allowed
- any other bundle value is invalid

This keeps the contract consistent across bundle and non-bundle entity types while preserving clear validation behavior.

### Per-bundle safety

`addFieldToBundle()` should also be safe when called directly. If the entity type settings do not yet exist, it should initialize them automatically before continuing. This keeps the per-bundle method resilient for direct callers and prevents automator creation from depending on a separate precondition.

---

## Exception Model

The builder should throw exceptions. Drush and recipe actions should not duplicate business validation logic.

### `\InvalidArgumentException`

Use for invalid caller input:

- unsupported entity type
- empty bundle list
- mixed `['*', 'page']`
- missing bundle
- invalid non-bundle synthetic bundle input

### `\RuntimeException`

Use only when the builder encounters an unexpected runtime failure:

- storage lookup failure that cannot be handled locally
- save failure that should be rethrown with clearer context

Suggested message shapes:

- `The entity type node is not supported.`
- `The bundles list for node cannot be empty.`
- `The bundles list for node cannot mix "*" with explicit bundle names.`
- `The bundle article does not exist for the entity type node.`
- `The non-bundle entity type user requires the synthetic bundle user.`

---

## Drush Changes

Refactor `Drupal\ai_schemadotorg_jsonld\Drush\Commands\AiSchemaDotOrgJsonLdCommands::addField()` so it no longer owns entity type or bundle validation logic.

### Current behavior

The command currently:

- validates the supported entity type
- validates or resolves the bundle
- calls `manager->addEntityTypes([$entity_type])`
- calls the builder for one bundle

### New behavior

The command should:

- keep the same CLI command name: `ai_schemadotorg_jsonld:add-field`
- keep support for:
  - `drush ai_schemadotorg_jsonld:add-field node page`
  - `drush ai_schemadotorg_jsonld:add-field user`
  - `drush ai_schemadotorg_jsonld:add-field node '*'`
- convert CLI input into a bundle array:
  - bundle argument provided: `[$bundle]`
  - bundle omitted for non-bundle entities: `['*']`
- call `addFieldToBundles($entity_type, $bundles)`
- catch `\Throwable` or the narrower expected exception types and surface a Drush error

For bundle entity types, the explicit `*` CLI argument is normalized to `['*']` and uses the same builder validation and expansion path as the recipe config action.

### Resulting benefits

- one validation path
- one orchestration path
- simpler Drush command
- fewer drift risks between Drush and recipes

---

## Recipe Design

Add a new reusable recipe:

`recipes/drupal_playground_ai_schemadotorg_jsonld/recipe.yml`

This recipe should:

- install:
  - `ai_schemadotorg_jsonld`
  - `json_field_widget`
  - `ai_schemadotorg_jsonld_breadcrumb`
  - `ai_schemadotorg_jsonld_log`
- apply the new config action:

```yaml
config:
  actions:
    ai_schemadotorg_jsonld.settings:
      addField:
        entity_type: node
        bundles: ['*']
```

The Drupal Playground install command can then replace the current imperative enable-and-Drush block with:

- apply the new recipe

This recipe is intentionally reusable. Other projects can copy the same action shape for different entity types and bundle sets.

---

## Idempotency Expectations

The new public builder method must remain safe to call repeatedly.

Expected behavior:

- existing field storage is reused
- existing field config is reused
- existing automator is reused
- existing form display component is not duplicated
- existing view display component is not duplicated
- missing entity type settings are created automatically when needed

Calling the recipe again after new bundles are created should update those bundles when `bundles: ['*']` is used, because `['*']` resolves the current bundle list each time the recipe is applied.

---

## Testing Plan

### Builder kernel tests

Add or update kernel tests for:

- `addFieldToBundle('node', 'page')` initializes missing entity type settings automatically
- `addFieldToBundles('node', ['page'])`
- `addFieldToBundles('node', ['article', 'page'])`
- `addFieldToBundles('node', ['*'])`
- repeated calls remain idempotent
- invalid bundle throws
- mixed `['*', 'page']` throws
- non-bundle entity type handling works

These tests should focus on behavior:

- field storage exists
- field config exists for the expected bundles
- automator exists
- form display contains the field
- view display contains the field

### Config action kernel tests

Add tests for:

- explicit bundles via action payload
- `['*']` via action payload
- invalid payload fails with the expected exception

### Drush tests

If Drush command tests are already present or easy to add, confirm:

- `node page` still works
- `node '*'` resolves to all current bundles
- non-bundle invocation still works
- invalid input returns a useful error

If dedicated Drush command tests are too heavy for this change, rely on builder and config action tests and keep the command thin enough that its behavior is obvious.

---

## Open Decisions Resolved

- Use `addField` as the config action name.
- Use `bundles: ['*']` for all current bundles.
- Keep `bundles` as an array in all cases.
- Rename `addFieldToEntity()` to `addFieldToBundle()`.
- Add `addFieldToBundles()` as the new public orchestration entry point.
- Let the builder throw exceptions and let Drush and recipe actions format or bubble them.

---

## Implementation Summary

The design introduces a reusable, recipe-friendly API without changing the underlying field creation strategy.

The core change is to move from:

- Drush command as orchestration entry point

to:

- builder service as orchestration entry point
- Drush and recipe config action as thin adapters

This keeps the module reusable, keeps the recipe declarative, and removes imperative installation logic from Drupal Playground where recipe support is now sufficient.
