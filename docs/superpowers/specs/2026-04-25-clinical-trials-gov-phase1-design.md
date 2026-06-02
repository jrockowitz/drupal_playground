# ClinicalTrials.gov — Drupal Module Specification

## Executive Summary

A Drupal integration for ClinicalTrials.gov built in two phases:

- **Phase 1 — Report:** A `clinical_trials_gov_report` submodule that lets admins browse ClinicalTrials.gov trial data natively inside Drupal using native render elements. No content is imported.
- **Phase 2 — Import:** Full import/migration workflow that syndicates trial data into Drupal Trial nodes, with a dynamic content type, field builder, and migrate source plugin.

## Module Metadata

- **Module label:** ClinicalTrials.gov
- **Machine name:** `clinical_trials_gov`
- **Namespace:** `Drupal\clinical_trials_gov`
- **Class prefix:** `ClinicalTrialsGov`
- **Admin path:** `/admin/config/web-services/clinical-trials-gov`

---

## Phase 1 — Report

### Module Structure

```
web/modules/custom/clinical_trials_gov/
├── README.md
├── clinical_trials_gov.info.yml
├── clinical_trials_gov.libraries.yml
├── clinical_trials_gov.module
├── clinical_trials_gov.services.yml
├── css/
│   └── clinical_trials_gov_studies_query.css
├── js/
│   └── clinical_trials_gov_studies_query.js
├── test/                                             ← POC PHP explorer (reference implementation)
│   ├── AGENTS.md
│   ├── README.md
│   ├── TODO.md
│   ├── clinical_trials_gov.inc
│   └── clinical_trials_gov.php
├── src/
│   ├── ClinicalTrialsGovApiInterface.php
│   ├── ClinicalTrialsGovApi.php
│   ├── ClinicalTrialsGovManagerInterface.php
│   ├── ClinicalTrialsGovManager.php
│   ├── ClinicalTrialsGovBuilderInterface.php
│   ├── ClinicalTrialsGovBuilder.php
│   └── Element/
│       └── ClinicalTrialsGovStudiesQuery.php
├── modules/
│   └── clinical_trials_gov_report/
│       ├── clinical_trials_gov_report.info.yml
│       ├── clinical_trials_gov_report.libraries.yml
│       ├── clinical_trials_gov_report.links.menu.yml
│       ├── clinical_trials_gov_report.routing.yml
│       ├── css/
│       │   └── clinical_trials_gov_report.css
│       └── src/
│           ├── Form/
│           │   └── ClinicalTrialsGovReportStudiesSearchForm.php
│           └── Controller/
│               ├── ClinicalTrialsGovReportStudiesController.php
│               └── ClinicalTrialsGovReportStudyController.php
└── tests/
    ├── modules/
    │   └── clinical_trials_gov_test/
    │       ├── clinical_trials_gov_test.info.yml
    │       ├── clinical_trials_gov_test.services.yml
    │       ├── fixtures/
    │       │   ├── studies.json
    │       │   ├── study-NCT01205711.json
    │       │   ├── study-NCT05088187.json
    │       │   ├── study-NCT05189171.json
    │       │   ├── metadata.json
    │       │   ├── enums.json
    │       │   ├── search-areas.json
    │       │   └── version.json
    │       └── src/
    │           └── ClinicalTrialsGovManagerStub.php
    └── src/
        ├── Unit/
        │   ├── ClinicalTrialsGovApiTest.php
        │   └── ClinicalTrialsGovManagerTest.php
        └── Kernel/
            ├── ClinicalTrialsGovBuilderTest.php
            └── ClinicalTrialsGovStudiesQueryTest.php
```

### Services (main module only)

The main `clinical_trials_gov` module provides services only. No routes, forms, or controllers.

#### ClinicalTrialsGovApi

- **Service:** `clinical_trials_gov.api`
- **Interface:** `ClinicalTrialsGovApiInterface`

Low-level HTTP client for the ClinicalTrials.gov API v2. Handles endpoint construction, HTTP requests, and JSON response decoding. No knowledge of Drupal entities or field mappings.

#### ClinicalTrialsGovManager

- **Service:** `clinical_trials_gov.manager`
- **Interface:** `ClinicalTrialsGovManagerInterface`

Uses `ClinicalTrialsGovApi` to fetch and organize API data. Public interface:

