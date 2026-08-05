# Improve Schema.org Blueprints JavaScript test coverage

## Summary

Use real production pages for browser tests whenever they already provide the needed interaction. Keep test fixtures exceptional: retain `schemadotorg_mermaid_test`; add a submodule fixture only if a stable production-page scenario is unavailable, at `/schemadotorg_<module_name>/test/javascript/{library_name}`.

## JavaScript coverage audit and baseline

| Module | JavaScript file | Obvious route/path using it | Current coverage and baseline |
|---|---|---|---|
| `schemadotorg` | `schemadotorg.autocomplete.js` | `/schemadotorg-autocomplete-element-test` | Existing test form but no browser coverage; test suggestion selection and action/dialog navigation. |
| `schemadotorg` | `schemadotorg.codemirror.js` | `/admin/config/schemadotorg/settings/properties` | No browser coverage; test real textarea initialization and highlighted example rendering. |
| `schemadotorg` | `schemadotorg.details.js` | `/admin/config/schemadotorg/settings/*` | No browser coverage; test persisted state, hash targets, and expand/collapse control. |
| `schemadotorg` | `schemadotorg.dialog.js` | Settings forms with Schema.org report links, including `/admin/config/schemadotorg/settings/properties` | No browser coverage; test eligible report links open a modal and other URLs navigate normally. |
| `schemadotorg` | `schemadotorg.form.js` | Recipe and mapping-set confirmation forms | No behavior coverage; extend recipe browser coverage to assert one-time submission. |
| `schemadotorg` | `schemadotorg.jstree.js` | `/admin/config/schemadotorg/mappings/add` | No browser coverage; test tree initialization, expand/collapse, and activated link behavior. |
| `schemadotorg` | `schemadotorg.mermaid.js` | Schema.org help and relationship-report diagrams | Existing dedicated fixture and browser test; retain rendering, Panzoom, responsive, idempotency, and SVG-download assertions. |
| `schemadotorg` | `schemadotorg.settings.element.js` | `/admin/config/schemadotorg/settings/properties` | No browser coverage; test example disclosure by mouse and keyboard, including `aria-expanded`. |
| `schemadotorg_content_model_documentation` | `schemadotorg_content_model_documentation.dialog.js` | Content-model document modal pages reached from documented node forms, e.g. `/node/add/place` | Server-side markup coverage only; add browser coverage for nested documentation links opening in a modal. |
| `schemadotorg_field_prefix` | `schemadotorg_field_prefix.js` | None found; the library is declared but not attached | Resolve whether to attach this legacy behavior to `/admin/structure/types/manage/{type}/fields/add-field` or remove it, then test that decision. |
| `schemadotorg_jsonld_preview` | `schemadotorg_jsonld_preview.js` | `/node/{node}/schemadotorg-jsonld` | Functional setup already creates this page; add browser coverage for clipboard payload, visible feedback, and announcement. |
| `schemadotorg_ui` | `schemadotorg_ui.js` | `/admin/config/schemadotorg/mappings/add` | Functional setup already creates this page; add browser coverage for property filtering, mapped/unmapped persistence, status classes, and summaries. |

## Implementation changes

- Do not create a shared JavaScript fixture module.
- Reuse production-page setup in each owning module’s FunctionalJavascript tests.
- Keep Mermaid’s isolated fixture module; add dedicated submodule fixture modules only where production state is impractical.
- Test Schema.org behavior and DOM outcomes, not third-party library internals.
- Resolve the unused Field Prefix library before committing browser-test coverage.

## Verification and ticket

- Run each new FunctionalJavascript class with `ddev phpunit`, then `ddev code-review` and `git diff --check`.
- Create a Drupal.org **Task** titled **“Improve Schema.org Blueprints JavaScript test coverage”**, link [#3279761](https://www.drupal.org/project/schemadotorg/issues/3279761), and include this matrix plus the Field Prefix attachment decision.

## Assumptions

- Real production routes are preferred over fixtures.
- Dedicated submodule test routes use `/schemadotorg_<module_name>/test/javascript/{library_name}`.
- Baseline coverage targets critical interactions, not exhaustive browser-specific or third-party branches.
