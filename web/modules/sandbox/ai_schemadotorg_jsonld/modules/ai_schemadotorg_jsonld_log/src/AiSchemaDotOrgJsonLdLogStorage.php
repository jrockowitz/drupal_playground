<?php

declare(strict_types=1);

namespace Drupal\ai_schemadotorg_jsonld_log;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Database\Query\PagerSelectExtender;
use Drupal\Core\Entity\EntityInterface;

/**
 * Database-backed storage for AI Schema.org JSON-LD log rows.
 */
class AiSchemaDotOrgJsonLdLogStorage implements AiSchemaDotOrgJsonLdLogStorageInterface {

  /**
   * The log table name.
   */
  protected const TABLE_NAME = 'ai_schemadotorg_jsonld_log';

  /**
   * Constructs an AiSchemaDotOrgJsonLdLogStorage object.
   *
   * @param \Drupal\Core\Database\Connection $connection
   *   The database connection.
   * @param \Drupal\Component\Datetime\TimeInterface $time
   *   The time service.
   */
  public function __construct(
    protected readonly Connection $connection,
    protected readonly TimeInterface $time,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function insert(array $values): void {
    $values['created'] = $values['created'] ?? $this->time->getCurrentTime();
    $this->connection->insert(self::TABLE_NAME)
      ->fields($values)
      ->execute();
  }

  /**
   * {@inheritdoc}
   */
  public function loadAll(): array {
    return $this->connection->select(self::TABLE_NAME, 'log')
      ->fields('log')
      ->orderBy('id', 'DESC')
      ->execute()
      ->fetchAll(\PDO::FETCH_ASSOC);
  }

  /**
   * {@inheritdoc}
   */
  public function loadPage(int $limit = 10): array {
    $query = $this->connection->select(self::TABLE_NAME, 'log')
      ->fields('log')
      ->orderBy('id', 'DESC')
      ->extend(PagerSelectExtender::class);
    assert($query instanceof PagerSelectExtender);
    $query->limit($limit);
    return $query->execute()->fetchAll(\PDO::FETCH_ASSOC);
  }

  /**
   * {@inheritdoc}
   */
  public function deleteByEntity(EntityInterface $entity): void {
    $entity_id = $entity->id();
    if ($entity_id === NULL) {
      return;
    }

    $this->connection->delete(self::TABLE_NAME)
      ->condition('entity_type', $entity->getEntityTypeId())
      ->condition('entity_id', (string) $entity_id)
      ->execute();
  }

  /**
   * {@inheritdoc}
   */
  public function truncate(): void {
    $this->connection->truncate(self::TABLE_NAME)->execute();
  }

}
