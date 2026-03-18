<?php

declare(strict_types=1);

namespace Drupal\entity_labels;

/**
 * Base interface for entity label exporters.
 */
interface EntityLabelsExporterInterface {

  /**
   * Returns the ordered column names for display and CSV (excluding 'notes').
   *
   * @return string[]
   *   Column machine names.
   */
  public function getHeader(): array;

  /**
   * Returns report rows for the given scope.
   *
   * Row shape and sort order vary by implementation.
   * Every row includes a 'notes' key (string, may be empty).
   *
   * @return array
   *   Rows of report data; each row is a map of column name to value.
   */
  public function getData(?string $entity_type_id = NULL, ?string $bundle = NULL): array;

  /**
   * Builds CSV rows (header first, notes column last) ready for fputcsv().
   *
   * @return array
   *   CSV rows as lists of strings; first row is the header.
   */
  public function export(?string $entity_type_id = NULL, ?string $bundle = NULL): array;

}
