# ClinicalTrials.gov

`clinical_trials_gov` is a custom Drupal module that helps site builders find studies on ClinicalTrials.gov, review the results, generate a Drupal content model from real study data, import studies, and manage the imported content.

This README is the human-facing companion to [AGENTS.md](/modules/custom/clinical_trials_gov/AGENTS.md). `AGENTS.md` is the implementation guide for coding agents and developers. This file focuses on the product concepts, workflow, and the URLs you are most likely to need.

## Drush Setup

The module now includes a Drush setup command:

```bash
ddev drush clinical-trials-gov:setup 'query.cond=Cancer&query.term=New%20York&filter.overallStatus=RECRUITING'
```

What it does:

- saves the raw query to `clinical_trials_gov.settings:query`
- discovers and saves `clinical_trials_gov.settings:query_paths`
- derives the default `clinical_trials_gov.settings:fields` mapping using the same default-selection behavior as the Configure UI
- creates the configured content type and fields
- regenerates the `clinical_trials_gov` migration

After the setup command completes, run:

```bash
ddev drush migrate:import clinical_trials_gov
```

If you want the install script to do both steps for you, use:

```bash
ddev install trials-setup
```

## Key Concepts

### The module is a guided wizard

The main workflow lives at:

- [ClinicalTrials.gov wizard](https://drupal-playground.ddev.site/admin/config/services/clinical-trials-gov)

The wizard is designed to move in this order:

1. `Find` a study query
2. `Review` the matching studies and metadata
3. `Configure` the destination content type and fields
4. `Import` the studies through Drupal Migrate
5. `Manage` the imported nodes
6. `Settings` for optional advanced behavior such as readonly imported fields

### The query drives everything

The saved ClinicalTrials.gov query is the starting point for the entire workflow.

When you save the query on the `Find` step, the module:

- stores the raw query string
- discovers field paths from matching studies
- saves those discovered paths into configuration
- uses those paths to decide which fields can be configured and migrated

If the saved query changes, the discovered field paths and generated migration need to be refreshed too.

### Paths are discovered, not hard-coded

The module no longer relies on a static default allow-list of field paths.

Instead, it discovers available paths from the first set of studies returned by the saved query. This means:

- different queries can expose different available fields
- `Configure` is blocked until study paths have been discovered
- if a field seems to be missing, the first thing to check is whether the `Find` step has been saved successfully for a query that returns studies

The discovery logic now also captures child paths inside repeated structured lists. For example, a repeated location structure can contribute both:

- `protocolSection.contactsLocationsModule.locations`
- `protocolSection.contactsLocationsModule.locations.facility`

### The module generates Drupal structure for you

The `Configure` step can create or update:

- a destination content type
- Drupal field storage and field instances
- field groups for nested study sections
- a generated `migrate_plus` migration named `clinical_trials_gov`
- the `clinical-trials-gov:setup` Drush command for CLI-driven setup

When the destination ClinicalTrials.gov content type exists, Drupal users cannot create those nodes manually through `/node/add`. Trial content is intended to be created by the import workflow.

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
- [2. Review / Studies](https://drupal-playground.ddev.site/admin/config/services/clinical-trials-gov/review)
- [2. Review / Metadata](https://drupal-playground.ddev.site/admin/config/services/clinical-trials-gov/review/metadata)
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
- after save, the status message tells you to review the selected studies below
- if no studies are found, `Configure` will remain blocked because there are no discovered paths to work from

### 2. Review

The `Review` step has two subtasks:

- `Studies` lists the studies returned by the saved query
- `Metadata` shows the flattened metadata rows for the exact saved `paths`

It is meant to answer:

- did the saved query return the studies I expected?
- do these studies have the data shape I want to build fields for?

The `Metadata` page also includes:

- the same `Studies query` details block shown on the `Studies` page
- a `Field paths` details section listing all saved field paths used by the queried studies

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
- if readonly mode is enabled through `Settings`, generated ClinicalTrials.gov fields become readonly on edit forms

### 4. Import

The `Import` step shows:

- the saved query summary
- the configured content type summary
- migration status statistics such as total, imported, unprocessed, messages, and last imported

From here you run the generated migration.

Important behavior:

- the migration only exists when `query`, `query_paths`, `type`, and `fields` are all populated
- if one of those is missing, the generated migration is deleted and the import step is not ready

### 5. Manage

The `Manage` step is a convenience redirect.

It sends you to:

- the Drupal content listing filtered to the configured content type

Imported trial nodes can be managed from the content listing, but they cannot be manually created from Drupal's add-content UI.

If the destination content type has not actually been created yet, it redirects back to `Configure` with a message.

### Settings

The optional `Settings` step controls advanced behavior for the generated trial content type.

Current advanced options include:

- destination content type machine name
- generated field prefix
- optional readonly mode for imported fields

The configured ClinicalTrials.gov destination bundle is treated as import-managed content. Even users with elevated Drupal permissions cannot manually create those nodes through the UI.

The saved `field_prefix` value is used directly when generating Drupal field names. For example, `trial_version_holder` produces generated Drupal field names like `trial_version_holder_brief_title`.

Readonly mode requires the contrib module `readonly_field_widget`. When enabled:

- mapped ClinicalTrials.gov fields are shown with a readonly widget on node edit forms
- the built-in ClinicalTrials.gov link fields `trial_nct_url` and `trial_nct_api` are also shown with a readonly widget
- the editable Drupal node title input is hidden
- the generated `briefTitle` field remains visible as readonly display text

## Field Modeling Notes

Some of the most important modeling decisions in this module are:

- `briefTitle` maps to Drupal node `title`
- `briefTitle` is also preserved as its own generated field, such as `trial_brief_title`
- required fields are always forced in
- array-valued API types become unlimited-cardinality Drupal fields
- nested structs may become `custom_field` fields or `field_group` containers
- unsupported nested values inside promoted `custom_field` fields fall back to YAML-backed `string_long` properties labeled with `(YAML)`
- markup content is stored as long text

The field-resolution details live in code, but those concepts explain why the generated field list sometimes looks different from the raw API JSON.

### Recommended required paths

When `clinical_trials_gov.settings:required_paths` is used to guarantee enough metadata for both search and study-detail display, it helps to think about the paths in categories even though the saved config itself should remain a flat ordered list.

`*` means the path is especially useful for Search API / Elasticsearch filters or facets.

Recommended categories:

- Identity and summary
  - `protocolSection.identificationModule.nctId` *
  - `protocolSection.identificationModule.orgStudyIdInfo.id`
  - `protocolSection.identificationModule.secondaryIdInfos`
  - `protocolSection.identificationModule.briefTitle` *
  - `protocolSection.identificationModule.officialTitle`
  - `protocolSection.identificationModule.acronym` *
  - `protocolSection.identificationModule.organization.fullName`
  - `protocolSection.identificationModule.organization.class` *
  - `protocolSection.descriptionModule.briefSummary`
  - `protocolSection.descriptionModule.detailedDescription`
- Status and dates
  - `protocolSection.statusModule.whyStopped`
  - `protocolSection.statusModule.studyFirstSubmitDate`
  - `protocolSection.statusModule.overallStatus` *
  - `protocolSection.statusModule.startDateStruct.date` *
  - `protocolSection.statusModule.primaryCompletionDateStruct.date`
  - `protocolSection.statusModule.completionDateStruct.date`
  - `protocolSection.statusModule.lastUpdatePostDateStruct.date`
- Conditions and terms
  - `protocolSection.conditionsModule.conditions` *
  - `protocolSection.conditionsModule.keywords` *
- Study design
  - `protocolSection.designModule.studyType` *
  - `protocolSection.designModule.phases` *
  - `protocolSection.designModule.enrollmentInfo.count`
- Interventions
  - `protocolSection.armsInterventionsModule.armGroups`
  - `protocolSection.armsInterventionsModule.interventions.type` *
  - `protocolSection.armsInterventionsModule.interventions.name` *
  - `protocolSection.armsInterventionsModule.interventions.description`
- Outcomes
  - `protocolSection.outcomesModule.primaryOutcomes`
  - `protocolSection.outcomesModule.secondaryOutcomes`
- Eligibility
  - `protocolSection.eligibilityModule.eligibilityCriteria`
  - `protocolSection.eligibilityModule.sex` *
  - `protocolSection.eligibilityModule.minimumAge` *
  - `protocolSection.eligibilityModule.maximumAge` *
  - `protocolSection.eligibilityModule.stdAges` *
  - `protocolSection.eligibilityModule.healthyVolunteers` *
  - `protocolSection.eligibilityModule.studyPopulation`
  - `protocolSection.eligibilityModule.samplingMethod` *
- Sponsors and collaborators
  - `protocolSection.sponsorCollaboratorsModule.leadSponsor.name` *
  - `protocolSection.sponsorCollaboratorsModule.leadSponsor.class` *
  - `protocolSection.sponsorCollaboratorsModule.collaborators.name`
  - `protocolSection.sponsorCollaboratorsModule.collaborators.class` *
- Contacts and officials
  - `protocolSection.contactsLocationsModule.centralContacts`
  - `protocolSection.contactsLocationsModule.overallOfficials`
- Locations
  - `protocolSection.contactsLocationsModule.locations.facility`
  - `protocolSection.contactsLocationsModule.locations.status` *
  - `protocolSection.contactsLocationsModule.locations.city` *
  - `protocolSection.contactsLocationsModule.locations.state` *
  - `protocolSection.contactsLocationsModule.locations.country` *
- Results
  - `hasResults` *

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

Notable coverage includes:

- `ClinicalTrialsGovPathDiscoveryBatchTest` for the Find-step field-path discovery batch
- `ClinicalTrialsGovReviewMetadataControllerTest` for the filtered review metadata page
- `ClinicalTrialsGovSourceTest` for flattened migrate source rows, including repeated struct child paths

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
- `clinical_trials_gov.settings:query_paths` has been populated

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
