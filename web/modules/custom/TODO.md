\Drupal\entity_labels\EntityLabelsFieldExporter::getData
- When the bundle is specified we want to display the fields in the order they would be displayed in the default form mode.
- @see \Drupal\schemadotorg_export\Controller\SchemaDotOrgExportMappingController for code that applies field group and field weight to order of fields.
- Consider moving the $row render logic for fields and custom fields to a helper method.
- Consider adding a private ::getBundleData() to handle the field group display logic for bundles
- Field not displayed in the default form mode should appear last with a note that the field is not displayed.

\Drupal\entity_labels\Controller\EntityLabelsController
- Update $rows with field_type = 'field_group' to be bold text with #ccc background.
