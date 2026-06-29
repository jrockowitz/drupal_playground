<?php

namespace Drupal\term_reference;

/**
 * Discovers entity reference fields that can reference taxonomy terms.
 */
interface TermReferenceDiscoveryInterface {

  /**
   * Gets every unique reference field across vocabularies.
   *
   * @return array
   *   Reference fields keyed by entity type ID and field name.
   */
  public function getAllReferenceFields(): array;

  /**
   * Gets reference fields for a vocabulary.
   *
   * @param string $vocabulary_id
   *   The taxonomy vocabulary ID.
   *
   * @return array
   *   Reference fields keyed by entity type ID and field name.
   */
  public function getReferenceFieldsForVocabulary(string $vocabulary_id): array;

  /**
   * Gets one reference field for a vocabulary.
   *
   * @param string $vocabulary_id
   *   The taxonomy vocabulary ID.
   * @param string $entity_type_id
   *   The content entity type ID.
   * @param string $field_name
   *   The field name.
   *
   * @return array|null
   *   The reference field, or NULL when none exists.
   */
  public function getReferenceField(string $vocabulary_id, string $entity_type_id, string $field_name): ?array;

  /**
   * Clears cached reference field discovery.
   */
  public function clearCachedReferenceFields(): void;

}
