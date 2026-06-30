<?php

namespace Drupal\term_reference;

use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\taxonomy\TermInterface;

/**
 * Manages taxonomy term references on content entities.
 */
interface TermReferenceManagerInterface {

  /**
   * Loads entities that reference a term through a field.
   *
   * @param \Drupal\taxonomy\TermInterface $term
   *   The taxonomy term.
   * @param array $field
   *   The field.
   *
   * @return \Drupal\Core\Entity\ContentEntityInterface[]
   *   The referencing entities.
   */
  public function loadReferencingEntities(TermInterface $term, array $field): array;

  /**
   * Checks whether an account can manage references for a term and field.
   *
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The account.
   * @param \Drupal\taxonomy\TermInterface $term
   *   The taxonomy term.
   * @param array $field
   *   The field.
   *
   * @return \Drupal\Core\Access\AccessResultInterface
   *   The access result.
   */
  public function accessReference(AccountInterface $account, TermInterface $term, array $field): AccessResultInterface;

  /**
   * Adds a term reference to an entity.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The entity.
   * @param \Drupal\taxonomy\TermInterface $term
   *   The taxonomy term.
   * @param string $field_name
   *   The field name.
   */
  public function addReference(ContentEntityInterface $entity, TermInterface $term, string $field_name): void;

  /**
   * Removes a term reference from an entity.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The entity.
   * @param \Drupal\taxonomy\TermInterface $term
   *   The taxonomy term.
   * @param string $field_name
   *   The field name.
   */
  public function removeReference(ContentEntityInterface $entity, TermInterface $term, string $field_name): void;

  /**
   * Checks whether an entity can be managed through a field.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The entity.
   * @param array $field
   *   The field.
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The account.
   *
   * @return bool
   *   TRUE when the entity can be managed.
   */
  public function entityCanBeManaged(ContentEntityInterface $entity, array $field, AccountInterface $account): bool;

}
