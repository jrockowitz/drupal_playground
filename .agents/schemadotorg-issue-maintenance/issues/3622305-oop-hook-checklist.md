# #3622305 production hook conversion checklist

Track the actual production conversion here, one row per runtime hook in the
active module. The [issue note](3622305.md) keeps the module-level status. The
[Rector POC inventory](3622305-oop-hook-rector-poc-inventory.md) is historical
research and a source of candidates, not proof that a hook is converted.

Before editing a module, reconcile its current `*.module`, `*.inc`, existing
`src/Hook` classes, and POC candidates. Include hooks implemented on behalf of
another module and hooks the POC skipped. Add rows for every runtime hook, then
record its final class/method and a test that exercises it through Drupal's hook
dispatch. Record a reason and covering functional test if kernel coverage is
not possible. Keep required procedural install/update/meta hooks and named
callbacks in a separate retained-function section for that module.

Use `Pending`, `Converted`, `Existing OOP verified`, `Ordering replaced`, or
`Retained procedural (approved)` as the status. Use the last status only for a
hook that must remain procedural, and explain it in the retained-function table.
A module is complete only when every hook row has a resolved status, behavior
coverage or a documented exception, the maintainer has reviewed its diff, and
the approved module commit is recorded. Update the module-level ledger in the
issue note at the same checkpoint.

## Main-project module completion

Check a module only after the completion conditions above are met. Keep these
boxes consistent with the module-level ledger in the issue note. Experimental
modules are tracked in their separate linked work.

- [ ] `schemadotorg`
- [ ] `schemadotorg_allowed_formats`
- [ ] `schemadotorg_address`
- [ ] `schemadotorg_auto_entitylabel`
- [ ] `schemadotorg_block_content`
- [ ] `schemadotorg_content_moderation`
- [ ] `schemadotorg_entity_reference_override`
- [ ] `schemadotorg_existing_values_autocomplete_widget`
- [ ] `schemadotorg_field_validation`
- [ ] `schemadotorg_geolocation`
- [ ] `schemadotorg_inline_entity_form`
- [ ] `schemadotorg_jsonapi`
- [ ] `schemadotorg_jsonld_breadcrumb`
- [ ] `schemadotorg_jsonld_embed`
- [ ] `schemadotorg_mapping_set`
- [ ] `schemadotorg_office_hours`
- [ ] `schemadotorg_physical`
- [ ] `schemadotorg_report`
- [ ] `schemadotorg_simple_sitemap`
- [ ] `schemadotorg_smart_date`
- [ ] `schemadotorg_content_model_documentation`
- [ ] `schemadotorg_descriptions`
- [ ] `schemadotorg_diagram`
- [ ] `schemadotorg_epp`
- [ ] `schemadotorg_field_prefix`
- [ ] `schemadotorg_help`
- [ ] `schemadotorg_jsonapi_preview`
- [ ] `schemadotorg_jsonld_endpoint`
- [ ] `schemadotorg_jsonld_preview`
- [ ] `schemadotorg_mercury_editor`
- [ ] `schemadotorg_metatag`
- [ ] `schemadotorg_node`
- [ ] `schemadotorg_options`
- [ ] `schemadotorg_recipe`
- [ ] `schemadotorg_translation`
- [ ] `schemadotorg_type_tray`
- [ ] `schemadotorg_ui`
- [ ] `schemadotorg_additional_mappings`
- [ ] `schemadotorg_additional_type`
- [ ] `schemadotorg_cer`
- [ ] `schemadotorg_custom_field`
- [ ] `schemadotorg_field_group`
- [ ] `schemadotorg_jsonld`
- [ ] `schemadotorg_jsonld_custom`
- [ ] `schemadotorg_layout_paragraphs`
- [ ] `schemadotorg_media`
- [ ] `schemadotorg_paragraphs`
- [ ] `schemadotorg_pathauto`
- [ ] `schemadotorg_role`
- [ ] `schemadotorg_scheduler`
- [ ] `schemadotorg_taxonomy`
- [ ] `schemadotorg_export` — confirm the no-runtime-hook finding before marking complete.

## Runtime hooks

Populate the base `schemadotorg` rows before its implementation, then populate
each submodule before its implementation. Do not copy generated class names or
recommendations from the POC without checking current source and callers.

| Module | Source hook/function | Status | Final hook class/method | Behavior test or exception | Commit/notes |
|---|---|---|---|---|---|

## Retained procedural functions and callbacks

| Module | Function | Reason retained | Test or verification | Commit/notes |
|---|---|---|---|---|
