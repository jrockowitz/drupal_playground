# #3622305 production hook conversion checklist

Track the actual production conversion here, one row per runtime hook in the
active module. The [issue note](3622305.md) keeps the module-level status. The
[Rector POC inventory](3622305-oop-hook-rector-poc-inventory.md) is historical
research and a source of candidates, not proof that a hook is converted.

Before editing a module, reconcile its current `*.module`, `*.inc`, existing
`src/Hook` classes, and POC candidates. Include hooks implemented on behalf of
another module and hooks the POC skipped. Add rows for every runtime hook, then
record its final class/method and any existing behavior test that covers it.
Record a coverage gap where no existing test establishes the behavior. Do not
add, move, or expand tests solely for this conversion; update an existing test
only when a moved hook breaks it, preserving its assertions. Keep required
procedural install/update/meta hooks and named callbacks in a separate
retained-function section for that module.

Use `Pending`, `Converted`, `Existing OOP verified`, `Ordering replaced`,
`Removed (approved)`, or `Retained procedural (approved)` as the status. Use
`Removed (approved)` only when the maintainer explicitly approves deleting an
obsolete hook, and record the reason. Use `Retained procedural (approved)` only
for a hook that must remain procedural, and explain it in the retained-function
table.
A module is complete only when every hook row has a resolved status and its
existing behavior coverage or gap is documented, the maintainer has reviewed
its diff, and the approved module commit is recorded. Update the module-level
ledger in the issue note at the same checkpoint.

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

Base module conversion committed as `7f98d583` on `3622305-oop-hooks` and
pushed in draft MR !321; focused tests passed. The complete suite was stopped
at the maintainer's request and remains a verification item before marking
the MR ready for review.

