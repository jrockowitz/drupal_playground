<?php

namespace Drupal\term_reference;

use Drupal\Component\Utility\Unicode;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Cache\CacheTagsInvalidatorInterface;
use Drupal\Core\Entity\ContentEntityTypeInterface;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\field\FieldConfigInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Discovers entity fields that can reference taxonomy vocabularies.
 */
class TermReferenceDiscovery implements TermReferenceDiscoveryInterface {

  /**
   * The cache tag used for field discovery.
   */
  protected const CACHE_TAG = 'term_reference:fields';

  /**
   * Constructs a TermReferenceDiscovery object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   * @param \Drupal\Core\Entity\EntityTypeBundleInfoInterface $entityTypeBundleInfo
   *   The entity type bundle info service.
   * @param \Drupal\Core\Entity\EntityFieldManagerInterface $entityFieldManager
   *   The entity field manager.
   * @param \Drupal\Core\Cache\CacheBackendInterface $cacheBackend
   *   The cache backend.
   * @param \Drupal\Core\Cache\CacheTagsInvalidatorInterface $cacheTagsInvalidator
   *   The cache tags invalidator.
   */
  public function __construct(
    protected EntityTypeManagerInterface $entityTypeManager,
    protected EntityTypeBundleInfoInterface $entityTypeBundleInfo,
    protected EntityFieldManagerInterface $entityFieldManager,
    #[Autowire(service: 'cache.discovery')]
    protected CacheBackendInterface $cacheBackend,
    protected CacheTagsInvalidatorInterface $cacheTagsInvalidator,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function getAllFields(): array {
    return $this->getFields();
  }

  /**
   * {@inheritdoc}
   */
  public function getFieldsForVocabulary(string $vocabulary_id, ?AccountInterface $account = NULL): array {
    $fields = $this->getFields($vocabulary_id);
    if ($account === NULL) {
      return $fields;
    }
    return $this->filterFieldsByAccount($fields, $account);
  }

  /**
   * {@inheritdoc}
   */
  public function getField(string $vocabulary_id, string $entity_type_id, string $field_name): ?array {
    $fields = $this->getFieldsForVocabulary($vocabulary_id);
    return $fields[$entity_type_id . '.' . $field_name] ?? NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function clearCachedFields(): void {
    $this->cacheTagsInvalidator->invalidateTags([static::CACHE_TAG]);
  }

  /**
   * Gets fields.
   *
   * @param string|null $vocabulary_id
   *   The taxonomy vocabulary ID, or NULL for all taxonomy references.
   *
   * @return array
   *   Fields keyed by entity type ID and field name.
   */
  protected function getFields(?string $vocabulary_id = NULL): array {
    $cache_id = $this->getFieldsCacheId($vocabulary_id);
    $cached_fields = $this->cacheBackend->get($cache_id);
    if ($cached_fields !== FALSE) {
      /** @var array $fields */
      $fields = $cached_fields->data;
      return $fields;
    }

    $fields = [];
    foreach ($this->entityTypeManager->getDefinitions() as $entity_type_id => $entity_type) {
      if (!$entity_type instanceof ContentEntityTypeInterface || !$entity_type->entityClassImplements('\Drupal\Core\Entity\FieldableEntityInterface')) {
        continue;
      }
      foreach ($this->entityTypeBundleInfo->getBundleInfo($entity_type_id) as $bundle_id => $bundle) {
        foreach ($this->entityFieldManager->getFieldDefinitions($entity_type_id, $bundle_id) as $field_name => $field_definition) {
          if (!$field_definition instanceof FieldConfigInterface || !$this->fieldTargetsTaxonomy($field_definition)) {
            continue;
          }
          if ($vocabulary_id !== NULL && !$this->fieldTargetsVocabulary($field_definition, $vocabulary_id)) {
            continue;
          }
          $field_id = $entity_type_id . '.' . $field_name;
          $fields[$field_id] ??= [
            'id' => $field_id,
            'entity_type_id' => $entity_type_id,
            'entity_type_label' => (string) $entity_type->getLabel(),
            'bundle_entity_type_label' => $this->getBundleEntityTypeLabel($entity_type),
            'field_name' => $field_name,
            'field_label' => (string) $field_definition->label(),
            'vocabulary_id' => $vocabulary_id,
            'bundles' => [],
          ];
          $fields[$field_id]['bundles'][$bundle_id] = [
            'id' => $bundle_id,
            'label' => (string) ($bundle['label'] ?? $bundle_id),
          ];
        }
      }
    }
    ksort($fields);
    foreach ($fields as &$field) {
      ksort($field['bundles']);
    }
    $this->cacheBackend->set($cache_id, $fields, CacheBackendInterface::CACHE_PERMANENT, [static::CACHE_TAG]);
    return $fields;
  }

  /**
   * Filters fields to those editable by an account.
   *
   * @param array $fields
   *   The fields.
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The account.
   *
   * @return array
   *   The editable fields.
   */
  protected function filterFieldsByAccount(array $fields, AccountInterface $account): array {
    return array_filter($fields, fn (array $field): bool => $this->fieldEditableByAccount($field, $account));
  }

  /**
   * Checks whether an account can edit at least one field instance.
   *
   * @param array $field
   *   The field.
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The account.
   *
   * @return bool
   *   TRUE when the account can edit at least one eligible field instance.
   */
  protected function fieldEditableByAccount(array $field, AccountInterface $account): bool {
    $access_handler = $this->entityTypeManager->getAccessControlHandler($field['entity_type_id']);
    foreach (array_keys($field['bundles']) as $bundle_id) {
      if (!$this->bundleEditableByAccount($field['entity_type_id'], $bundle_id, $account)) {
        continue;
      }
      $field_definitions = $this->entityFieldManager->getFieldDefinitions($field['entity_type_id'], $bundle_id);
      if (!isset($field_definitions[$field['field_name']])) {
        continue;
      }
      if ($access_handler->fieldAccess('edit', $field_definitions[$field['field_name']], $account)) {
        return TRUE;
      }
    }
    return FALSE;
  }

  /**
   * Checks whether an account can update an entity shell for a bundle.
   *
   * @param string $entity_type_id
   *   The entity type ID.
   * @param string $bundle_id
   *   The bundle ID.
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The account.
   *
   * @return bool
   *   TRUE when the account can update the bundle.
   */
  protected function bundleEditableByAccount(string $entity_type_id, string $bundle_id, AccountInterface $account): bool {
    $entity_type = $this->entityTypeManager->getDefinition($entity_type_id);
    $bundle_key = $entity_type->getKey('bundle');
    $values = [];
    if ($bundle_key) {
      $values[$bundle_key] = $bundle_id;
    }
    $entity = $this->entityTypeManager->getStorage($entity_type_id)->create($values);
    return $entity->access('update', $account);
  }

  /**
   * Gets the bundle entity type label for a content entity type.
   *
   * @param \Drupal\Core\Entity\ContentEntityTypeInterface $entity_type
   *   The content entity type.
   *
   * @return string
   *   The bundle entity type label.
   */
  protected function getBundleEntityTypeLabel(ContentEntityTypeInterface $entity_type): string {
    $bundle_entity_type_id = $entity_type->getBundleEntityType();
    if ($bundle_entity_type_id && $this->entityTypeManager->hasDefinition($bundle_entity_type_id)) {
      return Unicode::ucfirst((string) $this->entityTypeManager->getDefinition($bundle_entity_type_id)->getPluralLabel());
    }
    return Unicode::ucfirst((string) $entity_type->getBundleLabel());
  }

  /**
   * Gets the cache ID for field discovery.
   *
   * @param string|null $vocabulary_id
   *   The taxonomy vocabulary ID, or NULL for all taxonomy references.
   *
   * @return string
   *   The cache ID.
   */
  protected function getFieldsCacheId(?string $vocabulary_id = NULL): string {
    return 'term_reference:fields:' . ($vocabulary_id ?? 'all');
  }

  /**
   * Checks whether a field targets taxonomy terms.
   *
   * @param \Drupal\field\FieldConfigInterface $field_definition
   *   The field definition.
   *
   * @return bool
   *   TRUE when the field targets taxonomy terms.
   */
  protected function fieldTargetsTaxonomy(FieldConfigInterface $field_definition): bool {
    return $field_definition->getType() === 'entity_reference'
      && $field_definition->getSetting('target_type') === 'taxonomy_term';
  }

  /**
   * Checks whether a field targets a vocabulary.
   *
   * @param \Drupal\field\FieldConfigInterface $field_definition
   *   The field definition.
   * @param string $vocabulary_id
   *   The vocabulary ID.
   *
   * @return bool
   *   TRUE when the field targets the vocabulary.
   */
  protected function fieldTargetsVocabulary(FieldConfigInterface $field_definition, string $vocabulary_id): bool {
    $handler_settings = $field_definition->getSetting('handler_settings');
    $target_bundles = $handler_settings['target_bundles'] ?? [];
    return empty($target_bundles) || in_array($vocabulary_id, $target_bundles);
  }

}
