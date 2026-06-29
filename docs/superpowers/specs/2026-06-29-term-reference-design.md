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
- Corresponding Entity References synchronizes paired reference fields.
- EVA displays reverse-reference views attached to entities.
- The `term_reference` project path on Drupal.org currently returns 404.

The proposed module is a new workflow: from a taxonomy term page, add or remove
the term on eligible entity reference fields across content entities.

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
/taxonomy/term/{taxonomy_term}/references/{entity_type_id}/{field_name}
```

Route names use this pattern:

```text
term_reference.references.{entity_type_id}.{field_name}
```

The route arguments identify the taxonomy term, content entity type, and field
name. The form discovers eligible bundles for the current term's vocabulary at
runtime.

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
- The grouped field config definitions needed for validation and access checks.

## Form

The management form is shown for one taxonomy term, entity type, and field name.

The form includes a summary details element with:

- Entity type.
- Field label and machine name.
- Target vocabulary.
- Eligible bundles.

The add section provides an entity autocomplete field restricted to eligible
bundles for the chosen entity type and field. Submitting `Add` appends the
taxonomy term to the configured field when the entity does not already reference
it.

The existing references table includes:

- Label.
- ID.
- Published.
- Operations.

Rows can be selected and removed. Removing clears only the current term from the
configured field and leaves other referenced terms intact.

## Access

Access should rely primarily on Drupal entity and field access.

The route is available only when:

- The route's entity type and field name are valid for the current term's
  vocabulary.
- The account can update at least one eligible entity/field path or otherwise
  has a narrowly defined administrative permission if route visibility proves
  too expensive through entity access alone.

The form must check entity update access and field edit access before adding or
removing the term. The autocomplete must avoid suggesting entities the current
user cannot manage.

## Test Module And Coverage

A test module should create a `tags` vocabulary and add `field_tags` to:

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

## Removed MSK-Specific Behavior

The contrib module should not preserve MSK-specific behavior from
`MskTaxonomyNodeManageForm`.

Remove these assumptions:

- Node-only discovery and mutations.
- First-name, last-name, computed-name, and parent-title sorting/display.
- `administer nodes` checks.
- NodeOrder integration.
- MSK helper classes and procedural query helpers.

Use generic content entity APIs, `$entity->label()`, entity operations links,
and entity/field access checks instead.
