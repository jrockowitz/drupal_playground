# Entity Labels Module — Build Specification

## Overview

A Drupal 10/11 contributed module (`entity_labels`) that provides a **Reports** page called **"Entity labels"** allowing site builders to browse, audit, and export all entity type, bundle, and field label/description metadata across their entire site — with CSV export and reimport support for bulk editing.

**Module machine name:** `entity_labels`  
**PHP namespace:** `Drupal\entity_labels`  
**Drupal compatibility:** 10.x, 11.x  
**Package:** Reports  
**Type:** `drupal-module`

---

## Requirements

### Functional Requirements

1. Provide a report page at `admin/reports/entity-labels` listing all entity types and their bundles.
2. Allow drilling down from entity type → bundle to view all fields, using **optional route path parameters** (`{entity_type}` and `{bundle}`) rather than query strings.
3. Display configurable metadata at both the bundle level and the field level.
4. Include base fields (e.g., `title`) alongside configurable fields in the field report.
5. Report and CSV export reflect only the **site's active interface language** — labels are resolved via the language manager.
6. Support CSV export scoped to the **currently filtered/displayed data**, rendered as a link at the **bottom** of each report view.
7. Support CSV reimport to bulk-update field labels and descriptions, using a `langcode` column to target the correct language config.
8. The `allowed_values` column is **display-only** in both the report and export. It is **not importable**. This limitation is prominently noted in the admin UI on the import form.
9. All tables are sorted by entity type, then bundle, then field name.
10. Provide a breadcrumb that reflects the current drill-down level.
11. When listing fields by bundle, include an additional **field-without-bundle** summary row for each field that shows the common default label shared across all bundle instances (or flags disagreement if bundles differ).
12. Use Drupal core permissions — `access site reports` for the report and export, `administer site configuration` for the import — rather than declaring custom module permissions.
13. No pagination — the report renders all data in a single table (sites commonly have fewer than 1,000 fields).

### Non-Functional Requirements

- Follow Drupal coding standards (PHPCS Drupal/DrupalPractice sniffs).
- Use strict data typing throughout: every PHP file must declare `declare(strict_types=1)` immediately after the opening `<?php` tag. All method signatures must use typed parameters and typed return values. No untyped arrays without a `@param array<...>` or `@return array<...>` docblock annotation where the shape matters.
- Use dependency injection throughout; no procedural service calls in classes.
- Use Drupal's routing, access checking, and breadcrumb systems correctly.
- All strings must be translatable via `$this->t()`.
- Module must not declare any hard dependency beyond Drupal core.
- Tests must cover the controller, manager, and import form.

---

## Naming Conventions

All names consistently use the `entity_labels` (plural) machine name:

| Context | Value |
|---|---|
| Module machine name | `entity_labels` |
| PHP namespace | `Drupal\entity_labels` |
| Route prefix | `entity_labels.*` |
| Service IDs | `entity_labels.manager`, `entity_labels.importer` |
| YAML file prefix | `entity_labels.*` |
| Class prefix | `EntityLabels*` |

Examples: `EntityLabelsController`, `EntityLabelsManager`, `EntityLabelsManagerInterface`, `EntityLabelsImporter`, `EntityLabelsImporterInterface`, `EntityLabelsImportForm`.

---

## Routing Design

### Mixed Strategy: Path Params for Report, Query Params for Export

The report and export routes use different strategies because a shared path structure is ambiguous: `/admin/reports/entity-labels/{entity_type}/export` cannot be distinguished from `/admin/reports/entity-labels/{entity_type}/{bundle}` where `bundle = "export"`.

**Report route** — uses **optional path parameters**. This produces clean, canonical, bookmarkable URLs for the three drill-down levels.

**Export route** — uses a **fixed path** (`/admin/reports/entity-labels/export`) with `entity_type` and `bundle` as **query parameters**. This allows all three export scopes (all, entity-type-filtered, bundle-specific) to be addressed from the same path without ambiguity.

| Route | Path | Scope parameters |
|---|---|---|
| `entity_labels.report` | `/admin/reports/entity-labels/{entity_type}/{bundle}` | Optional path params, both default to `NULL` |
| `entity_labels.export` | `/admin/reports/entity-labels/export` | `?entity_type=X` and `?bundle=Y` query params |
| `entity_labels.import` | `/admin/reports/entity-labels/import` | None |

**Report URL examples:**
- `admin/reports/entity-labels` — all entity types and bundles
- `admin/reports/entity-labels/node` — all bundles for `node`
- `admin/reports/entity-labels/node/article` — field detail for node/article

**Export URL examples:**
- `admin/reports/entity-labels/export` — all bundles CSV
- `admin/reports/entity-labels/export?entity_type=node` — node bundles CSV
- `admin/reports/entity-labels/export?entity_type=node&bundle=article` — node/article fields CSV

The export link in the report controller is built by passing the current route params as query options to `entity_labels.export`.

---

## Breadcrumb

The breadcrumb dynamically reflects the current drill-down level, giving users clear context and navigation. Implement via a dedicated `EntityLabelsBreadcrumbBuilder` service implementing `BreadcrumbBuilderInterface`, tagged as `breadcrumb_builder`.

| Route params present | Breadcrumb trail |
|---|---|
| Neither | Home › Administration › Reports › **Entity labels** |
| `{entity_type}` only | Home › Administration › Reports › Entity labels › **{Entity Type Label}** |
| `{entity_type}` + `{bundle}` | Home › Administration › Reports › Entity labels › {Entity Type Label} › **{Bundle Label}** |

- "Entity labels" links to `entity_labels.report` (no params).
- `{Entity Type Label}` links to `entity_labels.report` with `{entity_type}` only.
- `{Bundle Label}` is the current (non-linked) active crumb.

Read route params from `RouteMatchInterface::getParameter()`, not from the request query bag.

---

## Features

### Feature 1 — Bundle List View (no route params)

When no route params are present, the report renders all entity types and bundles sorted by entity type label, then bundle label:

| Column | Source |
|---|---|
| Entity Type | `EntityTypeInterface::id()` + label |
| Bundle | `BundleEntityInterface::id()` + label |
| Label | Bundle config entity `label()` |
| Description | Bundle config entity `description` key |
| Help | Bundle config entity `help` key (primarily node types) |

- Each **Bundle** label cell links to `entity_labels.report` with both `{entity_type}` and `{bundle}` params, e.g. `admin/reports/entity-labels/node/article`.
- Each **Entity Type** label cell links to `entity_labels.report` with only `{entity_type}`, e.g. `admin/reports/entity-labels/node`.
- An **"Export CSV"** link at the **bottom** of the table links to `entity_labels.export` with no query params, i.e. `admin/reports/entity-labels/export`.

### Feature 2 — Entity Type Filter View (`{entity_type}` only, no bundle)

When only `{entity_type}` is present, the report renders all bundles for that entity type. The table structure is identical to Feature 1 but filtered to a single entity type. The breadcrumb reflects the entity type level.

### Feature 3 — Field Detail View (`{entity_type}` + `{bundle}`)

When both route params are present, the report renders all fields for that bundle, sorted by field name:

| Column | Source |
|---|---|
| Langcode | Active interface language code |
| Entity Type | `{entity_type}` route param |
| Bundle | `{bundle}` route param |
| Field Name | `FieldDefinitionInterface::getName()` |
| Field Type | `FieldDefinitionInterface::getType()` |
| Label | `FieldDefinitionInterface::getLabel()` |
| Description | `FieldDefinitionInterface::getDescription()` |
| Allowed Values | `FieldDefinitionInterface::getSetting('allowed_values')` — display only |

