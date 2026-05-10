<?php

declare(strict_types=1);

namespace Drupal\clinical_trials_gov\Plugin\search_api\processor;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Field\FieldConfigInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\search_api\Attribute\SearchApiProcessor;
use Drupal\search_api\Datasource\DatasourceInterface;
use Drupal\search_api\Item\ItemInterface;
use Drupal\search_api\Processor\ProcessorPluginBase;
use Drupal\search_api\Processor\ProcessorProperty;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Adds flattened multi-value Search API properties for custom-field values.
 */
#[SearchApiProcessor(
  id: 'clinical_trials_gov_flattened_custom_field_values',
  label: new TranslatableMarkup('ClinicalTrials.gov flattened custom field values'),
  description: new TranslatableMarkup('Exposes derived multi-value string properties for serialized custom-field values.'),
  stages: [
    'add_properties' => 0,
  ],
  hidden: TRUE,
)]
class ClinicalTrialsGovFlattenedCustomFieldValues extends ProcessorPluginBase {

  /**
   * The entity type manager.
   */
  protected ?EntityTypeManagerInterface $entityTypeManager = NULL;

  /**
   * Creates the processor plugin from the service container.
   *
   * @param \Symfony\Component\DependencyInjection\ContainerInterface $container
   *   The service container.
   * @param array $configuration
   *   The plugin configuration.
   * @param mixed $plugin_id
   *   The plugin identifier.
   * @param mixed $plugin_definition
   *   The plugin definition.
   *
   * @return static
   *   The instantiated processor plugin.
   */
  // phpcs:ignore SlevomatCodingStandard.TypeHints.ParameterTypeHint.MissingNativeTypeHint
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    /** @var static $processor */
    $processor = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $processor->setEntityTypeManager($container->get('entity_type.manager'));
    return $processor;
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration(): array {
    return [
      'mappings' => [],
    ] + parent::defaultConfiguration();
  }

  /**
   * Sets the entity type manager.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   *
   * @return $this
   *   The current processor.
   */
  public function setEntityTypeManager(EntityTypeManagerInterface $entity_type_manager): static {
    $this->entityTypeManager = $entity_type_manager;
    return $this;
  }

  /**
   * Gets the entity type manager.
   *
   * @return \Drupal\Core\Entity\EntityTypeManagerInterface
   *   The entity type manager.
   */
  protected function getEntityTypeManager(): EntityTypeManagerInterface {
    if ($this->entityTypeManager instanceof EntityTypeManagerInterface) {
      return $this->entityTypeManager;
    }

    throw new \LogicException('The entity type manager has not been set.');
  }

  /**
   * {@inheritdoc}
   */
  public function getPropertyDefinitions(?DatasourceInterface $datasource = NULL): array {
    if (
      !$datasource
      || ($datasource->getEntityTypeId() !== 'node')
    ) {
      return [];
    }

    $definitions = [];
    foreach ($this->getMappings() as $mapping) {
      $metadata = $this->getMappingMetadata($datasource, $mapping);
      $definitions[$mapping['property_path']] = new ProcessorProperty([
        'label' => $metadata['label'],
        'description' => $metadata['description'],
        'type' => 'string',
        'processor_id' => $this->getPluginId(),
        'is_list' => TRUE,
      ]);
    }

    return $definitions;
  }

  /**
   * {@inheritdoc}
   */
  public function addFieldValues(ItemInterface $item): void {
    $entity = $item->getOriginalObject()->getValue();
    if (!$entity instanceof ContentEntityInterface) {
      return;
    }

    foreach ($this->getMappings() as $mapping) {
      $this->addValuesToProperty(
        $item,
        $mapping['property_path'],
        $this->extractFieldValues($entity, $mapping['field_name'], $mapping['column_name']),
      );
    }
  }

  /**
   * Gets normalized processor mappings.
   *
   * @return array
   *   The normalized mappings.
   */
  protected function getMappings(): array {
    $mappings = $this->configuration['mappings'] ?? [];
    if (!is_array($mappings)) {
      return [];
    }

    $normalized_mappings = [];
    $seen_property_paths = [];
    foreach ($mappings as $mapping) {
      if (!is_array($mapping)) {
        continue;
      }

      $property_path = trim((string) ($mapping['property_path'] ?? ''));
      $field_name = trim((string) ($mapping['field_name'] ?? ''));
      $column_name = trim((string) ($mapping['column_name'] ?? ''));
      if (
        ($property_path === '')
        || ($field_name === '')
        || ($column_name === '')
        || isset($seen_property_paths[$property_path])
      ) {
        continue;
      }

      $seen_property_paths[$property_path] = TRUE;
      $normalized_mappings[] = [
        'property_path' => $property_path,
        'field_name' => $field_name,
        'column_name' => $column_name,
      ];
    }

    return $normalized_mappings;
  }

