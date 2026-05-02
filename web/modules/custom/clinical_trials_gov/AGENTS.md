# ClinicalTrials.gov Module Guide

## Purpose

`clinical_trials_gov` is a custom Drupal module that integrates with the ClinicalTrials.gov API v2. It provides:

- shared API, manager, render-builder, field-resolution, entity-creation, and migration services
- an admin import wizard at `/admin/config/services/clinical-trials-gov`
- a generated `migrate_plus` migration named `clinical_trials_gov`
- a report submodule, `clinical_trials_gov_report`
- test fixtures and service overrides for Kernel and Functional tests

## Architecture

Core services:

| Service | Responsibility |
|---|---|
| `ClinicalTrialsGovApi` | HTTP client for `/studies`, `/studies/{nctId}`, `/studies/metadata`, `/studies/enums`, `/version` |
| `ClinicalTrialsGovStudyManager` | Fetches and flattens study data and metadata into dot-notation keys; in-memory cache per request |
| `ClinicalTrialsGovPathsManager` | Owns raw/effective `query_paths` and `required_paths`; discovers, normalizes, expands, and orders metadata paths |
| `ClinicalTrialsGovNames` | Converts API `piece` values to Drupal machine names and labels |
| `ClinicalTrialsGovBuilder` | Converts study arrays into render arrays (list tables and study detail) |
| `ClinicalTrialsGovFieldManager` | Curates which metadata keys appear in Configure; resolves metadata into Drupal field definitions |
| `ClinicalTrialsGovEntityManager` | Creates content types, field storage/config, display components, field groups, and shared default field-selection behavior |
| `ClinicalTrialsGovMigrationManager` | Generates and updates the active `migrate_plus.migration.clinical_trials_gov` config |

Drush commands:

- Place module Drush commands in `src/Drush/Commands/`.
- Use Drush 13 PSR-4 discovery with `Drush\\Commands\\AutowireTrait` for dependency injection.
- Do not add a legacy `drush.services.yml` file for this module unless there is a proven discovery or autowiring limitation that cannot be solved with the Drush 13 command pattern.

## Wizard Flow

All wizard pages use `_title: 'ClinicalTrials.gov'` — the local task tabs provide step context.

Routes:

- `clinical_trials_gov.index` — Overview (`ClinicalTrialsGovController::index`)
- `clinical_trials_gov.find` — Step 1: Find (`ClinicalTrialsGovFindForm`)
- `clinical_trials_gov.review` — Step 2: Review / Studies (`ClinicalTrialsGovReviewStudiesController::index`)
- `clinical_trials_gov.review.metadata` — Review metadata (`ClinicalTrialsGovReviewMetadataController::index`)
- `clinical_trials_gov.review.study` — Study detail (`ClinicalTrialsGovReviewStudiesController::study`, `_title_callback: ::title`)
- `clinical_trials_gov.configure` — Step 3: Configure (`ClinicalTrialsGovConfigForm`)
- `clinical_trials_gov.import` — Step 4: Import (`ClinicalTrialsGovImportForm`)
- `clinical_trials_gov.manage` — Step 5: Manage (`ClinicalTrialsGovManageController::index`)
- `clinical_trials_gov.settings` — Optional Settings (`ClinicalTrialsGovSettingsForm`)

Step behaviour:

**1. Find** — stores the raw query string in `clinical_trials_gov.settings:query`. On save, it starts a batch that scans up to 500 studies, fetches each study individually, and writes the discovered field paths to `clinical_trials_gov.settings:query_paths`. Limited to `query.*`, `filter.overallStatus`, `filter.ids`. Includes an Ajax preview section that auto-loads on first render if a saved query exists. The save message is `The studies query has been saved. Please review the selected studies below.`

**2. Review** — splits into `Studies` and `Metadata`. Studies lists studies from the saved query and uses `clinical_trials_gov.review.study` for modal study links. Pagination is UI-only via `nextPageToken`. Metadata shows only the effective saved `clinical_trials_gov.settings:query_paths`, includes the same `Studies query` details element as the Studies page, and includes a `Field paths` footer details element with the saved paths. The study detail title callback returns the study's `briefTitle`, falling back to `'ClinicalTrials.gov'`.