- **Allowed values** render as a read-only comma-separated `key|label` string. A note below the table states: *"Allowed values are displayed for reference only and cannot be updated via CSV import."*
- **Base fields** such as `title`, `uid`, `status`, `created` are included. Pure base fields without a `BaseFieldOverride` are marked `(base field)` and excluded from import processing. Base fields with a `BaseFieldOverride` are fully importable.
- An **"Export CSV"** link at the **bottom** of the table links to `entity_labels.export` with `?entity_type={entity_type}&bundle={bundle}` query params, e.g. `admin/reports/entity-labels/export?entity_type=node&bundle=article`.

### Feature 4 — Field-Without-Bundle Summary Row

When listing fields for a specific bundle (`{entity_type}` + `{bundle}` route params), the table includes an additional **summary row** for each field that shows the field's **storage-level (cross-bundle) default label and description** — the value defined on `field_storage_config` or the base field definition itself, before any per-bundle overrides.

This row is visually distinguished (e.g., a lighter background or an italic style class) and labelled with `bundle = "(default / all bundles)"` in the Bundle column.

**Purpose:** When a field such as `field_tags` is shared across multiple bundles (`article`, `page`, `blog`), the summary row lets a site builder immediately see whether the per-bundle label differs from the storage-level default. If all bundles agree with the default, the summary row label matches. If any bundle has overridden the label to something different, the summary row flags this with a note such as: *"Differs across bundles: article → 'Tags', page → 'Categories'"*.

**Implementation notes for `EntityLabelsManager::getFieldData()`:**
- After collecting per-bundle field rows, for each `field_name`, also collect the storage-level default:
  - For `FieldConfig` fields: load `FieldStorageConfig::load("{entity_type}.{field_name}")` and use its `label`.
  - For base fields: use the base `FieldDefinition` label (before any `BaseFieldOverride`).
- Cross-reference all bundle instances of the same field across the entity type (via `$fieldManager->getFieldDefinitions($entityTypeId, $otherBundle)` for sibling bundles) to detect label disagreement.
- The summary row carries `bundle = NULL` internally and is rendered with `bundle_label = $this->t('(default / all bundles)')`.
- Summary rows are inserted immediately before their corresponding per-bundle field row (or at the top of the field group if grouped by field name) — the sort order is: field name ascending, summary row first, then per-bundle rows.

### Feature 5 — CSV Export (`entity_labels.export`)

A single `EntityLabelsController::export()` method at the fixed path `/admin/reports/entity-labels/export` reads `entity_type` and `bundle` from the **request query string**:

- **No params** → exports all bundle metadata.
- **`?entity_type=X` only** → exports bundle metadata filtered to that entity type.
- **`?entity_type=X&bundle=Y`** → exports all field metadata for that bundle, including the field-without-bundle summary rows.

Both bundle and field modes return a `\Symfony\Component\HttpFoundation\StreamedResponse`.

**Bundle CSV headers:**
```
langcode,entity_type,entity_type_label,bundle,bundle_label,description,help
```

**Field CSV headers:**
```
langcode,entity_type,bundle,field_name,field_type,label,description,allowed_values
```

- `langcode` is populated with the active interface language code for every row.
- `allowed_values` is included as a display-only column in the field CSV. Rows with no allowed values have an empty cell.
- Summary rows (field-without-bundle) are included in the field CSV with `bundle` = `(default / all bundles)`.
- Filename convention: `entity-labels-bundles.csv` or `entity-labels-{entity_type}-{bundle}.csv`.
- Exported rows always mirror the currently rendered table — same scope, same language, same sort order.

### Feature 6 — CSV Import (`entity_labels.import`)

Import form at `admin/reports/entity-labels/import`:

- Managed file upload accepting `.csv`.
- A prominent notice at the top of the form: *"Note: Allowed values cannot be updated via CSV import. Any `allowed_values` column present in your file will be ignored. To update allowed values, edit the field storage configuration directly."*
- Required CSV headers: `langcode`, `entity_type`, `bundle`, `field_name`, `label`, `description`.
- The `allowed_values` column is **silently ignored** during import even if present.
- Rows where `bundle` = `(default / all bundles)` are also **silently skipped** on import — the summary row is informational only and does not map to a writable config entity.
- On submit, `processImport()` iterates rows and:
  - Loads the `field_config` entity keyed as `{entity_type}.{bundle}.{field_name}`.
  - For base fields with a `BaseFieldOverride`, loads or creates the override entity.
  - Pure base fields without override support are skipped.
  - Uses the row's `langcode` to retrieve or create the config translation via `->getTranslation($langcode)`.
  - Sets `label` and `description` on the translated config entity and saves.
- Result summary via `$this->messenger()`: rows updated, rows skipped, rows errored.

### Feature 7 — Language Handling

- The service resolves labels using `LanguageManagerInterface::getCurrentLanguage()`.
- The report and export always reflect the **site's active interface language** for the current admin session.
- The `langcode` column in the CSV carries the language code of the exported data.
- On import, the `langcode` column drives which language's config translation is updated. If the translation does not yet exist for the given langcode, it is created.
- Multi-language workflow: export in language A → edit → reimport; each row's `langcode` independently targets the correct translation.

### Feature 8 — Table Sorting

All report tables are sorted consistently:

1. **Bundle list view:** sorted by entity type label ASC, then bundle label ASC.
2. **Field detail view:** sorted by field name ASC, with the field-without-bundle summary row first within each field name group.

Sorting is applied in the manager layer (`getBundleData()`, `getFieldData()`) so the CSV export inherits the same order automatically.

### Feature 9 — Access Control

The module reuses Drupal core permissions rather than declaring its own:

| Route | Permission required | Rationale |
|---|---|---|
| `entity_labels.report` | `access site reports` | Standard permission for all Reports pages |
| `entity_labels.export` | `access site reports` | Export is scoped to what the report shows |
| `entity_labels.import` | `administer site configuration` | Import writes to config entities — equivalent risk to any config change |

No `entity_labels.permissions.yml` file is created. The `entity_labels.info.yml` does not list any permissions.

---

## Module File Structure

```
entity_labels/
├── composer.json                           # Composer package definition
├── entity_labels.info.yml
├── entity_labels.routing.yml
├── entity_labels.links.menu.yml
├── entity_labels.links.task.yml
├── entity_labels.services.yml              # registers EntityLabelsManager, EntityLabelsImporter + BreadcrumbBuilder
├── AGENTS.md                               # AI agent instructions for working on this module
├── README.md                               # end-user and developer documentation
├── docs/
│   └── PROMPT.md                           # original AI prompt used to scaffold this module
├── src/
│   ├── Breadcrumb/
│   │   └── EntityLabelsBreadcrumbBuilder.php
│   ├── Controller/
│   │   └── EntityLabelsController.php      # report (all views) + export
│   ├── Form/
│   │   └── EntityLabelsImportForm.php      # CSV upload/import form
│   ├── EntityLabelsManagerInterface.php    # public contract for data gathering + CSV export
│   ├── EntityLabelsManager.php             # data gathering, sorting, CSV row building
│   ├── EntityLabelsImporterInterface.php   # public contract for CSV import processing
│   └── EntityLabelsImporter.php            # CSV import logic
└── tests/
    └── src/
        ├── Unit/
        │   ├── EntityLabelsManagerTest.php
        │   └── EntityLabelsImporterTest.php
        └── Functional/
            ├── EntityLabelsReportTest.php
            └── EntityLabelsImportTest.php
```

Data gathering and CSV row building live in `EntityLabelsManager`. Import processing lives in `EntityLabelsImporter`. The controller's `export()` method calls the manager and wraps output in a `StreamedResponse`. The form's `submitForm()` calls the importer.