  /**
   * Gets Search API metadata for one mapping.
   *
   * @param \Drupal\search_api\Datasource\DatasourceInterface $datasource
   *   The datasource.
   * @param array $mapping
   *   The mapping configuration.
   *
   * @return array
   *   The property metadata.
   */
  protected function getMappingMetadata(DatasourceInterface $datasource, array $mapping): array {
    $field_config = $this->loadFieldConfig($datasource, $mapping['field_name']);
    if (!$field_config instanceof FieldConfigInterface) {
      return [
        'label' => $this->buildFallbackLabel($mapping['column_name']),
        'description' => '',
      ];
    }

    $field_settings = $field_config->getSetting('field_settings');
    if (
      !is_array($field_settings)
      || !isset($field_settings[$mapping['column_name']])
      || !is_array($field_settings[$mapping['column_name']])
    ) {
      return [
        'label' => $this->buildFallbackLabel($mapping['column_name']),
        'description' => '',
      ];
    }

    $column_settings = $field_settings[$mapping['column_name']];
    $field_label = trim((string) $field_config->label());
    $label = trim((string) ($column_settings['label'] ?? ''));
    $description = trim((string) ($column_settings['description'] ?? ''));

    return [
      'label' => $this->buildPrefixedLabel($field_label, $label, $mapping['column_name']),
      'description' => $description,
    ];
  }

  /**
   * Loads a custom field config for the configured entity field name.
   *
   * @param \Drupal\search_api\Datasource\DatasourceInterface $datasource
   *   The datasource.
   * @param string $field_name
   *   The field machine name.
   *
   * @return \Drupal\Core\Field\FieldConfigInterface|null
   *   The field config, if available.
   */
  protected function loadFieldConfig(DatasourceInterface $datasource, string $field_name): ?FieldConfigInterface {
    $field_configs = $this->getEntityTypeManager()
      ->getStorage('field_config')
      ->loadByProperties([
        'entity_type' => $datasource->getEntityTypeId(),
        'field_name' => $field_name,
      ]);

    return reset($field_configs) ?: NULL;
  }

  /**
   * Builds a fallback label when field settings are unavailable.
   *
   * @param string $column_name
   *   The custom-field column name.
   *
   * @return string
   *   The fallback label.
   */
  protected function buildFallbackLabel(string $column_name): string {
    return ucwords(str_replace('_', ' ', $column_name));
  }

  /**
   * Builds a prefixed label for the derived Search API property.
   *
   * @param string $field_label
   *   The source custom field label.
   * @param string $column_label
   *   The source custom-field subproperty label.
   * @param string $column_name
   *   The source custom-field subproperty machine name.
   *
   * @return string
   *   The prefixed label.
   */
  protected function buildPrefixedLabel(string $field_label, string $column_label, string $column_name): string {
    $column_label = ($column_label !== '') ? $column_label : $this->buildFallbackLabel($column_name);
    if ($field_label === '') {
      return $column_label;
    }

    return $field_label . ': ' . $column_label;
  }

  /**
   * Extracts normalized string values from one custom-field property.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The entity being indexed.
   * @param string $field_name
   *   The custom field machine name.
   * @param string $property_name
   *   The custom-field property name.
   *
   * @return array
   *   The flattened string values.
   */
  protected function extractFieldValues(ContentEntityInterface $entity, string $field_name, string $property_name): array {
    if (
      !$entity->hasField($field_name)
      || $entity->get($field_name)->isEmpty()
    ) {
      return [];
    }

    $values = [];
    foreach ($entity->get($field_name)->getValue() as $item) {
      if (
        !is_array($item)
        || !array_key_exists($property_name, $item)
      ) {
        continue;
      }

      $values = array_merge($values, $this->normalizeStoredValue($item[$property_name]));
    }

    return array_values(array_unique($values));
  }

  /**
   * Normalizes one stored custom-field value into flat strings.
   *
   * @param mixed $value
   *   The stored value.
   *
   * @return array
   *   The flattened string values.
   */
  protected function normalizeStoredValue(mixed $value): array {
    if (is_resource($value)) {
      $value = stream_get_contents($value) ?: '';
    }

    if (is_string($value)) {
      $value = trim($value);
      if ($value === '') {
        return [];
      }

      $decoded_value = @unserialize($value, ['allowed_classes' => FALSE]);
      if (is_array($decoded_value)) {
        return $this->flattenStringValues($decoded_value);
      }

      return [$value];
    }

    if (is_array($value)) {
      return $this->flattenStringValues($value);
    }

    return [];
  }

  /**
   * Flattens nested arrays into a de-duplicated list of strings.
   *
   * @param array $values
   *   The nested values to flatten.
   *
   * @return array
   *   The flattened string values.
   */
  protected function flattenStringValues(array $values): array {
    $flattened_values = [];

    array_walk_recursive($values, static function (mixed $value) use (&$flattened_values): void {
      if (!is_string($value)) {
        return;
      }

      $value = trim($value);
      if ($value === '') {
        return;
      }

      $flattened_values[] = $value;
    });

    return array_values(array_unique($flattened_values));
  }

  /**
   * Adds normalized values to a derived Search API property.
   *
   * @param \Drupal\search_api\Item\ItemInterface $item
   *   The Search API item.
   * @param string $property_path
   *   The derived property path.
   * @param array $values
   *   The values to add.
   */
  protected function addValuesToProperty(ItemInterface $item, string $property_path, array $values): void {
    if ($values === []) {
      return;
    }

    $fields = $this->getFieldsHelper()
      ->filterForPropertyPath($item->getFields(FALSE), $item->getDatasourceId(), $property_path);

    foreach ($fields as $field) {
      foreach ($values as $value) {
        $field->addValue($value);
      }
    }
  }

}
