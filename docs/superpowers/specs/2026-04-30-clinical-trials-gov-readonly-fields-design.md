# ClinicalTrials.gov Readonly Fields — Design Spec

**Date:** 2026-04-30
**Status:** Draft for review

---

## Context

The `clinical_trials_gov` module already generates a destination content type, Drupal fields, and a migration based on ClinicalTrials.gov study data. Editors can then open imported trial nodes and manually change imported values.

This enhancement adds an optional readonly mode for imported ClinicalTrials.gov fields using the `readonly_field_widget` contrib module. The feature is controlled from the existing Settings form and only activates when both:

- `clinical_trials_gov.settings:readonly` is enabled
- the `readonly_field_widget` module is installed and enabled

The readonly behavior should affect only fields that belong to the saved ClinicalTrials.gov field mapping, with one special rule for the title:

- the generated `briefTitle` field should become readonly
- the core node title input should be completely hidden

This gives site builders an optional way to prevent editorial drift on imported trial data while still allowing the feature to stay off by default.

---

## Goals

- Add `readonly` to `clinical_trials_gov.settings`
- Add an optional readonly toggle to the Settings form
- Hide that toggle when `readonly_field_widget` is not enabled
- Integrate with `readonly_field_widget` through `hook_entity_form_display_alter()`
- Apply readonly behavior only to fields stored in `clinical_trials_gov.settings:fields`
- Hide the core node title input when readonly mode is active and the ClinicalTrials.gov title mapping is present
- Keep the generated `briefTitle` field visible and readonly
- Add test coverage using `readonly_field_widget` as a `require-dev` dependency
- Update `README.md` and `AGENTS.md`

---

## Non-goals

- No permanent rewrite of field storage or field configuration when readonly mode is toggled
- No readonly behavior for unrelated fields on the same content type
- No replacement of the core node title with a readonly display widget
- No requirement that sites install `readonly_field_widget` in production unless they want this feature

---

## Dependency Model

The module-level Composer metadata will include:

- `drupal/readonly_field_widget` in `require-dev`

This keeps automated coverage and local development support available without making the dependency mandatory for every production installation.

The feature remains optional at runtime. If `readonly_field_widget` is not enabled, the readonly toggle is hidden and the hook behavior remains inactive.

---

## Configuration Model

`clinical_trials_gov.settings` gains:

```yaml
readonly: false
```

### Meaning

- `FALSE`
  - imported trial fields behave normally on edit forms
- `TRUE`
  - imported mapped trial fields are rendered with `readonly_field_widget`
  - the node title input is hidden when the ClinicalTrials.gov title mapping is active

This setting is operational, not structural, so it should remain editable even after the destination content type and fields have already been created.

---

## Settings Form

### New form element

Add a checkbox to `ClinicalTrialsGovSettingsForm`:

- Title: `Read-only imported fields`
- `#config_target`: `clinical_trials_gov.settings:readonly`
- `#access`: `FALSE` unless the `readonly_field_widget` module is enabled

### Description

The checkbox description should explain that enabling this option makes imported ClinicalTrials.gov fields readonly on edit forms and hides the editable node title when the generated title mapping is present.

### Locking behavior

Unlike `type` and `field_prefix`, this checkbox is not locked after structure creation. Site builders should be able to turn readonly behavior on or off later.

---

## Hook Integration

### Hook location

Implement the behavior in the module’s OOP hook class:

- `\Drupal\clinical_trials_gov\Hook\ClinicalTrialsGovHooks`

using:

- `hook_entity_form_display_alter()`

This follows the module’s existing hook pattern and the `readonly_field_widget` example.

### Activation rules

Only alter a form display when all of these are true:

1. `clinical_trials_gov.settings:readonly` is `TRUE`
2. `readonly_field_widget` is enabled
3. the altered entity type is `node`
4. the altered bundle matches `clinical_trials_gov.settings:type`
5. `clinical_trials_gov.settings:fields` contains ClinicalTrials.gov mapped fields

### Field widget changes

