# ClinicalTrials.gov — Drupal Module Specification

## Executive Summary

- Syndicates clinical trial data from the ClinicalTrials.gov API into Drupal Trial nodes
- Provides a numbered, three-step admin interface: configure, review, and import
- Dynamically creates a Trial content type and fields that mirror the ClinicalTrials.gov data schema
- Leverages existing Drupal contributed modules (Migrate, Migrate Plus, Migrate Tools) with minimal custom code
- Uses NCT ID as the unique identifier for upsert logic during import

## Module Metadata

- **Module label:** ClinicalTrials.gov
- **Machine name:** `clinical_trials_gov`
- **Namespace:** `Drupal\clinical_trials_gov`
- **Class prefix:** `ClinicalTrialsGov`
- **Admin path:** `/admin/config/web-services/clinical-trials-gov`

## Dependencies

| Module | URL | Description |
|---|---|---|
| `migrate` | https://www.drupal.org/docs/drupal-apis/migrate-api | Core migration framework providing ETL pipeline for data import |
| `migrate_plus` | https://www.drupal.org/project/migrate_plus | Extends Migrate with URL source plugin, HTTP data fetcher, and JSON parser |
| `migrate_tools` | https://www.drupal.org/project/migrate_tools | Provides Drush commands and admin UI for executing and managing migrations |

## Module Architecture

```
clinical_trials_gov/
├── AGENTS.md
├── README.md
├── clinical_trials_gov.info.yml
├── clinical_trials_gov.install
├── clinical_trials_gov.module
├── clinical_trials_gov.routing.yml
├── clinical_trials_gov.services.yml
├── clinical_trials_gov.links.menu.yml
├── clinical_trials_gov.links.task.yml
├── config/
│   ├── install/
│   │   ├── clinical_trials_gov.settings.yml
│   │   └── migrate_plus.migration.clinical_trials_gov.yml
│   └── schema/
│       └── clinical_trials_gov.schema.yml
├── src/
│   ├── ClinicalTrialsGovApiInterface.php
│   ├── ClinicalTrialsGovApi.php
│   ├── ClinicalTrialsGovManagerInterface.php
│   ├── ClinicalTrialsGovManager.php
│   ├── ClinicalTrialsGovFieldBuilderInterface.php
│   ├── ClinicalTrialsGovFieldBuilder.php
│   ├── Controller/
│   │   ├── ClinicalTrialsGovController.php
│   │   └── ClinicalTrialsGovReviewController.php
│   ├── Form/
│   │   ├── ClinicalTrialsGovConfigureForm.php
│   │   └── ClinicalTrialsGovImportForm.php
│   └── Plugin/
│       └── migrate/
│           └── source/
│               └── ClinicalTrialsGovSource.php
├── tests/
│   └── test.php
└── js/
```

## Core Services

The module provides three primary services, each defined through an interface.

### ClinicalTrialsGovApi

- **Service name:** `clinical_trials_gov.api`
- **Interface:** `ClinicalTrialsGovApiInterface`
- **Class:** `ClinicalTrialsGovApi`

A low-level HTTP client for calling the ClinicalTrials.gov API. Handles endpoint construction, HTTP requests, pagination via `nextPageToken`, and raw JSON response parsing. This service has no knowledge of Drupal entities or field mappings.

### ClinicalTrialsGovManager

- **Service name:** `clinical_trials_gov.manager`
- **Interface:** `ClinicalTrialsGovManagerInterface`
- **Class:** `ClinicalTrialsGovManager`

Uses the `ClinicalTrialsGovApi` service to fetch data from ClinicalTrials.gov and organizes it into structured data that the module can consume. Handles autocomplete lookups, trial summary formatting, and count retrieval.

### ClinicalTrialsGovFieldBuilder

- **Service name:** `clinical_trials_gov.field_builder`
- **Interface:** `ClinicalTrialsGovFieldBuilderInterface`
- **Class:** `ClinicalTrialsGovFieldBuilder`

Creates and manages the Trial content type and its fields. On module installation, it creates the Trial content type with a set of required and common fields. During configuration, it dynamically creates additional fields based on the admin's selected ClinicalTrials.gov schema fields.

## Installation Behavior

When the module is installed, `hook_install` calls the Field Builder service to create a `trial` content type with a predefined set of fields.

### Required Fields (cannot be removed)

These fields are always present and cannot be unconfigured by the admin.

| Field Label | Drupal Field Name | Drupal Field Type | Source |
|---|---|---|---|
| NCT ID | `field_nct_id` | `string` (max 11 chars) | `protocolSection.identificationModule.nctId` |
| Title | `title` | Node title (core) | `protocolSection.identificationModule.briefTitle` |
| Brief Summary | `field_brief_summary` | `text_long` | `protocolSection.descriptionModule.briefSummary` |

