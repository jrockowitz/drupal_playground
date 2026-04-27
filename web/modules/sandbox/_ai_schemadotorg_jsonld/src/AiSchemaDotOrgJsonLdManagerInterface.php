<?php

declare(strict_types=1);

namespace Drupal\ai_schemadotorg_jsonld;

/**
 * Interface for the AI Schema.org JSON-LD manager service.
 */
interface AiSchemaDotOrgJsonLdManagerInterface {

  /**
   * Returns TRUE when a content entity type is supported.
   *
   * @param string $entity_type_id
   *   The content entity type ID.
   */
  public function isSupportedEntityType(string $entity_type_id): bool;

  /**
   * Returns supported content entity type definitions.
   *
   * Supported entity types are content entities that are fieldable,
   * have a canonical link template, and are not in the unsupported list.
   *
   * @return \Drupal\Core\Entity\ContentEntityTypeInterface[]
   *   Supported entity type definitions keyed by entity type ID.
   */
  public function getSupportedEntityTypes(): array;

  /**
   * Syncs configured entity types to the enabled entity type list.
   *
   * @param array $entity_type_ids
   *   The enabled content entity type IDs.
   */
  public function syncEntityTypes(array $entity_type_ids): void;

  /**
   * Adds entity type settings for newly enabled content entity types.
   *
   * For each new entity type, a default prompt is built (either from a file
   * or a fallback template) and saved to the module settings.
   *
   * @param array $entity_type_ids
   *   The content entity type IDs to add.
   */
  public function addEntityTypes(array $entity_type_ids): void;

  /**
   * Adds entity type settings for a single content entity type.
   *
   * @param string $entity_type_id
   *   The content entity type ID to add.
   */
  public function addEntityType(string $entity_type_id): void;

}
