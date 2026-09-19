AI-assisted by Codex

# #3622305 Rector POC summary

This file is a condensed local summary of the disposable Drupal Rector proof of concept for [issue #3622305](https://www.drupal.org/project/schemadotorg/issues/3622305). The complete row-by-row inventory remains in the [published attachment](https://www.drupal.org/files/issues/2026-09-10/3622305-oop-hook-rector-poc-inventory.md). Use this file for context; use the production [checklist](3622305-oop-hook-checklist.md) as the live source of truth.

> Caller counts were lexical review leads, not proof that a service or interface was private or safe to remove.

## Conversion rules

- Convert one production module at a time; review its complete diff and focused tests before the next.
- Inventory hooks, callbacks, helpers, services, interfaces, callers, ordering, and required procedural functions.
- Use cohesive `#[Hook]` classes, constructor injection, and no new static `\Drupal` calls.
- Remove converted wrappers only after behavior/order review; preserve public services and interfaces unless separately approved.
- Record coverage gaps instead of adding tests solely for the conversion.
- Rebuild caches/container, run targeted PHPUnit and code review, run `git diff --check`, then update the checklist and issue ledger.

## Scope and sequence

- Main project: 51 submodules plus the base module. `schemadotorg_allowed_formats` is the pilot; `schemadotorg_export` remains an assessment candidate; the base module is last because of ordering and focal-point behavior.
- Experimental project: 8 modules, deferred until the main-project convention is established and tracked separately.
- The production checklist records module status and final hook destinations; this summary does not duplicate that ledger.

## POC execution and results

The maintainer authorized a raw, uncommitted conversion on 2026-09-10 using the standalone upstream HookConvertRector configuration. The project `rector.php` was not changed, and generated output was discarded after evidence capture.

- Baseline: PHPUnit 280 tests / 4,778 assertions; PHPCS passed; PHPStan 130 existing errors; CSpell and ESLint passed; Stylelint had 2,464 existing errors.
- Rector: 61 changed input files, 58 rule applications, no Rector errors; 111 modified tracked files plus 62 untracked files; 236 `#[Hook]` methods and matching `#[LegacyHook]` wrappers; 189 static `\Drupal` lookups.
- Other output: API definitions were not converted but imports were normalized; container/cache rebuild passed.
- Verification: PHPUnit completed with one deterministic focal-point ordering failure (`image_image` instead of `image_focal_point`); focused Allowed Formats checks passed. PHPCS reported 4,194 errors/429 warnings; PHPStan 328 errors; CSpell and ESLint passed; Stylelint reported 2,517 errors; `git diff --check` passed.
- Closeout: caches rebuilt; the focal-point sentinel passed with 1 test/33 assertions; Allowed Formats passed with 2 tests/32 assertions; Experimental stayed unchanged and clean.

## Manual follow-up

### Ordering and skipped runtime behavior

- Focal-point hook ordering must remain explicit and covered; the sentinel is the known regression risk.
- Both `schemadotorg_module_implements_alter()` and `schemadotorg_node_module_implements_alter()` need manual ordering replacement.
- `schemadotorg_translation_entity_insert()` needs manual hook conversion; its docblock names the wrong hook.
- `schemadotorg_ui_schemadotorg_mapping_type_delete()` needs manual entity-type hook conversion and calls a converted legacy wrapper.
- `schemadotorg_help_preprocess_help_section()` needs manual preprocess-hook conversion.

### Callbacks and helpers left outside Rector discovery

These remain separate from runtime hook conversion and require classification as callback refactors or procedural-only helpers:

`schemadotorg_additional_type_settings_form_submit()`, `schemadotorg_content_model_documentation_form_schemadotorg_types_settings_form_submit()`, `schemadotorg_descriptions_general_settings_submit()`, `schemadotorg_diagram_settings_form_submit()`, `schemadotorg_epp_schemadotorg_properties_settings_submit()`, `schemadotorg_field_group_form_schemadotorg_properties_settings_form_submit()`, `schemadotorg_field_prefix_form_field_ui_field_storage_add_ajax_callback()`, `schemadotorg_field_prefix_form_field_ui_field_storage_add_form_validate()`, `schemadotorg_help_form_after_build()`, `schemadotorg_jsonapi_preview_settings_form_submit()`, `schemadotorg_jsonld_endpoint_settings_form_submit()`, `schemadotorg_jsonld_preview_settings_form_submit()`, `schemadotorg_mercury_editor_form_layout_paragraphs_component_form_after_build()`, `schemadotorg_metatag_form_schemadotorg_properties_settings_form_submit()`, `schemadotorg_options_allowed_values_country()`, `schemadotorg_options_allowed_values_language()`, `schemadotorg_recipe_is_applying()`, `schemadotorg_translation_form_schemadotorg_types_settings_form_submit()`, and `schemadotorg_type_tray_settings_form_submit()`.

### Procedural install/update inventory

Rector left 112 install, uninstall, requirements, schema, and update functions procedural. Keep them procedural unless Drupal provides an approved equivalent; the production checklist tracks module-specific retained functions.

## Service/API review rule

The generated inventory found many apparent pass-through services and lexical callers. Treat those as review leads only: compare callers and service implementations before removing any service or interface. Preserve reusable services, builders, managers, and public APIs; move logic into hook classes only when it is hook-specific and separately approved.

## Public research

- Issue: [#3622305](https://www.drupal.org/project/schemadotorg/issues/3622305)
- Complete public inventory: [3622305-oop-hook-rector-poc-inventory.md](https://www.drupal.org/files/issues/2026-09-10/3622305-oop-hook-rector-poc-inventory.md)
- Public comment #2: [comment 16766652](https://www.drupal.org/project/schemadotorg/issues/3622305#comment-16766652)
