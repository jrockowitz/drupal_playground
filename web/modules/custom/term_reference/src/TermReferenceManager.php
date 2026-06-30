<?php

namespace Drupal\term_reference;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AccountInterface;
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
   * @param \Drupal\Core\Entity\EntityFieldManagerInterface $entityFieldManager
   *   The entity field manager.
   */
  public function __construct(
    protected EntityTypeManagerInterface $entityTypeManager,
    protected EntityFieldManagerInterface $entityFieldManager,
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
  public function accessReference(AccountInterface $account, TermInterface $term, array $field): AccessResultInterface {
    $term_access = $term->access('update', $account, TRUE);
    $field_access = AccessResult::neutral();
    $access_handler = $this->entityTypeManager->getAccessControlHandler($field['entity_type_id']);
    foreach (array_keys($field['bundles']) as $bundle_id) {
      $field_definitions = $this->entityFieldManager->getFieldDefinitions($field['entity_type_id'], $bundle_id);
      if (!isset($field_definitions[$field['field_name']])) {
        continue;
      }
      $field_access = $field_access->orIf($access_handler->fieldAccess('edit', $field_definitions[$field['field_name']], $account, NULL, TRUE));
    }
    return $term_access->andIf($field_access);
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

  /**
   * {@inheritdoc}
   */
  public function entityCanBeManaged(ContentEntityInterface $entity, array $field, AccountInterface $account): bool {
    if (!$entity->access('update', $account) || !$entity->hasField($field['field_name'])) {
      return FALSE;
    }
    $entity_type = $this->entityTypeManager->getDefinition($field['entity_type_id']);
    $bundle_key = $entity_type->getKey('bundle');
    if ($bundle_key && !isset($field['bundles'][$entity->bundle()])) {
      return FALSE;
    }
    $access_handler = $this->entityTypeManager->getAccessControlHandler($field['entity_type_id']);
    return $access_handler->fieldAccess('edit', $entity->get($field['field_name'])->getFieldDefinition(), $account, $entity->get($field['field_name']));
  }

}
