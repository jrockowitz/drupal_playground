# ClinicalTrials.gov

`clinical_trials_gov` is a custom Drupal module that helps site builders find studies on ClinicalTrials.gov, review the results, generate a Drupal content model from real study data, import studies, and manage the imported content.

This README is the human-facing companion to [AGENTS.md](/modules/custom/clinical_trials_gov/AGENTS.md). `AGENTS.md` is the implementation guide for coding agents and developers. This file focuses on the product concepts, workflow, and the URLs you are most likely to need.

## Key Concepts

### The module is a guided wizard

The main workflow lives at:

- [ClinicalTrials.gov wizard](https://drupal-playground.ddev.site/admin/config/services/clinical-trials-gov)

The wizard is designed to move in this order:

1. `Find` a study query
2. `Review` the matching studies
3. `Configure` the destination content type and fields
4. `Import` the studies through Drupal Migrate
5. `Manage` the imported nodes

### The query drives everything

The saved ClinicalTrials.gov query is the starting point for the entire workflow.

When you save the query on the `Find` step, the module:

- stores the raw query string
- discovers study data paths from matching studies
- saves those discovered paths into configuration
- uses those paths to decide which fields can be configured and migrated

If the saved query changes, the discovered field paths and generated migration need to be refreshed too.

### Paths are discovered, not hard-coded

The module no longer relies on a static default allow-list of field paths.

Instead, it discovers available paths from the first set of studies returned by the saved query. This means:

- different queries can expose different available fields
- `Configure` is blocked until study paths have been discovered
- if a field seems to be missing, the first thing to check is whether the `Find` step has been saved successfully for a query that returns studies

### The module generates Drupal structure for you

The `Configure` step can create or update:

- a destination content type
- Drupal field storage and field instances
- field groups for nested study sections
- a generated `migrate_plus` migration named `clinical_trials_gov`

This is not a hand-authored migration workflow. The migration is generated from the saved wizard configuration.

The saved `fields` config is stored as a mapping of generated Drupal field or group name to the source metadata path.

### Import uses Drupal Migrate

The `Import` step runs a Drupal Migrate batch import based on the generated migration config.

The migration source:

- fetches matching `nctId` values from ClinicalTrials.gov
- loads each full study individually
- flattens study data into dot-notation paths
- preserves parent structured objects where needed so complex custom fields can map cleanly

## Main URLs

### Wizard

- [Overview](https://drupal-playground.ddev.site/admin/config/services/clinical-trials-gov)
- [1. Find](https://drupal-playground.ddev.site/admin/config/services/clinical-trials-gov/find)
- [2. Review](https://drupal-playground.ddev.site/admin/config/services/clinical-trials-gov/review)
- [3. Configure](https://drupal-playground.ddev.site/admin/config/services/clinical-trials-gov/configure)
- [4. Import](https://drupal-playground.ddev.site/admin/config/services/clinical-trials-gov/import)
- [5. Manage](https://drupal-playground.ddev.site/admin/config/services/clinical-trials-gov/manage)

### Supporting admin pages

- [Migration overview](https://drupal-playground.ddev.site/admin/structure/migrate/manage/default/migrations/clinical_trials_gov)
- [Imported content](https://drupal-playground.ddev.site/admin/content)

### Test and exploration tools

- [Standalone explorer](https://drupal-playground.ddev.site/modules/custom/clinical_trials_gov/test/clinical_trials_gov.php)
- [Drupal status report entry for the report submodule](https://drupal-playground.ddev.site/admin/reports/status/clinical-trials-gov)

### External references

- [ClinicalTrials.gov API v2 docs](https://clinicaltrials.gov/data-api/api)
- [ClinicalTrials.gov protocol definitions](https://clinicaltrials.gov/policy/protocol-definitions)

## Wizard Steps

### 1. Find

The `Find` step is where you save the ClinicalTrials.gov search query.

It also provides:

- a query builder form element for supported parameters
- an Ajax preview of returned studies
- a batch process that discovers available study paths after the query is saved

Important behavior:

- previewing a query is not the same as saving it
- the query must be saved to update the stored query, discovered paths, and generated migration
- if no studies are found, `Configure` will remain blocked because there are no discovered paths to work from

### 2. Review

The `Review` step lists the studies returned by the saved query.

It is meant to answer:

- did the saved query return the studies I expected?
- do these studies have the data shape I want to build fields for?

The study detail page uses the study `briefTitle` as the page title and is available at the route with `{nctId}` appended.

### 3. Configure

The `Configure` step lets you:

- create or reuse the destination content type
- choose which discovered study paths become Drupal fields
- inspect field types and grouped structures before importing

Important behavior:

- `Configure` requires discovered `paths`
- some rows are structural only and exist to support field groups
- some nested structs are promoted into `custom_field` fields instead of expanding into many top-level Drupal fields

### 4. Import

The `Import` step shows:

- the saved query summary
- the configured content type summary
- migration status statistics such as total, imported, unprocessed, messages, and last imported

From here you run the generated migration.

Important behavior:

- the migration only exists when `query`, `paths`, `type`, and `fields` are all populated
- if one of those is missing, the generated migration is deleted and the import step is not ready

### 5. Manage

The `Manage` step is a convenience redirect.

It sends you to:

- the Drupal content listing filtered to the configured content type

If the destination content type has not actually been created yet, it redirects back to `Configure` with a message.

## Field Modeling Notes

Some of the most important modeling decisions in this module are:

- `briefTitle` maps to Drupal node `title`
- `briefTitle` is also preserved as its own generated field, `field_brief_title`
- required fields are always forced in
- array-valued API types become unlimited-cardinality Drupal fields
- nested structs may become `custom_field` fields or `field_group` containers
- markup content is stored as long text

The field-resolution details live in code, but those concepts explain why the generated field list sometimes looks different from the raw API JSON.

## Useful Files

- [AGENTS.md](/modules/custom/clinical_trials_gov/AGENTS.md)
- [Module services](/modules/custom/clinical_trials_gov/clinical_trials_gov.services.yml)
- [Routing](/modules/custom/clinical_trials_gov/clinical_trials_gov.routing.yml)
- [Install config](/modules/custom/clinical_trials_gov/config/install/clinical_trials_gov.settings.yml)
- [Standalone explorer script](https://drupal-playground.ddev.site/modules/custom/clinical_trials_gov/test/clinical_trials_gov.php)

## Test Support

The module includes a test helper module and fixtures for Kernel and Functional coverage.

Key locations:

- [Test fixtures and stub manager](/modules/custom/clinical_trials_gov/tests/modules/clinical_trials_gov_test)
- [Kernel tests](/modules/custom/clinical_trials_gov/tests/src/Kernel)
- [Functional tests](/modules/custom/clinical_trials_gov/tests/src/Functional)

## Development Commands

```bash
# Run the module test suite.
ddev phpunit web/modules/custom/clinical_trials_gov

# Run focused tests.
ddev phpunit web/modules/custom/clinical_trials_gov/tests/src/Functional/ClinicalTrialsGovTest.php
ddev phpunit web/modules/custom/clinical_trials_gov/tests/src/Kernel/ClinicalTrialsGovSourceTest.php
ddev phpunit web/modules/custom/clinical_trials_gov/tests/src/Kernel/ClinicalTrialsGovEntityManagerTest.php
ddev phpunit web/modules/custom/clinical_trials_gov/tests/src/Kernel/ClinicalTrialsGovMigrationManagerTest.php

# Run linting and static analysis.
ddev code-review web/modules/custom/clinical_trials_gov

# Clear caches and inspect migration status.
ddev drush cr
ddev drush migrate:status clinical_trials_gov
```

## Troubleshooting

### Configure is blocked

Check whether:

- the query has been saved from `Find`
- the save batch finished successfully
- `clinical_trials_gov.settings:paths` has been populated

### A field is missing

Usually this means one of these:

- the saved query did not return studies containing that path
- the path discovery batch has not been rerun since the query changed
- the field is structural only and not directly selectable

### The migration is missing

The generated migration is removed when any of these are incomplete:

- `query`
- `paths`
- `type`
- `fields`

### Import errors mention field length

ClinicalTrials.gov metadata is not always complete about maximum text length. If a field overflows a generated Drupal column, check:

- the field definition logic in `ClinicalTrialsGovFieldManager`
- any policy-backed limits from ClinicalTrials.gov protocol definitions
- whether the field storage was generated before the latest fix and needs to be recreated
