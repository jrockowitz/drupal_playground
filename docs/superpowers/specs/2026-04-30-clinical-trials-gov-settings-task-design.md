# ClinicalTrials.gov Settings Task — Design Spec

**Date:** 2026-04-30
**Status:** Draft for review

---

## Context

The `clinical_trials_gov` module already provides a multi-step admin workflow under `/admin/config/services/clinical-trials-gov` for finding studies, reviewing metadata, configuring a destination bundle, importing studies, and managing imported content. This enhancement adds a dedicated **Settings** task at the end of that workflow so advanced users can configure machine-name-oriented settings without mixing those controls into the main Configure step.

The current Configure step already stores and reads `type` from `clinical_trials_gov.settings`, but it owns both bundle creation inputs and field mapping. The new design separates those responsibilities:

- **Settings** owns advanced configuration values through `ConfigFormBase` and `#config_target`
- **Configure** reads those values and focuses on reviewing what will be created plus selecting field mappings

This keeps the primary wizard flow simple for most users while giving advanced users a clear place to adjust machine names before anything is created.

---

## Goals

- Add a **Settings** local task at the end of the ClinicalTrials.gov tasks
- Implement Settings as a `ConfigFormBase` form using `#config_target`
- Move ownership of `type` into Settings instead of Configure
- Add a new `field_prefix` setting used by `\Drupal\clinical_trials_gov\ClinicalTrialsGovEntityManager`
- Default both settings to stable values that work for the existing workflow
- Prevent changes to `type` and `field_prefix` after the destination content type and generated fields exist
- Update the overview page and Configure form so the new Settings step is discoverable

---

## Non-goals

- No migration or renaming workflow for already-created content types or fields
- No automatic backfill of previously generated field names
- No changes to the import, review, or manage logic beyond navigation and messaging needed to support the new Settings step

---

## Configuration Model

The `clinical_trials_gov.settings` config object will include:

```yaml
query: ''
paths: { }
type: trial
field_prefix: trial
fields: { }
```

### Key definitions

- `type`
  - Content type machine name used by Configure, Import, and Manage
  - Default: `trial`
- `field_prefix`
  - Prefix used when generating Drupal field machine names for fields created through `ClinicalTrialsGovEntityManager`
  - Default: `trial`

### Guidance text

The Settings form descriptions for both `type` and `field_prefix` should explicitly note that common values may include `trial`, `study`, or `nct`, because those are short, readable machine-name conventions that fit the module's use case.

---

## Routing And Navigation

### New route

Add a new route:

- Route name: `clinical_trials_gov.settings`
- Path: `/admin/config/services/clinical-trials-gov/settings`
- Form class: `\Drupal\clinical_trials_gov\Form\ClinicalTrialsGovSettingsForm`
- Permission: `administer clinical_trials_gov`

### New local task

Add a new local task at the end of the existing task list:

- Title: `Settings`
- Base route: `clinical_trials_gov.index`

This task should appear after:

1. Find
2. Review
3. Configure
4. Import
5. Manage
6. Settings

### Overview page update

`ClinicalTrialsGovController::index()` should add a sixth admin block item:

- Title: `Settings`
- Description: explains that advanced users can configure the destination content type machine name and generated field prefix
- URL: `clinical_trials_gov.settings`

The introductory text should also be updated so the task list description includes Settings as part of the overall workflow.

---

## Settings Form

### Class

Create `\Drupal\clinical_trials_gov\Form\ClinicalTrialsGovSettingsForm` extending `ConfigFormBase`.

This form owns the editable configuration for:

- `clinical_trials_gov.settings:type`
- `clinical_trials_gov.settings:field_prefix`

using `#config_target`.

### Form fields

The form contains two textfields:

1. `type`
   - Title: `Content type machine name`
   - `#config_target`: `clinical_trials_gov.settings:type`
   - Default config value: `trial`
   - Description should explain that this controls the content type used by the ClinicalTrials.gov workflow and that values such as `trial`, `study`, or `nct` are reasonable examples

2. `field_prefix`
   - Title: `Field prefix`
   - `#config_target`: `clinical_trials_gov.settings:field_prefix`
   - Default config value: `trial`
   - Description should explain that this value is used to generate Drupal field machine names for fields created by `ClinicalTrialsGovEntityManager`, and that values such as `trial`, `study`, or `nct` are reasonable examples

### Locking behavior

