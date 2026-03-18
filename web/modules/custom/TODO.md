In \Drupal\entity_labels\EntityLabelsFieldExporter
  - Remove abbreviations from variable names
  - Move ::isCustomFieldVersion4 into ::isCustomFieldInstalled and remove ::isCustomFieldVersion4
  - Limit \Drupal\entity_labels\EntityLabelsFieldExporter::serializeAllowedValues string length to 500 characters using Unicode::truncate with ...