---

## Implementation Tasks

### Task 1 — Scaffold the Module

- [ ] Create `composer.json`:
  ```json
  {
      "name": "drupal/entity_labels",
      "description": "Report and CSV export/import for entity type, bundle, and field label metadata.",
      "type": "drupal-module",
      "license": "GPL-2.0-or-later",
      "homepage": "https://drupal.org/project/entity_labels",
      "support": {
          "issues": "https://drupal.org/project/issues/entity_labels",
          "source": "https://git.drupalcode.org/project/entity_labels"
      },
      "require": {
          "drupal/core": "^10 || ^11"
      }
  }
  ```
  Run `composer validate` after creating or modifying this file. The `drupal/core` constraint must stay in sync with `core_version_requirement` in `entity_labels.info.yml`. No `authors` block is needed until the project has a confirmed maintainer account on Drupal.org — add it then.

- [ ] Create `entity_labels.info.yml`:
  ```yaml
  name: 'Entity labels'
  type: module
  description: 'Report and CSV export/import for entity type, bundle, and field label metadata.'
  package: Reports
  core_version_requirement: ^10 || ^11
  ```
- [ ] Do **not** create `entity_labels.permissions.yml` — no custom permissions are declared.
- [ ] Create `entity_labels.links.menu.yml` placing "Entity labels" under `system.admin_reports`.
- [ ] Create `entity_labels.services.yml` with the following complete content:
  ```yaml
  services:

    _defaults:
      autowire: true

    # Manager — constructor args resolved from type hints via _defaults.
    entity_labels.manager:
      class: Drupal\entity_labels\EntityLabelsManager

    # Interface alias — allows type-hinting EntityLabelsManagerInterface
    # directly in container->get() calls and supports future decoration.
    Drupal\entity_labels\EntityLabelsManagerInterface:
      alias: entity_labels.manager

    # Importer — handles CSV import processing.
    entity_labels.importer:
      class: Drupal\entity_labels\EntityLabelsImporter

    # Interface alias for the importer.
    Drupal\entity_labels\EntityLabelsImporterInterface:
      alias: entity_labels.importer

    # Breadcrumb builder — tag must be declared explicitly
    # (Drupal does not auto-discover tagged services from attributes).
    entity_labels.breadcrumb_builder:
      class: Drupal\entity_labels\Breadcrumb\EntityLabelsBreadcrumbBuilder
      tags:
        - { name: breadcrumb_builder, priority: 100 }
  ```
  > Note: `EntityLabelsController` and `EntityLabelsImportForm` extend
  > `ControllerBase` and `FormBase` respectively and are **not** registered
  > as services — they use the `create()` factory pattern for injection.
- [ ] Create `docs/PROMPT.md` — see Task 9.
- [ ] Create `AGENTS.md` — see Task 10.
- [ ] Create `README.md` — see Task 11.

### Task 2 — Routing

Create `entity_labels.routing.yml`:

```yaml
entity_labels.report:
  path: '/admin/reports/entity-labels/{entity_type}/{bundle}'
  defaults:
    _controller: '\Drupal\entity_labels\Controller\EntityLabelsController::report'
    _title: 'Entity labels'
    entity_type: ~
    bundle: ~
  requirements:
    _permission: 'access site reports'

entity_labels.export:
  path: '/admin/reports/entity-labels/export'
  defaults:
    _controller: '\Drupal\entity_labels\Controller\EntityLabelsController::export'
    _title: 'Export entity labels'
  requirements:
    _permission: 'access site reports'

entity_labels.import:
  path: '/admin/reports/entity-labels/import'
  defaults:
    _form: '\Drupal\entity_labels\Form\EntityLabelsImportForm'
    _title: 'Import entity labels'
  requirements:
    _permission: 'administer site configuration'
```

**Report route:** Setting a parameter's default to `~` (YAML null) makes it optional — Drupal's router accepts `/admin/reports/entity-labels`, `/admin/reports/entity-labels/node`, and `/admin/reports/entity-labels/node/article`, injecting `NULL` for any missing params.

**Export route:** Uses a fixed path with no path parameters. Scope is controlled entirely via `entity_type` and `bundle` query string parameters read from the request inside `export()`. This avoids the ambiguity that would arise from a shared path structure (e.g. `/admin/reports/entity-labels/node/export` would be indistinguishable from a bundle named `export`).

> **Route ordering note:** The fixed `/export` and `/import` paths are more specific than `/{entity_type}` so they are matched correctly regardless of declaration order — but declaring them after the parameterised report route is good convention.

### Task 3 — EntityLabelsManagerInterface and EntityLabelsManager

#### 3a — EntityLabelsManagerInterface

Create `src/EntityLabelsManagerInterface.php`. All consuming code (controller, tests) must type-hint against the interface.

```php
<?php

declare(strict_types=1);

namespace Drupal\entity_labels;

/**
 * Defines the interface for the Entity Labels manager service.
 *
 * Responsible for gathering entity type, bundle, and field metadata
 * and building CSV export rows.
 */
interface EntityLabelsManagerInterface {

  /**
   * Returns bundle-level rows for all entity types, or filtered to one.
   *
   * Rows are sorted by entity type label ASC, then bundle label ASC.
   *
   * Each returned item is an associative array with keys:
   *   - langcode (string): Active interface language code.
   *   - entity_type_id (string): Entity type machine name.
   *   - entity_type_label (string): Human-readable entity type label.
   *   - bundle_id (string): Bundle machine name.
   *   - bundle_label (string): Human-readable bundle label.
   *   - description (string): Bundle description, or empty string.
   *   - help (string): Bundle help text (node types), or empty string.
   *
   * @param string|null $entityTypeId
   *   Optional entity type machine name to filter results. When NULL,
   *   all entity types are returned.
   *
   * @return array<int, array<string, string>>
   *   Sorted list of bundle metadata rows.
   */
  public function getBundleData(?string $entityTypeId = NULL): array;

  /**
   * Returns field-level rows for a specific entity type and bundle.
   *
   * Rows are sorted by field_name ASC. For each field_name, a summary row
   * (is_summary_row = TRUE) appears before the per-bundle row. Both
   * FieldConfig and BaseField definitions are included.
   *
   * Each returned item is an associative array with keys:
   *   - langcode (string): Active interface language code.
   *   - entity_type (string): Entity type machine name.
   *   - bundle (string|null): Bundle machine name, or NULL for summary rows.
   *   - field_name (string): Field machine name.
   *   - field_type (string): Field type plugin ID (e.g. 'string', 'entity_reference').
   *   - label (string): Field label for this bundle (or storage default for summary rows).
   *   - description (string): Field description, or disagreement note for summary rows.
   *   - allowed_values (string): Serialised key|label pairs, or empty string.
   *   - is_base_field (bool): TRUE for pure base fields without a BaseFieldOverride.
   *   - is_summary_row (bool): TRUE for the storage-default summary row.
   *
   * @param string $entityTypeId
   *   Entity type machine name.
   * @param string $bundle
   *   Bundle machine name.
   *
   * @return array<int, array<string, string|bool|null>>
   *   Sorted list of field metadata rows including summary rows.
   */
  public function getFieldData(string $entityTypeId, string $bundle): array;

  /**
   * Builds rows ready for bundle-level CSV output.
   *
   * Output mirrors getBundleData() but keyed by column position for
   * direct use with fputcsv(). Header row is included as the first item.
   *
   * @param string|null $entityTypeId
   *   Optional entity type filter. When NULL, all entity types are included.
   *
   * @return array<int, list<string>>
   *   CSV rows, header first, each row a flat list of string values.
   */
  public function buildBundleExportRows(?string $entityTypeId = NULL): array;

  /**
   * Builds rows ready for field-level CSV output.
   *
   * Output mirrors getFieldData() including summary rows. Header row is
   * included as the first item.
   *
   * @param string $entityTypeId
   *   Entity type machine name.
   * @param string $bundle
   *   Bundle machine name.
   *
   * @return array<int, list<string>>
   *   CSV rows, header first, each row a flat list of string values.
   */
  public function buildFieldExportRows(string $entityTypeId, string $bundle): array;

}
```

