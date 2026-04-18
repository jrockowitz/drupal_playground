<?php

declare(strict_types=1);

namespace Drupal\ai_schemadotorg_jsonld;

/**
 * Interface for the AI Schema.org JSON-LD manager service.
 */
interface AiSchemaDotOrgJsonLdManagerInterface {

  /**
   * Adds entity type settings for newly enabled content entity types.
   *
   * @param array $entity_type_ids
   *   The content entity type IDs.
   */
  public function addEntityTypes(array $entity_type_ids): void;

  /**
   * Syncs configured entity types to the enabled entity type list.
   *
   * @param array $entity_type_ids
   *   The enabled content entity type IDs.
   */
  public function syncEntityTypes(array $entity_type_ids): void;

  /**
   * Returns supported content entity type definitions.
   *
   * @return array
   *   Supported entity type definitions keyed by entity type ID.
   */
  public function getSupportedEntityTypes(): array;

}
