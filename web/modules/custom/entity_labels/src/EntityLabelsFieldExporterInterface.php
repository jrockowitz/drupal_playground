<?php

declare(strict_types=1);

namespace Drupal\entity_labels;

/**
 * Exporter interface for field label metadata.
 */
interface EntityLabelsFieldExporterInterface extends EntityLabelsExporterInterface {

  /**
   * Returns field report rows, optionally filtered by entity type or bundle.
   *
   * Rows sorted by entity type, bundle, field name.
   *
   * Keys: langcode, entity_type, bundle, field_name,
   *       field_column (empty string unless custom_field 4.x is installed),
   *       field_type, label, description, allowed_values, notes.
   * When field_group is installed, additional rows are appended with
   * field_type = 'field_group'. When custom_field 4.x is installed,
   * additional rows are appended per column with field_column set.
   *
   * @return array
   *   Field report rows; each row is a map of column name to string, bool,
   *   or null value.
   */
  public function getData(?string $entity_type_id = NULL, ?string $bundle = NULL): array;

  /**
   * Builds CSV rows (header first) ready for fputcsv().
   *
   * Header includes field_column when custom_field 4.x is installed:
   *   langcode,entity_type,bundle,field_name,field_column,field_type,
   *   label,description,allowed_values,notes
   * Header without custom_field:
   *   langcode,entity_type,bundle,field_name,field_type,label,description,
   *   allowed_values,notes.
   *
   * @return array
   *   CSV rows as lists of strings; first row is the header.
   */
  public function export(?string $entity_type_id = NULL, ?string $bundle = NULL): array;

}
