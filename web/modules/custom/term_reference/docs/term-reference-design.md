# Term Reference Design

## Goal

Term Reference (`term_reference`, `Drupal\term_reference`) is a contrib-ready
Drupal module that lets editors manage entities referencing a taxonomy term from
a dedicated `References` tab on the term page.

The module is intentionally generic. It works with fieldable content entity
types that have configurable entity reference fields targeting taxonomy terms.

## Existing Module Research

Existing contrib modules cover adjacent needs but do not provide this exact
workflow.

- Entity Usage tracks relationships between entities.
- Entity Reference Manager analyzes and replaces entity references.
- Corresponding Entity References synchronizes paired fields.
- EVA displays reverse-reference views attached to entities.
- The `term_reference` project path on Drupal.org currently returns 404.

The proposed module is a new workflow: from a taxonomy term page, add or remove
the term on eligible entity fields across content entities.

## Navigation Model

The module adds one primary local task named `References` to taxonomy term
pages. The route has an optional field parameter:

```text
/taxonomy/term/{taxonomy_term}/references/{field}
```

The `field` value is `{entity_type_id}.{field_name}`, such as
`node.field_tags` or `media.field_tags`.

When the field parameter is omitted, `TermReferenceForm` filters discovered
fields to those the current account can manage for the term:

- Zero accessible fields: the route is not accessible.
- One accessible field: the form opens directly.
- Multiple accessible fields: the page renders an `admin_block_content` chooser
  with links back to the same route and the selected field parameter.

This keeps the common single-field workflow direct while avoiding generated
secondary local tasks for the less common multi-field case.

## Discovery

Discovery scans fieldable content entity types and groups configurable
`entity_reference` field instances targeting taxonomy terms by
`{entity_type_id}.{field_name}`.

A field instance is eligible for a taxonomy term when its reference handler
configuration allows the term's vocabulary. Discovery returns:

- Field ID, entity type ID, and entity type label.
- Field name and display label.
- Target vocabulary ID.
- Eligible bundle IDs and labels.

Discovery is exposed through `term_reference.discovery` and
`Drupal\term_reference\TermReferenceDiscoveryInterface`. Results are cached in
Drupal's `cache.discovery` bin with the `term_reference:fields` cache tag.
`TermReferenceHooks::fieldConfigClearCache()` clears discovery when field config
is inserted, updated, or deleted. `getFieldsForVocabulary()` accepts an optional
account argument to filter fields to bundles and field definitions that account
can edit.

## Services

The module defines two autowired services and aliases their interfaces:

- `term_reference.discovery`:
  `Drupal\term_reference\TermReferenceDiscoveryInterface`.
- `term_reference.manager`:
  `Drupal\term_reference\TermReferenceManagerInterface`.

The form owns the single UI route, while discovery filters field choices and the
manager owns reusable reference access and entity management checks.

## Form

`Drupal\term_reference\Form\TermReferenceForm` handles title, route access,
field selection, the multi-field chooser, add/remove submissions, and AJAX
refreshes.

The add fieldset provides a multi-value `entity_autocomplete` element named
`entities`. It is restricted to the discovered entity type and eligible bundles
for the selected field:

- `#target_type` is the discovered entity type ID.
- `#tags` is `TRUE` so multiple entities can be selected.
- `#selection_handler` is `default`.
- `#selection_settings['target_bundles']` is the eligible bundle list.
- `#validate_reference` is `TRUE`.

The existing references fieldset includes a table with Label, ID, Bundle,
Published, and Operations columns. Rows can be selected and removed. Removing
clears only the current term from the configured field and leaves other
referenced terms intact.

The form supports normal form submissions and Drupal AJAX submissions. AJAX
responses replace the form wrapper, refresh in-form status messages, use
`AnnounceCommand` for screen-reader feedback, and move focus to the rebuilt
autocomplete field or existing references fieldset.

## Access

Access relies on Drupal entity and field access instead of a module-specific
permission.

The route is available only when at least one field can be managed for the
current term. For a selected field, access requires:

- The route's entity type and field name are valid for the current term's
  vocabulary.
- The account can update the taxonomy term.
- The account has edit access to at least one eligible field definition, checked
  through the target entity type access handler with
  `fieldAccess('edit', ...)`.

Add and remove operations also check target entity update access, bundle
eligibility, field existence, and target entity field edit access before
changing entities.

## Test Coverage

Functional tests verify:

- The taxonomy term page has a `References` primary task.
- Multiple accessible fields render chooser links such as `Tags (Content)` and
  `Tags (Media)`.
- A term with one accessible field opens the management form directly from the
  primary route.
- The content field can add both Basic page and Article nodes through
  `field_tags`.
- The media field can add Image media through `field_tags`.
- The table displays label, ID, bundle, published state, and operations.
- Removing a reference clears only the selected term from the configured field.
- Users without sufficient update or field access cannot manage references.
- Non-JavaScript submissions continue to work.
- AJAX add and remove submissions refresh the form, update visible messages, and
  preserve focus.

Kernel tests cover discovery, manager, and hook contracts. Kernel tests share
setup through `TermReferenceKernelBase`.

## Generic Contrib Scope

The module remains generic and does not preserve site-specific behavior from
any originating implementation. Avoid these assumptions:

- Node-only discovery and mutations.
- First-name, last-name, computed-name, and parent-title sorting/display.
- `administer nodes` checks.
- NodeOrder integration.
- Custom helper classes and procedural query helpers from a source site.

Use generic content entity APIs, `$entity->label()`, entity operations links,
and entity/field access checks instead.
