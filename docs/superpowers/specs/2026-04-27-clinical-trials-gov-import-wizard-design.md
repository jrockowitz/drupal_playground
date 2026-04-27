# ClinicalTrials.gov Import Wizard — Design Spec

**Date:** 2026-04-27
**Status:** Approved

---

## Context

The `clinical_trials_gov` module already provides API integration, a query builder element, and an admin report for browsing studies from ClinicalTrials.gov. This spec covers Phase 2: a four-step import wizard that lets administrators find studies, review results, configure a destination content type, and run a full-sync migration into Drupal.

---

## Architecture

### Module placement

The wizard lives in the top-level `clinical_trials_gov` module alongside existing code. No new submodule is created.

**New Drupal module dependencies:**
- `migrate` (Drupal core)
- `migrate_tools` — provides `MigrateBatchExecutable` for batch migration execution
- `migrate_plus` — provides config-entity-based migration definitions

### Builder refactor (prerequisite)

Study-list rendering logic currently in `ClinicalTrialsGovReportStudiesController` is extracted into `ClinicalTrialsGovBuilder` so the Review step can reuse it without depending on the report submodule.

### Element update (prerequisite)

`ClinicalTrialsGovStudiesQuery` gains an `#excluded_fields` property to suppress API parameters not relevant to a query (e.g. `pageSize`, `pageToken`). The Find form passes this property to hide those fields from the query builder UI.

---

## Routing & Navigation

All routes live under `/admin/config/services/clinical-trials-gov` in the **Web services** admin section.

| Route name | Path | Class |
|---|---|---|
| `clinical_trials_gov.index` | `/admin/config/services/clinical-trials-gov` | `ClinicalTrialsGovController::index` |
| `clinical_trials_gov.find` | `/admin/config/services/clinical-trials-gov/find` | `ClinicalTrialsGovFindForm` |
| `clinical_trials_gov.review` | `/admin/config/services/clinical-trials-gov/review` | `ClinicalTrialsGovReviewController` |
| `clinical_trials_gov.configure` | `/admin/config/services/clinical-trials-gov/configure` | `ClinicalTrialsGovConfigForm` |
| `clinical_trials_gov.import` | `/admin/config/services/clinical-trials-gov/import` | `ClinicalTrialsGovImportForm` |

All routes require a new `administer clinical_trials_gov` permission.

---

## Config Schema

All wizard state is stored in `clinical_trials_gov.settings` with three keys:

```yaml
query: string     # Query string saved from Step 1 (e.g. "query.cond=cancer&filter.overallStatus=RECRUITING")
type: string      # Content type machine name from Step 3 (default: "trial")
fields: sequence  # Selected API field keys from Step 3 field mapping
```

Last-import tracking is handled by the Migrate API — no custom timestamp needed.

---

## Step Breakdown

### Index page — `ClinicalTrialsGovController::index`

Displays all four steps numbered 1–4 with keyword, description, and link for each. The Import step link is only actionable when `query`, `type`, and `fields` are all non-empty in config.

### Step 1 — Find (`ClinicalTrialsGovFindForm extends ConfigFormBase`)

- Renders the `clinical_trials_gov_studies_query` element with `#excluded_fields` set to suppress `pageSize`, `pageToken`, and other non-query parameters
- On submit: saves `query` to config, calls `ClinicalTrialsGovMigrationManager::updateMigration()`, redirects to Review

### Step 2 — Review (`ClinicalTrialsGovReviewController`)

- Reads `query` from config, calls `ClinicalTrialsGovManager::getStudies()`
- Reuses study-list render logic from `ClinicalTrialsGovBuilder`
- Paginated results; each row links to the existing study detail route
- Read-only — writes nothing to config
- "Continue to Configure" link navigates to Step 3

### Step 3 — Configure (`ClinicalTrialsGovConfigForm extends ConfigFormBase`)

