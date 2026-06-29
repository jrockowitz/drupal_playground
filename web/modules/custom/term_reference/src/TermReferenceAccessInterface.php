<?php

namespace Drupal\term_reference;

use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\taxonomy\TermInterface;

/**
 * Defines term reference access methods.
 */
interface TermReferenceAccessInterface {

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
  public function overviewAccess(AccountInterface $account, TermInterface $taxonomy_term): AccessResultInterface;

  /**
   * Checks access to a term reference route.
   *
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The current account.
   * @param \Drupal\taxonomy\TermInterface $taxonomy_term
   *   The taxonomy term.
   * @param string $field
   *   The field ID.
   *
   * @return \Drupal\Core\Access\AccessResultInterface
   *   The access result.
   */
  public function routeAccess(AccountInterface $account, TermInterface $taxonomy_term, string $field): AccessResultInterface;

  /**
   * Checks whether the account can update the term and edit a field.
   *
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The current account.
   * @param \Drupal\taxonomy\TermInterface $taxonomy_term
   *   The taxonomy term.
   * @param array $field
   *   The field.
   *
   * @return \Drupal\Core\Access\AccessResultInterface
   *   The access result.
   */
  public function fieldAccess(AccountInterface $account, TermInterface $taxonomy_term, array $field): AccessResultInterface;

  /**
   * Checks whether an entity can be managed.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The entity.
   * @param array $field
   *   The field.
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The current account.
   *
   * @return bool
   *   TRUE when the entity can be managed.
   */
  public function entityCanBeManaged(ContentEntityInterface $entity, array $field, AccountInterface $account): bool;

}
