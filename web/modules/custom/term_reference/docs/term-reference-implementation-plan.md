# Term Reference Implementation Plan

## Goal

Build a contrib-ready Drupal module that adds a `References` tab to taxonomy
term pages and lets editors add or remove the current term from eligible
taxonomy term entity fields.

## Implemented Architecture

Term Reference discovers configurable entity fields that target
taxonomy terms, keys them by `{entity_type_id}.{field_name}`, and derives a
secondary local task for each field. Routes are static and parameterized so the
module does not need to generate one route per field.

The main route pattern is:

```text
/taxonomy/term/{taxonomy_term}/references/{entity_type_id}.{field_name}
```

The primary `References` tab redirects to the first accessible secondary task
for the current term vocabulary.

## Files

- `term_reference.info.yml`: module metadata.
- `term_reference.routing.yml`: primary and manage routes.
- `term_reference.links.task.yml`: primary task and secondary task deriver.
- `term_reference.services.yml`: autowired service definitions.
- `src/TermReferenceDiscoveryInterface.php`: discovery contract.
- `src/TermReferenceDiscovery.php`: field discovery.
- `src/TermReferenceManagerInterface.php`: reference query and mutation contract.
- `src/TermReferenceManager.php`: generic content entity reference operations.
- `src/Access/TermReferenceAccessCheck.php`: term and field access checks.
- `src/Controller/TermReferenceOverviewController.php`: primary tab redirect.
- `src/Form/TermReferenceForm.php`: add/remove form and table.
- `src/Hook/TermReferenceHooks.php`: local task cache invalidation after field config changes.
- `src/Plugin/Derivative/TermReferenceLocalTasks.php`: secondary local task generation.
- `tests/src/Functional/TermReferenceFormTest.php`: functional coverage.
- `.gitlab-ci.yml`, `composer.json`, `phpcs.xml.dist`, `phpstan.neon`, and `README.md`: Drupal.org project files.

## Access Model

The module intentionally does not define a custom permission.

Route access requires:

- Taxonomy term update access.
- A valid `{entity_type_id}.{field_name}` field for the term vocabulary.
- Field edit access for at least one eligible field instance, checked through
  the target entity type access handler with `fieldAccess('edit', ...)`.

Add and remove operations also require:

- Target entity update access.
- The configured field to exist on the target entity.
- The target entity bundle to be one of the eligible bundles for the field.
- Field edit access on the target entity field item list.

## Test Coverage

The functional test creates:

- A `tags` vocabulary.
- `page` and `article` node types.
- An `image` media type.
- `field_tags` on `node.page`, `node.article`, and `media.image`.

It verifies:

- Discovery returns `node.field_tags` and `media.field_tags`.
- The taxonomy term page renders the `References` primary task.
- Secondary tasks render as `Tags (Content)` and `Tags (Media)`.
- The content task can add Basic page and Article nodes.
- The media task can add Image media.
- The table shows label, ID, published state, and operations.
- Remove clears only the selected term reference.
- A limited user receives a 403 response.

## Verification

Focused functional test:

```bash
ddev phpunit web/modules/custom/term_reference/tests/src/Functional/TermReferenceFormTest.php
```

Full module lint and static analysis:

```bash
ddev code-review web/modules/custom/term_reference
```
