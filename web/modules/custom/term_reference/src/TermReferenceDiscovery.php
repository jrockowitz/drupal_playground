<?php

namespace Drupal\term_reference;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Cache\CacheTagsInvalidatorInterface;
use Drupal\Core\Entity\ContentEntityTypeInterface;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\field\FieldConfigInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Discovers entity reference fields that can reference taxonomy vocabularies.
 */
class TermReferenceDiscovery implements TermReferenceDiscoveryInterface {

  /**
   * The cache tag used for reference field discovery.
   */
  protected const CACHE_TAG = 'term_reference:reference_fields';

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
  public function getAllReferenceFields(): array {
    return $this->getReferenceFields();
  }

  /**
   * {@inheritdoc}
   */
  public function getReferenceFieldsForVocabulary(string $vocabulary_id): array {
    return $this->getReferenceFields($vocabulary_id);
  }

  /**
   * {@inheritdoc}
   */
  public function getReferenceField(string $vocabulary_id, string $entity_type_id, string $field_name): ?array {
    $fields = $this->getReferenceFieldsForVocabulary($vocabulary_id);
    return $fields[$entity_type_id . '.' . $field_name] ?? NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function clearCachedReferenceFields(): void {
    $this->cacheTagsInvalidator->invalidateTags([static::CACHE_TAG]);
  }

  /**
   * Gets reference fields.
   *
   * @param string|null $vocabulary_id
   *   The taxonomy vocabulary ID, or NULL for all taxonomy references.
   *
   * @return array
   *   Reference fields keyed by entity type ID and field name.
   */
  protected function getReferenceFields(?string $vocabulary_id = NULL): array {
    $cache_id = $this->getReferenceFieldsCacheId($vocabulary_id);
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
            'entity_type_label_plural' => (string) $entity_type->getLabel(),
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
   * Gets the cache ID for reference field discovery.
   *
   * @param string|null $vocabulary_id
   *   The taxonomy vocabulary ID, or NULL for all taxonomy references.
   *
   * @return string
   *   The cache ID.
   */
  protected function getReferenceFieldsCacheId(?string $vocabulary_id = NULL): string {
    return 'term_reference:reference_fields:' . ($vocabulary_id ?? 'all');
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
