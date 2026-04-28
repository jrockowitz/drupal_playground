# ClinicalTrials.gov Module Guide

## Purpose

`clinical_trials_gov` is a custom Drupal module that integrates with the ClinicalTrials.gov API v2.

It currently provides:

- shared API, manager, render-builder, field-resolution, entity-creation, and migration services
- an admin import wizard at `/admin/config/services/clinical-trials-gov`
- a generated `migrate_plus` migration named `clinical_trials_gov`
- a report submodule, `clinical_trials_gov_report`
- test fixtures and service overrides for Kernel and Functional tests

This file is meant to help future agents or engineers quickly understand how the module works today.

## High-Level Architecture

Core runtime pieces:

- `ClinicalTrialsGovApi`
  - low-level HTTP client for `/studies`, `/studies/{nctId}`, `/studies/metadata`, `/studies/enums`, and `/version`
- `ClinicalTrialsGovManager`
  - main data-fetching service
  - flattens study data and metadata into dot-notation keys
  - caches study metadata and enums in-memory per request
- `ClinicalTrialsGovBuilder`
  - converts study arrays into render arrays
  - builds study list tables and study detail output
  - supports modal study links when requested
- `ClinicalTrialsGovFieldManager`
  - curates which metadata keys appear in the Configure step
  - currently uses a hard-coded allow-list based on vetted example studies plus required and ancestor keys
- `ClinicalTrialsGovEntityManager`
  - resolves ClinicalTrials.gov metadata rows into Drupal field definitions
  - creates content types, field storage/config, field display components, and field groups
- `ClinicalTrialsGovMigrationManager`
  - generates the active config for `migrate_plus.migration.clinical_trials_gov`
- `ClinicalTrialsGovSource`
  - migrate source plugin
  - paginates through `/studies` with `pageSize=1000` and follows `nextPageToken`
  - flattens rows while preserving parent structured values on parent keys

## Wizard Flow

Routes:

- `clinical_trials_gov.index`
- `clinical_trials_gov.find`
- `clinical_trials_gov.review`
- `clinical_trials_gov.configure`
- `clinical_trials_gov.import`

Local tasks:

- `Overview`
- `1. Find`
- `2. Review`
- `3. Configure`
- `4. Import`

Permissions:

- all wizard pages require `administer clinical_trials_gov`

Step behavior:

1. `Find`
- implemented by `ClinicalTrialsGovFindForm`
- stores the raw saved query string in `clinical_trials_gov.settings:query`
- uses the custom form element `clinical_trials_gov_studies_query`
- currently limits query controls to:
  - `query.*`
  - `filter.overallStatus`
  - `filter.ids`
- includes an Ajax `Preview` section
- preview uses unsaved form values and modal study links
- if a saved query already has parameters, preview auto-loads on first render

2. `Review`
- implemented by `ClinicalTrialsGovReviewController`
- lists studies returned by the saved query
- uses modal links for study detail
- direct navigation to `/review/{nctId}` still works
- pagination in Review is UI-only and based on the API response `nextPageToken`

3. `Configure`
- implemented by `ClinicalTrialsGovConfigForm`
- creates or reuses a destination content type
- shows curated field definitions from `ClinicalTrialsGovFieldManager`
- uses a plain Drupal table with core `drupal.tableselect` behavior
- field-group rows are structural only and have no checkbox
- custom-field child properties are shown under `Field name`
- empty field-group rows are hidden

4. `Import`
- implemented by `ClinicalTrialsGovImportForm`
- shows summary and migration id-map stats
- runs a full sync import using `MigrateBatchExecutable`

## Config and Generated Migration

Primary config:

- `clinical_trials_gov.settings`

Expected keys:

- `query`
- `type`
- `fields`

Generated migration config:

- `migrate_plus.migration.clinical_trials_gov`

Important migration behavior:

- source plugin is `clinical_trials_gov`
- destination plugin is `entity:node`
- `title` is mapped from `protocolSection.identificationModule.briefTitle`
- all other selected fields map by Drupal field machine name to source dot-notation keys
- group-only rows are not added to `process`

## Field Resolution Rules

Field resolution lives mainly in `ClinicalTrialsGovEntityManager::resolveFieldDefinition()`.

Current important rules:

- `briefTitle` maps to node `title`
- required wizard fields are:
  - `protocolSection.identificationModule.nctId`
  - `protocolSection.identificationModule.briefTitle`
  - `protocolSection.descriptionModule.briefSummary`
- scalar `TEXT` becomes:
  - `string` by default
  - `text_long` for markup or long text
- enum fields become `list_string`
- numeric fields become `integer`
- boolean fields become `boolean`
- `DATE` source values become Drupal `datetime` storage with `datetime_type: date`
  - Configure labels these as `date`
- `type[]` becomes unlimited cardinality `-1`
  - this is used for true multi-value fields like `conditions`

### Structured fields

There are three main structured outcomes:

1. `custom_field`
- used for simple non-repeatable structs with scalar children
- also used for the explicit structure whitelist
- examples:
  - `organization`
  - `responsibleParty`
  - `startDateStruct`

2. `field_group`
- used for nested container/grouping structs when `field_group` is available
- these rows are structural, selectable only indirectly, and create form/view group wrappers

3. unsupported
- if a struct is neither simple enough for `custom_field` nor promoted to `field_group`, it is shown as unsupported or hidden by curation

### Partial date rules

Current behavior:

- `PartialDate` leaf fields resolve to Drupal date fields
- `PartialDateStruct` resolves to `custom_field`

This is a recent design decision and should not be “simplified” back to JSON without checking the latest expectations.

### Cardinality