### Update vs. Create Logic

The migration uses NCT ID as the unique migration map key. On each import run, if a trial with a matching NCT ID already exists in the migration map, the existing node is updated with any changed data. If no match is found, a new Trial node is created. This upsert pattern ensures data stays current without creating duplicates.

## Data Structure and Field Mapping

### Reference

- **Data structure metadata:** https://clinicaltrials.gov/api/v2/studies/metadata
- **Data structure documentation:** https://clinicaltrials.gov/data-api/about-api/study-data-structure

The ClinicalTrials.gov API v2 returns study data as hierarchical JSON organized into modules within a `protocolSection` and a `derivedSection`. Data uses ISO 8601 dates, enumerated values for statuses and phases, and CommonMark Markdown for rich text.

### API to Drupal Field Mapping

The following table maps common ClinicalTrials.gov API fields to Drupal node fields. Fields marked with ⚠️ have Drupal machine names exceeding 32 characters and will need to be shortened.

| API Path | API Type | Drupal Field Name | Drupal Type | Multi | Notes |
|---|---|---|---|---|---|
| `protocolSection.identificationModule.nctId` | string | `field_nct_id` | `string` (11) | No | Required. Unique ID. |
| `protocolSection.identificationModule.briefTitle` | string | `title` | Node title | No | Required. |
| `protocolSection.identificationModule.officialTitle` | string | `field_official_title` | `string_long` | No | |
| `protocolSection.identificationModule.acronym` | string | `field_acronym` | `string` (20) | No | |
| `protocolSection.identificationModule.organization.fullName` | string | `field_organization` | `string` (255) | No | |
| `protocolSection.statusModule.overallStatus` | enum | `field_overall_status` | `list_string` | No | e.g., RECRUITING, COMPLETED |
| `protocolSection.statusModule.startDateStruct.date` | date | `field_start_date` | `datetime` | No | ISO 8601 |
| `protocolSection.statusModule.completionDateStruct.date` | date | `field_completion_date` | `datetime` | No | ISO 8601 |
| `protocolSection.statusModule.primaryCompletionDateStruct.date` | date | `field_primary_completion` | `datetime` | No | Shortened from `field_primary_completion_date` |
| `protocolSection.statusModule.lastUpdatePostDateStruct.date` | date | `field_last_update_date` | `datetime` | No | |
| `protocolSection.sponsorCollaboratorsModule.leadSponsor.name` | string | `field_lead_sponsor` | `string` (255) | No | |
| `protocolSection.sponsorCollaboratorsModule.leadSponsor.class` | enum | `field_lead_sponsor_class` | `list_string` | No | e.g., INDUSTRY, NIH |
| `protocolSection.sponsorCollaboratorsModule.collaborators` | array | `field_collaborators` | `string` (255) | Yes | Array of names |
| `protocolSection.descriptionModule.briefSummary` | markdown | `field_brief_summary` | `text_long` | No | Required. CommonMark. |
| `protocolSection.descriptionModule.detailedDescription` | markdown | `field_detailed_description` | `text_long` | No | ⚠️ 27 chars, OK |
| `protocolSection.conditionsModule.conditions` | array | `field_conditions` | `string` (255) | Yes | |
| `protocolSection.conditionsModule.keywords` | array | `field_keywords` | `string` (255) | Yes | |
| `protocolSection.designModule.studyType` | enum | `field_study_type` | `list_string` | No | INTERVENTIONAL, OBSERVATIONAL |
| `protocolSection.designModule.phases` | array(enum) | `field_phases` | `list_string` | Yes | PHASE1, PHASE2, etc. |
| `protocolSection.designModule.enrollmentInfo.count` | integer | `field_enrollment` | `integer` | No | |
| `protocolSection.designModule.enrollmentInfo.type` | enum | `field_enrollment_type` | `list_string` | No | ACTUAL, ESTIMATED |
| `protocolSection.armsInterventionsModule.interventions` | array | `field_interventions` | `string` (255) | Yes | Intervention names |
| `protocolSection.armsInterventionsModule.interventions[].type` | enum | `field_intervention_types` | `list_string` | Yes | DRUG, DEVICE, etc. |
| `protocolSection.eligibilityModule.sex` | enum | `field_eligible_sex` | `list_string` | No | ALL, FEMALE, MALE |
| `protocolSection.eligibilityModule.minimumAge` | string | `field_minimum_age` | `string` (20) | No | e.g., "18 Years" |
| `protocolSection.eligibilityModule.maximumAge` | string | `field_maximum_age` | `string` (20) | No | e.g., "65 Years" |
| `protocolSection.eligibilityModule.eligibilityCriteria` | markdown | `field_eligibility_criteria` | `text_long` | No | |
| `protocolSection.contactsLocationsModule.locations` | array | `field_locations` | `string_long` | Yes | Serialized or structured |
| `protocolSection.outcomesModule.primaryOutcomes` | array | `field_primary_outcomes` | `text_long` | Yes | |
| `protocolSection.outcomesModule.secondaryOutcomes` | array | `field_secondary_outcomes` | `text_long` | Yes | |
| `protocolSection.referencesModule.references` | array | `field_references` | `link` | Yes | PMIDs / URLs |
| `derivedSection.miscInfoModule.versionHolder` | string | `field_version_holder` | `string` (20) | No | |
| `hasResults` | boolean | `field_has_results` | `boolean` | No | Top-level field |

