<?php

declare(strict_types=1);

namespace Drupal\entity_labels;

/**
 * Importer interface for entity type and bundle label metadata.
 */
interface EntityLabelsEntityImporterInterface extends EntityLabelsImporterInterface {

  /**
   * Parses and processes a CSV string, updating bundle config entity labels.
   *
   * Required CSV headers: langcode, entity_type, bundle, label, description.
   * Optional: help (imported for entity types whose bundle config entity
   * exposes setHelp(), e.g. node types; silently ignored otherwise), notes
   * (always ignored).
   *
   * Rows where the bundle config entity cannot be loaded (NULL) are counted
   * as skipped and their identifier (entity_type.bundle) recorded in
   * 'null_fields'.
   *
   * @throws \Drupal\entity_labels\Exception\EntityLabelsCsvParseException
   * @throws \Drupal\entity_labels\Exception\EntityLabelsImportException
   *
   * @return array
   *   Result map with keys: 'updated' (int), 'skipped' (int), 'errors'
   *   (string[]), 'null_fields' (string[]) — identifiers (entity_type.bundle)
   *   of rows where the bundle config entity could not be loaded.
   */
  public function import(string $csv): array;

}