- single-value fields use cardinality `1`
- array-valued scalar fields use cardinality `-1`
- do not use `0` for unlimited cardinality in Drupal

### Field names

Field machine names are deterministic and capped at 32 characters.

Rules:

- generated from metadata `piece` where possible
- normalized to snake_case
- special overrides live in `ClinicalTrialsGovEntityManager::FIELD_NAMES`
- long names are truncated and suffixed with a hash

## Curated Field List

The Configure step does not show all metadata rows from the API.

Instead:

- `ClinicalTrialsGovFieldManager` uses `AVAILABLE_FIELD_KEYS`
- this is a curated allow-list based on vetted example studies
- required fields are always added
- ancestor keys are also added so parent structures can appear

Implication:

- if a metadata row exists in the API but is not in the curated list, it will not appear in Configure even if the resolver could technically support it
- changes to supported fields usually require updating both:
  - `AVAILABLE_FIELD_KEYS`
  - tests that assume the curated table contents

## Source Plugin Behavior

`ClinicalTrialsGovSource` is important:

- it parses the saved raw query string with `ClinicalTrialsGovStudiesQuery::parseQueryString()`
- it forces `pageSize=1000`
- it loops over `nextPageToken`
- it fetches all pages, not just the first page

Important implementation detail:

- flattened scalar leaves are exposed on dotted source keys
- structured parent objects are also preserved on the parent key

That preserved parent data is what allows `custom_field` destinations to map from a struct parent key instead of reconstructing values field-by-field in migrate.

## UI and Styling

Shared module styling:

- library: `clinical_trials_gov/clinical_trials_gov`
- stylesheet: `css/clinical_trials_gov.css`
- used to top-align table cells on Find, Review, and Configure

Studies query form element:

- `ClinicalTrialsGovStudiesQuery`
- supports `#include_fields`
- accepts exact keys and prefixes like `query.`
- blank `#include_fields` means include everything

Modal study links:

- supported by `ClinicalTrialsGovBuilder::buildStudiesList(..., ['modal' => TRUE])`
- wizard Find preview and Review use modal links
- report submodule should remain non-modal unless intentionally changed

Gin integration:

- form sticky actions are intentionally ignored for wizard forms via `hook_gin_ignore_sticky_form_actions()`

## Display and Grouping

When fields are created:

- default form display components are created
- default view display components are created
- field groups are created on both displays when needed

Current field-group display behavior:

- form display uses `details`
- form `details` are `open: true`
- view display uses `fieldset`

## Testing Setup

Test support is split into two locations:

- `tests/modules/clinical_trials_gov_test`
  - real stub service and JSON fixtures for tests
- `modules/clinical_trials_gov_test`
  - lightweight wrapper module for service substitution

Main test classes:

- `ClinicalTrialsGovApiTest`
- `ClinicalTrialsGovManagerTest`
- `ClinicalTrialsGovBuilderTest`
- `ClinicalTrialsGovFieldManagerTest`
- `ClinicalTrialsGovEntityManagerTest`
- `ClinicalTrialsGovMigrationManagerTest`
- `ClinicalTrialsGovSourceTest`
- `ClinicalTrialsGovStudiesQueryTest`
- `ClinicalTrialsGovTest`

The stub manager now simulates paginated `/studies` responses so source-plugin tests can verify pagination behavior.

## Important Current Constraints and Gotchas

1. The module is intentionally opinionated.
- It is not a generic “import every possible ClinicalTrials.gov field” system.
- Field curation is a product decision, not just a technical limitation.

2. `custom_field` is not generic JSON storage.
- Every custom field struct needs explicit column definitions.
- Struct support depends on what `buildCustomFieldColumnDefinition()` can express.

3. `field_group` rows are structural.
- They should not act like normal selected fields.
- Empty group-only rows should stay hidden.

4. The import source now paginates.
- If import totals seem wrong, check source pagination and `nextPageToken` behavior first.

5. `fields=NCTId` is not currently used in the source plugin.
- It would be too aggressive because the migration needs the full selected field payload, not just ids.

6. Review and Find preview may show only the current page.
- Import is now “all pages”.
- UI preview/review and migration source do not have identical fetch behavior.

7. There are two manager stub files.
- The real test logic lives under `tests/modules/...`
- the module-level wrapper under `modules/...` can still affect PHPCS and service wiring

## Useful Commands

Module checks:

```bash
ddev phpcs /Users/rockowij/Sites/drupal_playground/web/modules/custom/clinical_trials_gov
```

Focused tests:

```bash
ddev phpunit web/modules/custom/clinical_trials_gov/tests/src/Functional/ClinicalTrialsGovTest.php
ddev phpunit web/modules/custom/clinical_trials_gov/tests/src/Kernel/ClinicalTrialsGovSourceTest.php
ddev phpunit web/modules/custom/clinical_trials_gov/tests/src/Kernel/ClinicalTrialsGovEntityManagerTest.php
ddev phpunit web/modules/custom/clinical_trials_gov/tests/src/Kernel/ClinicalTrialsGovMigrationManagerTest.php
```

Migration and cache:

```bash
ddev drush cr
ddev drush migrate:status clinical_trials_gov
```

## When Editing This Module

Prefer these habits:

- update tests when changing:
  - field curation
  - struct resolution
  - migration generation
  - source pagination
  - Configure table behavior
- be careful with curated field-list changes because they ripple into Functional tests
- preserve the distinction between:
  - UI preview/review behavior
  - migration source behavior
- if a field seems “missing”, check the field manager allow-list before changing the resolver
- if a struct seems “wrong”, inspect:
  - metadata `type`
  - metadata `sourceType`
  - `children`
  - whether it is intended to be `custom_field`, `field_group`, or unsupported
