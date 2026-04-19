<?php

declare(strict_types=1);

namespace Drupal\ai_schemadotorg_jsonld_log;

use Drupal\Core\Entity\EntityInterface;

/**
 * Stores and retrieves AI Schema.org JSON-LD log rows.
 */
interface AiSchemaDotOrgJsonLdLogStorageInterface {

  /**
   * Inserts a log row.
   *
   * @param array $values
   *   The values to insert.
   */
  public function insert(array $values): void;

  /**
   * Loads all log rows ordered newest first.
   *
   * @return array
   *   The log rows.
   */
  public function loadAll(): array;

  /**
   * Loads a single paged slice of log rows ordered newest first.
   *
   * @param int $limit
   *   The number of rows per page.
   *
   * @return array
   *   The log rows.
   */
  public function loadPage(int $limit = 10): array;

  /**
   * Deletes log rows for a specific entity.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity.
   */
  public function deleteByEntity(EntityInterface $entity): void;

  /**
   * Deletes all log rows.
   */
  public function truncate(): void;

}
