# ClinicalTrials.gov Custom Field Manager Refactor

**Date:** 2026-04-29
**Status:** Implemented

## Context

`ClinicalTrialsGovFieldManager` currently owns two different jobs:

1. Curating and decorating the field list shown in the import wizard.
2. Translating supported `STRUCT` metadata into `custom_field` definitions.

Those responsibilities meet inside `resolveFieldDefinition()`, but most of the custom-field-specific behavior is concentrated in a separate block of methods and constants:

- `STRUCTURE_WHITELIST`
- `LINK_TYPE_GENERIC`
- `resolveStructuredFieldDefinition()`
- `buildCustomFieldDefinition()`
- `isSimpleCustomFieldStruct()`
- `buildCustomFieldColumnDefinition()`
- `supportsCustomFieldStringArray()`
- the `MAX_CHAR_OVERRIDES` policy that currently exists only to support custom-field columns

This makes the class harder to read because the general field-resolution rules and the `custom_field` implementation details are interleaved in one service.

## Recommendation

Extract the `custom_field` logic into a dedicated `ClinicalTrialsGovCustomFieldManager` service and keep `ClinicalTrialsGovFieldManager` as the orchestrator for wizard-facing field definitions.

This is worth doing because it creates a clear boundary without forcing a larger redesign:

- `ClinicalTrialsGovFieldManager` stays responsible for required paths, allow-list decoration, title mapping, scalar field mapping, and `field_group` fallback.
- `ClinicalTrialsGovCustomFieldManager` becomes responsible for deciding whether a struct can become a `custom_field` and for building the resulting storage and instance settings.

I would not move all struct handling out of `ClinicalTrialsGovFieldManager`. The parent manager should still decide the top-level outcome for a `STRUCT` path:

1. `custom_field`
2. `field_group`
3. unsupported

That keeps the wizard policy in one place while moving the column-generation details into the dedicated collaborator.

## Current Seam

The natural seam is already present.

In `ClinicalTrialsGovFieldManager::resolveFieldDefinition()`, the `STRUCT` branch:

- asks whether a structured field can be resolved
- falls back to unsupported
- optionally promotes unsupported nested structs to `field_group`

The actual `custom_field` synthesis happens later in the file and only depends on:

- `ClinicalTrialsGovManagerInterface`
- `ClinicalTrialsGovNamesInterface`

The `field_group` decision is the part that still depends on `ModuleHandlerInterface`, so that part should remain in `ClinicalTrialsGovFieldManager`.

## Proposed Service Boundary

### New service

Add a new service:

- `clinical_trials_gov.custom_field_manager`
- interface: `ClinicalTrialsGovCustomFieldManagerInterface`

Constructor dependencies:

- `ClinicalTrialsGovManagerInterface`
- `ClinicalTrialsGovNamesInterface`

No config factory dependency is needed.
No module handler dependency is needed.

### Interface

The interface can stay small:

```php
interface ClinicalTrialsGovCustomFieldManagerInterface {

  public function resolveStructuredFieldDefinition(string $path): ?array;

}
```

I would keep the helper methods protected inside the concrete class rather than promoting them into the public API.

## Responsibility Split

### ClinicalTrialsGovFieldManager keeps

- `TITLE_FIELD_PATH`
- required field key policy
- available field key discovery and ancestor inclusion
- base field-definition assembly
- scalar and enum field mapping
- `field_group` fallback
- `buildDisplayTypeLabel()`
- `getFieldDefinition()` and `getFieldDefinitions()` decoration

### ClinicalTrialsGovCustomFieldManager owns

- `STRUCTURE_WHITELIST`
- `LINK_TYPE_GENERIC`
- custom-field-only max-char override policy
- `resolveStructuredFieldDefinition()`
- `buildCustomFieldDefinition()`
- `isSimpleCustomFieldStruct()`
- `buildCustomFieldColumnDefinition()`
- `supportsCustomFieldStringArray()`

## Proposed Code Changes

### 1. Add a new interface and service

Create:

- `web/modules/custom/clinical_trials_gov/src/ClinicalTrialsGovCustomFieldManagerInterface.php`
- `web/modules/custom/clinical_trials_gov/src/ClinicalTrialsGovCustomFieldManager.php`

Register them in `clinical_trials_gov.services.yml`.

