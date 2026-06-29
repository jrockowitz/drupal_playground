# Term Reference

Term Reference adds a **References** tab to taxonomy term pages. The tab lets
editors add or remove the current term from eligible taxonomy term entity
fields on content entities.

## Features

- **References primary tab** on taxonomy term pages.
- **Generated secondary tabs** per `{entity_type_id}.{field_name}` pair, such as
  `Tags (Content)` and `Tags (Media)`.
- **Cross-bundle management** for the same entity type and field name. For
  example, `node.field_tags` can manage Basic page and Article nodes from one
  `Tags (Content)` task.
- **Bundle-aware entity autocomplete** for adding one or more existing entities
  from the current field.
- **Generic entity table** with Label, ID, Published, and Operations columns.
- **Access-aware additions and removals** using entity update access and
  field edit access.

## Requirements

- Drupal 10 or Drupal 11.
- Core Taxonomy and Field modules.

## Installation

Install the module as usual for a Drupal contributed module:

```bash
composer require drupal/term_reference
drush en term_reference
```

## Usage

1. Add a taxonomy term entity field to a fieldable content entity type,
   such as content or media.
2. Configure the field instance to target one or more vocabularies.
3. Visit a taxonomy term page from one of those vocabularies.
4. Open the **References** tab.
5. Choose the secondary tab for the field, add existing entities through
   autocomplete, or remove existing references from the table.

## Access

Term Reference does not provide a module-specific permission. Access is granted
only when the current account can update the taxonomy term and edit at least one
eligible target field. Add and remove operations also check target entity update
access and field edit access before changing entities.

## Documentation

Design notes and the implementation plan are available in the `docs/` directory.

---

This module was created using AI and understood by humans. See [Never submit code you do not understand](https://dri.es/never-submit-code-you-do-not-understand).
