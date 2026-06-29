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
 * Checks access to term reference management routes and entities.
 */
class TermReferenceAccess implements TermReferenceAccessInterface {

  /**
   * Constructs a TermReferenceAccess object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   * @param \Drupal\Core\Entity\EntityFieldManagerInterface $entityFieldManager
   *   The entity field manager.
   * @param \Drupal\term_reference\TermReferenceDiscoveryInterface $termReferenceDiscovery
   *   The term reference discovery service.
   */
  public function __construct(
    protected EntityTypeManagerInterface $entityTypeManager,
    protected EntityFieldManagerInterface $entityFieldManager,
    protected TermReferenceDiscoveryInterface $termReferenceDiscovery,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function overviewAccess(AccountInterface $account, TermInterface $taxonomy_term): AccessResultInterface {
    $access = AccessResult::neutral()->addCacheableDependency($taxonomy_term);
    foreach ($this->termReferenceDiscovery->getFieldsForVocabulary($taxonomy_term->bundle()) as $field) {
      $access = $access->orIf($this->fieldAccess($account, $taxonomy_term, $field));
    }
    return $access;
  }

  /**
   * {@inheritdoc}
   */
  public function routeAccess(AccountInterface $account, TermInterface $taxonomy_term, string $field): AccessResultInterface {
    [$entity_type_id, $field_name] = $this->splitField($field);
    $field = $this->termReferenceDiscovery->getField($taxonomy_term->bundle(), $entity_type_id, $field_name);
    if (!$field) {
      return AccessResult::forbidden()->addCacheableDependency($taxonomy_term);
    }
    return $this->fieldAccess($account, $taxonomy_term, $field);
  }

  /**
   * {@inheritdoc}
   */
  public function fieldAccess(AccountInterface $account, TermInterface $taxonomy_term, array $field): AccessResultInterface {
    $term_access = $taxonomy_term->access('update', $account, TRUE);
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

  /**
   * Splits a field ID into route parts.
   *
   * @param string $field
   *   The field ID.
   *
   * @return array
   *   The entity type ID and field name.
   */
  protected function splitField(string $field): array {
    return explode('.', $field, 2) + ['', ''];
  }

}