For each saved field name in `clinical_trials_gov.settings:fields`:

- if the form display has a component for that field name
- change the component widget `type` to `readonly_field_widget`
- preserve the component where possible and inject readonly widget settings

The widget settings should follow the contrib module example and use a readable formatter configuration so the field value is visible in the edit form rather than appearing disabled or empty.

### Scope

Only mapped ClinicalTrials.gov fields should be affected. Unrelated site fields on the same content type must remain editable.

---

## Title Handling

### Special-case behavior

The core node title input is not stored in `clinical_trials_gov.settings:fields`, because that config stores generated field/group names rather than base-field properties.

To support the desired UX:

- detect whether the saved ClinicalTrials.gov field mapping includes the `briefTitle` source path
- when readonly mode is active and that mapping exists, hide the core node title input completely

### Hiding strategy

The title should be hidden, not displayed as readonly text.

The generated `briefTitle` field remains on the form and should be switched to `readonly_field_widget`, so the editor still sees the imported title value in readonly form.

### Intent

This produces the requested final state:

- no editable node title input
- no alternate readonly title widget for the core title element
- readonly generated `briefTitle` field visible on the form

---

## Implementation Boundaries

### Form display alteration

The readonly widget switch belongs in `hook_entity_form_display_alter()` because that is the supported integration point for `readonly_field_widget`.

### Form element hiding

Because the node title is a base field and not one of the saved generated field names, the implementation may need a companion form-level alteration or equivalent mechanism to hide the title input after the form display changes are applied.

The implementation should keep this special case minimal and tightly scoped to:

- readonly mode enabled
- configured ClinicalTrials.gov bundle
- `briefTitle` source path present in saved mappings

---

## Testing Strategy

### Functional coverage

Extend the main ClinicalTrials.gov functional test to cover readonly behavior:

- when `readonly_field_widget` is enabled, the Settings form shows the readonly checkbox
- saving the checkbox persists `clinical_trials_gov.settings:readonly`
- after the wizard creates the destination structure and readonly is enabled, opening the trial node edit form shows:
  - the core title input is hidden
  - the generated `briefTitle` field is present
  - mapped ClinicalTrials.gov fields are rendered in readonly mode

Also add coverage for the hidden-checkbox path if needed:

- when `readonly_field_widget` is not enabled, the readonly checkbox is not available on Settings

### Kernel coverage

Add focused kernel coverage for hook/display behavior:

- readonly off: mapped fields keep their normal widget types
- readonly on with `readonly_field_widget` enabled: mapped fields switch to `readonly_field_widget`
- unrelated fields are not altered

### Test dependencies

Add `readonly_field_widget` to the relevant test module lists so the functional and kernel tests can exercise the actual widget plugin.

---

## Documentation Updates

### README.md

Update the human-facing README to document:

- the optional readonly mode
- the `readonly_field_widget` integration
- the Settings toggle
- the title-hiding behavior and readonly `briefTitle` behavior

### AGENTS.md

Update the developer/agent guide to document:

- `clinical_trials_gov.settings:readonly`
- that readonly mode only applies when `readonly_field_widget` is enabled
- that mapped ClinicalTrials.gov fields become readonly on edit forms
- that the core title input is hidden when readonly mode is active and the title mapping exists
- that the generated `briefTitle` field remains visible and readonly

---

## Risks And Constraints

- `readonly_field_widget` must have a value to display, so readonly behavior depends on the imported field actually containing data
- Hiding the node title must not accidentally affect unrelated bundles
- The implementation must not make unrelated fields readonly just because they live on the same content type
- The readonly toggle must degrade gracefully when the contrib module is absent

---

## Implementation Summary

This enhancement adds an optional readonly mode for imported ClinicalTrials.gov fields. A new `readonly` setting is exposed in Settings only when `readonly_field_widget` is enabled. When turned on, mapped trial fields are rendered with `readonly_field_widget`, the core node title input is hidden, and the generated `briefTitle` field stays visible as readonly. The feature remains optional, bundle-scoped, and covered by both functional and kernel tests.