The form remains editable until the generated destination structure exists. After that, both textfields become disabled.

The lock condition is:

- the configured content type exists, or
- generated fields using the configured prefix already exist for that bundle

The practical intent is to prevent users from changing machine-name settings after Configure has already created the destination structure.

When locked:

- `type` is `#disabled => TRUE`
- `field_prefix` is `#disabled => TRUE`
- each field includes description/help text explaining that machine names are locked after the destination content type and fields are created

### Validation

No custom validation beyond the normal textfield requirements is required for this enhancement unless the existing codebase already applies a machine-name pattern helper. The design assumes the values are stored as machine-name-style strings and relies on downstream Drupal entity creation to reject invalid bundle names if one is provided.

If implementation discovers a strong existing machine-name validation pattern in this module, the form should reuse that pattern rather than inventing a new one.

---

## Configure Form Changes

### Ownership change

`ClinicalTrialsGovConfigForm` no longer owns or edits `type`. It reads the configured `type` and uses it as the source of truth for:

- loading an existing bundle
- deciding whether a bundle needs to be created
- deciding which fields already exist
- creating new fields for the configured bundle

### UI behavior

Configure continues to handle:

- reviewing the destination content type details
- creating the content type if it does not yet exist
- selecting field mappings

Configure no longer allows direct editing of the content type machine name.

If the configured bundle does not yet exist, Configure should display a message above the creation/mapping UI:

`Review the content type and fields that will be created below. Go to Settings to change the machine names and field prefix.`

Requirements for this message:

- `Settings` links to `clinical_trials_gov.settings`
- the message appears only when the destination content type and generated fields do not yet exist
- the message is phrased as guidance, not as an error

### Content type section behavior

When the configured content type does not exist:

- show the label and description inputs needed to create the node type
- show the configured machine name as read-only context, not as an editable field

When the configured content type already exists:

- continue showing label, machine name, and description as read-only items

This preserves Configure as the place where the bundle is reviewed and created, while Settings remains the place where advanced machine-name configuration happens.

---

## Field Generation Changes

### Source of truth

`ClinicalTrialsGovEntityManager` must use the configured `field_prefix` instead of a hard-coded or implicit prefix when generating field machine names.

This affects:

- generated metadata field names
- generated built-in system link fields created by the entity manager

### Behavior

Field generation must remain:

- deterministic
- Drupal-safe
- consistent with the current length-limiting strategy

Only the prefix source changes.

If the current behavior effectively generates names that look like `field_*`, the updated behavior should incorporate the configured prefix while preserving Drupal field naming conventions and the current collision-avoidance/length strategy. The implementation should follow the existing field-name normalization pattern already used by this module.

### Existing structure

This enhancement does not rename fields that already exist. The prefix only affects fields created after the configuration is saved and before the structure is locked by creation.

---

## Testing Strategy

### Functional coverage

Add functional coverage for the new Settings task:

- the overview page shows `Settings`
- the Settings task is linked from the overview page
- the Settings form shows default values `trial` and `trial`
- the descriptions mention example values `trial`, `study`, and `nct`
- before any destination structure exists, both fields are editable
- after Configure creates the destination content type and fields, both fields are disabled

Update wizard-flow functional coverage to assert:

- the Configure form shows the new guidance message when the content type and fields do not yet exist
- the message includes a link to Settings

### Kernel or unit coverage

Add coverage around generated field names so the configured `field_prefix` is used by `ClinicalTrialsGovEntityManager`.

This should verify at least:

- the default prefix still generates the expected names for the current workflow
- a changed prefix such as `study` or `nct` changes generated field names deterministically

### Scope note

The tests do not need to cover renaming existing fields because that is explicitly out of scope for this enhancement.

---

## Risks And Constraints

- Locking must be based on real created structure, not just on whether config values are non-empty
- The Settings form and Configure form must not drift into conflicting sources of truth for `type`
- Field-prefix changes must be applied consistently across ordinary generated fields and system link fields
- Because the module already has existing tests that assume `trial`, test fixtures may need minor updates to keep default behavior explicit rather than implied

---

## Implementation Summary

The enhancement adds a dedicated Settings task that owns the advanced machine-name settings for the ClinicalTrials.gov workflow. `type` and `field_prefix` are editable before structure creation, then locked after the destination content type and generated fields exist. Configure becomes a consumer of those settings, shows users what will be created, and directs advanced users to Settings when they need to change machine names before creation.