**Part 1 — Content type:**
- If the content type does not exist: editable fields for label, machine name (default `trial`), and description
- If the content type already exists: label, machine name, and description are displayed as read-only; an operations dropdown links to the existing content type's admin operations
- Only machine name (`type`) is saved to config; label and description are written to the node type entity itself

**Part 2 — Field mapping:**
- `tableselect` populated from `ClinicalTrialsGovManager::getStudyMetadata()`, grouped by API metadata category
- Required fields (NCT Number, Title, Description) pre-checked and disabled
- If a field already exists on the content type, its checkbox is disabled (cannot be unchecked)
- Each row includes an operations dropdown linking to the field's admin operations when the field exists
- On submit: saves `fields` to config, calls `ClinicalTrialsGovEntityManager` to create content type and fields, calls `ClinicalTrialsGovMigrationManager::updateMigration()`

### Step 4 — Import (`ClinicalTrialsGovImportForm extends FormBase`)

- Displays summary table: saved query, content type, selected field count
- Shows Migrate API stats from previous run if available (created / updated / deleted)
- "Run Import" submit handler calls `batch_set()` using `MigrateBatchExecutable` with `MigrateExecutable::SYNCING` flag for full create/update/delete sync
- After batch completes, redirects back to this page with updated stats

---

## Services

### `ClinicalTrialsGovEntityManager` (`clinical_trials_gov.entity_manager`)

Manages the content type and field entities:

- `createContentType(string $type, string $label, string $description): void` — creates the `node_type` config entity; no-op if it already exists
- `createFields(string $type, array $fields): void` — creates `field_storage_config` and `field_config` entities for each selected API field, skipping any that already exist

Called from `ClinicalTrialsGovConfigForm::submitForm()`.

### `ClinicalTrialsGovMigrationManager` (`clinical_trials_gov.migration_manager`)

Manages the `migrate_plus.migration.clinical_trials_gov` config entity:

- `updateMigration(): void` — reads current `query`, `type`, and `fields` from config and writes (or overwrites) the migration config entity with:
  - `source` plugin: `ClinicalTrialsGovSource` with saved `query` parameters
  - `process` plugins: one `get` mapping per selected field
  - `destination` plugin: `entity:node` with `default_bundle` set to saved `type`
  - `migration_tags`: `['clinical_trials_gov']`

Called from both `ClinicalTrialsGovFindForm::submitForm()` and `ClinicalTrialsGovConfigForm::submitForm()`.

### `ClinicalTrialsGovSource` (Migrate source plugin, id: `clinical_trials_gov`)

Custom source plugin extending `SourcePluginBase`:
- Fetches paginated pages from `ClinicalTrialsGovManager::getStudies()` using saved `query`
- Yields one row per study with flattened dot-notation keys

---

## Testing

**Kernel tests (service business logic):**
- `ClinicalTrialsGovEntityManagerTest` — verifies `createContentType()` creates the node type entity, and `createFields()` creates field storage and field config entities, skipping pre-existing fields
- `ClinicalTrialsGovMigrationManagerTest` — verifies `updateMigration()` writes the correct migration config entity structure (source query, process map, destination bundle)
- `ClinicalTrialsGovSourceTest` — verifies source plugin yields expected rows using `ClinicalTrialsGovManagerStub`

**Functional test:**
- `ClinicalTrialsGovTest` — single test method stepping through the Find and Configure steps via the form: submits a query, verifies Review page renders, submits content type and field selections, verifies Import page shows summary and readiness state

---

## Open Questions Resolved

| Question | Decision |
|---|---|
| Migration trigger | Drupal Batch API via `MigrateBatchExecutable` |
| Module placement | Top-level `clinical_trials_gov` module |
| State storage | Config only (`query`, `type`, `fields`) |
| Field grouping | API metadata categories from `getStudyMetadata()` |
| Re-import behavior | Full sync (create + update + delete) |
| Step completion indicator | Import step requires all three config keys non-empty |
| Migration definition storage | `migrate_plus` config entity (`migrate_plus.migration.clinical_trials_gov`) |
