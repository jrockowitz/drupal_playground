# #3622305 production hook conversion checklist

This is the live production ledger. The [issue note](3622305.md) holds module-level status; the [Rector summary](3622305-oop-hook-rector-poc-inventory.md) is historical evidence only.

Reconcile current `*.module`, `*.inc`, `src/Hook`, callbacks, callers, and POC candidates before editing. Keep one row per runtime hook, including hooks implemented for another module and hooks skipped by Rector. Record the final class/method and existing behavior coverage or a concise gap. Do not add tests solely for the conversion; preserve assertions when an existing test must change.

Valid statuses: `Pending`, `Converted`, `Existing OOP verified`, `Ordering replaced`, `Removed (approved)`, and `Retained procedural (approved)`. Deletions and retained procedural hooks require maintainer approval and a reason. A module is complete only when every hook is resolved, coverage/gaps are recorded, the diff is reviewed, and its approved commit is logged in the issue note.

## Main-project module completion

Check a module only after the completion conditions above are met. Keep these
boxes consistent with the module-level ledger in the issue note. Experimental
modules are tracked in their separate linked work.

- [x] `schemadotorg`
- [x] `schemadotorg_allowed_formats`
- [x] `schemadotorg_address`
- [x] `schemadotorg_auto_entitylabel`
- [x] `schemadotorg_block_content`
- [x] `schemadotorg_content_moderation`
- [x] `schemadotorg_entity_reference_override`
- [x] `schemadotorg_existing_values_autocomplete_widget`
- [x] `schemadotorg_field_validation`
- [x] `schemadotorg_geolocation`
- [x] `schemadotorg_inline_entity_form`
- [x] `schemadotorg_jsonapi`
- [x] `schemadotorg_jsonld_breadcrumb`
- [x] `schemadotorg_jsonld_embed`
- [x] `schemadotorg_mapping_set`
- [x] `schemadotorg_office_hours`
- [x] `schemadotorg_physical`
- [ ] `schemadotorg_report`
- [ ] `schemadotorg_simple_sitemap`
- [ ] `schemadotorg_smart_date`
- [ ] `schemadotorg_content_model_documentation`
- [x] `schemadotorg_descriptions`
- [ ] `schemadotorg_diagram`
- [x] `schemadotorg_epp`
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