#### 3b — EntityLabelsManager

Create `src/EntityLabelsManager.php` implementing `EntityLabelsManagerInterface`.

```php
<?php

declare(strict_types=1);

namespace Drupal\entity_labels;

// ... use statements ...

/**
 * Provides entity type, bundle, and field label data for the Entity Labels report.
 */
class EntityLabelsManager implements EntityLabelsManagerInterface {
  // ...
}
```

**Injected services** (constructor-injected, all typed):
```php
public function __construct(
  private readonly EntityTypeManagerInterface $entityTypeManager,
  private readonly EntityTypeBundleInfoInterface $bundleInfoManager,
  private readonly EntityFieldManagerInterface $fieldManager,
  private readonly LanguageManagerInterface $languageManager,
) {}
```

**Key implementation notes:**

`getBundleData()`:
- Iterate `$this->entityTypeManager->getDefinitions()`, filtered optionally by `$entityTypeId`.
- Detect `$entityType->getBundleEntityType()` to find the bundle config entity type.
- Load bundle config entities via `$this->entityTypeManager->getStorage($bundleEntityType)->load($bundleId)`.
- Duck-type for `getDescription()` and `getHelp()` via `method_exists()`.
- `langcode` = `$this->languageManager->getCurrentLanguage()->getId()`.
- Sort output by `entity_type_label` ASC, then `bundle_label` ASC using `usort()`.
- Cast all scalar values to `string` before returning to satisfy the typed return shape.

`getFieldData()`:
- Call `$this->fieldManager->getFieldDefinitions($entityTypeId, $bundle)`.
- For each definition, determine `is_base_field`: `$def instanceof BaseFieldDefinition && !($def instanceof BaseFieldOverride)`.
- `allowed_values`: `$def->getSetting('allowed_values')` — if set, serialize as `key1|Label 1,key2|Label 2`; otherwise `''`.
- After building per-bundle rows, for each `field_name`:
  - Resolve the storage-level default label: for `FieldConfig` fields use `FieldStorageConfig::load("{entity_type}.{field_name}")->getLabel()`; for base fields use the unoverridden `BaseFieldDefinition` label.
  - Collect labels from all other bundles of the same entity type that share this field.
  - If all bundle instances (including current) share the same label as the default → summary row label = default label.
  - If any differ → summary row label = default label, with a `description` annotation like `"Differs: article → 'Tags', page → 'Categories'"`.
  - Set `is_summary_row = TRUE`, `bundle = NULL`.
- Final sort: field name ASC, summary row first within each field name group.

### Task 4 — EntityLabelsImporterInterface and EntityLabelsImporter

#### 4a — EntityLabelsImporterInterface

Create `src/EntityLabelsImporterInterface.php`. `EntityLabelsImportForm` must type-hint against this interface.

```php
<?php

declare(strict_types=1);

namespace Drupal\entity_labels;

/**
 * Defines the interface for the Entity Labels importer service.
 *
 * Responsible for processing CSV import rows and applying label and
 * description updates to field config entities.
 */
interface EntityLabelsImporterInterface {

  /**
   * Processes an array of imported CSV rows.
   *
   * Each row must contain the following keys: langcode, entity_type,
   * bundle, field_name, label, description.
   *
   * Processing rules:
   *   - Rows with bundle = '(default / all bundles)' are silently skipped;
   *     they represent storage-level summary rows and have no writable target.
   *   - The allowed_values column is silently ignored if present.
   *   - Pure base fields without a BaseFieldOverride are silently skipped.
   *   - For each valid row, the config translation identified by langcode
   *     is retrieved or created, then label and description are set and saved.
   *
   * @param array<int, array<string, string>> $rows
   *   Associative rows parsed from a CSV upload (header row excluded).
   *
   * @return array{updated: int, skipped: int, errors: list<string>}
   *   Summary counts: number of rows updated, skipped, and a list of
   *   human-readable error strings for rows that could not be processed.
   */
  public function import(array $rows): array;

}
```

#### 4b — EntityLabelsImporter

Create `src/EntityLabelsImporter.php` implementing `EntityLabelsImporterInterface`.

```php
<?php

declare(strict_types=1);

namespace Drupal\entity_labels;

// ... use statements ...

/**
 * Processes CSV import rows to update field label and description config.
 */
class EntityLabelsImporter implements EntityLabelsImporterInterface {
  // ...
}
```

`EntityLabelsImporter` has no injected Drupal services in its constructor — it uses static `FieldConfig::load()` and `BaseFieldOverride::load()` calls. If future refactoring requires injecting `EntityTypeManagerInterface` to avoid static calls, add it then.

**`import()` implementation notes:**
- Required columns: `langcode`, `entity_type`, `bundle`, `field_name`, `label`, `description`.
- Silently skip rows where `bundle` = `(default / all bundles)`.
- Silently ignore `allowed_values` column if present.
- For each row: attempt `FieldConfig::load("{entity_type}.{bundle}.{field_name}")`, then `BaseFieldOverride::load("{entity_type}.{bundle}.{field_name}")`, skip if neither found.
- Call `->getTranslation($langcode)` (creating translation if absent), set label and description, save.
- Return `['updated' => $updated, 'skipped' => $skipped, 'errors' => $errors]`.

### Task 5 — EntityLabelsBreadcrumbBuilder

Create `src/Breadcrumb/EntityLabelsBreadcrumbBuilder.php` implementing `BreadcrumbBuilderInterface`.

Register in `entity_labels.services.yml` (breadcrumb entry; the manager and importer entries live in Task 1):

```yaml
entity_labels.breadcrumb_builder:
  class: Drupal\entity_labels\Breadcrumb\EntityLabelsBreadcrumbBuilder
  tags:
    - { name: breadcrumb_builder, priority: 100 }
```

Constructor arguments are resolved automatically via the file-level `_defaults: autowire: true` declared in Task 1. The `tags` entry must still be declared explicitly — Drupal's container does not auto-discover tagged services from PHP attributes (unlike Symfony's `#[AutoconfigureTag]`).

**`applies()` method:** Return `TRUE` when the current route name is `entity_labels.report`.

**`build()` method:**
- Always add: Home, Administration, Reports, Entity labels (linked to `entity_labels.report` with no params).
- Read `$entity_type = $route_match->getParameter('entity_type')` and `$bundle = $route_match->getParameter('bundle')`.
- If `$entity_type` is non-null: resolve entity type label and add as a link to `entity_labels.report` with `['entity_type' => $entity_type]`.
- If `$entity_type` + `$bundle` are both non-null: add the entity type crumb as a link (above), then add `{Bundle Label}` as the current (unlinked) crumb.

### Task 6 — EntityLabelsController

Create `src/Controller/EntityLabelsController.php` extending `ControllerBase`.

**Injected services:** `EntityLabelsManagerInterface` and `RequestStack`. Route parameters for `report()` are injected directly by the routing system as method arguments. `RequestStack` is needed only by `export()` to read the `entity_type` and `bundle` query parameters.

Controllers extending `ControllerBase` use the static `create()` factory pattern — the container is not autowired for them. Inject via `create()` as usual:

