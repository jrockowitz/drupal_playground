# Term Reference

Term Reference adds a **References** tab to taxonomy term pages. The tab lets
editors add or remove the current term from eligible taxonomy term entity
fields on content entities.

## Use case

Term Reference is useful when a site builder or editor is given a list of
content items to add to a taxonomy term. Instead of opening and editing each
content item individually, they can open the taxonomy term, use the
**References** tab, and add all matching content items from one place.

## Features

- **References primary tab** on taxonomy term pages.
- **Single-field shortcut** that opens the add/remove form directly when only
  one accessible field references the term vocabulary.
- **Multi-field chooser** using Drupal admin block links when more than one
  accessible field references the term vocabulary.
- **Cross-bundle management** for the same entity type and field name. For
  example, `node.field_tags` can manage Basic page and Article nodes from one
  Content field page.
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
5. If one field is available, add existing entities through autocomplete or
   remove existing references from the table.
6. If multiple fields are available, choose the field link first, then add or
   remove references.

## Access

Term Reference does not provide a module-specific permission. Access is granted
only when the current account can update the taxonomy term and edit at least one
eligible target field. Add and remove operations also check target entity update
access and field edit access before changing entities.

## Developer notes

Term Reference uses one route with an optional field parameter:

```text
/taxonomy/term/{taxonomy_term}/references/{field}
```

The `field` parameter is optional, so both URLs are valid:

```text
/taxonomy/term/1/references
/taxonomy/term/1/references/node.field_tags
```

When no field parameter is present, `TermReferenceForm` filters discovered
fields by access. One accessible field opens the management form directly.
Multiple accessible fields render an `admin_block_content` chooser. Each
chooser link points back to the same route with the selected
`{entity_type_id}.{field_name}` value.

Important services:

- `term_reference.discovery` discovers eligible taxonomy term reference fields
  and caches results in `cache.discovery`.
- `term_reference.manager` loads, adds, and removes term references on content
  entities. It also exposes the shared reference access checks used by the form.

The form owns the single UI route and delegates reusable access decisions to
discovery and manager services. The module does not provide a custom permission.

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
/taxonomy/term/1/references
/taxonomy/term/1/references/node.field_tags
```

For deeper architecture notes, see the `docs/` directory. Avoid running
`ddev code-review` only against `docs/`; PHPStan expects PHP files and stylelint
will try to parse Markdown as CSS.

## Documentation

Design notes and the implementation plan are available in the `docs/` directory.

---

This module was created using AI and understood by humans. See [Never submit code you do not understand](https://dri.es/never-submit-code-you-do-not-understand).