**3. Configure** — creates or reuses a destination content type; shows curated field definitions from `ClinicalTrialsGovFieldManager`. Field-group rows are structural only. Empty group-only rows are hidden. Child rows beneath a promoted `custom` field are hidden. The configured ClinicalTrials.gov bundle is import-managed and cannot be manually created through Drupal node add routes.

**4. Import** — shows migration summary and id-map stats; runs a full sync via `MigrateBatchExecutable`.

**5. Manage** — redirects to the node listing filtered to the configured content type. If the configured type is missing or has not been created yet, it redirects back to Configure with a status message. This is the intended place to manage imported trials because manual node creation for that bundle is blocked.

## Configuration

Primary config: `clinical_trials_gov.settings` — keys: `query`, `query_paths`, `required_paths`, `title_path`, `type`, `field_prefix`, `readonly`, `fields`. `fields` is stored as a mapping of generated Drupal field or group name to metadata path.
Install defaults live in `config/install/clinical_trials_gov.settings.yml`.

`field_prefix` is used directly as the generated Drupal field-name prefix. For example, a saved value of `trial_version_holder` generates Drupal field names beginning with `trial_version_holder_`.

Generated migration: `migrate_plus.migration.clinical_trials_gov`. Deleted when query/query_paths/type/fields are incomplete.

The configured `type` is also used by the node access override that blocks manual node creation for the ClinicalTrials.gov destination bundle, including when the Trash module swaps in its own node access handler.

## Field Resolution

Field resolution lives in `ClinicalTrialsGovFieldManager::resolveFieldDefinition()`.

**Key rules:**
- `briefTitle` maps to node `title` (via `callback` plugin with `Unicode::truncate`) **and** generates a `field_brief_title` string field (max_length 300). Both mappings are written to the migration.
- Required fields (always included): `nctId`, `briefTitle`, `briefSummary`
- `STRUCT` source type → resolved to `custom_field`, `field_group`, or unsupported (in that priority order)
- `MARKUP` source type → `text_long` field; inside `custom_field` → `string_long` column with `formatted: true`
- `isEnum: true` → `list_string` with allowed values from the API
- Array types (`type[]`) → cardinality `-1`; everything else → cardinality `1` (never use `0`)
- `PartialDateStruct` → `custom_field`; `PartialDate` leaf → `datetime` field with `datetime_type: date`. Do not simplify back to JSON.

**Struct resolution:**
1. `custom_field` — simple non-repeatable structs with scalar children, plus the explicit `STRUCTURE_WHITELIST`
2. `field_group` — nested container structs when `field_group` module is available; `group_only: true`, not directly saved as a field
3. unsupported — everything else; hidden from Configure

**Available field list:** `ClinicalTrialsGovFieldManager::getAvailableFieldKeys()` now derives from `ClinicalTrialsGovPathsManager::getQueryPaths()`. The paths manager owns raw/effective `query_paths` and `required_paths`, adds ancestors, and keeps ordering aligned with metadata. There is no legacy fallback list anymore; if `query_paths` is empty, Configure is blocked until Find discovers fields.

**Field names** are generated from the metadata `piece`, normalised to snake_case, and prefixed with `{field_prefix}_` when a custom prefix is configured. Long names are capped at 32 characters, truncated, and suffixed with an 8-character SHA-256 hash. Overrides live in `ClinicalTrialsGovNames::FIELD_NAMES`.

**Custom-field YAML fallback** is used for unsupported nested struct/list properties inside promoted `custom_field` definitions. Those properties are stored as `string_long`, labeled with a `(YAML)` suffix, serialized in the migrate process plugin, rendered inside `<pre>` tags, and syntax-validated on edit forms.

## Readonly Mode

Readonly mode is optional and only applies when:

- `clinical_trials_gov.settings:readonly` is `TRUE`
- the `readonly_field_widget` module is enabled
- the edited node bundle matches `clinical_trials_gov.settings:type`

When active:

- fields listed in `clinical_trials_gov.settings:fields` are switched to `readonly_field_widget`
- the built-in ClinicalTrials.gov link fields `trial_nct_url` and `trial_nct_api` are also switched to `readonly_field_widget`
- unrelated bundle fields remain editable
- the core node title input is hidden when the saved mapping includes `protocolSection.identificationModule.briefTitle`
- the generated `briefTitle` field remains visible and readonly

## Source Plugin

