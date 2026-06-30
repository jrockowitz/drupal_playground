# Term Reference Module Guide

## Purpose

`term_reference` adds a `References` local task to taxonomy term pages. Editors
use the tab to add or remove the current taxonomy term from content entities
that have eligible taxonomy term entity reference fields.

## Architecture

- One route handles the UI: `/taxonomy/term/{taxonomy_term}/references/{field}`.
- The `{field}` parameter is optional. When present, it is
  `{entity_type_id}.{field_name}`, such as `node.field_tags` or
  `media.field_tags`.
- `Form\TermReferenceForm` owns the route title, route access callback, field
  chooser, add/remove form, existing references table, and AJAX refresh.
- `term_reference.discovery` discovers configurable taxonomy term reference
  fields, groups them by `{entity_type_id}.{field_name}`, and caches results in
  `cache.discovery`.
- `term_reference.manager` loads referencing entities, adds references, removes
  references, and owns reusable reference-management access checks.
- `Hook\TermReferenceHooks::fieldConfigClearCache()` clears discovery cache when
  field config is inserted, updated, or deleted.

## Services

- `term_reference.discovery` discovers eligible taxonomy term reference fields,
  groups them by `{entity_type_id}.{field_name}`, stores bundle labels and bundle
  entity type labels, and caches results in `cache.discovery`.
- `term_reference.manager` loads, adds, and removes term references on content
  entities. It also exposes `accessReference()` and `entityCanBeManaged()` for
  shared reference-management access checks.
- `TermReferenceForm` owns the single UI route and delegates reusable discovery
  and access decisions to those services.

## UI Flow

- `/taxonomy/term/{taxonomy_term}/references` opens the management form directly
  when the current account can manage one discovered field for the term.
- The same route renders an `admin_block_content` chooser when the account can
  manage more than one field for the term.
- Chooser links point back to the same route with the selected optional field
  parameter.
- Direct field URLs such as `/taxonomy/term/1/references/node.field_tags` render
  the management form for that field.

## Form Behavior

- The add field uses Drupal core's `entity_autocomplete` element with
  `#tags => TRUE`, `#validate_reference => TRUE`, and bundle-restricted
  `#selection_settings`.
- The existing references fieldset renders the table, remove controls, and
  operations links.
- Add and remove submissions work with and without JavaScript.
- AJAX responses replace `term-reference-form-wrapper`, refresh status
  messages, announce changes, and move focus to the autocomplete field or
  existing references fieldset.

## Access Model

- Do not add a module-specific permission unless term, entity, and field access
  cannot express a required policy.
- Route access requires update access to the taxonomy term and edit access to at
  least one eligible target field definition.
- Add and remove operations also require target entity update access, eligible
  bundle membership, field existence, and target entity field edit access.

## When Editing This Module

- Keep the module generic across fieldable content entity types.
- Do not assume nodes, media, or a specific bundle unless a test fixture is
  intentionally scoped to that entity type.
- Do not add controllers, secondary local task derivers, custom autocomplete
  routes, or raw ID input.
- Keep add and remove behavior working with and without JavaScript.
- Update tests when changing route behavior, discovery metadata, access checks,
  AJAX behavior, or existing references table columns.
- Keep public documentation focused on the current design. Avoid documenting
  earlier implementation approaches in `README.md` or `docs/`.

## Verification

```bash
ddev phpunit web/modules/custom/term_reference/tests
ddev phpcs web/modules/custom/term_reference
ddev phpstan web/modules/custom/term_reference
ddev drush cr
```

Smoke test these routes after cache rebuilds:

```text
/taxonomy/term/1/references
/taxonomy/term/1/references/node.field_tags
```
