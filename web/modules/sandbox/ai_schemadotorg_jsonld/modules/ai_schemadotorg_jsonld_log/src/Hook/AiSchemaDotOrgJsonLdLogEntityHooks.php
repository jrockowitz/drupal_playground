<?php

declare(strict_types=1);

namespace Drupal\ai_schemadotorg_jsonld_log\Hook;

use Drupal\ai_schemadotorg_jsonld_log\AiSchemaDotOrgJsonLdLogStorageInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Hook\Attribute\Hook;

/**
 * Entity hook implementations for the AI Schema.org JSON-LD log module.
 */
class AiSchemaDotOrgJsonLdLogEntityHooks {

  /**
   * Constructs an AiSchemaDotOrgJsonLdLogEntityHooks object.
   *
   * @param \Drupal\ai_schemadotorg_jsonld_log\AiSchemaDotOrgJsonLdLogStorageInterface $logStorage
   *   The log storage.
   */
  public function __construct(
    protected readonly AiSchemaDotOrgJsonLdLogStorageInterface $logStorage,
  ) {}

  /**
   * Implements hook_entity_delete().
   */
  #[Hook('entity_delete')]
  public function entityDelete(EntityInterface $entity): void {
    $this->logStorage->deleteByEntity($entity);
  }

}
