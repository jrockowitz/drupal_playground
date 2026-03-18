<?php

declare(strict_types=1);

namespace Drupal\entity_labels;

/**
 * Base interface for entity label importers.
 */
interface EntityLabelsImporterInterface {

  /**
   * Parses a CSV string and applies label/description updates.
   *
   * @throws \Drupal\entity_labels\Exception\EntityLabelsCsvParseException
   *   On malformed CSV or missing required headers.
   * @throws \Drupal\entity_labels\Exception\EntityLabelsImportException
   *   On row-level failures that should abort the import.
   *
   * @return array
   *   Result map with keys: 'updated' (int), 'skipped' (int), 'errors'
   *   (string[]), 'null_fields' (string[]) — identifiers (entity_type.bundle
   *   or entity_type.bundle.field_name) of rows where the config entity could
   *   not be loaded.
   */
  public function import(string $csv): array;

}
