<?php

declare(strict_types=1);

namespace Drupal\entity_labels;

/**
 * Exporter interface for entity type and bundle label metadata.
 */
interface EntityLabelsEntityExporterInterface extends EntityLabelsExporterInterface {

  /**
   * Returns entity/bundle report rows, optionally filtered by entity type.
   *
   * Only entity types that support bundles are included.
   * Rows sorted by entity type, then bundle.
   *
   * Keys: langcode, entity_type, bundle, label, description, help, notes.
   * The 'help' value is populated only for entity types whose bundle config
   * entity exposes a getHelp() method (e.g. node types); it is an empty
   * string otherwise.
   *
   * Note: $bundle is accepted for interface compatibility but ignored by
   * this implementation — the Entities tab supports entity-type-level
   * drill-down only.
   *
   * @return array
   *   Entity/bundle report rows; each row is a string map of column name
   *   to value.
   */
  public function getData(?string $entity_type_id = NULL, ?string $bundle = NULL): array;

  /**
   * Builds CSV rows (header first) ready for fputcsv().
   *
   * Header: langcode,entity_type,bundle,label,description,help,notes.
   *
   * @return array
   *   CSV rows as lists of strings; first row is the header.
   */
  public function export(?string $entity_type_id = NULL, ?string $bundle = NULL): array;

}
