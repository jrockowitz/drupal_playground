<?php

namespace Drupal\term_reference;

/**
 * Discovers entity fields that can reference taxonomy terms.
 */
interface TermReferenceDiscoveryInterface {

  /**
   * Gets every unique field across vocabularies.
   *
   * @return array
   *   Fields keyed by entity type ID and field name.
   */
  public function getAllFields(): array;

  /**
   * Gets fields for a vocabulary.
   *
   * @param string $vocabulary_id
   *   The taxonomy vocabulary ID.
   *
   * @return array
   *   Fields keyed by entity type ID and field name.
   */
  public function getFieldsForVocabulary(string $vocabulary_id): array;

  /**
   * Gets one field for a vocabulary.
   *
   * @param string $vocabulary_id
   *   The taxonomy vocabulary ID.
   * @param string $entity_type_id
   *   The content entity type ID.
   * @param string $field_name
   *   The field name.
   *
   * @return array|null
   *   The field, or NULL when none exists.
   */
  public function getField(string $vocabulary_id, string $entity_type_id, string $field_name): ?array;

  /**
   * Clears cached field discovery.
   */
  public function clearCachedFields(): void;

}
