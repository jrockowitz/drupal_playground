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
| `schemadotorg_allowed_formats` | `schemadotorg_property_field_alter` | Converted | `SchemaDotOrgAllowedFormatsHooks::schemadotorgPropertyFieldAlter()` | `SchemaDotOrgAllowedFormatsKernelTest::testAllowedFormats()` covers allowed formats and widget third-party settings. | Manager logic moved into the hook class; hook-only manager service and interface removed with maintainer approval. |
| `schemadotorg_allowed_formats` | `form_schemadotorg_properties_settings_form_alter` | Converted | `SchemaDotOrgAllowedFormatsFormHooks::formSchemadotorgPropertiesSettingsFormAlter()` | `SchemaDotOrgAllowedFormatsSettingsFormTest::testSettingsForm()` covers form save behavior; no direct field-definition assertion. | Filter format repository injected; translation uses `TranslatableMarkup`. |
| `schemadotorg_existing_values_autocomplete_widget` | `schemadotorg_property_field_alter` | Converted | `SchemaDotOrgExistingValuesAutocompleteWidgetHooks::schemadotorgPropertyFieldAlter()` | `SchemaDotOrgExistingValuesAutocompleteWidgetKernelTest` covers widget selection. | Hook-specific manager logic moved into the hook; manager service and interface removed. |
| `schemadotorg_existing_values_autocomplete_widget` | `form_schemadotorg_properties_settings_form_alter` | Converted | `SchemaDotOrgExistingValuesAutocompleteWidgetFormHooks::formSchemadotorgPropertiesSettingsFormAlter()` | `SchemaDotOrgExistingValuesAutocompleteWidgetSettingsFormTest` covers settings form behavior. | Existing form coverage retained. |
| `schemadotorg_field_validation` | `field_config_insert` | Converted | `SchemaDotOrgFieldValidationHooks::fieldConfigInsert()` | `SchemaDotOrgFieldValidationKernelTest` covers validation rule creation. | Hook-specific manager logic moved into the hook; manager service and interface removed. |
| `schemadotorg_field_validation` | `form_schemadotorg_properties_settings_form_alter` | Converted | `SchemaDotOrgFieldValidationFormHooks::formSchemadotorgPropertiesSettingsFormAlter()` | `SchemaDotOrgFieldValidationSettingsFormTest` covers settings form behavior. | Existing form coverage retained. |
| `schemadotorg_geolocation` | `schemadotorg_property_field_alter` | Converted | `SchemaDotOrgGeolocationHooks::schemadotorgPropertyFieldAlter()` | Geolocation kernel coverage exercises widget and formatter configuration. | Hook-specific manager logic moved into the hook; manager service and interface removed. |
| `schemadotorg_geolocation` | `schemadotorg_jsonld_schema_property_alter` | Converted | `SchemaDotOrgGeolocationJsonLdHooks::schemadotorgJsonldSchemaPropertyAlter()` | Geolocation JSON-LD kernel coverage exercises GeoCoordinates output. | JSON-LD manager logic moved into the module-specific JSON-LD hook; manager service and interface removed. |
| `schemadotorg_inline_entity_form` | `entity_insert` | Converted | `SchemaDotOrgInlineEntityFormHooks::entityInsert()` | Inline Entity Form kernel coverage exercises form-mode/display creation. | Hook-specific manager logic moved into the hook; manager service and interface removed. |
| `schemadotorg_inline_entity_form` | `schemadotorg_property_field_alter` | Converted | `SchemaDotOrgInlineEntityFormHooks::schemadotorgPropertyFieldAlter()` | Inline Entity Form kernel coverage exercises widget configuration. | Hook-specific manager logic moved into the hook; manager service and interface removed. |
| `schemadotorg_inline_entity_form` | `form_schemadotorg_properties_settings_form_alter` | Converted | `SchemaDotOrgInlineEntityFormFormHooks::formSchemadotorgPropertiesSettingsFormAlter()` | Existing settings-form coverage exercises saved defaults; no direct field assertion. | Existing form coverage retained. |
| `schemadotorg_jsonapi` | `help` | Converted | `SchemaDotOrgJsonApiHelpHooks::help()` | No direct help-output assertion; coverage gap. | Translation moved to hook class. |
| `schemadotorg_jsonapi` | `schemadotorg_mapping_insert` | Converted | `SchemaDotOrgJsonApiHooks::schemadotorgMappingInsert()` | `SchemaDotOrgJsonApiManagerKernelTest` covers resource creation. | Shared manager retained and injected. |
| `schemadotorg_jsonapi` | `schemadotorg_mapping_update` | Converted | `SchemaDotOrgJsonApiHooks::schemadotorgMappingUpdate()` | `SchemaDotOrgJsonApiManagerKernelTest` covers resource updates. | Shared manager retained and injected. |
| `schemadotorg_jsonapi` | `field_config_insert` | Converted | `SchemaDotOrgJsonApiHooks::fieldConfigInsert()` | `SchemaDotOrgJsonApiManagerKernelTest` covers field resource creation. | Shared manager retained and injected. |
| `schemadotorg_jsonapi` | `form_schemadotorg_mapping_add_form_alter` | Converted | `SchemaDotOrgJsonApiFormHooks::formSchemadotorgMappingAddFormAlter()` | Existing JSON:API functional coverage exercises mapping form behavior. | Submit callback is now a class method callable; assertions unchanged. |
| `schemadotorg_address` | `schemadotorg_property_field_alter` | Converted | `SchemaDotOrgAddressHooks::schemadotorgPropertyFieldAlter()` | `SchemaDotOrgAddressKernelTest` covers field override behavior. | Manager implementation moved into the hook class; manager service API removed. |
| `schemadotorg_address` | `schemadotorg_jsonld_schema_property_alter` | Converted | `SchemaDotOrgAddressJsonLdHooks::schemadotorgJsonldSchemaPropertyAlter()` | `SchemaDotOrgAddressJsonLdKernelTest` covers address JSON-LD output. | JSON-LD manager implementation moved into the hook class. |
| `schemadotorg_address` | `form_schemadotorg_properties_settings_form_alter` | Converted | `SchemaDotOrgAddressFormHooks::formSchemadotorgPropertiesSettingsFormAlter()` | No direct settings-form field assertion; existing form coverage gap. | Uses `StringTranslationTrait` and `$this->t()`. |
| `schemadotorg_auto_entitylabel` | `schemadotorg_mapping_insert` | Converted | `SchemaDotOrgAutoEntityLabelHooks::schemadotorgMappingInsert()` | `SchemaDotOrgAutoEntityLabelKernelTest` covers mapping-created configuration. | Manager implementation moved into the hook class; manager service API removed. |
| `schemadotorg_auto_entitylabel` | `form_schemadotorg_types_settings_form_alter` | Converted | `SchemaDotOrgAutoEntityLabelFormHooks::formSchemadotorgTypesSettingsFormAlter()` | No direct settings-form field assertion; existing form coverage gap. | Uses `StringTranslationTrait` and `$this->t()`. |
| `schemadotorg_block_content` | `schemadotorg_jsonld` | Converted | `SchemaDotOrgBlockContentJsonLdHooks::schemadotorgJsonld()` | `SchemaDotOrgBlockContentJsonLdKernelTest` and functional coverage exercise JSON-LD. | JSON-LD manager implementation moved into the hook class; manager service API removed. |
| `schemadotorg_block_content` | `form_alter` | Converted | `SchemaDotOrgBlockContentFormHooks::formAlter()` | Functional block-content test covers the mapped form behavior. | Mapping lookup now uses injected entity type manager. |
| `schemadotorg_content_moderation` | `schemadotorg_mapping_insert` | Converted | `SchemaDotOrgContentModerationHooks::schemadotorgMappingInsert()` | `SchemaDotOrgContentModerationKernelTest` covers workflow and display changes. | Manager implementation moved into the hook class; manager service API removed. |
| `schemadotorg_content_moderation` | `form_schemadotorg_types_settings_form_alter` | Converted | `SchemaDotOrgContentModerationFormHooks::formSchemadotorgTypesSettingsFormAlter()` | No direct settings-form field assertion; existing form coverage gap. | Uses `StringTranslationTrait` and `$this->t()`. |
| `schemadotorg_entity_reference_override` | `schemadotorg_property_field_alter` | Converted | `SchemaDotOrgEntityReferenceOverrideHooks::schemadotorgPropertyFieldAlter()` | `SchemaDotOrgEntityReferenceOverrideKernelTest` covers field configuration. | Manager implementation moved into the hook class; manager service API removed. |
| `schemadotorg_entity_reference_override` | `field_widget_single_element_entity_reference_override_autocomplete_form_alter` | Converted | `SchemaDotOrgEntityReferenceOverrideHooks::fieldWidgetSingleElementEntityReferenceOverrideAutocompleteFormAlter()` | No direct widget-form alter assertion; existing coverage gap. | Manager implementation moved into the hook class; manager service API removed. |
| `schemadotorg_entity_reference_override` | `schemadotorg_jsonld_schema_property_alter` | Converted | `SchemaDotOrgEntityReferenceOverrideJsonLdHooks::schemadotorgJsonldSchemaPropertyAlter()` | `SchemaDotOrgEntityReferenceOverrideJsonLdKernelTest` covers Role output. | JSON-LD manager implementation moved into the hook class; manager service API removed. |
| `schemadotorg_entity_reference_override` | `form_schemadotorg_properties_settings_form_alter` | Converted | `SchemaDotOrgEntityReferenceOverrideFormHooks::formSchemadotorgPropertiesSettingsFormAlter()` | No direct settings-form field assertion; existing form coverage gap. | Uses `StringTranslationTrait` and `$this->t()`. |
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
| `schemadotorg_allowed_formats` | `schemadotorg_allowed_formats_update_10000()` | Drupal update entry point must remain procedural. | No OOP equivalent. | Update execution remains outside this conversion. |
| `schemadotorg_address` | `schemadotorg_address_install()`, `schemadotorg_address_uninstall()` | Drupal install/uninstall entry points must remain procedural. | No OOP equivalent. | Install/uninstall execution remains outside this conversion. |
| `schemadotorg_geolocation` | `schemadotorg_geolocation_install()`, `schemadotorg_geolocation_uninstall()` | Drupal install/uninstall entry points must remain procedural. | No OOP equivalent. | Install/uninstall execution remains outside this conversion. |
| `schemadotorg_inline_entity_form` | `schemadotorg_inline_entity_form_install()` | Drupal install entry point must remain procedural. | No OOP equivalent. | Install execution remains outside this conversion. |
| `schemadotorg_jsonapi` | `schemadotorg_jsonapi_install()` | Drupal install entry point must remain procedural. | No OOP equivalent. | Install execution remains outside this conversion. |
| `schemadotorg_auto_entitylabel` | `schemadotorg_auto_entitylabel_update_10001()` | Drupal update entry point must remain procedural. | No OOP equivalent. | Update execution remains outside this conversion. |
| `schemadotorg_block_content` | `schemadotorg_block_content_install()` | Drupal install entry point must remain procedural. | No OOP equivalent. | Install execution remains outside this conversion. |
| `schemadotorg` | `SchemaDotOrgSettingsFormBase::afterBuildDetails()` and other registered class callbacks | Retained class callbacks; not runtime hook implementations. | `SchemaDotOrgSettingsFormTest` and existing JavaScript tests. | `7f98d583`. |
| `schemadotorg` | `schemadotorg.api.php` hook definitions | API documentation, not runtime implementations. | Source inspection. | `7f98d583`. |
