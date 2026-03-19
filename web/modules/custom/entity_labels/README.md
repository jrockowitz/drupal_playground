# Entity Labels

A Drupal 10/11 module that provides a **Reports** page listing entity type and
bundle label metadata (label, description, help) and field label metadata (label,
description, allowed values) — with CSV export and CSV import for bulk updates.

## Features

- **Entities tab** — browse all entity types that support bundles; drill down by
  entity type; view label, description, and help text.
- **Fields tab** — browse all fields across all bundles; drill down by entity type
  and bundle; view label, description, field type, and allowed values. On the bundle
  view, fields are ordered to match the default form display.
- **CSV export** — scoped to the current view (all, entity type, or bundle). Every
  export includes a `notes` column and a `langcode` column to target translations.
- **CSV import** — upload a modified CSV to bulk-update labels and descriptions.
  `allowed_values` and `field_type` are display-only and never imported.
  Only FieldConfig-based (non-base) fields can be updated; base fields
  such as `title` are skipped.
- **Multilingual** — uses Drupal's core translation detection; import targets the
  language specified in the `langcode` column.

## Optional Module Support

When the following contrib modules are installed, the Fields tab, export, and import are automatically extended:

- **[Field Group](https://www.drupal.org/project/field_group)** (`drupal/field_group`, any version) — Groups from the default form mode appear as rows
  with `field_type = field_group`; group label and description are exportable and importable.
- **[Custom Field](https://www.drupal.org/project/custom_field)** (`drupal/custom_field`, 4.x only) — Each column within a `custom_field` field gets its own row
  with a `field_column` identifier; column label and description are exportable and importable.

## Permissions

| Permission | Purpose |
|---|---|
| `access site reports` | View reports and download CSV exports |
| `administer site configuration` | Upload CSV imports |

## Usage

1. Navigate to **Administration → Reports → Entity labels**.
2. Use the **Entities** and **Fields** primary tabs to browse metadata.
3. Click an entity type cell to filter by type. On the Fields tab, click a bundle cell to drill into that bundle.
4. Click **⇩ Download CSV** to export the current view.
5. Edit the CSV, then use the **Import** secondary tab to upload and apply changes.

## TODO

### Drush support

- Export entity/field labels to CSV via Drush command.
- Import a CSV file via Drush command (non-interactive bulk updates).

### Multilingual

- Config translation support: import targets the language in the `langcode` column, but config entity
  translation (i.e. writing to config translation overrides rather than the base config) is not yet implemented.
- UI language switcher for the report pages.
