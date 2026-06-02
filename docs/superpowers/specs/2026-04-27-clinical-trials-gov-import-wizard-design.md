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
- `custom_field` — provides structured custom fields for imported non-core study data
- `json_field` — provides JSON-backed storage for partial-date structures that do not map cleanly to Drupal core date fields

### Builder refactor (prerequisite)

Study-list rendering logic currently in `ClinicalTrialsGovReportStudiesController` is extracted into `ClinicalTrialsGovBuilder` so the Review step can reuse it without depending on the report submodule.

### Element update (prerequisite)

`ClinicalTrialsGovStudiesQuery` gains an `#excluded_fields` property to suppress API parameters not relevant to a query (e.g. `pageSize`, `pageToken`). The Find form passes this property to hide those fields from the query builder UI.

### Field naming & storage strategy

Drupal field machine names are generated from ClinicalTrials.gov dot-notation keys.

- Normalize the API key to snake case
- Prefix with `field_`
- Enforce Drupal's 32-character limit
- If the normalized name would exceed 32 characters, truncate it and append a stable hash suffix so the final machine name remains deterministic and unique

Example intent:

- `protocolSection.identificationModule.nctId` becomes a short deterministic field machine name
- Long metadata keys still resolve to the same field machine name every time

Field storage is determined from ClinicalTrials.gov metadata:

- Scalar text and enum values use Drupal text-based fields
- Boolean values use Drupal boolean fields
- Numeric values use Drupal integer fields
- Full normalized dates use Drupal datetime fields
- Partial date structures use `json_field`
- Supported structured values use `custom_field`
- API cardinality comes from the metadata `type` value:
  - `foo` means single-value
  - `foo[]` means multi-value
- Enum rows are identified by `isEnum: true`

Phase 2 only creates fields for importable metadata rows. Container-only metadata rows that exist only to group child properties are not imported as standalone Drupal fields.

### Field type mapping

| ClinicalTrials.gov metadata | Drupal destination |
|---|---|
| `TEXT` / enum / identifier with `maxChars <= 255` | `string` |
| `TEXT` / markup / long text with `maxChars > 255` or unknown long content | `text_long` |
| `BOOLEAN` | `boolean` |
| `NUMERIC` integer | `integer` |
| `DATE` with normalized full date or datetime value | `datetime` |
| `DATE` with `PartialDate`, or `STRUCT` with `PartialDateStruct` | `json` via `json_field` |
| Whitelisted `STRUCT` / `STRUCT[]` values with an explicit sub-column recipe | `custom` field type provided by `custom_field` |

Special mapping rules:

- `protocolSection.identificationModule.briefTitle` maps to the node `title` property, not to a custom field
- Enum values use Drupal list fields, not free-text fields:
  - single-value enum -> `list_string`
  - multi-value enum -> `list_string` with unlimited cardinality
- Non-enum cardinality follows the API metadata `type` suffix:
  - `type: "foo"` -> cardinality `1`
  - `type: "foo[]"` -> unlimited cardinality
- Required fields are:
  - NCT Number — custom field
  - Title — node title
  - Description — custom field
- All other selected metadata rows map to custom Drupal fields based on the field-type rules above

### Structured field whitelist

`custom_field` 4.x is used only when the wizard can generate a complete and deterministic column definition for the structure. The Drupal field type plugin id is `custom`.

Phase 2 support policy:

- `PartialDate` and `PartialDateStruct` always use `json_field`
- `STRUCT` / `STRUCT[]` rows only use `custom_field` when they match an explicit whitelist
- Unsupported container rows and unsupported complex/research-results structures are shown as disabled in Step 3

Whitelisted `custom_field` structures for Phase 2:

| API metadata type | Example keys | Storage strategy |
|---|---|---|
| `Organization` | `protocolSection.identificationModule.organization` | `custom` field with string / enum sub-columns |
| `ExpandedAccessInfo` | `protocolSection.statusModule.expandedAccessInfo` | `custom` field with boolean / NCT / enum sub-columns |
| `EnrollmentInfo` | `protocolSection.designModule.enrollmentInfo` | `custom` field with integer + enum sub-columns |
| `Contact[]` | `protocolSection.contactsLocationsModule.centralContacts`, `protocolSection.contactsLocationsModule.locations.contacts` | multi-value `custom` field with contact sub-columns |
| `Official[]` | `protocolSection.contactsLocationsModule.overallOfficials` | multi-value `custom` field with text / enum sub-columns |
| `Reference[]` | `protocolSection.referencesModule.references` | multi-value `custom` field with citation sub-columns |
| `SeeAlsoLink[]` | `protocolSection.referencesModule.seeAlsoLinks` | multi-value `custom` field with link/text sub-columns |
| `AvailIpd[]` | `protocolSection.referencesModule.availIpds` | multi-value `custom` field with text/link sub-columns |

Explicitly unsupported in Phase 2:

- high-volume results structures under `resultsSection.*`
- browse-derived topic structures under `derivedSection.*`
- uploaded document payloads under `documentSection.*`
- annotation event structures under `annotationSection.*`
- complex protocol arrays such as `ArmGroup[]`, `Intervention[]`, `Outcome[]`, `Location[]`, `SecondaryIdInfo[]`, and `Sponsor[]`

