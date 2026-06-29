<?php

namespace Drupal\term_reference\Controller;

use Drupal\Component\Utility\Tags;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityAccessControlHandlerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\taxonomy\TermInterface;
use Drupal\term_reference\TermReferenceDiscoveryInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * Provides autocomplete suggestions for term reference management forms.
 */
class TermReferenceAutocompleteController extends ControllerBase {

  /**
   * Constructs a TermReferenceAutocompleteController object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $termReferenceEntityTypeManager
   *   The entity type manager.
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The current user.
   * @param \Drupal\term_reference\TermReferenceDiscoveryInterface $termReferenceDiscovery
   *   The term reference discovery service.
   */
  public function __construct(
    protected EntityTypeManagerInterface $termReferenceEntityTypeManager,
    protected AccountInterface $account,
    protected TermReferenceDiscoveryInterface $termReferenceDiscovery,
  ) {}

  /**
   * Returns matching manageable entities.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request.
   * @param \Drupal\taxonomy\TermInterface $taxonomy_term
   *   The taxonomy term.
   * @param string $field
   *   The field ID.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   The JSON autocomplete response.
   */
  public function autocomplete(Request $request, TermInterface $taxonomy_term, string $field): JsonResponse {
    $matches = [];
    $input = Tags::explode($request->query->get('q', ''));
    $search = trim((string) end($input));
    [$entity_type_id, $field_name] = $this->splitField($field);
    $field = $this->termReferenceDiscovery->getField($taxonomy_term->bundle(), $entity_type_id, $field_name);
    if (!$field || $search === '') {
      return new JsonResponse($matches);
    }

    $storage = $this->termReferenceEntityTypeManager->getStorage($entity_type_id);
    $entity_type = $this->termReferenceEntityTypeManager->getDefinition($entity_type_id);
    $label_key = $entity_type->getKey('label');
    $bundle_key = $entity_type->getKey('bundle');
    $entity_ids = [];
    $exact_match = $storage->load($search);
    if ($exact_match instanceof ContentEntityInterface) {
      $entity_ids[] = $exact_match->id();
    }

    $query = $storage->getQuery()->accessCheck(TRUE)->range(0, 10);
    if ($label_key) {
      $query->condition($label_key, $search, 'CONTAINS');
      $query->sort($label_key);
    }
    if ($bundle_key) {
      $query->condition($bundle_key, array_keys($field['bundles']), 'IN');
    }
    $entity_ids = array_unique(array_merge($entity_ids, array_values($query->execute())));

    $access_handler = $this->termReferenceEntityTypeManager->getAccessControlHandler($entity_type_id);
    foreach ($storage->loadMultiple($entity_ids) as $entity) {
      if (!$entity instanceof ContentEntityInterface) {
        continue;
      }
      if (!$this->entityCanBeManaged($entity, $field, $access_handler)) {
        continue;
      }
      $matches[] = [
        'value' => $entity->label() . ' (' . $entity->id() . ')',
        'label' => $entity->label() . ' (' . $entity->id() . ')',
      ];
    }

    return new JsonResponse($matches);
  }

  /**
   * Checks whether an entity can be managed by the current user.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The entity.
   * @param array $field
   *   The field.
   * @param \Drupal\Core\Entity\EntityAccessControlHandlerInterface $access_handler
   *   The entity access handler.
   *
   * @return bool
   *   TRUE when the entity can be managed.
   */
  protected function entityCanBeManaged(ContentEntityInterface $entity, array $field, EntityAccessControlHandlerInterface $access_handler): bool {
    if (!$entity->access('update', $this->account) || !$entity->hasField($field['field_name'])) {
      return FALSE;
    }
    $entity_type = $this->termReferenceEntityTypeManager->getDefinition($field['entity_type_id']);
    $bundle_key = $entity_type->getKey('bundle');
    if ($bundle_key && !isset($field['bundles'][$entity->bundle()])) {
      return FALSE;
    }
    return $access_handler->fieldAccess('edit', $entity->get($field['field_name'])->getFieldDefinition(), $this->account, $entity->get($field['field_name']));
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