### 2. Inject the new collaborator into `ClinicalTrialsGovFieldManager`

Update the constructor to inject `ClinicalTrialsGovCustomFieldManagerInterface`.

After that, the `STRUCT` branch in `resolveFieldDefinition()` becomes simpler:

```php
$structured_definition = $this->customFieldManager->resolveStructuredFieldDefinition($path);
if ($structured_definition !== NULL) {
  return array_merge($definition, $structured_definition);
}
```

The unsupported and `field_group` fallback logic can then stay exactly where it is.

### 3. Remove custom-field-only helpers from `ClinicalTrialsGovFieldManager`

Delete the moved constants and protected methods from `ClinicalTrialsGovFieldManager`.

That should shrink the file substantially and make the remaining class read more like policy code than format-conversion code.

### 4. Keep the existing field manager interface stable

I would not change `ClinicalTrialsGovFieldManagerInterface` in the first pass.

Even though `resolveStructuredFieldDefinition()` would then delegate to another service, other consumers already call it through the field manager and there is no immediate benefit to widening the refactor. Keeping the existing interface stable lowers risk.

If we later decide that callers should depend on the dedicated custom-field manager directly, that can be a second refactor after this extraction lands.

## Suggested Implementation Order

1. Create the new interface and class by moving the custom-field methods over with minimal behavioral change.
2. Register the new service and inject it into `ClinicalTrialsGovFieldManager`.
3. Change `ClinicalTrialsGovFieldManager::resolveStructuredFieldDefinition()` to delegate to the new service.
4. Remove the now-dead helpers and constants from `ClinicalTrialsGovFieldManager`.
5. Update tests to cover the new class directly while keeping one integration test on `ClinicalTrialsGovFieldManager`.

This order keeps the changes mechanical and makes regressions easier to spot.

## Testing Impact

The current kernel coverage in `ClinicalTrialsGovFieldManagerTest` already exercises the behaviors we care about:

- simple structs resolving to `custom`
- whitelisted structs resolving to `custom`
- markup child columns becoming `string_long` with formatted settings
- array text children becoming `map_string`
- citation override becoming `string_long`
- nested structural parents resolving to `field_group`

I would split the test coverage like this:

### Move to a new `ClinicalTrialsGovCustomFieldManagerTest`

- simple struct support
- whitelist support
- column-type resolution for enum, markup, numeric, boolean, date, url, and string
- `map_string` handling for supported arrays
- max-char override handling
- returned `details` labels

### Keep in `ClinicalTrialsGovFieldManagerTest`

- required-field behavior
- allow-list and ancestor behavior
- title mapping
- scalar field mapping
- unsupported struct fallback
- `field_group` fallback
- one integration assertion that a supported struct still resolves to `custom`

That gives us focused unit-of-behavior tests without losing end-to-end confidence in the field manager.

## Risks And Watchouts

### Hidden policy coupling

`MAX_CHAR_OVERRIDES` looks generic by name, but today it only affects custom-field column generation. If that policy is moved, verify that no scalar field behavior changes accidentally.

### Duplicate metadata lookups

Both services use `ClinicalTrialsGovManagerInterface::getMetadataByPath()`. That is probably fine because the manager already caches metadata in memory, but the extraction should avoid introducing extra loops or repeated calls inside the same branch unless needed.

### Interface shape drift

If `ClinicalTrialsGovFieldManagerInterface::resolveStructuredFieldDefinition()` stays public, the field manager becomes a delegating facade for that behavior. That is acceptable, but it should be intentional and documented in the class docblock.

## Expected Outcome

After the refactor:

- `ClinicalTrialsGovFieldManager` should be easier to scan because it mostly reads as field-selection and field-policy code.
- `ClinicalTrialsGovCustomFieldManager` should become the single place to reason about `custom_field` support and column generation.
- Future changes to custom-field recipes should be less risky because they no longer live beside unrelated wizard logic.

## Recommendation Summary

I would do this refactor, but keep it narrow.

The best version is not "move everything about structs out of the field manager." The best version is:

- keep top-level struct outcome policy in `ClinicalTrialsGovFieldManager`
- move `custom_field` support detection and definition building into `ClinicalTrialsGovCustomFieldManager`
- keep the existing public field-manager API stable in the first pass

That gives the class-size and clarity benefits you want without turning a cleanup into a broader architectural rewrite.