| Method | Returns | Description |
|---|---|---|
| `getStudies(array $parameters): array` | Raw API response | `['studies' => [...], 'nextPageToken' => '...', 'totalCount' => N]`. No massaging. |
| `getVersion(): array` | Raw version response | Raw response from `/version`, typically including `apiVersion` and `dataTimestamp`. |
| `getStudy(string $nct_id): array` | Flat Index-field array | Flat associative array keyed by dot-notation Index field paths (e.g. `protocolSection.identificationModule.nctId`). Assoc arrays recursed into; lists and scalars stored as-is. |
| `getStudyMetadata(): array` | Flat metadata array | Keyed by Index field path. Each value: `[key, name, piece, title, type, sourceType, description, children]`. Mirrors `flatten_metadata()` from the POC. |
| `getStudyFieldMetadata(string $index_field): ?array` | Metadata for one field | Returns the metadata array for a single Index field path, or NULL. |
| `getEnums(): array` | Raw enums response | Raw response from `/studies/enums`. |
| `getEnum(string $enum_type): array` | Allowed values | List of allowed string values for one enum type (e.g. `'OverallStatus'`). |

#### ClinicalTrialsGovBuilder

- **Service:** `clinical_trials_gov.builder`
- **Interface:** `ClinicalTrialsGovBuilderInterface`

Converts API data into Drupal render arrays. Declared as a service because it requires `t()` for translated `#title` values. Designed to be reused in the Phase 2 review area.

**`buildStudy(array $study, string $nct_id): array`**

**Input:** Flat Index-field keyed array from `getStudy()`, plus the NCT ID (used to build upstream API and public study links).

**Output:** A render array using native Drupal elements only:

```
container
  └─ fieldset "Summary"
       └─ label/value pairs: title, overall status, phases, conditions,
          brief summary, interventions, outcomes, eligibility summary,
          locations, dates, enrollment, sex, age range
  └─ table  [ Field path | Value ]  (flat data table, all fields)
  └─ item   Link to upstream ClinicalTrials.gov API URL
```

The builder calls `getStudyFieldMetadata()` per field to enrich display labels. Multi-value leaf fields (lists) render as `<ul>` lists; associative sub-objects render as label-value lists.

### Form Element: ClinicalTrialsGovStudiesQuery

- **File:** `src/Element/ClinicalTrialsGovStudiesQuery.php`
- **Plugin type:** `FormElement`
- **Type:** `clinical_trials_gov_studies_query`

A reusable composite form element that encapsulates the full ClinicalTrials.gov `/studies` query interface. Used by the report search form and reusable in the Phase 2 review area.

- **`#default_value`:** Raw query string (e.g. `query.cond=cancer&filter.overallStatus=RECRUITING`)
- **Parent class:** `FormElementBase`
- **`#attached`:** `clinical_trials_gov/studies_query` library (CSS + JS). The JS behavior adds a multivalue chip UI for parameters listed in `MULTI_VALUE_KEYS`, allowing comma-, pipe-, or newline-separated values to be entered and displayed as removable chips.
- **`#process`:** Parses the query string using dot-notation-preserving parsing (manual split; no `parse_str()`), then builds one sub-element per parameter using data from the ClinicalTrials.gov API documentation (label, description, examples, allowed values). Fields with enum `allowed` values are populated via `getEnum()`. Related fields are grouped inside `details` elements ("Query parameters", "Filters", "Pagination").
- **`#element_validate`:** Assembles sub-values back into a query string, skipping empty values, and sets it on `$form_state` via `setValueForElement()`. Builds manually with `rawurlencode()` to preserve dot-notation keys.