```php
public static function create(ContainerInterface $container): static {
  return new static(
    $container->get(EntityLabelsManagerInterface::class),
    $container->get('request_stack'),
  );
}
```

All PHP files in the module declare `declare(strict_types=1)` after `<?php`. Controller method signatures:

```php
public function report(?string $entity_type = NULL, ?string $bundle = NULL): array;
public function export(Request $request): StreamedResponse;
```

**`report()` notes:**
- Route params `$entity_type` and `$bundle` are injected directly — no request object needed here.
- **Field detail** (both non-null): call `$this->entityLabelsManager->getFieldData($entity_type, $bundle)`, build field table render array. Split rows into summary rows (rendered with a CSS class like `entity-labels--summary`) and regular rows. Add allowed-values note as `#suffix` below the table. Add export link at bottom.
- **Bundle list / filtered bundle list** (`$entity_type` only, or both null): call `$this->entityLabelsManager->getBundleData($entity_type)`, build bundle table render array. Add export link at bottom.
- Export link: `Url::fromRoute('entity_labels.export', [], ['query' => array_filter(['entity_type' => $entity_type, 'bundle' => $bundle])])`.

**`export()` notes:**
- Read `$entityType = $request->query->getString('entity_type') ?: NULL` and `$bundle = $request->query->getString('bundle') ?: NULL` from the query string.
- Determine scope, call `$this->entityLabelsManager->buildBundleExportRows($entityType)` or `$this->entityLabelsManager->buildFieldExportRows($entityType, $bundle)`.
- Return `new StreamedResponse(...)` writing CSV via `fputcsv(fopen('php://output', 'w'), ...)`.
- Set `Content-Type: text/csv; charset=utf-8` and `Content-Disposition: attachment; filename="..."`.

### Task 7 — EntityLabelsImportForm

Create `src/Form/EntityLabelsImportForm.php`.

`FormBase` subclasses also use the `create()` factory pattern and do not benefit from autowiring. Inject `EntityLabelsImporterInterface` explicitly:

```php
public static function create(ContainerInterface $container): static {
  return new static(
    $container->get(EntityLabelsImporterInterface::class),
  );
}
```

**`buildForm()`:**
- `#markup` notice at the top about allowed values not being importable.
- Managed file upload element (`.csv` only).
- Submit button: "Import CSV".

**`validateForm()`:**
- Confirm uploaded file is readable.
- Parse first row as headers; assert presence of `langcode`, `entity_type`, `bundle`, `field_name`, `label`, `description`.

**`submitForm()`:**
- Parse CSV rows (skip header row).
- Call `$this->entityLabelsImporter->import($rows)`.
- Report via `$this->messenger()`: updated count (status), skipped count (warning, if any), per-row errors (error, if any).

### Task 8 — Local Task Tabs

`entity_labels.links.task.yml`:

```yaml
entity_labels.report_tab:
  route_name: entity_labels.report
  title: 'List'
  base_route: entity_labels.report

entity_labels.import_tab:
  route_name: entity_labels.import
  title: 'Import'
  base_route: entity_labels.report
```

---

## Implementation Steps

**Step 1** — Scaffold (info, routing, services, menu links — no permissions file). Verify `drush en entity_labels` installs cleanly.

**Step 2** — Define `EntityLabelsManagerInterface`. Implement `EntityLabelsManager::getBundleData()` and `getFieldData()`. Write `EntityLabelsManagerTest` unit tests first (TDD), mocking all injected services against the interface. Pay particular attention to the summary row generation and the cross-bundle label comparison logic.

**Step 3** — Define `EntityLabelsImporterInterface`. Implement `EntityLabelsImporter::import()`. Write `EntityLabelsImporterTest` unit tests covering update, skip, error, summary-row-skipped, allowed-values-ignored, and langcode-targeting cases.

**Step 4** — Implement `EntityLabelsBreadcrumbBuilder`. Verify breadcrumb updates correctly at all three drill-down levels.

**Step 5** — Implement `EntityLabelsController::report()` for bundle list and filtered bundle list views. Verify page loads, table sort order, and entity type filter links.

**Step 6** — Implement `EntityLabelsController::report()` for the field detail view. Verify summary rows appear, are visually distinct, and the allowed-values note is present.

**Step 7** — Implement `EntityLabelsManager::buildBundleExportRows()` and `buildFieldExportRows()`. Implement `EntityLabelsController::export()` with `StreamedResponse`. Verify CSV downloads match on-screen data including summary rows and sort order.

**Step 8** — Implement `EntityLabelsImportForm`. Verify upload, validation errors, result messaging, and the allowed-values notice.

**Step 9** — Add local task tabs. Verify tabs appear on report and import pages.

**Step 10** — Write functional tests (see Tests section).

**Step 11** — Create `docs/PROMPT.md`, `AGENTS.md`, and `README.md` (see Tasks 9–11).

**Step 12** — Code review pass: `phpcs --standard=Drupal,DrupalPractice`, string translatability, docblocks on all public methods.

---

## Additional Files

### Task 9 — docs/PROMPT.md

Create `docs/PROMPT.md` to preserve the full conversational prompt history used to design this module with an AI assistant. This provides transparency about the module's design decisions and serves as a reusable starting point for future AI-assisted iteration.

The file should contain:

```markdown
# Entity Labels — AI Prompt History

This file records the prompts used to design the `entity_labels` module
with an AI coding assistant. It is provided for transparency and as a
reusable starting point for contributors who want to continue
AI-assisted development on this module.

---

## Initial prompt

[Paste the full initial prompt here]

## Iteration prompts

[Paste each subsequent refinement prompt here, in order, with a brief
note on what changed]
```

**Why include this file?**
Drupal's community norm is to be transparent about tools and processes used in development. Recording the prompt chain:
- Lets future contributors understand *why* architectural decisions were made (e.g., optional route path parameters over query strings).
- Makes it easy to resume AI-assisted development in a fresh session by providing complete context.
- Serves as a living document — update it whenever the module is significantly revised with AI assistance.

### Task 10 — AGENTS.md

Create `AGENTS.md` at the module root. This file instructs AI coding agents (Claude Code, GitHub Copilot, Cursor, etc.) how to work correctly on this codebase.