Unsupported structures remain available in the source study data and review pages, but they are not selectable for field creation in the import wizard until a dedicated mapping recipe is added.

### Field mapping rules

Every selectable metadata row needs four decisions:

1. Drupal destination field type
2. Drupal cardinality
3. Migration destination property
4. Migration transform

| API pattern | Selectable | Drupal field | Cardinality | Migration mapping |
|---|---|---|---|---|
| Exact key `protocolSection.identificationModule.briefTitle` | Required | node `title` | 1 | Scalar string mapped directly to destination `title` |
| Exact key `protocolSection.identificationModule.nctId` | Required | `string` | 1 | Scalar string |
| Exact key `protocolSection.descriptionModule.briefSummary` | Required | `text_long` | 1 | Text value mapped to field item value |
| `isEnum: true` and `type` without `[]` | Yes | `list_string` | 1 | Scalar allowed value |
| `isEnum: true` and `type` with `[]` | Yes | `list_string` | Unlimited | Array of allowed values |
| `TEXT`, `nct`, `text`, or similar scalar type without `[]` and short content | Yes | `string` | 1 | Scalar string |
| `TEXT`, `MARKUP`, or long text without `[]` | Yes | `text_long` | 1 | Text value mapped to field item value |
| Text-like type with `[]` | Yes | `string` or `text_long` | Unlimited | Array of strings |
| `BOOLEAN` or metadata `type: "boolean"` without `[]` | Yes | `boolean` | 1 | Normalized boolean |
| `BOOLEAN` or metadata `type: "boolean"` with `[]` | Yes | `boolean` | Unlimited | Array of normalized booleans |
| `NUMERIC` integer without `[]` | Yes | `integer` | 1 | Normalized integer |
| `NUMERIC` integer with `[]` | Yes | `integer` | Unlimited | Array of normalized integers |
| `DATE` with full normalized date/datetime semantics | Yes | `datetime` | 1 or unlimited based on `[]` | Drupal-compatible datetime value |
| `DATE` with `PartialDate`, or `STRUCT` with `PartialDateStruct` | Yes | `json` via `json_field` | 1 or unlimited based on `[]` | JSON object preserving partial-date structure |
| Whitelisted `STRUCT` without `[]` | Limited | `custom` via `custom_field` | 1 | Per-subfield mapping into the custom field shape |
| Whitelisted `STRUCT[]` | Limited | `custom` via `custom_field` | Unlimited | Array of per-item custom-field payloads |
| Container-only metadata rows or unsupported struct patterns | No | none | n/a | Disabled in Step 3 |

Enums are sourced from `ClinicalTrialsGovManager::getEnums()`. When a selectable row is an enum, the created Drupal field stores only allowed values from the API enum definition and the Step 3 field table displays that the destination type is an enum-backed `list_string`.

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
- Title maps to the node title property; NCT Number and Description are stored as custom fields
- If a field already exists on the content type, its checkbox is disabled and remains effectively selected
- Field machine names are generated deterministically from API keys and must stay within Drupal's 32-character limit
- The field list includes the resolved Drupal field type, cardinality, and enum status so administrators can see how each selected metadata row will be stored
- Unsupported `STRUCT` / `STRUCT[]` rows are visible but disabled with an explanation that Phase 2 only supports the documented whitelist
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
- `createFields(string $type, array $fields): void` — creates `field_storage_config` and `field_config` entities for each selected API field, choosing the Drupal field type from ClinicalTrials.gov metadata and skipping any fields that already exist
- `generateFieldName(string $api_key): string` — returns the deterministic Drupal field machine name for a metadata key, including truncation and stable hash suffixing when needed
- `resolveFieldDefinition(string $api_key): array` — returns the Drupal field type, cardinality, allowed values, and transform metadata for a selectable API key
- `resolveStructuredFieldDefinition(string $api_key): array|null` — returns the `custom` field column recipe for whitelisted structure keys, or `NULL` when the structure is unsupported in Phase 2

Called from `ClinicalTrialsGovConfigForm::submitForm()`.

### `ClinicalTrialsGovMigrationManager` (`clinical_trials_gov.migration_manager`)

Manages the `migrate_plus.migration.clinical_trials_gov` config entity:

- `updateMigration(): void` — reads current `query`, `type`, and `fields` from config and writes (or overwrites) the migration config entity with:
  - `source` plugin: `ClinicalTrialsGovSource` with saved `query` parameters
  - `process` plugins: one mapping per selected field, using transforms that match the destination field type and cardinality
  - node `title` mapped from the selected Title metadata key
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
- `ClinicalTrialsGovMigrationManagerTest` — verifies `updateMigration()` writes the correct migration config entity structure (source query, process map, destination bundle), including enum fields, multi-value fields, title mapping, and partial-date handling
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
| Title destination | Map Title to node `title`; all other required fields remain custom fields |
| Existing fields in Step 3 | Disabled and cannot be unselected |
| Long field names | Deterministic truncation with hash suffix under 32 characters |
| Partial dates | Store with `json_field` rather than core datetime |