| Module | Source hook/function | Status | Final hook class/method | Existing behavior coverage or gap | Commit/notes |
|---|---|---|---|---|---|
| `schemadotorg` | `help` | Converted | `SchemaDotOrgHelpHooks::help()` | Gap: no existing help-output assertion. | Base module; `7f98d583`. |
| `schemadotorg` | `system_info_alter` | Removed (approved) | — | Gap: no existing demo dependency assertion. | Maintainer confirmed `schemadotorg_demo` is deprecated and unsupported; this hook only modified that module's dependencies. |
| `schemadotorg` | `module_implements_alter` | Ordering replaced | `SchemaDotOrgFormHooks::formAlter()` uses `Order::Last`; procedural meta hook removed. | `SchemaDotOrgFocalPointKernelTest` retains the widget-order sentinel; no existing direct form order assertion. | Meta hook is not an OOP implementation. |
| `schemadotorg` | `page_attachments` | Converted | `SchemaDotOrgPageHooks::pageAttachments()` | `SchemaDotOrgDetailsJavaScriptTest` exercises attached behavior; no direct request-method assertion. | Base module; `7f98d583`. |
| `schemadotorg` | `form_alter` | Converted | `SchemaDotOrgFormHooks::formAlter()` | `SchemaDotOrgSettingsFormTest`. | Ordered last. |
| `schemadotorg` | `form_system_modules_alter` | Converted | `SchemaDotOrgFormHooks::formSystemModulesAlter()` | `SchemaDotOrgSystemModulesTest`. | Base module; `7f98d583`. |
| `schemadotorg` | `field_config_delete` | Converted | `SchemaDotOrgFieldHooks::fieldConfigDelete()` | `SchemaDotOrgEntityKernelTest::testDependencies()`. | Base module; `7f98d583`. |
| `schemadotorg` | `schemadotorg_mapping_insert` | Converted | `SchemaDotOrgHooks::mappingInsert()` | `SchemaDotOrgReferenceSelectionKernelTest` exercises mapping creation and target bundles. | Public selection helper retained. |
| `schemadotorg` | `field_config_presave` | Converted | `SchemaDotOrgFieldHooks::fieldConfigPresave()` | `SchemaDotOrgReferenceSelectionKernelTest` exercises saved reference fields indirectly. | Public selection helper retained. |
| `schemadotorg` | `entity_form_display_presave` | Converted | `SchemaDotOrgEntityHooks::entityFormDisplayPresave()` | `SchemaDotOrgEntityDisplayBuilderKernelTest` covers component weights; mapping creation saves form displays. | Builder service and interface retained. |
| `schemadotorg` | `entity_view_display_presave` | Converted | `SchemaDotOrgEntityHooks::entityViewDisplayPresave()` | `SchemaDotOrgEntityDisplayBuilderKernelTest` covers view-display behavior; mapping creation saves displays. | Builder service and interface retained. |
| `schemadotorg` | `form_entity_form_display_edit_form_alter` | Converted | `SchemaDotOrgFormHooks::formEntityFormDisplayEditFormAlter()` | `SchemaDotOrgMappingTypeFormTest` checks the displayed weight warning. | Builder service and interface retained. |
| `schemadotorg` | `form_entity_view_display_edit_form_alter` | Converted | `SchemaDotOrgFormHooks::formEntityViewDisplayEditFormAlter()` | Gap: no existing view-display editor warning assertion. | Builder service and interface retained. |
| `schemadotorg` | `form_field_config_edit_form_alter` | Converted | `SchemaDotOrgFormHooks::formFieldConfigEditFormAlter()` | Gap: no existing recommended-type description assertion. | Base module; `7f98d583`. |
| `schemadotorg` | `views_data_alter` | Converted | `SchemaDotOrgViewsHooks::viewsDataAlter()` | `SchemaDotOrgViewsSchemaTypeFilterKernelTest` executes the filter. | Base module; `7f98d583`. |
| `schemadotorg` | `preprocess_page_title` | Converted | `SchemaDotOrgPageHooks::preprocessPageTitle()` | Gap: no existing logo or title-prefix assertion. | Base module; `7f98d583`. |
| `schemadotorg` | `node_presave` | Converted | `SchemaDotOrgNodeHooks::nodePresave()` | Gap: no existing devel-generate node assertion. | User's upstream PHPStan directive became unmatched after the move. |
| `schemadotorg` | `modules_uninstalled` | Converted | `SchemaDotOrgModuleHooks::modulesUninstalled()` | `SchemaDotOrgConfigActionTest`; invocation updated because the procedural function was removed. | Base module; `7f98d583`. |
| `schemadotorg` | `field_info_alter` | Converted | `SchemaDotOrgFieldHooks::fieldInfoAlter()` | Gap: no existing category normalization assertion. | Base module; `7f98d583`. |
| `schemadotorg` for `entity_browser` | `schemadotorg_property_field_alter` | Converted | `SchemaDotOrgContribHooks::entityBrowserPropertyFieldAlter()` | `SchemaDotOrgEntityBrowserKernelTest`. | Preserves implementation module. |
| `schemadotorg` for `focal_point` | `schemadotorg_mapping_insert` | Converted | `SchemaDotOrgContribHooks::focalPointMappingInsert()` | `SchemaDotOrgFocalPointKernelTest` checks the final image widget. | Ordered last to preserve sentinel. |
| `schemadotorg` for `focal_point` | `schemadotorg_property_field_alter` | Converted | `SchemaDotOrgContribHooks::focalPointPropertyFieldAlter()` | `SchemaDotOrgFocalPointKernelTest`. | Preserves implementation module. |
| `schemadotorg` for `linkit` | `schemadotorg_property_field_alter` | Converted | `SchemaDotOrgContribHooks::linkitPropertyFieldAlter()` | Gap: no existing Linkit widget assertion. | Preserves implementation module. |
| `schemadotorg` for `media_library_media_modify` | `schemadotorg_property_field_type_alter` | Converted | `SchemaDotOrgContribHooks::mediaLibraryPropertyFieldTypeAlter()` | `SchemaDotOrgMediaLibraryMediaModifyKernelTest`. | Preserves implementation module. |
| `schemadotorg` for `media_library_media_modify` | `schemadotorg_property_field_alter` | Converted | `SchemaDotOrgContribHooks::mediaLibraryPropertyFieldAlter()` | `SchemaDotOrgMediaLibraryMediaModifyKernelTest`. | Preserves implementation module. |
| `schemadotorg` | `token_info` | Existing OOP verified | `SchemaDotOrgTokenHooks::tokenInfo()` | `SchemaDotOrgTokenKernelTest`. | Arrived from updated `1.0.x`. |
| `schemadotorg` | `tokens` | Existing OOP verified | `SchemaDotOrgTokenHooks::tokens()` | `SchemaDotOrgTokenKernelTest`. | Arrived from updated `1.0.x`. |

## Retained procedural functions and callbacks

| Module | Function | Reason retained | Test or verification | Commit/notes |
|---|---|---|---|---|
| `schemadotorg` | `schemadotorg_requirements()`, `schemadotorg_install()`, `schemadotorg_schema()`, `schemadotorg_update_10000()` through `schemadotorg_update_10020()` (defined numbers only) | Drupal install/update entry points must remain procedural. | `SchemaDotOrgInstallerKernelTest` covers installation; update execution remains outside this conversion. | `7f98d583`. |
| `schemadotorg` | `SchemaDotOrgSettingsFormBase::afterBuildDetails()` and other registered class callbacks | Retained class callbacks; not runtime hook implementations. | `SchemaDotOrgSettingsFormTest` and existing JavaScript tests. | `7f98d583`. |
| `schemadotorg` | `schemadotorg.api.php` hook definitions | API documentation, not runtime implementations. | Source inspection. | `7f98d583`. |
