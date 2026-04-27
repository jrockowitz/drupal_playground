<?php

declare(strict_types=1);

namespace Drupal\ai_schemadotorg_jsonld_log;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Database\Query\PagerSelectExtender;
use Drupal\Core\Database\Query\SelectInterface;
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
   * The number of rows to show per pager page.
   */
  protected int $limit = 20;

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
    return $this->buildSelectQuery()
      ->execute()
      ->fetchAll(\PDO::FETCH_ASSOC);
  }

  /**
   * {@inheritdoc}
   */
  public function loadAllByEntity(string $entity_type_id, string $entity_id): array {
    return $this->buildSelectQuery($entity_type_id, $entity_id)
      ->execute()
      ->fetchAll(\PDO::FETCH_ASSOC);
  }

  /**
   * {@inheritdoc}
   */
  public function loadMultiple(string $entity_type_id = '', string $entity_id = ''): array {
    $query = $this->buildSelectQuery($entity_type_id, $entity_id)
      ->extend(PagerSelectExtender::class);
    assert($query instanceof PagerSelectExtender);
    $query->limit($this->limit);
    return $query->execute()->fetchAll(\PDO::FETCH_ASSOC);
  }

  /**
   * Builds the base select query for log rows.
   *
   * @param string $entity_type_id
   *   The entity type ID.
   * @param string $entity_id
   *   The entity ID.
   *
   * @return \Drupal\Core\Database\Query\SelectInterface
   *   The select query.
   */
  protected function buildSelectQuery(string $entity_type_id = '', string $entity_id = ''): SelectInterface {
    $query = $this->connection->select(self::TABLE_NAME, 'log')
      ->fields('log')
      ->orderBy('id', 'DESC');

    if ($entity_type_id && $entity_id) {
      $query->condition('entity_type', $entity_type_id);
      $query->condition('entity_id', $entity_id);
    }

    return $query;
  }

  /**
   * {@inheritdoc}
   */
  public function deleteByEntity(EntityInterface $entity): void {
    $this->connection->delete(self::TABLE_NAME)
      ->condition('entity_type', $entity->getEntityTypeId())
      ->condition('entity_id', (string) $entity->id())
      ->execute();
  }

  /**
   * {@inheritdoc}
   */
  public function truncate(): void {
    $this->connection->truncate(self::TABLE_NAME)->execute();
  }

}