## User Interface

The module provides an admin interface at `/admin/config/web-services/clinical-trials-gov`.

### Landing Page

The landing page uses Drupal's `system_admin_block` to display the three steps as numbered blocks:

1. **Configure** — Set up the ClinicalTrials.gov query
2. **Review** — Preview matching trials
3. **Import** — Import trials into Drupal

### 1. Configure Tab

The configure tab presents a form where administrators enter a search query parameter for the ClinicalTrials.gov API. A text field labeled "Query" accepts search expressions such as "cancer" or "heart disease." As the admin types, an autocomplete callback powered by the `ClinicalTrialsGovManager` service fetches the top ten matching trials from ClinicalTrials.gov to assist in query building. Admins can also select which ClinicalTrials.gov fields to pull beyond the required defaults. A submit button stores the query and field selections in module configuration.

### 2. Review Tab

The review tab executes the stored query against the ClinicalTrials.gov API `/v2/studies` endpoint using the `ClinicalTrialsGovManager` service. It displays a paginated list of matching trials with summaries including trial title, status, condition, and lead sponsor. Each trial includes a link to the full trial on ClinicalTrials.gov. The total count of matching trials is displayed at the bottom.

### 3. Import Tab

The import tab displays the total count of trials from the previous query and presents an "Import Trials" button. This form extends Drupal's `ConfirmFormBase`, requiring the admin to confirm they want to trigger the import. Clicking the button triggers the migration using Migrate Tools' batch infrastructure.

## Migration Architecture

The module includes a custom Drupal migration that uses Migrate Plus's URL source plugin as its foundation. A custom migrate source plugin (`ClinicalTrialsGovSource`) extends the base URL plugin to:

- Inject the stored query parameter from module configuration
- Handle ClinicalTrials.gov API pagination via `nextPageToken`
- Parse the JSON response from `/api/v2/studies`

The migration YAML maps trial properties directly to Trial node fields. The unique trial identifier is the NCT ID, used for upsert logic. The migration can be executed via:

- The UI import button using Migrate Tools batch infrastructure
- Drush (`drush migrate:import clinical_trials_gov`)

## Configuration Storage

Query parameters, selected fields, and migration configuration are stored in Drupal's configuration system under the `clinical_trials_gov` namespace. Configuration persists across requests, allowing admins to return to the interface and re-run reviews or imports with the same query.

---

## Ideas and Notes

### Autocomplete Enhancement

As the admin types a query on the configure tab, a real-time autocomplete callback from the `ClinicalTrialsGovManager` fetches the top ten matching trials to guide query building.

### Pagination on Review Tab

The review tab needs robust pagination handling for large result sets. The ClinicalTrials.gov API uses cursor-based pagination via `nextPageToken` with a max page size of 1000.

### Data Model Documentation

We need a reliable way to document the complete data structure from https://clinicaltrials.gov/data-api/about-api/study-data-structure. Ideally, we should pull the data model as JSON from the metadata endpoint (https://clinicaltrials.gov/api/v2/studies/metadata) and use it to auto-generate field definitions.

### Import Button — Batch vs. Redirect

Investigate whether to build a custom import button that triggers the migration batch directly from the import page using Migrate Tools' batch infrastructure, or redirect to the Migrate Tools UI execute page. Explore Migrate Tools' public services and classes that can be extended to trigger migrations programmatically while staying on the import page.

### Migrate Tools UI Integration

The module requires Migrate Tools to be installed for admin UI and progress tracking. Explore integration points between the module's import button and Migrate Tools' execution interface.

### Test Script

Generate a basic plain-vanilla `test.php` script that can be used for basic testing, experimentation, and documentation of the ClinicalTrials.gov API responses.

---

## Coding Notes

- All three services (`ClinicalTrialsGovApi`, `ClinicalTrialsGovManager`, `ClinicalTrialsGovFieldBuilder`) must be defined with interfaces.
- Use constructor injection for all services.
- The import form (`ClinicalTrialsGovImportForm`) extends `ConfirmFormBase`.
- Follow Drupal coding standards and naming conventions throughout.
