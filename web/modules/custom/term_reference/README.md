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

## Developer notes

Routes are static and use a field parameter instead of generating one route per
field:

```text
/taxonomy/term/{taxonomy_term}/references
/taxonomy/term/{taxonomy_term}/references/{entity_type_id}.{field_name}
```

The primary `References` route redirects to the first accessible field-specific
task. The field-specific page title is `Add references to %term`.

Secondary local tasks are derived from discovered fields by
`TermReferenceLocalTasks`. A task is keyed by `{entity_type_id}.{field_name}`;
for example, `node.field_tags` can manage all eligible node bundles that have a
`field_tags` field targeting the current term vocabulary.

Important services:

- `term_reference.discovery` discovers eligible taxonomy term reference fields
  and caches results in `cache.discovery`.
- `term_reference.manager` loads, adds, and removes term references on content
  entities.
- `term_reference.access` handles route, field, and entity management access using term
  update access and target field edit access.

The add form uses Drupal core's `entity_autocomplete` element with
`#tags => TRUE`, `#validate_reference => TRUE`, and bundle-restricted selection
settings. Raw ID-only input and custom autocomplete routes are intentionally not
supported.

Add and remove submit buttons support Drupal AJAX and normal non-JavaScript form
submissions. AJAX responses replace the `term-reference-form-wrapper`, refresh
status messages, announce state changes, and move focus to the rebuilt
autocomplete field or existing references fieldset.

Useful verification commands:

```bash
ddev phpunit web/modules/custom/term_reference/tests
ddev phpcs web/modules/custom/term_reference
ddev phpstan web/modules/custom/term_reference
ddev drush cr
```

After rebuilding caches, smoke test a route such as:

```text
/taxonomy/term/1/references/node.field_tags
```

For deeper architecture notes, see the `docs/` directory. Avoid running
`ddev code-review` only against `docs/`; PHPStan expects PHP files and stylelint
will try to parse Markdown as CSS.

## Documentation

Design notes and the implementation plan are available in the `docs/` directory.

---

This module was created using AI and understood by humans. See [Never submit code you do not understand](https://dri.es/never-submit-code-you-do-not-understand).