`ClinicalTrialsGovSource` (migrate source plugin `clinical_trials_gov`):
- parses the saved raw query string with `ClinicalTrialsGovStudiesQuery::parseQueryString()`
- uses `/studies` only to collect lightweight `nctId` rows (`fields=NCTId`, `pageSize=1000`, follows `nextPageToken`)
- loads each full study in `prepareRow()` via `/studies/{nctId}`
- flattens rows via a modified `flattenStudy()` that **also preserves parent structured objects on their parent key** — this is intentional: it lets `custom_field` destinations map from a struct key rather than reconstructing values field-by-field in migrate
- repeated associative list items contribute child dot-paths without numeric indexes, so both parent keys like `protocolSection.contactsLocationsModule.locations` and child keys like `protocolSection.contactsLocationsModule.locations.facility` are available
- row IDs use the top-level `nctId` source property
- keeps the full payload out of `initializeIterator()`, which is the main memory-saving behavior for large imports

UI preview/review fetches only one page at a time; the migration source fetches all pages. These are intentionally different.

## Testing

Test support:

- `tests/modules/clinical_trials_gov_test/` — stub service (`ClinicalTrialsGovStudyManagerStub`) and JSON fixtures; this is the real test logic
- `modules/clinical_trials_gov_test/` — lightweight service-substitution wrapper

The stub simulates paginated `/studies` responses (two studies on page 1, one on page 2) and exposes `getStudiesRequests()` to assert pagination behaviour.

Test classes: `ClinicalTrialsGovApiTest`, `ClinicalTrialsGovManagerTest`, `ClinicalTrialsGovNamesTest`, `ClinicalTrialsGovBuilderTest`, `ClinicalTrialsGovFieldManagerTest`, `ClinicalTrialsGovEntityManagerTest`, `ClinicalTrialsGovMigrationManagerTest`, `ClinicalTrialsGovManagerDiscoveryTest`, `ClinicalTrialsGovReviewMetadataControllerTest`, `ClinicalTrialsGovSourceTest`, `ClinicalTrialsGovStudiesQueryTest`, `ClinicalTrialsGovTest` (functional).

## Useful Commands

```bash
# All tests
ddev phpunit web/modules/custom/clinical_trials_gov

# Focused tests
ddev phpunit web/modules/custom/clinical_trials_gov/tests/src/Functional/ClinicalTrialsGovTest.php
ddev phpunit web/modules/custom/clinical_trials_gov/tests/src/Kernel/ClinicalTrialsGovSourceTest.php
ddev phpunit web/modules/custom/clinical_trials_gov/tests/src/Kernel/ClinicalTrialsGovEntityManagerTest.php
ddev phpunit web/modules/custom/clinical_trials_gov/tests/src/Kernel/ClinicalTrialsGovMigrationManagerTest.php

# Linting
ddev code-review web/modules/custom/clinical_trials_gov

# Migration
ddev drush cr
ddev drush migrate:status clinical_trials_gov
```

## When Editing This Module

- Update tests when changing: field-path discovery, metadata table layout, field curation, struct resolution, migration generation, source pagination, or Configure table behaviour.
- Curated field-list changes ripple into the Functional test — check `ClinicalTrialsGovTest::testWizardFlow` assertions against the Configure table.
- If a field seems "missing", check `clinical_trials_gov.settings:query_paths` and the Find discovery batch before touching the resolver.
- If a repeated struct child path seems missing, inspect both `ClinicalTrialsGovStudyManager::flattenStudy()` and `ClinicalTrialsGovSource::flattenStudy()` before changing the batch.
- If Configure is blocked, check whether `clinical_trials_gov.settings:query_paths` is empty; there is no fallback allow-list anymore.
- If a struct seems wrong, inspect: metadata `type`, `sourceType`, `children`, and which outcome (`custom_field`, `field_group`, unsupported) is intended.
- Preserve the distinction between UI preview/review (one page) and migration source (all pages).
- Do not use `0` for unlimited cardinality — use `-1`.
- Do not revert `PartialDateStruct` to JSON storage — the `custom_field` approach is intentional.
- New Drush commands should live in `src/Drush/Commands/` and use Drush autowiring instead of legacy service-file registration.
- Only `ClinicalTrialsGovPathsManager` and `ClinicalTrialsGovSettingsForm` should read `query_paths` or `required_paths` directly from config. Other runtime code should use the paths manager.
