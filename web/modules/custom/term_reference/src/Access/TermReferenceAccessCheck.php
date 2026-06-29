<?php

namespace Drupal\term_reference\Access;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\taxonomy\TermInterface;
use Drupal\term_reference\TermReferenceDiscoveryInterface;

/**
 * Checks access to term reference management routes.
 */
class TermReferenceAccessCheck {

  /**
   * Constructs a TermReferenceAccessCheck object.
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
   * Checks access to the primary References task.
   *
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The current account.
   * @param \Drupal\taxonomy\TermInterface $taxonomy_term
   *   The taxonomy term.
   *
   * @return \Drupal\Core\Access\AccessResultInterface
   *   The access result.
   */
  public function overviewAccess(AccountInterface $account, TermInterface $taxonomy_term): AccessResultInterface {
    $access = AccessResult::neutral()->addCacheableDependency($taxonomy_term);
    foreach ($this->termReferenceDiscovery->getReferenceFieldsForVocabulary($taxonomy_term->bundle()) as $field) {
      $access = $access->orIf($this->fieldAccess($account, $taxonomy_term, $field));
    }
    return $access;
  }

  /**
   * Checks access to a term reference route.
   *
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The current account.
   * @param \Drupal\taxonomy\TermInterface $taxonomy_term
   *   The taxonomy term.
   * @param string $reference_field
   *   The reference field ID.
   *
   * @return \Drupal\Core\Access\AccessResultInterface
   *   The access result.
   */
  public function access(AccountInterface $account, TermInterface $taxonomy_term, string $reference_field): AccessResultInterface {
    [$entity_type_id, $field_name] = $this->splitReferenceField($reference_field);
    $field = $this->termReferenceDiscovery->getReferenceField($taxonomy_term->bundle(), $entity_type_id, $field_name);
    if (!$field) {
      return AccessResult::forbidden()->addCacheableDependency($taxonomy_term);
    }
    return $this->fieldAccess($account, $taxonomy_term, $field);
  }

  /**
   * Checks whether the account can update the term and edit a reference field.
   *
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The current account.
   * @param \Drupal\taxonomy\TermInterface $taxonomy_term
   *   The taxonomy term.
   * @param array $field
   *   The reference field.
   *
   * @return \Drupal\Core\Access\AccessResultInterface
   *   The access result.
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
   * Splits a reference field ID into route parts.
   *
   * @param string $reference_field
   *   The reference field ID.
   *
   * @return array
   *   The entity type ID and field name.
   */
  protected function splitReferenceField(string $reference_field): array {
    return explode('.', $reference_field, 2) + ['', ''];
  }

}