**Parameters** (replicating https://clinicaltrials.gov/data-api/api `/studies` endpoint):

| Key | Drupal type | Notes |
|---|---|---|
| `query.cond` | `textfield` | Condition or disease |
| `query.term` | `textfield` | Other search terms |
| `query.locn` | `textfield` | Location terms |
| `query.titles` | `textfield` | Title / acronym |
| `query.intr` | `textfield` | Intervention or treatment |
| `query.outc` | `textfield` | Outcome measure |
| `query.spons` | `textfield` | Sponsor or collaborator |
| `query.lead` | `textfield` | Lead sponsor only |
| `query.id` | `textfield` | NCT number or study ID |
| `filter.overallStatus` | `select` / `checkboxes` | Allowed values from `getEnum('OverallStatus')` |
| `filter.geo` | `textfield` | Geo filter |
| `filter.ids` | `textfield` | Pipe-separated NCT IDs |
| `filter.advanced` | `textfield` | Essie expression filter |
| `aggFilters` | `textfield` | Aggregation filters |
| `pageSize` | `number` | 1–1000, default 10 |
| `pageToken` | `textfield` | Pagination cursor |
| `countTotal` | `select` | Yes / No / — |
| `sort` | `textfield` | Field and direction |

### Report Submodule: `clinical_trials_gov_report`

Located at `clinical_trials_gov/modules/clinical_trials_gov_report/`. Depends on `clinical_trials_gov`. Contains only the form and controllers that build the report.

#### Routes

| Route | Path | Controller |
|---|---|---|
| `clinical_trials_gov_report.studies` | `/admin/reports/status/clinical-trials-gov` | `ClinicalTrialsGovReportStudiesController::index` |
| `clinical_trials_gov_report.study` | `/admin/reports/status/clinical-trials-gov/{nctId}` | `ClinicalTrialsGovReportStudyController::view` |

The `{nctId}` parameter is constrained to `NCT\d+`. Both routes require `access administration pages`. A menu link is registered under `system.admin_reports`.

#### ClinicalTrialsGovReportStudiesSearchForm

- **Base:** `FormBase`
- Contains one `ClinicalTrialsGovStudiesQuery` element (`#type => clinical_trials_gov_studies_query`)
- `#default_value` populated from the current request's query string
- On submit: redirects to the `clinical_trials_gov_report.studies` route with the assembled query string as URL parameters
- On reset: redirects back to the `clinical_trials_gov_report.studies` route with no parameters

#### ClinicalTrialsGovReportStudiesController

Renders the search form and results table at `/admin/reports/status/clinical-trials-gov`. Injects `ClinicalTrialsGovManagerInterface` and `DateFormatterInterface`.

1. Embeds `ClinicalTrialsGovReportStudiesSearchForm`
2. Parses the request query string (preserving dot-notation keys)
3. If parameters present, calls `getStudies($parameters)`
4. Renders a `#type => table` with columns: NCT ID (linked to study route), Title, Overall Status, Phases, Conditions
5. Renders a pager link if `nextPageToken` is present
6. Renders a total count item if `totalCount` is present

#### ClinicalTrialsGovReportStudyController

Renders a single study at `/admin/reports/status/clinical-trials-gov/{nctId}`.

1. Calls `getStudy($nctId)`
2. Passes result to `ClinicalTrialsGovBuilder::buildStudy($study, $nctId)`
3. Returns the render array; page title sourced from `protocolSection.identificationModule.briefTitle`

### Test Strategy

Tests live in `web/modules/custom/clinical_trials_gov/tests/` and follow the project's single-test-method-per-class Kernel/Functional convention.

#### Unit Tests (no Drupal bootstrap)

| Test class | What it covers |
|---|---|
| `ClinicalTrialsGovApiTest` | URL construction, HTTP error handling, JSON decoding, pagination token passthrough. Uses a mock HTTP client; no real API calls. |
| `ClinicalTrialsGovManagerTest` | `getStudy()` flattening logic, `getStudyMetadata()` flattening logic, `getEnum()` value extraction. Mocks `ClinicalTrialsGovApi`. Uses fixture JSON files recorded from the live API. |

#### Kernel Tests (Drupal bootstrap, no browser)

| Test class | What it covers |
|---|---|
| `ClinicalTrialsGovBuilderTest` | `buildStudy()` render array structure — verifies STRUCT nodes produce `details` elements, leaf nodes produce `item` elements, and the "Raw data" table is appended. Uses fixture data; mocks `ClinicalTrialsGovManager`. |
| `ClinicalTrialsGovStudiesQueryTest` | Element `#process` produces sub-elements for all 18 parameters. `#element_validate` assembles a correct query string from submitted values, skips empty fields, and preserves dot-notation keys. |

#### Functional Tests (full Drupal, browser-level)

| Test class | What it covers |
|---|---|
| `ClinicalTrialsGovReportTest` | Single test method covering: report page loads at `/admin/reports/status/clinical-trials-gov`, search form renders, form submission redirects with query parameters, results table appears with linked NCT IDs, study detail page renders nested details structure. Swaps `ClinicalTrialsGovManager` with a stub service via `$this->container->set()` to avoid live API calls. |

#### Test Module: `clinical_trials_gov_test`

A test-only module at `clinical_trials_gov/tests/modules/clinical_trials_gov_test/` that provides a stub manager and fixture data for all test types. Installing it replaces the real `clinical_trials_gov.manager` service via `clinical_trials_gov_test.services.yml` — no per-test mocking boilerplate required.

**`ClinicalTrialsGovManagerStub`** implements `ClinicalTrialsGovManagerInterface` and reads from `fixtures/`. Kernel and Functional tests install the test module:

```php
protected static $modules = [
  'clinical_trials_gov',
  'clinical_trials_gov_test',
  'clinical_trials_gov_report',
];
```

Unit tests skip the test module and load fixture JSON directly via `file_get_contents()`.

#### Fixture Files

Recorded API responses stored in `clinical_trials_gov_test/fixtures/`:

| File | Source endpoint | Notes |
|---|---|---|
| `studies.json` | `/studies` | Response containing NCT01205711, NCT05088187, NCT05189171 |
| `study-NCT01205711.json` | `/studies/{nctId}` | RECRUITING, all modules populated, multiple locations, eligibility with Inclusion/Exclusion split |
| `study-NCT05088187.json` | `/studies/{nctId}` | COMPLETED, `hasResults: true`, minimal optional fields |
| `study-NCT05189171.json` | `/studies/{nctId}` | Sparse data — several modules absent, tests builder null/missing field handling |
| `metadata.json` | `/studies/metadata` | Full field tree |
| `enums.json` | `/studies/enums` | All enum types and allowed values |
| `search-areas.json` | `/studies/search-areas` | Full-text search area definitions |
| `version.json` | `/version` | API version and dataset timestamp |

---

## Phase 2 — Import

> Phase 2 has not yet been designed in detail. The items below are carried forward from the original spec as starting points.

### Dependencies

| Module | URL | Description |
|---|---|---|
| `migrate` | https://www.drupal.org/docs/drupal-apis/migrate-api | Core migration framework providing ETL pipeline for data import |
| `migrate_plus` | https://www.drupal.org/project/migrate_plus | Extends Migrate with URL source plugin, HTTP data fetcher, and JSON parser |
| `migrate_tools` | https://www.drupal.org/project/migrate_tools | Provides Drush commands and admin UI for executing and managing migrations |

### ClinicalTrialsGovFieldBuilder (Phase 2 service)

- **Service name:** `clinical_trials_gov.field_builder`
- **Interface:** `ClinicalTrialsGovFieldBuilderInterface`

Creates and manages the Trial content type and its fields. On module installation, creates the Trial content type with a predefined set of fields. During configuration, dynamically creates additional fields based on selected ClinicalTrials.gov schema fields.

### Installation Behavior (Phase 2)

When the module is installed, `hook_install` calls the Field Builder service to create a `trial` content type.

#### Required Fields (cannot be removed)

| Field Label | Drupal Field Name | Drupal Field Type | Source |
|---|---|---|---|
| NCT ID | `field_nct_id` | `string` (max 11 chars) | `protocolSection.identificationModule.nctId` |
| Title | `title` | Node title (core) | `protocolSection.identificationModule.briefTitle` |
| Brief Summary | `field_brief_summary` | `text_long` | `protocolSection.descriptionModule.briefSummary` |

#### Update vs. Create Logic

The migration uses NCT ID as the unique migration map key. Existing nodes are updated on re-import; new nodes are created if no match is found.

### Admin Interface (Phase 2)

Three-step admin interface at `/admin/config/web-services/clinical-trials-gov`:

1. **Configure** — Set query parameters and select additional fields to import
2. **Review** — Preview matching trials (reuses `ClinicalTrialsGovStudiesQuery` element and `ClinicalTrialsGovBuilder::buildStudy()` from Phase 1; `clinical_trials_gov_test` stub covers this in tests)
3. **Import** — Confirm and trigger the migration via Migrate Tools batch infrastructure

### Migration Architecture (Phase 2)

A custom `ClinicalTrialsGovSource` migrate source plugin extends Migrate Plus's URL source plugin to:

- Inject the stored query parameter from module configuration
- Handle pagination via `nextPageToken`
- Parse the JSON response from `/api/v2/studies`

The migration can be run via the Import UI button or via Drush (`drush migrate:import clinical_trials_gov`).

### API to Drupal Field Mapping (Phase 2)

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
| `protocolSection.descriptionModule.detailedDescription` | markdown | `field_detailed_description` | `text_long` | No | |
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
| `protocolSection.contactsLocationsModule.locations` | array | `field_locations` | `string_long` | Yes | |
| `protocolSection.outcomesModule.primaryOutcomes` | array | `field_primary_outcomes` | `text_long` | Yes | |
| `protocolSection.outcomesModule.secondaryOutcomes` | array | `field_secondary_outcomes` | `text_long` | Yes | |
| `protocolSection.referencesModule.references` | array | `field_references` | `link` | Yes | PMIDs / URLs |
| `derivedSection.miscInfoModule.versionHolder` | string | `field_version_holder` | `string` (20) | No | |
| `hasResults` | boolean | `field_has_results` | `boolean` | No | Top-level field |

---

## Reference

- **API base:** https://clinicaltrials.gov/api/v2
- **API explorer:** https://clinicaltrials.gov/data-api/api
- **Data structure documentation:** https://clinicaltrials.gov/data-api/about-api/study-data-structure
- **Metadata endpoint:** https://clinicaltrials.gov/api/v2/studies/metadata
- **Enums endpoint:** https://clinicaltrials.gov/api/v2/studies/enums
