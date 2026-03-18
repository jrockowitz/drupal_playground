<?php

declare(strict_types=1);

namespace Drupal\entity_labels;

/**
 * Importer interface for field label metadata.
 */
interface EntityLabelsFieldImporterInterface extends EntityLabelsImporterInterface {

  /**
   * Parses and processes a CSV string, updating field config entity labels.
   *
   * Required CSV headers: langcode, entity_type, bundle, field_name, label,
   * description.
   * Optional: field_column (defaults to '' when absent), allowed_values,
   * field_type, notes.
   *
   * Rows where neither FieldConfig nor BaseFieldOverride can be loaded (NULL)
   * are counted as skipped and their identifier
   * (entity_type.bundle.field_name) recorded in 'null_fields'.
   *
   * field_group rows (field_type = 'field_group'): updates the group's
   * label/description via the default form display's third_party_settings.
   * Skipped with warning if field_group is not installed.
   *
   * custom_field column rows (non-empty field_column): updates the named
   * column's label/description in field_settings. Skipped with warning if
   * custom_field is not installed or not 4.x.
   *
   * @throws \Drupal\entity_labels\Exception\EntityLabelsCsvParseException
   * @throws \Drupal\entity_labels\Exception\EntityLabelsImportException
   *
   * @return array
   *   Result map with keys: 'updated' (int), 'skipped' (int), 'errors'
   *   (string[]), 'null_fields' (string[]) — identifiers
   *   (entity_type.bundle.field_name) of rows where neither FieldConfig nor
   *   BaseFieldOverride could be loaded.
   */
  public function import(string $csv): array;

}