```markdown
# AGENTS.md — Entity Labels Module

Instructions for AI coding agents working on this module.

## Module identity

- Machine name: `entity_labels` (plural, always)
- PHP namespace: `Drupal\entity_labels`
- All class names prefixed: `EntityLabels*`
- All route names prefixed: `entity_labels.*`
- All service IDs prefixed: `entity_labels.*`

## Architecture rules

- The **report route** uses optional path parameters `{entity_type}` and `{bundle}`, both defaulting to `~` (null). Clean paths: `/admin/reports/entity-labels/node/article`.
- The **export route** uses a fixed path `/admin/reports/entity-labels/export` with `entity_type` and `bundle` as query string parameters. This avoids the path ambiguity of `/admin/reports/entity-labels/node/export` (is `export` a bundle name or a keyword?).
- `report()` receives `$entity_type` and `$bundle` as direct method arguments from the routing system. It does not read from the request query bag.
- `export()` receives a `Request` object and reads `entity_type` and `bundle` from `$request->query`. It does not have route path parameters.
- No custom permissions file. Access is controlled entirely by Drupal core permissions: `access site reports` (report + export) and `administer site configuration` (import).
- `EntityLabelsManager` handles data gathering, sorting, and CSV row building. `EntityLabelsImporter` handles CSV import processing. Controllers and forms are thin.
- `EntityLabelsImportForm` injects `EntityLabelsImporterInterface`, not `EntityLabelsManagerInterface`.
- Type-hint against `EntityLabelsManagerInterface` and `EntityLabelsImporterInterface`; never the concrete classes.
- Sorting is applied in the manager layer so CSVs inherit the same order.
- The breadcrumb builder reads `{entity_type}` and `{bundle}` from `RouteMatchInterface::getParameter()`, not from the request query bag.

## Namespaces

All classes live directly under `Drupal\entity_labels` — no sub-namespaces except for the structural directories `Breadcrumb\`, `Controller\`, and `Form\`:

| Class | Namespace |
|---|---|
| `EntityLabelsManagerInterface` | `Drupal\entity_labels` |
| `EntityLabelsManager` | `Drupal\entity_labels` |
| `EntityLabelsImporterInterface` | `Drupal\entity_labels` |
| `EntityLabelsImporter` | `Drupal\entity_labels` |
| `EntityLabelsBreadcrumbBuilder` | `Drupal\entity_labels\Breadcrumb` |
| `EntityLabelsController` | `Drupal\entity_labels\Controller` |
| `EntityLabelsImportForm` | `Drupal\entity_labels\Form` |

## Autowiring

- Autowiring is enabled file-wide via `_defaults: autowire: true` at the top of `entity_labels.services.yml`. Do not add `autowire: true` to individual service definitions — the default covers them all.
- Do not add an `arguments:` list to any service — let the container resolve constructor dependencies from type hints.
- Both interface aliases (`EntityLabelsManagerInterface` and `EntityLabelsImporterInterface`) must remain in `entity_labels.services.yml` so `$container->get(...)` calls resolve correctly.
- `EntityLabelsController` and `EntityLabelsImportForm` extend `ControllerBase` / `FormBase` and are **not** registered as services. They must use the `create(ContainerInterface $container)` factory. Do not attempt to autowire them.
- The breadcrumb `tags` entry must always be declared explicitly — Drupal does not auto-discover tagged services from attributes.
- `EntityLabelsController` injects `RequestStack` for use in `export()` only. `report()` does not use the request object — params arrive as method arguments.

## Strict typing

- Every PHP file must declare `declare(strict_types=1)` immediately after
  the opening `<?php` tag — no exceptions.
- All method parameters and return types must be explicitly typed.
- Array shapes that matter (return values of getBundleData, getFieldData,
  processImport) must be annotated with `@return array<...>` or
  `@return array{...}` PHPDoc types.
- Never use untyped `array` without a PHPDoc shape annotation.

## Field data rules

- Include base fields (e.g., `title`, `uid`, `status`) in field reports.
- Mark pure base fields without a `BaseFieldOverride` as `is_base_field = TRUE`.
- `allowed_values` is display-only. Never write allowed values on import.
- Each field in the field detail view gets a summary row
  (`is_summary_row = TRUE`, `bundle = NULL`) showing the storage-level
  default label and flagging cross-bundle disagreements.

## Language rules

- Always resolve labels via `LanguageManagerInterface::getCurrentLanguage()`.
- Every CSV row includes a `langcode` column.
- On import, use the `langcode` column to target the correct config
  translation via `->getTranslation($langcode)`.

## Testing rules

- Unit tests (`EntityLabelsManagerTest`): granular, one method per
  behaviour, mock all injected services against the interface.
- Functional tests: one method per test class (`testReport()`,
  `testImport()`), with `// Check that...` inline comments marking
  individual assertion groups.

## Coding standards

- PHPCS: `Drupal` and `DrupalPractice` sniff sets.
- Dependency injection everywhere — no `\Drupal::service()` calls inside
  classes.
- All user-facing strings wrapped in `$this->t()`.
- Docblocks on all public methods.

## Do not

- Do not modify `composer.json` without running `composer validate` afterwards.
- Do not let the `drupal/core` version constraint in `composer.json` drift out of sync with `core_version_requirement` in `entity_labels.info.yml` — both must express `^10 || ^11`.
- Do not create `entity_labels.permissions.yml`.
- Do not add path parameters to the export route — `entity_type` and `bundle` are query params on `entity_labels.export`.
- Do not add query string params to the report route — `entity_type` and `bundle` are path params on `entity_labels.report`.
- Do not read `entity_type` or `bundle` from `$request->query` inside `report()` — they arrive as method arguments.
- Do not read `entity_type` or `bundle` from route params inside `export()` — read them from `$request->query`.
- Do not import or modify `allowed_values` on field storage config.
- Do not add pagination to the report tables.
- Do not use `static::create()` patterns that bypass the service container.
- Do not type-hint against `EntityLabelsManager` — always use `EntityLabelsManagerInterface`.
- Do not omit `declare(strict_types=1)` from any PHP file.
- Do not add `autowire: true` to individual service definitions — autowiring is set file-wide via `_defaults`.
- Do not register `EntityLabelsController` or `EntityLabelsImportForm` as services — they use the `create()` factory.
```

### Task 11 — README.md

Create `README.md` at the module root following
[Drupal.org module documentation guidelines](https://www.drupal.org/docs/develop/managing-a-drupalorg-theme-module-or-distribution-project/documenting-your-project/module-documentation-guidelines).

```markdown
# Entity Labels

Provides a **Reports** page that lets site builders view, audit, export,
and bulk-update all entity type, bundle, and field label metadata across
a Drupal site.

## Features

- Browse all entity types and bundles in a single sortable table.
- Drill down from entity type → bundle → fields via bookmarkable URLs.
- View field labels, descriptions, field types, and allowed values
  (read-only) for every field on every bundle.
- See a **storage-level summary row** per field showing the default label
  and whether bundle instances agree or differ.
