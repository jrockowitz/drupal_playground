<?php

namespace Drupal\term_reference;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\taxonomy\TermInterface;

/**
 * Manages taxonomy term references on content entities.
 */
class TermReferenceManager implements TermReferenceManagerInterface {

  /**
   * Constructs a TermReferenceManager object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   */
  public function __construct(
    protected EntityTypeManagerInterface $entityTypeManager,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function loadReferencingEntities(TermInterface $term, array $field): array {
    $storage = $this->entityTypeManager->getStorage($field['entity_type_id']);
    $entity_type = $this->entityTypeManager->getDefinition($field['entity_type_id']);
    $bundle_key = $entity_type->getKey('bundle');
    $query = $storage->getQuery()->accessCheck(TRUE);
    $query->condition($field['field_name'] . '.target_id', $term->id());
    if ($bundle_key) {
      $query->condition($bundle_key, array_keys($field['bundles']), 'IN');
    }
    $label_key = $entity_type->getKey('label');
    if ($label_key) {
      $query->sort($label_key);
    }
    $entity_ids = $query->execute();
    $entities = $entity_ids ? $storage->loadMultiple($entity_ids) : [];
    return array_filter($entities, static fn ($entity): bool => $entity instanceof ContentEntityInterface);
  }

  /**
   * {@inheritdoc}
   */
  public function addReference(ContentEntityInterface $entity, TermInterface $term, string $field_name): void {
    foreach ($entity->get($field_name) as $item) {
      $value = $item->getValue();
      if ((string) ($value['target_id'] ?? '') === (string) $term->id()) {
        return;
      }
    }
    $entity->get($field_name)->appendItem(['target_id' => $term->id()]);
    $entity->save();
  }

  /**
   * {@inheritdoc}
   */
  public function removeReference(ContentEntityInterface $entity, TermInterface $term, string $field_name): void {
    foreach ($entity->get($field_name) as $delta => $item) {
      $value = $item->getValue();
      if ((string) ($value['target_id'] ?? '') === (string) $term->id()) {
        $entity->get($field_name)->removeItem($delta);
      }
    }
    $entity->save();
  }

}
