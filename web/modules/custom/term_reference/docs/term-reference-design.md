# Term Reference Design

## Goal

Create a contrib-ready Drupal module named Term Reference (`term_reference`,
`Drupal\term_reference`) that lets editors manage entities referencing a
taxonomy term from a dedicated `References` tab on the term page.

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

## Drupal.org Project Files

The module should be ready for contribution on Drupal.org. Include the standard
project files at the module root:

- `.gitlab-ci.yml`.
- `composer.json`.
- `phpcs.xml.dist`.
- `phpstan.neon`.
- `README.md`.

After implementation, keep this design document and the implementation plan in a
`docs/` directory inside the module so future contributors can understand the
feature goals and build sequence.

## Navigation Model

The module adds a primary local task named `References` to taxonomy term pages.
Secondary local tasks are derived per `{entity_type_id}.{field_name}` pair.

For example, a `field_tags` field on content and media produces:

```text
References
- Tags (Content)
- Tags (Media)
```

The secondary task identity is not bundle-specific. A `node.field_tags` task can
manage all node bundles whose `field_tags` field instance targets the current
term's vocabulary, such as Basic page and Article.

## Route Model

Routes use this pattern:

```text
/taxonomy/term/{taxonomy_term}/references/{entity_type_id}.{field_name}
```

Route names are static and parameterized:

```text
term_reference.references
term_reference.reference
```

The route arguments identify the taxonomy term, content entity type, and field
name. The form discovers eligible bundles for the current term's vocabulary at
runtime. Static routes avoid route rebuilds for every field while the local
task deriver still creates one secondary tab per `{entity_type_id}.{field_name}`.

## Discovery

Discovery scans fieldable content entity types and groups configurable
`entity_reference` field instances targeting taxonomy terms by
`{entity_type_id}.{field_name}`.

A field instance is eligible for a taxonomy term when its reference handler
configuration allows the term's vocabulary. The first discovered field instance
label is used for the secondary task label, paired with the entity type plural
label, such as `Tags (Content)`.

Discovery returns:

- Entity type ID and plural label.
- Field name and display label.
- Target vocabulary ID.
- Eligible bundle IDs and labels.
- The field config definitions needed for validation and access checks.

## Form

The management form is shown for one taxonomy term, entity type, and field name.

The add section provides a multi-value entity autocomplete field restricted to
eligible bundles for the chosen entity type and field. The autocomplete
description lists the eligible bundles. Submitting `Add` appends the taxonomy
term to the configured field on each selected entity when the entity does not
already reference it.

The existing references fieldset includes a table with:

- Label.
- ID.
- Bundle.
- Published.
- Operations.

Rows can be selected and removed. Removing clears only the current term from the
configured field and leaves other referenced terms intact.

## Access

Access should rely on Drupal entity and field access instead of a module-specific
permission.

The route is available only when:

- The route's entity type and field name are valid for the current term's
  vocabulary.
- The account can update the taxonomy term.
- The account has edit access to at least one eligible field definition, checked
  through the target entity type access handler with
  `EntityAccessControlHandlerInterface::fieldAccess('edit', ...)`.

`fieldAccess()` does not grant entity access by itself. The form must also check
target entity update access and field edit access before adding or removing the
term.

## Test Coverage

Functional test coverage creates a `tags` vocabulary and adds `field_tags` to:

- `node.page`.
- `node.article`.
- `media.image`.

Functional tests should verify:

- The taxonomy term page has a `References` primary task.
- Secondary tasks include `Tags (Content)` and `Tags (Media)`.
- The content task can add both Basic page and Article nodes through
  `field_tags`.
- The media task can add Image media through `field_tags`.
- The table displays label, ID, published state, and operations.
- Removing a reference clears only the selected term from the configured field.
- Users without sufficient update or field access cannot add or remove
  references.

## Generic Contrib Scope

The module should remain generic and should not preserve site-specific behavior
from any originating implementation. Avoid these assumptions:

- Node-only discovery and mutations.
- First-name, last-name, computed-name, and parent-title sorting/display.
- `administer nodes` checks.
- NodeOrder integration.
- Custom helper classes and procedural query helpers from a source site.

Use generic content entity APIs, `$entity->label()`, entity operations links,
and entity/field access checks instead.