- Export the current view to CSV at any level (all bundles, one entity
  type, or one bundle's fields).
- Reimport a CSV to bulk-update field labels and descriptions in any
  language.
- Breadcrumb navigation reflects your current drill-down level.
- Supports Drupal 10 and 11.

## Requirements

- Drupal core 10.x or 11.x.
- No additional modules required.

## Installation

Install as you would any contributed Drupal module:

```bash
composer require drupal/entity_labels
drush en entity_labels
```

## Permissions

This module does not declare any custom permissions. Access relies on
two standard Drupal core permissions:

| Page | Required permission |
|---|---|
| Entity labels report and CSV export | `access site reports` |
| CSV import | `administer site configuration` |

## Usage

### Viewing the report

Navigate to **Administration → Reports → Entity labels**
(`admin/reports/entity-labels`).

- The default view lists all entity types and all their bundles.
- Click an **entity type** label to filter to that type only.
- Click a **bundle** label to drill into the field detail view for
  that bundle.

### Field detail view

The field detail view (`/admin/reports/entity-labels/{entity_type}/{bundle}`) shows every field attached to the bundle, including base fields such as `title`.

Each field has two rows:

1. **Storage default row** (`(default / all bundles)`) — shows the
   label as defined on the field storage, and notes if any sibling
   bundle uses a different label.
2. **Bundle row** — shows the label as configured specifically for
   this bundle.

> **Note:** Allowed values are displayed for reference only and cannot
> be updated via CSV import.

### Exporting to CSV

Click the **Export CSV** link at the bottom of any report view. The
exported file always matches the current scope and language:

| Current view | Exported file |
|---|---|
| All bundles | `entity-labels-bundles.csv` |
| One entity type | `entity-labels-bundles.csv` (filtered) |
| One bundle's fields | `entity-labels-{entity_type}-{bundle}.csv` |

CSV field-level headers:
```
langcode,entity_type,bundle,field_name,field_type,label,description,allowed_values
```

### Importing from CSV

Navigate to the **Import** tab
(`admin/reports/entity-labels/import`).

Requirements:
- File must be `.csv`.
- Required columns: `langcode`, `entity_type`, `bundle`, `field_name`,
  `label`, `description`.
- The `allowed_values` column is **ignored** on import.
- Rows with `bundle = (default / all bundles)` (summary rows) are
  silently skipped.

The `langcode` column determines which language translation of the
config entity is updated. This enables multilingual bulk editing:
export in language A, edit, reimport — each row independently targets
its language.

After import, a summary reports how many rows were updated, skipped,
or errored.

## Multilingual support

The report and export always display labels in the **site's active
interface language** for the current session. To update labels in a
specific language, ensure your CSV rows have the correct `langcode`
value before importing.

## For developers

### Architecture

```
EntityLabelsController        — thin controller, delegates to manager
EntityLabelsManagerInterface  — public contract for the manager
EntityLabelsManager           — all data gathering, sorting, CSV row building,
                                and import processing
EntityLabelsBreadcrumbBuilder — dynamic breadcrumb per drill-down level
EntityLabelsImportForm        — CSV upload, validation, and result messaging
```

All state is passed via optional route path parameters (`{entity_type}` and `{bundle}`) on the single `entity_labels.report` route. There are no query strings for drill-down state.

All PHP files use `declare(strict_types=1)`. All consuming code
type-hints against `EntityLabelsManagerInterface`.

### Extending

To add support for additional field metadata (e.g., view mode labels),
decorate `EntityLabelsManagerInterface` as a service or implement the
interface in a submodule. See `AGENTS.md` for architecture constraints
to follow when contributing.

## Maintainers

- [Your name](https://www.drupal.org/u/yourname)
```

---

## Tests

### Unit Tests

#### `EntityLabelsManagerTest`

Keep unit tests granular — they are fast and should cover each method and edge case individually. Mock all dependencies against their interfaces. Every test file declares `declare(strict_types=1)`.

```
testGetBundleDataReturnsExpectedStructure()
  Given mocked entity type + bundle info managers,
  assert getBundleData() returns rows with keys:
  langcode, entity_type_id, entity_type_label, bundle_id, bundle_label, description, help.

testGetBundleDataSortsByEntityTypeThenBundle()
  Given entity types in reverse alphabetical order,
  assert output rows are sorted entity_type_label ASC, bundle_label ASC.

testGetBundleDataFiltersToEntityType()
  Call getBundleData('node'); assert only node bundles are returned.

testGetBundleDataPopulatesLangcodeFromLanguageManager()
  Assert langcode value equals getCurrentLanguage()->getId().

testGetFieldDataReturnsConfigurableFields()
  Given a FieldConfig definition, assert it appears with is_base_field = FALSE.

testGetFieldDataIncludesBaseFields()
  Given a BaseFieldDefinition (no override), assert is_base_field = TRUE.

testGetFieldDataSortsByFieldName()
  Given fields in reverse alphabetical order, assert output is sorted field_name ASC.

testGetFieldDataSummaryRowAppearsBeforePerBundleRow()
  Assert for each field_name, the summary row (is_summary_row = TRUE) precedes the bundle row.

testGetFieldDataSummaryRowLabelMatchesStorageDefault()
  When all bundles agree with the storage default label,
  assert summary row label = default label, no disagreement note.

testGetFieldDataSummaryRowFlagsDisagreement()
  When sibling bundle 'page' has a different label for the same field,
  assert summary row description contains a disagreement note referencing both bundles.

testGetFieldDataHandlesAllowedValues()
  Given list_string field with allowed_values = ['foo' => 'Foo', 'bar' => 'Bar'],
  assert allowed_values in output is 'foo|Foo,bar|Bar'.

testGetFieldDataHandlesFieldsWithNoAllowedValues()
  Assert allowed_values is an empty string for a plain text field.

testProcessImportUpdatesFieldConfigLabel()
  Pass a valid row; assert FieldConfig::setLabel() called and result['updated'] === 1.

testProcessImportSkipsUnknownField()
  Pass a row with unknown field_name; assert result['skipped'] === 1.

testProcessImportSkipsSummaryRows()
  Pass a row with bundle = '(default / all bundles)';
  assert it is silently skipped and result['skipped'] incremented.

testProcessImportIgnoresAllowedValuesColumn()
  Pass a row with allowed_values column; assert no error and column has no effect.

testProcessImportUsesLangcodeForTranslation()
  Pass a row with langcode = 'fr'; assert getTranslation('fr') called on config entity.
```

### Functional Tests

Functional tests are slow. Each test class contains a single test method covering the full user journey for that class. Use inline `// Check that...` comments to delineate individual assertion groups within the method.

#### `EntityLabelsReportTest` (extends `BrowserTestBase`)

```php
public function testReport(): void {
  // --- Access control ---
  // Check that anonymous users are denied access to the report.
  // Check that users without 'access site reports' are denied access.
  // Check that users with 'access site reports' can access the report.

  // --- Bundle list view (no route params) ---
  // Check that the page title is 'Entity labels'.
  // Check that the table contains the expected headers:
  //   Entity Type, Bundle, Label, Description, Help.
  // Check that rows are sorted by entity type label, then bundle label.
  // Check that each bundle label cell contains a link with path
  //   /admin/reports/entity-labels/{entity_type}/{bundle}
  //   (no query string parameters).
  // Check that each entity type cell contains a link with path
  //   /admin/reports/entity-labels/{entity_type}.
  // Check that an export CSV link appears at the bottom of the table
  //   pointing to admin/reports/entity-labels/export (no params).
  // Check that the breadcrumb reads: Home > Administration > Reports > Entity labels.

  // --- Entity type filter view (/node) ---
  // Check that GET /admin/reports/entity-labels/node returns only node bundles.
  // Check that the breadcrumb reads: ... > Entity labels > Content.
  // Check that the export link is admin/reports/entity-labels/export?entity_type=node.

  // --- Field detail view (/node/article) ---
  // Check that GET /admin/reports/entity-labels/node/article returns 200.
  // Check that the table contains field-level headers:
  //   Langcode, Entity Type, Bundle, Field Name, Field Type, Label, Description, Allowed Values.
  // Check that rows are sorted by field name ASC.
  // Check that the 'title' base field appears in the table.
  // Check that the 'title' base field row is marked as a base field.
  // Check that the allowed values note text is present below the table.
  // Check that the export link at the bottom is admin/reports/entity-labels/export?entity_type=node&bundle=article.
  // Check that the breadcrumb reads: ... > Entity labels > Content > Article.

  // --- Field detail summary rows ---
  // Create a shared field on two bundles with different labels.
  // Check that a summary row (bundle = '(default / all bundles)') appears
  //   for the shared field.
  // Check that the summary row appears before the per-bundle row in the table.
  // Check that the summary row description contains a disagreement note
  //   referencing both bundle labels and their differing label values.
  // Check that when both bundles agree with the default, no disagreement note appears.

  // --- CSV export ---
  // Check that GET admin/reports/entity-labels/export returns Content-Type: text/csv.
  // Check that the first CSV row contains bundle-level headers including langcode.
  // Check that GET admin/reports/entity-labels/export?entity_type=node&bundle=article returns 200.
  // Check that the Content-Disposition filename contains 'node' and 'article'.
  // Check that the first field CSV row contains field-level headers including langcode.
  // Check that the 'title' field appears in the field CSV rows.
  // Check that the allowed_values column header is present but empty for fields
  //   without allowed values.
  // Check that summary rows (bundle = '(default / all bundles)') appear in the CSV.
  // Check that the CSV row order matches the on-screen table sort order.
}
```

#### `EntityLabelsImportTest` (extends `BrowserTestBase`)

```php
public function testImport(): void {
  // --- Access control ---
  // Check that users with only 'access site reports' get 403 on the import form.
  // Check that users with 'administer site configuration' get 200 on the import form.

  // --- Import form UI ---
  // Check that the allowed values notice text is present on the form.
  // Check that the file upload element is present.
  // Check that the 'Import CSV' submit button is present.

  // --- Validation ---
  // Check that uploading a .txt file shows a validation error.
  // Check that uploading a CSV missing the 'langcode' header shows a validation error.
  // Check that uploading a CSV missing 'field_name' header shows a validation error.

  // --- Successful label update ---
  // Export field CSV for node/article.
  // Edit the 'title' row: change label to 'Headline'.
  // Upload the modified CSV via the import form.
  // Check that the status message reports "1 rows updated".
  // Check that FieldConfig for node.article.title now has label 'Headline'.
  // Check that other fields were not affected.

  // --- Edge cases ---
  // Check that a CSV with an allowed_values column uploads without error.
  // Check that allowed values on the actual field are unchanged after import.
  // Check that a row with bundle = '(default / all bundles)' is silently skipped
  //   and the skipped count in the status message is incremented.
  // Check that a row referencing a non-existent field_name is skipped with a warning.
  // Check that a row referencing a non-existent entity_type is skipped with a warning.
  // Check that the page still returns 200 after encountering skipped/errored rows.

  // --- Multilingual import ---
  // Install the 'fr' language.
  // Export field CSV for node/article (langcode = 'en').
  // Modify one row: change langcode to 'fr', update the label.
  // Upload the modified CSV.
  // Check that the French translation of the field has the new label.
  // Check that the English label is unchanged.
}
```

---

## Later (Out of Scope for v1)

The following enhancements are intentionally deferred. Track as future issues on the module's issue queue.

### Drush Support

- `drush entity-labels:export [--entity-type=X] [--bundle=Y] [--langcode=Z]` — streams a CSV to stdout or a specified file, enabling export in CI/CD pipelines without a browser session.
- `drush entity-labels:import <file>` — processes a CSV import file directly via the CLI, bypassing the UI form, for use in automated deployments and config migration workflows.

### View Mode / Form Mode Label Support

- Extend the report to expose field display labels per **view mode** — specifically the "Label" display setting ("Above", "Inline", "Hidden", or a custom label override) stored in `entity_view_display` config entities under `components.{field_name}.label`.
- Extend the report to expose **form mode** settings per field, including placeholder text and form description overrides stored in `entity_form_display` config entities.
- These require reading `EntityViewDisplay` and `EntityFormDisplay` config entities and represent a meaningfully different data model from the current `field_config`-based report.

---

## Drupal.org Project Page

The following is the project page body copy for `drupal.org/project/entity_labels`, written in accordance with the [Drupal.org module documentation guidelines](https://www.drupal.org/docs/develop/managing-a-drupalorg-theme-module-or-distribution-project/documenting-your-project/module-documentation-guidelines).

---

### Entity Labels

Entity Labels provides a **Reports** page that gives site builders a complete, auditable view of every entity type, bundle, and field label across their Drupal site — with CSV export and reimport for bulk editing.

This is particularly valuable on large sites or multilingual sites where field labels and descriptions have drifted out of sync, where a content model audit is needed before a migration, or where labels need to be updated in bulk across many content types.

#### Features

- **Three-level drill-down report** — Browse all entity types and bundles at a glance, filter to a single entity type, or dive into the full field list for any bundle.
- **Storage-level summary rows** — For each field, a summary row shows the storage-level default label and flags any bundle-level disagreements (e.g., `field_tags` is labelled "Tags" on Article but "Categories" on Page).
- **Base field support** — Core fields such as `title`, `status`, `uid`, and `created` are included alongside configurable fields.
- **CSV export** — Export the current view to a spreadsheet-ready CSV at any level. The export always matches exactly what is on screen — same scope, same language, same sort order.
- **CSV import** — Upload a modified CSV to bulk-update field labels and descriptions. Uses the `langcode` column to target the correct language translation of each config entity.
- **Multilingual** — Report and export always reflect the site's active interface language. Import supports updating any installed language.
- **No custom permissions** — Uses Drupal's built-in `access site reports` (report and export) and `administer site configuration` (import) permissions. No new permissions to manage.
- **Clean architecture** — All business logic is encapsulated behind `EntityLabelsManagerInterface`, making the module straightforward to extend or decorate without modifying core classes.
- **Clean URLs** — Drill-down state is carried via optional route path parameters, producing canonical paths like `/admin/reports/entity-labels/node/article` that are bookmarkable, linkable, and usable in Drush commands.

#### Use cases

- **Content model audit** — See every field label and description on every bundle, exported as a spreadsheet, in one step.
- **Multilingual content model alignment** — Export in each language, update translations, reimport.
- **Label standardisation** — Identify where the same shared field has been labelled differently across bundles and correct them in bulk via CSV.
- **Migration preparation** — Document the full field metadata of a site before or after a Drupal migration.
- **Onboarding** — Give a new developer or content editor a complete picture of the content model without granting them access to Field UI.

#### Usage

Navigate to **Administration → Reports → Entity labels** (`admin/reports/entity-labels`).

Click any bundle label to see its fields. Click **Export CSV** at the bottom of any view to download the currently displayed data. Use the **Import** tab to upload a modified CSV and apply label changes in bulk.

See the project's [README](https://git.drupalcode.org/project/entity_labels/-/blob/1.0.x/README.md) for full documentation including CSV column reference and multilingual import instructions.

#### Requirements

- Drupal 10.x or 11.x
- No additional modules required

#### Related modules

- [Field Data](https://www.drupal.org/project/field_data) — inspired the Controller + Service architecture used here
- [Entities Info](https://www.drupal.org/project/entities_info) — similar concept, PDF-only export, no import

---

## Reference

(https://www.drupal.org/project/field_data) — Controller + Service architecture inspiration.
- [`EntityFieldManagerInterface::getFieldDefinitions()`](https://api.drupal.org/api/drupal/core!lib!Drupal!Core!Entity!EntityFieldManager.php/interface/EntityFieldManagerInterface)
- [`EntityTypeBundleInfoInterface::getBundleInfo()`](https://api.drupal.org/api/drupal/core!lib!Drupal!Core!Entity!EntityTypeBundleInfo.php/interface/EntityTypeBundleInfoInterface)
- [`FieldConfigInterface`](https://api.drupal.org/api/drupal/core!modules!field!src!FieldConfigInterface.php/interface/FieldConfigInterface) — label/description updates on import.
- [`BaseFieldOverride`](https://api.drupal.org/api/drupal/core!lib!Drupal!Core!Field!Entity!BaseFieldOverride.php/class/BaseFieldOverride) — importing label updates to base fields like `title`.
- [`FieldStorageConfig`](https://api.drupal.org/api/drupal/core!modules!field!src!Entity!FieldStorageConfig.php/class/FieldStorageConfig) — resolving storage-level default labels for summary rows.
- [`BreadcrumbBuilderInterface`](https://api.drupal.org/api/drupal/core!lib!Drupal!Core!Breadcrumb!BreadcrumbBuilderInterface.php/interface/BreadcrumbBuilderInterface) — custom breadcrumb per drill-down level.
- [`LanguageManagerInterface`](https://api.drupal.org/api/drupal/core!lib!Drupal!Core!Language!LanguageManagerInterface.php/interface/LanguageManagerInterface) — resolving active interface language.
- [Drupal coding standards](https://www.drupal.org/docs/develop/standards)
