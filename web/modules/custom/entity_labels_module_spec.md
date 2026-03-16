# Entity Labels Module — Build Specification

## Overview

A Drupal 10/11 contributed module (`entity_labels`) providing a **Reports** page with two dedicated primary tabs — **Entities** and **Fields** — each with their own drill-down report, CSV export, and CSV import.

---

## Requirements

### Functional

1. **Entities tab** — lists entity types that support bundles, with their bundle label/description/help metadata; drill-down by entity type and bundle.
2. **Fields tab** — lists all fields across all bundles; drill-down by entity type and bundle; includes base fields and storage-level summary rows.
3. Both tabs support drill-down via **optional route path parameters** (`{entity_type}`, `{bundle}`) — not query strings.
4. Both tabs allow clicking the entity type cell to filter to that type, and the bundle cell to drill into that bundle.
5. Language is determined by Drupal's core translation detection system — no explicit language resolution in module code.
6. All report table headers and all CSV column headers use **machine names** (e.g. `entity_type`, `bundle`, `field_name`). Human-readable labels are never used as column headers.
7. CSV export scoped to the current view; a **⇩ Download CSV** button appears at the bottom of each report table.
8. CSV reimport for bulk-updating labels/descriptions, using a `langcode` column to target config translations.
9. Every CSV export includes a `notes` column (last column, display-only, never imported).
10. Fields report: `allowed_values` and `field_type` columns are **display-only** — never importable. Note both prominently on the field import form.
11. Fields report and export include a **cross-bundle summary row** per field (only for fields shared across multiple bundles). See [Cross-bundle summary rows](#cross-bundle-summary-rows).
12. Tables sorted by entity type, bundle, and field name.
13. Single `EntityLabelsBreadcrumbBuilder` handles breadcrumbs for all six routes.
14. Reuse core permissions: `access site reports` (reports + exports), `administer site configuration` (imports). No `entity_labels.permissions.yml`.
15. No pagination.

### Optional Contrib Module Support

No hard dependency on contrib modules. When the following are installed, the Fields report, export, and import are extended automatically:

- `field_group` (any)
  - Groups from the default form mode appear as rows with `field_type = field_group`
  - Group label and description are exportable and importable.
- `custom_field` (4.x only)
  - If the Custom Field module is installed, column labels are exported, and column labels may be imported.
  - Each column within a `custom_field` field gets its own row, identified by a `field_column` CSV column
  - Other major versions are skipped with a warning.

### Non-Functional

- PHPCS `Drupal`/`DrupalPractice` sniffs.
- `declare(strict_types=1)` in every PHP file; fully typed method signatures; `@return array` docblocks; describe the shape in the doc comment body, not in the type expression (PHPCS cannot parse PHPStan generic syntax like `array<...>` or `array{...}` in `@return` tags).
- All function parameters use snake_case (e.g. `$entity_type_id`, not `$entityTypeId`).
- DI throughout — no `\Drupal::service()` or static calls inside classes (exception: importers use static config entity loading in v1).
- No hard dependencies beyond Drupal core.
- Tests covering controllers, services, and import forms.

---

## Steps to Review (⚫ = step  ✅ = pass  ❌ = fail)

### Entities report

- ⚫ Navigate to **Administration → Reports → Entity labels** (`/admin/reports/entity-labels/entity`).
- ⚫ Confirm the **Entities** primary tab is active and the table lists entity types that support bundles (e.g. Content, Taxonomy term) with machine-name column headers: `langcode`, `entity_type`, `bundle`, `label`, `description`, `help`.
- ⚫ Click an **entity_type** cell (e.g. "node") — confirm the view filters to that type only and the breadcrumb updates to show the entity type label.
- ⚫ Click a **bundle** cell (e.g. "article") — confirm the view filters to that bundle and the breadcrumb updates to show the bundle label.
- ⚫ Click **Entity labels** in the breadcrumb — confirm navigation returns to the full unfiltered Entities list.

### Entities export

- ⚫ From the full Entities list, click the **⇩ Download CSV** button at the bottom of the table — confirm a `.csv` file downloads containing all bundles and a header row of `langcode,entity_type,bundle,label,description,help,notes`.
- ⚫ Filter to a single entity type, then click the **⇩ Download CSV** button — confirm the downloaded file contains only rows for that entity type.

### Entities import

- ⚫ Open the downloaded entities CSV; edit the `label` of one row; save.
- ⚫ Navigate to the **Import** secondary tab (`/admin/reports/entity-labels/entity/import`); upload the modified file and submit.
- ⚫ Confirm the status message reports the correct updated/skipped counts.
- ⚫ Navigate back to the Entities report and confirm the bundle label reflects the imported value.

### Fields report

- ⚫ Click the **Fields** primary tab — confirm the table lists fields across all bundles with machine-name column headers: `langcode`, `entity_type`, `bundle`, `field_name`, `field_type`, `label`, `description`, `allowed_values`, `notes`.
- ⚫ Click an **entity_type** cell — confirm the view filters to fields for that type and the breadcrumb updates.
- ⚫ Click a **bundle** cell — confirm the view shows fields for that bundle, each field shared across multiple bundles has a cross-bundle summary row (bundle = "(default / all bundles)") before its per-bundle row with blank `label`/`description` when they match the default, and base fields (e.g. `title`) are present and marked.
- ⚫ Confirm the note appears below the table stating that `allowed_values` and `field_type` cannot be updated via CSV import.

### Fields export

- ⚫ From the bundle-level Fields view, click the **⇩ Download CSV** button — confirm a `.csv` file downloads with header `langcode,entity_type,bundle,field_name,field_type,label,description,allowed_values,notes` and that cross-bundle summary rows appear with `bundle = "(default / all bundles)"` and blank `label`/`description` where the value matches the default.

### Fields import

- ⚫ Open the downloaded fields CSV; change the `label` of a configurable field row (not a summary row); save.
- ⚫ Navigate to the **Import** secondary tab (`/admin/reports/entity-labels/field/import`); upload the modified file and submit.
- ⚫ Confirm the status message reports the correct updated/skipped counts (summary rows should be counted as skipped).
- ⚫ Navigate back to the Fields report for that bundle and confirm the field label reflects the imported value.

---

## Design

### Tab Hierarchy

```
Reports
└── Entity Labels                          (menu link → entity_labels.entity.report)
    ├── Entities  [primary tab]            entity_labels.entity.report
    │   ├── Export  [secondary tab]        entity_labels.entity.export
    │   └── Import  [secondary tab]        entity_labels.entity.import
    └── Fields    [primary tab]            entity_labels.field.report
        ├── Export  [secondary tab]        entity_labels.field.export
        └── Import  [secondary tab]        entity_labels.field.import
```

Both primary tabs share `entity_labels.entity.report` as their `base_route`. Each primary tab's secondary tabs use `parent_id` pointing to their parent primary tab (e.g. `parent_id: entity_labels.entity.report` for entity Export/Import); `base_route` is omitted on secondary tabs since Drupal inherits it from the parent.

### Routing

Report routes use optional path params; export routes use fixed paths with query params (avoids `/entity/export` being mistaken for a bundle named `export`).

| Route | Path | Scope |
|---|---|---|
| `entity_labels.entity.report` | `/admin/reports/entity-labels/entity/{entity_type}/{bundle}` | Optional path params, both `NULL` |
| `entity_labels.entity.export` | `/admin/reports/entity-labels/entity/export` | `?entity_type=X&bundle=Y` |
| `entity_labels.entity.import` | `/admin/reports/entity-labels/entity/import` | None |
| `entity_labels.field.report` | `/admin/reports/entity-labels/field/{entity_type}/{bundle}` | Optional path params, both `NULL` |
| `entity_labels.field.export` | `/admin/reports/entity-labels/field/export` | `?entity_type=X&bundle=Y` |
| `entity_labels.field.import` | `/admin/reports/entity-labels/field/import` | None |

### Breadcrumb

Single `EntityLabelsBreadcrumbBuilder` applies to all six routes. Route name prefix determines which report path to use for crumb links.

| Route params | Trail |
|---|---|
| Neither | Home › Administration › Reports › Entity labels › **Entities** or **Fields** |
| `{entity_type}` only | … › Entity labels › Entities/Fields › **{Entity Type Label}** |
| Both | … › Entity labels › Entities/Fields › {Entity Type Label} › **{Bundle Label}** |

- **`applies()`:** Return `TRUE` when `str_starts_with($route_match->getRouteName(), 'entity_labels.')`.
- Reads params via `RouteMatchInterface::getParameter()`, not the request query bag.
- "Entity labels" crumb always links to `entity_labels.entity.report` (the Reports menu entry, no params).
- "Entities" or "Fields" crumb links to the current tab's base report route (`entity_labels.entity.report` or `entity_labels.field.report`, no params). This crumb is always present and is the active (unlinked) crumb when no `{entity_type}` is set, or a link when drilling deeper.
- `{Entity Type Label}` links to the base report route with `{entity_type}` only.
- `{Bundle Label}` is the unlinked active crumb when both params are set.

### Cross-bundle summary rows

A **cross-bundle summary row** represents the storage-level default for a field — the label and description defined on `field_storage_config` (or the base field definition), before any per-bundle overrides. It is emitted **only when the field exists on more than one bundle** of the same entity type; single-bundle fields get no summary row.

The row has `bundle = NULL` internally, rendered as `"(default / all bundles)"` in the table and CSV.

**`notes` column value:** always `"Default label and description for all instances of this field"`. This makes it unambiguous in the CSV why the row exists and that it cannot be imported.

**`label` and `description` cell values:**
- If every bundle instance matches the storage default → render both cells **blank**. The default is unambiguous; repeating it adds noise.
- If any bundle instance differs from the storage default → populate the cells with the storage default value and append additional detail to `notes`, e.g. `"Default label and description for all instances of this field. Differs: article → 'Tags', page → 'Categories'"`.

**Purpose:** lets a site builder see, for any shared field, whether per-bundle overrides are in use and where they diverge — without having to compare rows manually.

**Import behaviour:** the field importer parses the entire CSV first, then re-sorts all rows by `entity_type`, `bundle`, and `field_name` before processing. Summary rows (`bundle = "(default / all bundles)"`) are applied as the default `label`/`description` for every per-bundle row of the same `entity_type` + `field_name` that does not supply its own values. Per-bundle rows with explicit `label`/`description` values always take precedence over the summary-row default.

### Service Architecture

Four services across two interface pairs. A base `EntityLabelsExporterInterface` and `EntityLabelsImporterInterface` define the shared method contracts; the dedicated `Entity`/`Field` sub-interfaces extend them to enable correct autowiring.

```
EntityLabelsExporterInterface
├── EntityLabelsEntityExporterInterface  ← EntityLabelsEntityExporter
└── EntityLabelsFieldExporterInterface   ← EntityLabelsFieldExporter

EntityLabelsImporterInterface
├── EntityLabelsEntityImporterInterface  ← EntityLabelsEntityImporter
└── EntityLabelsFieldImporterInterface   ← EntityLabelsFieldImporter
```

`EntityLabelsController` and `EntityLabelsImportForm` both inject the container directly and resolve the correct sub-interface at runtime using the `$type` route default.

See [`src/EntityLabelsExporterInterface.php`](#srcentitylabelsexporterinterfacephp) and [`src/EntityLabelsImporterInterface.php`](#srcentitylabelsimporterinterfacephp) for the authoritative method signatures and docblocks.

`import()` accepts a raw CSV string — this keeps the service decoupled from Symfony's file upload mechanism and makes Drush support straightforward. `EntityLabelsImportForm` catches both exception types and displays them via `$this->messenger()` as error messages.

### Controller Architecture

A single `EntityLabelsController` (extends `ControllerBase`) handles all six report and export routes. The route `defaults` include a `type` value (`'entity'` or `'field'`), read in `create()` and stored as `$this->type`. All `$type`-dependent behaviour — service resolution, route names, and labels — is provided by `EntityLabelsTypeTrait`. See [`src/EntityLabelsTypeTrait.php`](#srcentitylabelstypetraitphp).

### Form Architecture

A single `EntityLabelsImportForm` (extends `FormBase`) handles both import routes the same way: `$type` comes from the route `defaults`, and all `$type`-dependent behaviour is delegated to `EntityLabelsTypeTrait`. `buildForm()` conditionally prepends the field-only `allowed_values`/`field_type` notice when `$this->type === 'field'`.

### Shared Trait

`EntityLabelsTypeTrait` encapsulates every `$type`-driven helper used by both the controller and the form — route names, UI labels, and service resolution. The using class must declare `protected string $type` and inject `ContainerInterface $container`.

```php
trait EntityLabelsTypeTrait {
  // Route helpers
  protected function getReportRoute(): string;   // 'entity_labels.{type}.report'
  protected function getExportRoute(): string;   // 'entity_labels.{type}.export'
  protected function getImportRoute(): string;   // 'entity_labels.{type}.import'

  // Label helpers
  protected function getSingularLabel(): TranslatableMarkup;
  protected function getPluralLabel(): TranslatableMarkup;

  // Service resolution
  protected function getExporter(): EntityLabelsExporterInterface;
  protected function getImporter(): EntityLabelsImporterInterface;
}
```

Both `EntityLabelsController` and `EntityLabelsImportForm` `use EntityLabelsTypeTrait`.

---

## Module File Structure

```
entity_labels/
├── .gitlab-ci.yml
├── composer.json
├── logo.png
├── README.md
├── AGENTS.md
├── entity_labels.info.yml
├── entity_labels.routing.yml
├── entity_labels.links.menu.yml
├── entity_labels.links.task.yml
├── entity_labels.services.yml
├── src/
│   ├── Controller/
│   │   └── EntityLabelsController.php           # report() + export(); resolves service by $type
│   ├── Exception/
│   │   ├── EntityLabelsCsvParseException.php
│   │   └── EntityLabelsImportException.php
│   ├── Form/
│   │   └── EntityLabelsImportForm.php           # resolves import service by $type; field-only notice via match
│   ├── Breadcrumb/
│   │   └── EntityLabelsBreadcrumbBuilder.php
│   ├── EntityLabelsTypeTrait.php                # getSingularLabel(), getPluralLabel(), get*Route()
│   ├── EntityLabelsExporterInterface.php        # base: getData() + export()
│   ├── EntityLabelsImporterInterface.php        # base: import()
│   ├── EntityLabelsEntityExporterInterface.php  # extends EntityLabelsExporterInterface
│   ├── EntityLabelsEntityExporter.php
│   ├── EntityLabelsEntityImporterInterface.php  # extends EntityLabelsImporterInterface
│   ├── EntityLabelsEntityImporter.php
│   ├── EntityLabelsFieldExporterInterface.php   # extends EntityLabelsExporterInterface
│   ├── EntityLabelsFieldExporter.php
│   ├── EntityLabelsFieldImporterInterface.php   # extends EntityLabelsImporterInterface
│   └── EntityLabelsFieldImporter.php
└── tests/src/
    ├── Unit/
    │   ├── EntityLabelsEntityExportTest.php
    │   ├── EntityLabelsEntityImportTest.php
    │   ├── EntityLabelsFieldExportTest.php
    │   └── EntityLabelsFieldImportTest.php
    └── Functional/
        ├── EntityLabelsEntityReportTest.php
        ├── EntityLabelsEntityImportTest.php
        ├── EntityLabelsFieldReportTest.php
        └── EntityLabelsFieldImportTest.php
```

---

## Implementation

Files listed creation-order: scaffolding → infrastructure → src (thin to thick) → tests.

---

### README.md

```markdown
# Entity Labels

A Drupal 10/11 module that provides a **Reports** page listing entity type and
bundle label metadata (label, description, help) and field label metadata (label,
description, allowed values) — with CSV export and CSV import for bulk updates.

## Features

- **Entities tab** — browse all entity types that support bundles; drill down by
  entity type and bundle; view label, description, and help text.
- **Fields tab** — browse all fields across all bundles; drill down by entity type
  and bundle; view label, description, field type, and allowed values. Cross-bundle
  summary rows highlight shared fields and any per-bundle overrides.
- **CSV export** — scoped to the current view (all, entity type, or bundle). Every
  export includes a `notes` column and a `langcode` column to target translations.
- **CSV import** — upload a modified CSV to bulk-update labels and descriptions.
  `allowed_values` and `field_type` are display-only and never imported.
- Multilingual: uses Drupal's core translation detection; import targets the
  language specified in the `langcode` column.

## Requirements

- Drupal core 10.x or 11.x
- No additional contributed modules required

## Optional Module Support

When the following contrib modules are installed, the Fields tab, export, and import are automatically extended:

| Module | Version | Additional behaviour |
|---|---|---|
| `field_group` | any | Groups from the default form mode appear as rows with `field_type = field_group`; group label and description are exportable and importable. |
| `custom_field` | 4.x only | Each column within a `custom_field` field gets its own row with a `field_column` identifier; column label and description are exportable and importable. |

## Installation

Install as any Drupal module:

```bash
composer require drupal/entity_labels
drush en entity_labels
```

## Permissions

| Permission | Purpose |
|---|---|
| `access site reports` | View reports and download CSV exports |
| `administer site configuration` | Upload CSV imports |

## Usage

1. Navigate to **Administration → Reports → Entity labels**.
2. Use the **Entities** and **Fields** primary tabs to browse metadata.
3. Click an entity type cell to filter; click a bundle cell to drill in.
4. Click **⇩ Download CSV** to export the current view.
5. Edit the CSV, then use the **Import** secondary tab to upload and apply changes.

## Development

See [AGENTS.md](AGENTS.md) for AI-assisted development guidelines.
```

---

### AGENTS.md

Do not hand-author this file. After the module directory is created and the initial code is in place, generate it by running:

```bash
claude init
```

`claude init` inspects the codebase and produces an `AGENTS.md` tailored to the actual file structure, coding patterns, and architecture it finds.

---

### .gitlab-ci.yml

GitLab CI is enabled for every project on Drupal.org. The `.gitlab-ci.yml` file in the root of the repository configures automated testing via the Drupal Association's shared template, which handles PHP/DB environment matrix, PHPCS, PHPStan, and PHPUnit runs. The template is maintained upstream so it stays current with supported Drupal/PHP/DB versions without requiring local changes.

Create the file via the GitLab repository UI (recommended) or manually:

1. On `git.drupalcode.org`, navigate to the project, add a new file named `.gitlab-ci.yml`, and choose the **Drupal Association template** from the template picker.
2. Alternatively, copy the template directly from `https://git.drupalcode.org/project/gitlab_templates/-/blob/main/gitlab-ci.yml` and commit it.

Minimal starting content (the `include` pulls all job definitions from the upstream template):

```yaml
include:
  - project: $_GITLAB_TEMPLATES_REPO
    ref: $_GITLAB_TEMPLATES_REF
    file:
      - '/includes/include.drupalci.main.yml'
      - '/includes/include.drupalci.variables.yml'
      - '/includes/include.drupalci.workflows.yml'
```

Pipelines run automatically on merge request events, commits to the default branch, tags, and manual triggers. No additional configuration is needed for a standard contrib module. To opt in to additional test environments (e.g. previous/next major Drupal versions or max PHP):

```yaml
variables:
  OPT_IN_TEST_PREVIOUS_MAJOR: 1
  OPT_IN_TEST_NEXT_MINOR: 1
  OPT_IN_TEST_MAX_PHP: 1
```

See [GitLab CI — Using GitLab to contribute to Drupal](https://www.drupal.org/docs/develop/git/using-gitlab-to-contribute-to-drupal/gitlab-ci) for full documentation.

---

### logo.png

A 512×512 PNG logo is required for the module to display correctly in [Project Browser](https://www.drupal.org/docs/extending-drupal/contributed-modules/contributed-module-documentation/project-browser/module-maintainers-how-to-update-projects-to-be-compatible-with-project-browser#s-logo) and as the project avatar on `drupalcode.org` and `drupal.org`.

**Specifications:**

- **Dimensions:** 512×512 pixels, square
- **Format:** PNG, no animations
- **File size:** 10 KB or less (use a lossy tool such as `pngquant` at ~80% quality)
- **Filename:** `logo.png` (placed in the repository root on the default branch)
- Do **not** round the corners in the PNG itself
- Do **not** include the module name as text in the image (unless there is a compelling branding reason)
- If designed in a vector format, also commit `logo_svg.txt` (SVG content) to the repo root to facilitate future edits

Logos are cached and may take up to an hour to appear after committing.

**AI image-generation prompt:**

> A minimal, flat icon representing structured data labels and entity relationships in a Drupal site. The icon should suggest taxonomy or classification — for example, a grid of labelled cards or a layered hierarchy of tag/label shapes with subtle connector lines. Use a clean, modern style with a limited palette of two or three colours (blue-grey tones work well). No text. No gradients. Transparent or white background. Suitable for use as a square module icon at small sizes (512×512 px output). The module — Entity Labels — helps site builders view, export, and bulk-update the human-readable labels and descriptions attached to Drupal entity types, bundles, and fields via CSV.

---

### composer.json

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
    },
    "require-dev": {
        "drupal/field_group": "^4",
        "drupal/custom_field": "^4"
    }
}
```

Keep `drupal/core` constraint in sync with `core_version_requirement` in `entity_labels.info.yml`.

---

### entity_labels.info.yml

```yaml
name: 'Entity labels'
type: module
description: 'Report and CSV export/import for entity type, bundle, and field label metadata.'
package: Reports
core_version_requirement: ^10 || ^11
```

---

### entity_labels.routing.yml

The `type` default is passed to both the controller and the form, letting a single class serve all six routes. Report routes use a `_title_callback` on the controller so the page title reflects the current drill-down level (e.g. "Content" when filtered to node, "Article" when filtered to node/article).

```yaml
entity_labels.entity.report:
  path: '/admin/reports/entity-labels/entity/{entity_type}/{bundle}'
  defaults:
    _controller: '\Drupal\entity_labels\Controller\EntityLabelsController::report'
    _title_callback: '\Drupal\entity_labels\Controller\EntityLabelsController::title'
    type: 'entity'
    entity_type: ~
    bundle: ~
  requirements:
    _permission: 'access site reports'

entity_labels.entity.export:
  path: '/admin/reports/entity-labels/entity/export'
  defaults:
    _controller: '\Drupal\entity_labels\Controller\EntityLabelsController::export'
    _title: 'Export entity labels'
    type: 'entity'
  requirements:
    _permission: 'access site reports'

entity_labels.entity.import:
  path: '/admin/reports/entity-labels/entity/import'
  defaults:
    _form: '\Drupal\entity_labels\Form\EntityLabelsImportForm'
    _title: 'Import entity labels'
    type: 'entity'
  requirements:
    _permission: 'administer site configuration'

entity_labels.field.report:
  path: '/admin/reports/entity-labels/field/{entity_type}/{bundle}'
  defaults:
    _controller: '\Drupal\entity_labels\Controller\EntityLabelsController::report'
    _title_callback: '\Drupal\entity_labels\Controller\EntityLabelsController::title'
    type: 'field'
    entity_type: ~
    bundle: ~
  requirements:
    _permission: 'access site reports'

entity_labels.field.export:
  path: '/admin/reports/entity-labels/field/export'
  defaults:
    _controller: '\Drupal\entity_labels\Controller\EntityLabelsController::export'
    _title: 'Export field labels'
    type: 'field'
  requirements:
    _permission: 'access site reports'

entity_labels.field.import:
  path: '/admin/reports/entity-labels/field/import'
  defaults:
    _form: '\Drupal\entity_labels\Form\EntityLabelsImportForm'
    _title: 'Import field labels'
    type: 'field'
  requirements:
    _permission: 'administer site configuration'
```

---

### entity_labels.links.menu.yml

```yaml
entity_labels.entity.report:
  title: 'Entity labels'
  route_name: entity_labels.entity.report
  parent: system.admin_reports
  description: 'Browse, export, and import entity type, bundle, and field label metadata.'
```

---

### entity_labels.links.task.yml

```yaml
# Primary tabs (grouped under the same base_route).
entity_labels.entity.report:
  route_name: entity_labels.entity.report
  title: 'Entities'
  base_route: entity_labels.entity.report

entity_labels.field.report:
  route_name: entity_labels.field.report
  title: 'Fields'
  base_route: entity_labels.entity.report

# Secondary tabs under Entities (parent_id replaces base_route).
entity_labels.entity.export:
  route_name: entity_labels.entity.export
  title: 'Export'
  parent_id: entity_labels.entity.report

entity_labels.entity.import:
  route_name: entity_labels.entity.import
  title: 'Import'
  parent_id: entity_labels.entity.report

# Secondary tabs under Fields (parent_id replaces base_route).
entity_labels.field.export:
  route_name: entity_labels.field.export
  title: 'Export'
  parent_id: entity_labels.field.report

entity_labels.field.import:
  route_name: entity_labels.field.import
  title: 'Import'
  parent_id: entity_labels.field.report
```

---

### entity_labels.services.yml

```yaml
services:

  _defaults:
    autowire: true

  entity_labels.entity.exporter:
    class: Drupal\entity_labels\EntityLabelsEntityExporter

  Drupal\entity_labels\EntityLabelsEntityExporterInterface:
    alias: entity_labels.entity.exporter

  entity_labels.entity.importer:
    class: Drupal\entity_labels\EntityLabelsEntityImporter

  Drupal\entity_labels\EntityLabelsEntityImporterInterface:
    alias: entity_labels.entity.importer

  entity_labels.field.exporter:
    class: Drupal\entity_labels\EntityLabelsFieldExporter

  Drupal\entity_labels\EntityLabelsFieldExporterInterface:
    alias: entity_labels.field.exporter

  entity_labels.field.importer:
    class: Drupal\entity_labels\EntityLabelsFieldImporter

  Drupal\entity_labels\EntityLabelsFieldImporterInterface:
    alias: entity_labels.field.importer

  entity_labels.breadcrumb_builder:
    class: Drupal\entity_labels\Breadcrumb\EntityLabelsBreadcrumbBuilder
    tags:
      - { name: breadcrumb_builder, priority: 100 }
```

Controllers and forms are **not** registered as services — they use `create()` factory injection.

---

### src/Exception/EntityLabelsCsvParseException.php

Thrown when the CSV is malformed or required headers are missing. Extends `\RuntimeException`.

### src/Exception/EntityLabelsImportException.php

Thrown when a row cannot be processed (e.g. unknown entity type or field). Extends `\RuntimeException`.

---

### src/EntityLabelsExporterInterface.php

Base interface. `EntityLabelsEntityExporterInterface` and `EntityLabelsFieldExporterInterface` both extend this. `EntityLabelsController` type-hints against it.

```php
<?php

declare(strict_types=1);

namespace Drupal\entity_labels;

interface EntityLabelsExporterInterface {

  /**
   * Returns report rows for the given scope.
   *
   * Row shape and sort order vary by implementation.
   * Every row includes a 'notes' key (string, may be empty).
   *
   * @return array
   *   Rows of report data; each row is a map of column name to value.
   */
  public function getData(?string $entity_type_id = NULL, ?string $bundle = NULL): array;

  /**
   * Builds CSV rows (header first, notes column last) ready for fputcsv().
   *
   * @return array
   *   CSV rows as lists of strings; first row is the header.
   */
  public function export(?string $entity_type_id = NULL, ?string $bundle = NULL): array;

}
```

---

### src/EntityLabelsImporterInterface.php

Base interface. `EntityLabelsEntityImporterInterface` and `EntityLabelsFieldImporterInterface` both extend this. `EntityLabelsImportForm` type-hints against it.

```php
<?php

declare(strict_types=1);

namespace Drupal\entity_labels;

interface EntityLabelsImporterInterface {

  /**
   * Parses a CSV string and applies label/description updates to config entities.
   *
   * @throws \Drupal\entity_labels\Exception\EntityLabelsCsvParseException
   *   On malformed CSV or missing required headers.
   * @throws \Drupal\entity_labels\Exception\EntityLabelsImportException
   *   On row-level failures that should abort the import.
   *
   * @return array
   *   Result map with keys: 'updated' (int), 'skipped' (int), 'errors' (string[]),
   *   'null_fields' (string[]) — identifiers (entity_type.bundle or
   *   entity_type.bundle.field_name) of rows where the config entity could not be loaded.
   */
  public function import(string $csv): array;

}
```

---

### src/EntityLabelsEntityExporterInterface.php

```php
<?php

declare(strict_types=1);

namespace Drupal\entity_labels;

interface EntityLabelsEntityExporterInterface extends EntityLabelsExporterInterface {

  /**
   * Returns entity/bundle report rows, optionally filtered by entity type or bundle.
   *
   * Only entity types that support bundles are included.
   * Rows sorted by entity type, then bundle.
   *
   * Keys: langcode, entity_type, bundle, label, description, help, notes.
   * The 'help' value is populated only for entity types whose bundle config entity
   * exposes a getHelp() method (e.g. node types); it is an empty string otherwise.
   *
   * @return array
   *   Entity/bundle report rows; each row is a string map of column name to value.
   */
  public function getData(?string $entity_type_id = NULL, ?string $bundle = NULL): array;

  /**
   * Builds CSV rows (header first) ready for fputcsv().
   * Header: langcode,entity_type,bundle,label,description,help,notes
   *
   * @return array
   *   CSV rows as lists of strings; first row is the header.
   */
  public function export(?string $entity_type_id = NULL, ?string $bundle = NULL): array;

}
```

---

### src/EntityLabelsEntityImporterInterface.php

```php
<?php

declare(strict_types=1);

namespace Drupal\entity_labels;

interface EntityLabelsEntityImporterInterface extends EntityLabelsImporterInterface {

  /**
   * Parses and processes a CSV string, updating bundle config entity labels.
   *
   * Required CSV headers: langcode, entity_type, bundle, label, description.
   * Optional: help (imported for entity types whose bundle config entity exposes
   * setHelp(), e.g. node types; silently ignored otherwise), notes (always ignored).
   *
   * Rows where the bundle config entity cannot be loaded (NULL) are counted as
   * skipped and their identifier (entity_type.bundle) recorded in 'null_fields'.
   *
   * @throws \Drupal\entity_labels\Exception\EntityLabelsCsvParseException
   * @throws \Drupal\entity_labels\Exception\EntityLabelsImportException
   *
   * @return array
   *   Result map with keys: 'updated' (int), 'skipped' (int), 'errors' (string[]),
   *   'null_fields' (string[]) — identifiers (entity_type.bundle) of rows where
   *   the bundle config entity could not be loaded.
   */
  public function import(string $csv): array;

}
```

---

### src/EntityLabelsFieldExporterInterface.php

```php
<?php

declare(strict_types=1);

namespace Drupal\entity_labels;

interface EntityLabelsFieldExporterInterface extends EntityLabelsExporterInterface {

  /**
   * Returns field report rows, optionally filtered by entity type or bundle.
   *
   * When $bundle is provided, cross-bundle summary rows (is_summary_row = TRUE)
   * are emitted for fields shared across multiple bundles, immediately before
   * each field's per-bundle row. See "Cross-bundle summary rows" in Design.
   *
   * Rows sorted by entity type, bundle, field name.
   *
   * Keys: langcode, entity_type, bundle (null for summary), field_name,
   *       field_column (empty string unless custom_field 4.x is installed),
   *       field_type, label, description, allowed_values,
   *       is_base_field (bool), is_summary_row (bool), notes.
   * When field_group is installed, additional rows are appended with
   * field_type = 'field_group'. When custom_field 4.x is installed,
   * additional rows are appended per column with field_column set.
   *
   * @return array
   *   Field report rows; each row is a map of column name to string, bool, or null value.
   */
  public function getData(?string $entity_type_id = NULL, ?string $bundle = NULL): array;

  /**
   * Builds CSV rows (header first) ready for fputcsv().
   * Header includes field_column when custom_field 4.x is installed:
   *   langcode,entity_type,bundle,field_name,field_column,field_type,label,description,allowed_values,notes
   * Header without custom_field:
   *   langcode,entity_type,bundle,field_name,field_type,label,description,allowed_values,notes
   *
   * @return array
   *   CSV rows as lists of strings; first row is the header.
   */
  public function export(?string $entity_type_id = NULL, ?string $bundle = NULL): array;

}
```

---

### src/EntityLabelsFieldImporterInterface.php

```php
<?php

declare(strict_types=1);

namespace Drupal\entity_labels;

interface EntityLabelsFieldImporterInterface extends EntityLabelsImporterInterface {

  /**
   * Parses and processes a CSV string, updating field config entity labels.
   *
   * Required CSV headers: langcode, entity_type, bundle, field_name, label, description.
   * Optional: field_column (defaults to '' when absent), allowed_values, field_type, notes.
   *
   * Summary rows (bundle = '(default / all bundles)') are applied as the default
   * label/description for per-bundle rows of the same entity_type + field_name that
   * supply no values of their own. The entire CSV is re-sorted by entity_type, bundle,
   * field_name before processing to ensure defaults are available before per-bundle rows.
   *
   * Rows where neither FieldConfig nor BaseFieldOverride can be loaded (NULL) are
   * counted as skipped and their identifier (entity_type.bundle.field_name) recorded
   * in 'null_fields'.
   *
   * field_group rows (field_type = 'field_group'): updates the group's label/description
   * via the default form display's third_party_settings. Skipped with warning if
   * field_group is not installed.
   *
   * custom_field column rows (non-empty field_column): updates the named column's
   * label/description in field_settings. Skipped with warning if custom_field is not
   * installed or not 4.x.
   *
   * @throws \Drupal\entity_labels\Exception\EntityLabelsCsvParseException
   * @throws \Drupal\entity_labels\Exception\EntityLabelsImportException
   *
   * @return array
   *   Result map with keys: 'updated' (int), 'skipped' (int), 'errors' (string[]),
   *   'null_fields' (string[]) — identifiers (entity_type.bundle.field_name) of rows
   *   where neither FieldConfig nor BaseFieldOverride could be loaded.
   */
  public function import(string $csv): array;

}
```

---

### src/EntityLabelsEntityExporter.php

Implements `EntityLabelsEntityExporterInterface`.

**Constructor injection (autowired):**
```php
public function __construct(
  private readonly EntityTypeManagerInterface $entityTypeManager,
  private readonly EntityTypeBundleInfoInterface $bundleInfoManager,
) {}
```

**`getData()` notes:**
- Iterate `$this->entityTypeManager->getDefinitions()`; skip entity types where `$entity_type->getBundleEntityType()` returns `NULL`.
- Filter by `$entity_type_id` if set; filter to `$bundle` if set.
- Load bundle config entities via `$this->entityTypeManager->getStorage($bundle_entity_type)->load($bundle_id)`.
- Duck-type `getDescription()` and `getHelp()` via `method_exists()`. The `help` field is only populated for entity types whose bundle config entity exposes `getHelp()` (e.g. `NodeType`); it is an empty string otherwise — **do not** add a `help` column note to the report for entity types that do not support it.
- Sort by entity type, then bundle.
- Row keys: `langcode`, `entity_type`, `bundle`, `label`, `description`, `help`, `notes` (empty string).

**`export()` notes:**
- Header: `langcode,entity_type,bundle,label,description,help,notes`.
- Filename convention (set by controller): `entity-labels-entities.csv` or `entity-labels-entities-{entity_type}.csv`.

---

### src/EntityLabelsEntityImporter.php

Implements `EntityLabelsEntityImporterInterface`.

No constructor injection in v1 — uses static bundle config entity loading.

**`import(string $csv)` notes:**
- Parse `$csv` via `fgetcsv()` on a memory stream; throw `EntityLabelsCsvParseException` if malformed or required headers missing.
- Required headers: `langcode`, `entity_type`, `bundle`, `label`, `description`.
- Optional headers: `help`, `notes`. `notes` is silently ignored if present. `help` is imported for entity types whose bundle config entity exposes a `setHelp()` method (e.g. node types) and silently ignored for entity types that do not support it.
- Per row: load the bundle config entity. If the entity is `NULL` (unknown entity type or bundle), increment `$skipped`, append `"{entity_type}.{bundle}"` to `$null_fields`, and continue.
- For successfully loaded entities: `->getTranslation($langcode)` (creates if absent); set label/description; save.
- Return `['updated' => $updated, 'skipped' => $skipped, 'errors' => $errors, 'null_fields' => $null_fields]`.

---

### src/EntityLabelsFieldExporter.php

Implements `EntityLabelsFieldExporterInterface`.

**Constructor injection (autowired):**
```php
public function __construct(
  private readonly EntityTypeManagerInterface $entityTypeManager,
  private readonly EntityFieldManagerInterface $fieldManager,
  private readonly ModuleHandlerInterface $moduleHandler,
  private readonly EntityDisplayRepositoryInterface $entityDisplayRepository,
) {}
```

`ModuleHandlerInterface` gates field_group/custom_field logic via `moduleExists()`. `EntityDisplayRepositoryInterface` loads entity form displays for field_group groups.

**`getData()` notes:**

When `$bundle` is provided (field detail scope):
- `$this->fieldManager->getFieldDefinitions($entity_type_id, $bundle)`.
- `is_base_field`: `$def instanceof BaseFieldDefinition && !($def instanceof BaseFieldOverride)`.
- `allowed_values`: serialize as `key1|Label 1;key2|Label 2` or `''`.
- **Cross-bundle summary row** — emitted **only** when the field exists on more than one bundle of the same entity type (`is_summary_row = TRUE`, `bundle = NULL`); single-bundle fields get no summary row:
  - Resolve storage-level default: `FieldConfig` fields → `FieldStorageConfig::load("{entity_type}.{field_name}")->getLabel()`; base fields → unoverridden `BaseFieldDefinition` label.
  - Collect labels/descriptions from all sibling bundles via `$this->fieldManager->getFieldDefinitions($entity_type_id, $other_bundle)`.
  - `notes` always set to `"Default label and description for all instances of this field"`.
  - If all bundles agree with the storage default → `label` and `description` are **empty strings**.
  - If any bundle differs → `label` = storage default, `description` = storage default description, `notes` appended with e.g. `". Differs: article → 'Tags', page → 'Categories'"`.
  - Insert immediately before the per-bundle row.
- Sort: field name ASC, summary row first within each field name group.
- Row keys: `langcode`, `entity_type`, `bundle`, `field_name`, `field_column`, `field_type`, `label`, `description`, `allowed_values`, `is_base_field` (bool), `is_summary_row` (bool), `notes`. `field_column` is an empty string for all non-custom_field rows.
- **field_group rows** (when `field_group` is installed): load the default form display via `$this->entityDisplayRepository->getFormDisplay($entity_type_id, $bundle, 'default')`; read `getThirdPartySettings('field_group')` and append one row per group. Set `field_name` = group machine name, `field_type` = `'field_group'`, `notes` = `'Field group — default form mode'`. Inspect `field_group.entity_display.schema.yml` to confirm exact key names for label and description.
- **custom_field column rows** (when `custom_field` 4.x is installed): for each `custom_field` field, emit additional rows per column in `getSetting('field_settings')`. Set `field_column` = column machine name. Inspect `config/schema/custom_field.schema.yml` on the 4.0.x branch to confirm the outer key name. Skip with a warning for non-4.x installations.

When only `$entity_type_id` provided (or null): return all field rows across matching bundles without summary rows.

**`export()` notes:**
- Header when `custom_field` is installed: `langcode,entity_type,bundle,field_name,field_column,field_type,label,description,allowed_values,notes`.
- Header when not installed: `langcode,entity_type,bundle,field_name,field_type,label,description,allowed_values,notes`.
- Summary rows exported with `bundle = "(default / all bundles)"`.
- Filename convention (set by controller): `entity-labels-fields.csv` or `entity-labels-fields-{entity_type}-{bundle}.csv`.

---

### src/EntityLabelsFieldImporter.php

Implements `EntityLabelsFieldImporterInterface`.

**Constructor injection (autowired):**
```php
public function __construct(
  private readonly ModuleHandlerInterface $moduleHandler,
  private readonly EntityDisplayRepositoryInterface $entityDisplayRepository,
) {}
```

Static `FieldConfig::load()` / `BaseFieldOverride::load()` calls are used for per-bundle field loading.

**`import(string $csv)` notes:**
- Parse `$csv`; throw `EntityLabelsCsvParseException` if malformed or required headers missing.
- Required headers: `langcode`, `entity_type`, `bundle`, `field_name`, `label`, `description`.
- Optional headers (silently accepted): `field_column`, `allowed_values`, `field_type`, `notes`. Default `field_column` to `''` when absent (backwards-compatible with CSVs exported without custom_field installed).
- Re-sort all parsed rows by `entity_type`, `bundle`, `field_name` before processing (ensures summary-row defaults are indexed before per-bundle rows are handled).
- Build a defaults map from summary rows: for each row where `bundle` = `(default / all bundles)`, store `label` and `description` keyed by `"{entity_type}.{field_name}"`.
- Per-bundle row processing: `FieldConfig::load("{entity_type}.{bundle}.{field_name}")` → fallback `BaseFieldOverride::load(...)`. If both return `NULL`, increment `$skipped`, append `"{entity_type}.{bundle}.{field_name}"` to `$null_fields`, and continue. For found entities: if the row's `label`/`description` are empty, fall back to the defaults map entry for that field; call `->getTranslation($langcode)`, set label/description, save.
- Summary rows are not directly saved; they only populate the defaults map and are counted as skipped.
- **field_group rows** (`field_type = 'field_group'`): load the default form display via `EntityDisplayRepositoryInterface`, update the group's `label`/`description` within `third_party_settings.field_group.<field_name>` using `setThirdPartySetting()`, save. Only update the label/description keys — never replace the full group settings array. Skip with warning if `field_group` is not installed.
- **custom_field column rows** (`field_column` non-empty): load the `FieldConfig`, call `getSetting('field_settings')`, update the named column's label/description, call `setSetting('field_settings', ...)`, save. Skip with warning if `custom_field` is not installed or not 4.x.
- Return `['updated' => $updated, 'skipped' => $skipped, 'errors' => $errors, 'null_fields' => $null_fields]`.

---

### src/EntityLabelsTypeTrait.php

Used by both `EntityLabelsController` and `EntityLabelsImportForm`. The using class must declare `protected string $type` and inject `ContainerInterface $container` before invoking the trait methods.

```php
<?php

declare(strict_types=1);

namespace Drupal\entity_labels;

use Drupal\Core\StringTranslation\TranslatableMarkup;

trait EntityLabelsTypeTrait {

  protected function getSingularLabel(): TranslatableMarkup {
    return match ($this->type) {
      'field'  => $this->t('Field'),
      default  => $this->t('Entity'),
    };
  }

  protected function getPluralLabel(): TranslatableMarkup {
    return match ($this->type) {
      'field'  => $this->t('Fields'),
      default  => $this->t('Entities'),
    };
  }

  protected function getReportRoute(): string {
    return 'entity_labels.' . $this->type . '.report';
  }

  protected function getExportRoute(): string {
    return 'entity_labels.' . $this->type . '.export';
  }

  protected function getImportRoute(): string {
    return 'entity_labels.' . $this->type . '.import';
  }

  protected function getExporter(): EntityLabelsExporterInterface {
    return $this->container->get('entity_labels.' . $this->type . '.exporter');
  }

  protected function getImporter(): EntityLabelsImporterInterface {
    return $this->container->get('entity_labels.' . $this->type . '.importer');
  }

}
```

---

### src/Controller/EntityLabelsController.php

Single controller extending `ControllerBase`. Injects the container directly so the export service can be resolved lazily once `$type` is known from the route default.

```php
<?php

declare(strict_types=1);

namespace Drupal\entity_labels\Controller;

use Drupal\entity_labels\EntityLabelsTypeTrait;

class EntityLabelsController extends ControllerBase {

  use EntityLabelsTypeTrait;

  public function __construct(
    private readonly ContainerInterface $container,
    private readonly RequestStack $requestStack,
    protected readonly string $type,
  ) {}

  public static function create(ContainerInterface $container): static {
    $type = $container->get('request_stack')
      ->getCurrentRequest()->attributes->get('type', 'entity');
    return new static($container, $container->get('request_stack'), $type);
  }

  // getExporter(), getExportRoute(), getReportRoute(), and label helpers
  // are provided by EntityLabelsTypeTrait.

  public function title(?string $entity_type = NULL, ?string $bundle = NULL): TranslatableMarkup;
  public function report(?string $entity_type = NULL, ?string $bundle = NULL): array;
  public function export(Request $request): StreamedResponse;

}
```

**`title()` notes:**
- Returns the bundle label when both `$entity_type` and `$bundle` are set, the entity type label when only `$entity_type` is set, or the plural tab label (e.g. "Entities" / "Fields") when neither is set.
- Uses `$this->getPluralLabel()` for the no-params case so entity and field tabs show their respective titles.
- Always return `TranslatableMarkup`. Wrap plain-string labels (e.g. from `getLabel()`) with `$this->t('@label', ['@label' => $label])` so the return type is consistently `TranslatableMarkup` with no union. PHPStan level 5 flags unreachable branches in a `string|TranslatableMarkup` union.

**`report()` notes:**
- Calls `$this->getExporter()->getData($entity_type, $bundle)`.
- All table headers use machine names matching the row keys.
- Entity type and bundle links are built from `$this->getReportRoute()` so they always stay on the current tab.
- Field detail view: appends a note below the table that `allowed_values` and `field_type` cannot be imported; applies `entity-labels--summary` CSS class to summary rows.
- **Download CSV button:** rendered as a Drupal link styled as a button (`#type => 'link'`, `#attributes => ['class' => ['button']]`) pointing to `Url::fromRoute($this->getExportRoute(), [], ['query' => array_filter(['entity_type' => $entity_type, 'bundle' => $bundle])])`. The button label is `'⇩ Download CSV'`. It appears immediately below the table.

**`export()` notes:**
- `$entity_type = $request->query->getString('entity_type') ?: NULL`; `$bundle` similarly.
- Calls `$this->getExporter()->export($entity_type, $bundle)`.
- Returns `new StreamedResponse(...)` writing rows via `fputcsv(fopen('php://output', 'w'), ...)`.
- Headers: `Content-Type: text/csv; charset=utf-8`, `Content-Disposition: attachment; filename="..."`.

---

### src/Form/EntityLabelsImportForm.php

Single form extending `FormBase`. Uses `EntityLabelsTypeTrait` for route and label helpers. Injects the container directly to resolve the import service lazily.

```php
<?php

declare(strict_types=1);

namespace Drupal\entity_labels\Form;

use Drupal\entity_labels\EntityLabelsTypeTrait;

class EntityLabelsImportForm extends FormBase {

  use EntityLabelsTypeTrait;

  public function __construct(
    private readonly ContainerInterface $container,
    protected readonly string $type,
  ) {}

  public static function create(ContainerInterface $container): static {
    $type = $container->get('request_stack')
      ->getCurrentRequest()->attributes->get('type', 'entity');
    return new static($container, $type);
  }

  // getImporter(), getImportRoute(), and label helpers
  // are provided by EntityLabelsTypeTrait.

  public function getFormId(): string {
    return 'entity_labels_' . $this->type . '_import_form';
  }

}
```

**`buildForm()` notes:**
- When `$this->type === 'field'`: prepend a `#markup` notice that `allowed_values` and `field_type` will be ignored during import.
- Add a plain `#type => 'file'` element (not `managed_file`) with `#element_validate` pointing to a static `validateFileUpload()` method, following the pattern from [xurizaemon/csvimport CSVimportForm.php](https://github.com/xurizaemon/csvimport/blob/8.x-1.x/src/Form/CSVimportForm.php). Set `$form['#attributes']['enctype'] = 'multipart/form-data'`. Add an "Import CSV" submit button.

**`validateFileUpload()` notes (static element validator):**
- Call `file_save_upload()` with `['file_validate_extensions' => ['csv CSV']]` validators.
- If the upload succeeds, copy the file to `temporary://entity_labels/` via `FileSystemInterface::prepareDirectory()` + `file_copy()`.
- Store the destination path in form state via `$form_state->setValue('csvupload', $destination)`.
- Set a form error via `$form_state->setErrorByName()` if the directory cannot be prepared or the copy fails.

**`validateForm()` notes:**
- If `$form_state->getValue('csvupload')` is set, open the file with `fopen()` and confirm it is readable; set a form error if not.

**`submitForm()` notes:**
- Open the file at `$form_state->getValue('csvupload')` with `fopen()` and read it into a raw CSV string.
- Call `$this->getImporter()->import($csv_string)`.
- Catch `EntityLabelsCsvParseException` and `EntityLabelsImportException`; display each via `$this->messenger()->addError()`.
- On success display updated/skipped counts via `$this->messenger()->addStatus()`. If `$result['null_fields']` is non-empty, display a warning listing the unresolvable identifiers (e.g. "Could not load: node.article.body").
- After import (success or failure), delete the temporary file with `unlink()`.

---

### src/Breadcrumb/EntityLabelsBreadcrumbBuilder.php

Implements `BreadcrumbBuilderInterface`. Constructor args resolved via file-level autowiring. Injected services: `EntityTypeManagerInterface` (to resolve entity type and bundle labels), `EntityTypeBundleInfoInterface` (to look up bundle label by entity type + bundle machine name).

- **`applies()`:** Return `TRUE` when `str_starts_with($route_match->getRouteName(), 'entity_labels.')`.
- **`build()`:**
  - Determine the current tab from the route name: `entity_labels.entity.*` → entity tab (base route `entity_labels.entity.report`, tab label "Entities"); `entity_labels.field.*` → field tab (base route `entity_labels.field.report`, tab label "Fields").
  - Always add: Home → Administration → Reports → Entity labels (linked to `entity_labels.entity.report`, no params) → Entities or Fields (linked to the tab's base report route when drilling deeper; unlinked when no `{entity_type}` is set).
  - `$entity_type_id = $route_match->getParameter('entity_type')`: if non-null, resolve the entity type label via `$this->entityTypeManager->getDefinition($entity_type_id)->getLabel()` and add as a linked crumb pointing to the base report route with `{entity_type}` only.
  - `$bundle = $route_match->getParameter('bundle')`: if both params are present, look up the bundle label via `$this->bundleInfoManager->getBundleInfo($entity_type_id)[$bundle]['label']` and add as the unlinked active crumb.
  - All params are read via `RouteMatchInterface::getParameter()`, not the request query bag (export/import routes have no path params and therefore show only the Home–Admin–Reports–Entity labels–Tab trail).

---

### tests/

#### Unit Tests

Granular: one test method per behaviour. Mock all dependencies against interfaces.

**PHPDoc conventions for test classes:**
- Data-provider methods use `@return array` (no generics) with a plain-English description on the next line.
- `@param` tags must appear **before** `@dataProvider` in the docblock — PHPCS enforces this ordering.
- Example (provider method):
  ```php
  /**
   * Data provider for testImportUpdatesBundleConfigLabel().
   *
   * @return array
   *   Keyed test cases; each value is [csv string, expected updated count].
   */
  public function provideImportCsvCases(): array {
  ```
- Example (test method consuming the provider):
  ```php
  /**
   * Tests that import updates the bundle config label.
   *
   * @param string $csv
   *   The CSV string to import.
   * @param int $expected_updated
   *   The expected updated count.
   *
   * @dataProvider provideImportCsvCases
   */
  public function testImportUpdatesBundleConfigLabel(string $csv, int $expected_updated): void {
  ```

##### `EntityLabelsEntityExportTest`

```
testGetDataOnlyIncludesEntityTypesWithBundleSupport()
testGetDataReturnsExpectedStructure()
testGetDataSortsByEntityTypeThenBundle()
testGetDataFiltersToEntityType()
testGetDataFiltersToBundle()
testGetDataRowIncludesNotesKey()
testExportReturnsHeaderRow()              // header ends with 'notes'
testExportRowsMatchGetDataOutput()
```

##### `EntityLabelsEntityImportTest`

```
testImportThrowsOnMalformedCsv()              // EntityLabelsCsvParseException
testImportThrowsOnMissingRequiredHeaders()    // EntityLabelsCsvParseException
testImportUpdatesBundleConfigLabel()          // result['updated'] === 1
testImportSkipsUnknownBundle()                // result['skipped'] === 1
testImportIgnoresNotesColumn()               // no error, no effect
testImportUsesLangcodeForTranslation()        // getTranslation('fr') called
```

##### `EntityLabelsFieldExportTest`

```
testGetDataReturnsConfigurableFields()                  // is_base_field = FALSE
testGetDataIncludesBaseFields()                         // is_base_field = TRUE
testGetDataSortsByFieldName()
testGetDataSummaryRowEmittedOnlyForMultiBundleFields()  // single-bundle field → no summary row
testGetDataSummaryRowAppearsBeforePerBundleRow()
testGetDataSummaryRowHasBlankLabelWhenAllBundlesAgree()
testGetDataSummaryRowHasNotesWhenBundlesDisagree()
testGetDataHandlesAllowedValues()                       // 'foo|Foo,bar|Bar'
testGetDataHandlesFieldsWithNoAllowedValues()           // empty string
testGetDataRowIncludesNotesKey()
testExportReturnsHeaderRow()                            // header ends with 'notes'
testExportRowsMatchGetDataOutput()

// field_group support (moduleHandler->moduleExists('field_group') mocked TRUE)
testGetDataAppendsFieldGroupRowsWhenModuleInstalled()   // one row per group; field_type = 'field_group'
testGetDataFieldGroupRowHasCorrectNotes()               // notes = 'Field group — default form mode'
testGetDataSkipsFieldGroupsWhenModuleNotInstalled()     // moduleExists returns FALSE → no group rows
testExportHeaderExcludesFieldColumnWhenCustomFieldAbsent()  // no field_column column in header

// custom_field support (moduleHandler->moduleExists('custom_field') mocked TRUE)
testGetDataAppendsCustomFieldColumnRowsWhenModuleInstalled()  // one row per column; field_column set
testGetDataCustomFieldColumnRowHasFieldColumnKey()            // field_column = column machine name
testGetDataSkipsCustomFieldColumnsWhenModuleNotInstalled()    // no extra rows
testGetDataSkipsCustomFieldColumnsWhenNotVersion4()           // wrong major version → skip with warning
testExportHeaderIncludesFieldColumnWhenCustomFieldInstalled() // field_column present in header row
testExportFieldColumnEmptyStringForNonCustomFieldRows()       // regular field rows have '' field_column
```

##### `EntityLabelsFieldImportTest`

Uses kernel test base or static-call test doubles.

```
testImportThrowsOnMalformedCsv()              // EntityLabelsCsvParseException
testImportThrowsOnMissingRequiredHeaders()    // EntityLabelsCsvParseException
testImportUpdatesFieldConfigLabel()           // result['updated'] === 1
testImportSkipsUnknownField()                 // result['skipped'] === 1
testImportSkipsSummaryRows()                  // bundle = '(default / all bundles)'
testImportIgnoresAllowedValuesColumn()        // no error, no effect
testImportIgnoresFieldTypeColumn()            // no error, no effect
testImportIgnoresNotesColumn()                // no error, no effect
testImportUsesLangcodeForTranslation()        // getTranslation('fr') called
testImportAcceptsFieldColumnHeaderGracefully() // CSV without field_column → field_column defaults to ''

// field_group support (moduleHandler->moduleExists('field_group') mocked TRUE)
testImportUpdatesFieldGroupLabel()            // third_party_settings label updated; result['updated'] === 1
testImportUpdatesFieldGroupDescription()      // third_party_settings description updated
testImportSkipsFieldGroupRowWhenModuleAbsent() // moduleExists('field_group') = FALSE → skipped with warning
testImportNeverReplacesFullFieldGroupSettings() // only label/description keys touched, not entire group array

// custom_field support (moduleHandler->moduleExists('custom_field') mocked TRUE)
testImportUpdatesCustomFieldColumnLabel()     // named column label in field_settings updated; result['updated'] === 1
testImportUpdatesCustomFieldColumnDescription()
testImportSkipsCustomFieldRowWhenModuleAbsent() // moduleExists('custom_field') = FALSE → skipped with warning
testImportSkipsCustomFieldRowWhenNotVersion4()  // wrong major version → skipped with warning
```

#### Functional Tests

One test method per class; inline `// Check that...` comments delineate assertion groups.

##### `EntityLabelsEntityReportTest` (extends `BrowserTestBase`)

```php
public function testEntityReport(): void {
  // --- Access control ---
  // Check that anonymous users are denied access.
  // Check that authenticated users without 'access site reports' are denied.
  // Check that a user with 'access site reports' gets a 200 response.

  // --- Bundle list view (no params) ---
  // Check that table headers include: langcode, entity_type, bundle, label, description, help.
  // Check that only entity types with bundle support appear.
  // Check that rows are sorted by entity type, then bundle.
  // Check that each entity_type cell links to /admin/reports/entity-labels/entity/{entity_type}.
  // Check that each bundle cell links to /admin/reports/entity-labels/entity/{entity_type}/{bundle}.
  // Check that a '⇩ Download CSV' button appears at the bottom of the table pointing to /entity/export.
  // Check that the breadcrumb reads: Home > Administration > Reports > Entity labels.
  // Check that primary tabs 'Entities' and 'Fields' are both present.
  // Check that secondary tabs 'Export' and 'Import' are present under Entities.

  // --- Entity type filter view (/entity/node) ---
  // Check that only node bundles are returned.
  // Check that the breadcrumb reads: … > Entity labels > Content.
  // Check that the export link points to /entity/export?entity_type=node.

  // --- Bundle detail view (/entity/node/article) ---
  // Check that the page returns 200 with the node/article bundle row.
  // Check that the breadcrumb reads: … > Entity labels > Content > Article.
  // Check that the export link points to ?entity_type=node&bundle=article.

  // --- CSV export ---
  // Check that GET /entity/export returns Content-Type: text/csv.
  // Check that the first CSV row contains headers: langcode,entity_type,bundle,label,description,help,notes.
  // Check that GET ?entity_type=node&bundle=article returns 200 with Content-Disposition filename containing 'node' and 'article'.
  // Check that the CSV row order matches the on-screen sort order.
}
```

##### `EntityLabelsEntityImportTest` (extends `BrowserTestBase`)

```php
public function testEntityImport(): void {
  // --- Access control ---
  // Check that a user with 'access site reports' only is denied (403).
  // Check that a user with 'administer site configuration' gets a 200 response.

  // --- Import form UI ---
  // Check that the file upload element is present.
  // Check that the 'Import CSV' submit button is present.

  // --- Validation / exception handling ---
  // Check that uploading a .txt file shows an error message.
  // Check that uploading a CSV missing 'langcode' header catches EntityLabelsCsvParseException and displays the error.
  // Check that uploading a CSV missing 'bundle' header catches EntityLabelsCsvParseException and displays the error.

  // --- Successful bundle label update ---
  // Check that exporting entity CSV, editing one bundle label, and re-uploading shows status "1 row updated".
  // Check that the bundle config entity now has the updated label.
  // Check that other bundles are unchanged.

  // --- Multilingual import ---
  // Check that installing 'fr', exporting (langcode = 'en'), changing langcode → 'fr' and updating the label updates the French translation.
  // Check that the English label is unchanged.
}
```

##### `EntityLabelsFieldReportTest` (extends `BrowserTestBase`)

```php
public function testFieldReport(): void {
  // --- Access control ---
  // Check that anonymous users are denied access.
  // Check that authenticated users without 'access site reports' are denied.
  // Check that a user with 'access site reports' gets a 200 response.

  // --- Field list view (no params) ---
  // Check that table headers include: langcode, entity_type, bundle, field_name, field_type, label, description, allowed_values, notes.
  // Check that rows are sorted by entity type, bundle, field name.
  // Check that each entity_type cell links to /admin/reports/entity-labels/field/{entity_type}.
  // Check that each bundle cell links to /admin/reports/entity-labels/field/{entity_type}/{bundle}.
  // Check that a '⇩ Download CSV' button appears at the bottom pointing to /field/export.
  // Check that primary tabs 'Entities' and 'Fields' are present.
  // Check that secondary tabs 'Export' and 'Import' are present under Fields.

  // --- Entity type filter view (/field/node) ---
  // Check that only node fields are returned.
  // Check that the breadcrumb reads: … > Entity labels > Content.
  // Check that the export link points to /field/export?entity_type=node.

  // --- Bundle detail view (/field/node/article) ---
  // Check that the page returns 200.
  // Check that 'title' base field is present and marked as a base field.
  // Check that the note below the table states that allowed_values and field_type cannot be updated via CSV import.
  // Check that fields shared across multiple bundles have a cross-bundle summary row (bundle = '(default / all bundles)') appearing before the per-bundle row.
  // Check that the summary row label and description are blank when all bundles agree with the storage default.
  // Check that the summary row notes column contains a disagreement note when sibling bundles have differing labels.
  // Check that single-bundle fields do not have a summary row.
  // Check that the export link points to ?entity_type=node&bundle=article.
  // Check that the breadcrumb reads: … > Entity labels > Content > Article.

  // --- CSV export ---
  // Check that GET /field/export returns Content-Type: text/csv.
  // Check that the first CSV row contains headers: langcode,entity_type,bundle,field_name,field_type,label,description,allowed_values,notes (field_column absent when custom_field not installed).
  // Check that GET ?entity_type=node&bundle=article returns Content-Disposition filename containing 'node' and 'article'.
  // Check that the allowed_values column is empty for fields without allowed values.
  // Check that cross-bundle summary rows appear in the CSV with bundle = '(default / all bundles)'.
  // Check that the CSV row order matches the on-screen sort order.
}
```

##### `EntityLabelsFieldImportTest` (extends `BrowserTestBase`)

```php
public function testFieldImport(): void {
  // --- Access control ---
  // Check that a user with 'access site reports' only is denied (403).
  // Check that a user with 'administer site configuration' gets a 200 response.

  // --- Import form UI ---
  // Check that the notice about allowed_values and field_type not being importable is present.
  // Check that the file upload element is present.
  // Check that the 'Import CSV' button is present.

  // --- Validation / exception handling ---
  // Check that uploading a .txt file shows an error message.
  // Check that uploading a CSV missing 'langcode' catches EntityLabelsCsvParseException and displays the error.
  // Check that uploading a CSV missing 'field_name' catches EntityLabelsCsvParseException and displays the error.

  // --- Successful field label update ---
  // Check that exporting field CSV for node/article, changing 'title' label → 'Headline', and uploading shows "1 row updated".
  // Check that BaseFieldOverride for node.article.title now has label 'Headline'.
  // Check that other fields are unchanged.

  // --- Edge cases ---
  // Check that a CSV with an allowed_values column uploads without error and the field's allowed values are unchanged.
  // Check that a CSV with a field_type column uploads without error.
  // Check that a CSV with a notes column uploads without error.
  // Check that a row with bundle = '(default / all bundles)' is silently skipped and the skipped count is incremented.
  // Check that a row with a non-existent field_name is skipped with a warning.
  // Check that a row with a non-existent entity_type is skipped with a warning.
  // Check that the page returns 200 after encountering skipped/errored rows.

  // --- Multilingual import ---
  // Check that installing 'fr', exporting node/article (langcode = 'en'), changing langcode → 'fr' and updating the label updates the French translation.
  // Check that the English label is unchanged.
}
```

---

##### `EntityLabelsFieldGroupTest` (extends `BrowserTestBase`)

Installs `field_group` in `$modules`. Creates a field group on the node/article default form mode as test fixture.

```php
public function testFieldGroupReport(): void {
  // --- Report ---
  // Check that the Fields report for node/article includes a row with field_type = 'field_group'.
  // Check that the field_group row's field_name matches the group machine name.
  // Check that the field_group row notes column reads 'Field group — default form mode'.
  // Check that non-field_group rows are unaffected.
}

public function testFieldGroupExport(): void {
  // Check that GET /field/export?entity_type=node&bundle=article returns a CSV containing a row with field_type = 'field_group'.
  // Check that the field_group row's field_name column matches the group machine name.
  // Check that the field_group row's label column matches the group's configured label.
}

public function testFieldGroupImport(): void {
  // Check that uploading a CSV with a field_group row updates the group's label in the default form display's third_party_settings.
  // Check that the status message reports the correct updated count (group row counted as updated).
  // Check that other keys in the field_group settings (e.g. children, weight) are unchanged after import.
  // Check that uploading a field_group row for an unknown group machine name is skipped with a warning.
}
```

##### `EntityLabelsCustomFieldTest` (extends `BrowserTestBase`)

Installs `custom_field` (4.x) in `$modules`. Creates a `custom_field` field on node/article with at least two columns as test fixture.

```php
public function testCustomFieldReport(): void {
  // --- Report ---
  // Check that the Fields report for node/article includes one additional row per custom_field column.
  // Check that each column row has the correct field_column value (column machine name).
  // Check that the column row field_name matches the parent custom_field's machine name.
  // Check that the parent custom_field row has field_column = '' (empty).
}

public function testCustomFieldExport(): void {
  // Check that GET /field/export?entity_type=node&bundle=article includes field_column in the CSV header.
  // Check that column rows have the correct field_column value in the CSV.
  // Check that the parent custom_field row has an empty field_column value.
  // Check that non-custom_field rows also have an empty field_column value.
}

public function testCustomFieldImport(): void {
  // Check that uploading a CSV with a non-empty field_column updates the named column's label in field_settings.
  // Check that the status message reports the correct updated count.
  // Check that other columns in field_settings are unchanged after a single-column import.
  // Check that uploading a row with an unrecognised field_column value is skipped with a warning.
  // Check that a CSV exported without field_column (i.e. without custom_field installed) can still be imported without error — field_column defaults to ''.
}
```

### Drush Support

`import()` accepting a raw CSV string makes Drush commands straightforward — read file, pass string, handle exceptions.

- `drush entity-labels:entity-export [--entity-type=X] [--bundle=Y]` — entities CSV to stdout.
- `drush entity-labels:field-export [--entity-type=X] [--bundle=Y]` — fields CSV to stdout.
- `drush entity-labels:entity-import <file>` / `drush entity-labels:field-import <file>` — CLI import.

---

## Reference

### Drupal.org Contrib Standards

- [GitLab CI — Using GitLab to contribute to Drupal](https://www.drupal.org/docs/develop/git/using-gitlab-to-contribute-to-drupal/gitlab-ci) — configuring `.gitlab-ci.yml` for automated testing on Drupal.org
- [Project Browser: logo and compatibility requirements for module maintainers](https://www.drupal.org/docs/extending-drupal/contributed-modules/contributed-module-documentation/project-browser/module-maintainers-how-to-update-projects-to-be-compatible-with-project-browser#s-logo) — `logo.png` specifications (512×512 PNG, ≤10 KB) and Project Browser card requirements

### Drupal APIs

- [EntityTypeManagerInterface](https://api.drupal.org/api/drupal/core!lib!Drupal!Core!Entity!EntityTypeManagerInterface.php/interface/EntityTypeManagerInterface/11.x) — `getDefinitions()`, `getStorage()`
- [EntityFieldManagerInterface::getFieldDefinitions()](https://api.drupal.org/api/drupal/core!lib!Drupal!Core!Entity!EntityFieldManagerInterface.php/function/EntityFieldManagerInterface%3A%3AgetFieldDefinitions/11.x) — per-bundle field definitions
- [EntityTypeBundleInfoInterface::getBundleInfo()](https://api.drupal.org/api/drupal/core!lib!Drupal!Core!Entity!EntityTypeBundleInfoInterface.php/function/EntityTypeBundleInfoInterface%3A%3AgetBundleInfo/11.x) — bundle metadata
- [FieldConfigInterface](https://api.drupal.org/api/drupal/core!lib!Drupal!Core!Field!FieldConfigInterface.php/interface/FieldConfigInterface/11.x) — per-bundle field config entities
- [BaseFieldDefinition](https://api.drupal.org/api/drupal/core!lib!Drupal!Core!Field!BaseFieldDefinition.php/class/BaseFieldDefinition/11.x) — core field definitions
- [BaseFieldOverride](https://api.drupal.org/api/drupal/core!lib!Drupal!Core!Field!Entity!BaseFieldOverride.php/class/BaseFieldOverride/11.x) — per-bundle overrides
- [FieldStorageConfig](https://api.drupal.org/api/drupal/core!modules!field!src!Entity!FieldStorageConfig.php/class/FieldStorageConfig/11.x) — storage-level field config
- [BreadcrumbBuilderInterface](https://api.drupal.org/api/drupal/core!lib!Drupal!Core!Breadcrumb!BreadcrumbBuilderInterface.php/interface/BreadcrumbBuilderInterface/11.x) — breadcrumb integration
- [ControllerBase](https://api.drupal.org/api/drupal/core!lib!Drupal!Core!Controller!ControllerBase.php/class/ControllerBase/11.x) — base class for route controllers
- [FormBase](https://api.drupal.org/api/drupal/core!lib!Drupal!Core!Form!FormBase.php/class/FormBase/11.x) — base class for forms
- [file_save_upload()](https://api.drupal.org/api/drupal/core!modules!file!file.module/function/file_save_upload/11.x) — saves an uploaded file to a temporary location; used in element validators
- [Routing — optional parameters](https://www.drupal.org/docs/drupal-apis/routing-system/parameters-in-routes#s-parameters-with-default-values) — how to define defaults: `param: ~`
- [Providing module-defined local tasks](https://www.drupal.org/docs/drupal-apis/menu-api/providing-module-defined-local-tasks) — `base_route` for primary tabs, `parent_id` for secondary tabs
- [Config entity translations (getTranslation())](https://api.drupal.org/api/drupal/core!lib!Drupal!Core!Entity!EntityInterface.php/function/EntityInterface%3A%3AgetTranslation/11.x) — creating or loading a translation

### Drupal/Symfony Patterns

- [PHP fgetcsv()](https://www.php.net/manual/en/function.fgetcsv.php) — parsing CSV rows
- [PHP fputcsv()](https://www.php.net/manual/en/function.fputcsv.php) — generating CSV rows
- [Symfony StreamedResponse](https://symfony.com/doc/current/components/http_foundation.html#streaming-a-response) — streaming large CSV downloads

### Related Projects

- [Field Data module](https://www.drupal.org/project/field_data) — inspiration for the single-controller + `$type` route-default pattern and the Controller + Service architecture used here
- [xurizaemon/csvimport — CSVimportForm.php](https://github.com/xurizaemon/csvimport/blob/8.x-1.x/src/Form/CSVimportForm.php) — reference implementation for plain `#type => 'file'` CSV upload using `file_save_upload()` in an element validator

### Drupal.org Project Setup

- [GitLab CI — Using GitLab to contribute to Drupal](https://www.drupal.org/docs/develop/git/using-gitlab-to-contribute-to-drupal/gitlab-ci) — how to configure automated testing via `.gitlab-ci.yml` and the Drupal Association's maintained template
- [Project Browser — Module logo and compatibility](https://www.drupal.org/docs/extending-drupal/contributed-modules/contributed-module-documentation/project-browser/module-maintainers-how-to-update-projects-to-be-compatible-with-project-browser#s-logo) — `logo.png` specification and requirements for Project Browser display

