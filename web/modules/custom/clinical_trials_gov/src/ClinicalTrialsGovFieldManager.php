<?php

declare(strict_types=1);

namespace Drupal\clinical_trials_gov;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;

/**
 * Curates wizard field options from a vetted set of study field indexes.
 */
class ClinicalTrialsGovFieldManager implements ClinicalTrialsGovFieldManagerInterface {

  /**
   * Cached ordered list of configured field keys.
   */
  protected ?array $availableFieldKeys = NULL;

  /**
   * Constructs a new ClinicalTrialsGovFieldManager.
   */
  public function __construct(
    protected ConfigFactoryInterface $configFactory,
    protected ModuleHandlerInterface $moduleHandler,
    protected ClinicalTrialsGovStudyManagerInterface $studyManager,
    protected ClinicalTrialsGovPathsManagerInterface $pathsManager,
    protected ClinicalTrialsGovNamesInterface $names,
    protected ClinicalTrialsGovCustomFieldManagerInterface $customFieldManager,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function getRequiredFieldKeys(): array {
    return $this->pathsManager->getRequiredPaths();
  }

  /**
   * {@inheritdoc}
   */
  public function getAvailableFieldKeys(): array {
    if ($this->availableFieldKeys === NULL) {
      $this->availableFieldKeys = $this->pathsManager->getQueryPaths();
    }

    return $this->availableFieldKeys;
  }

  /**
   * {@inheritdoc}
   */
  public function getAvailableFieldDefinitions(): array {
    $definitions = [];

    foreach ($this->getAvailableFieldKeys() as $path) {
      $definitions[$path] = $this->getFieldDefinition($path);
    }

    return $definitions;
  }

  /**
   * {@inheritdoc}
   */
  public function resolveFieldDefinition(string $path): array {
    $metadata = $this->studyManager->getMetadataByPath($path);
    $piece = (string) ($metadata['piece'] ?? $path);
    $type = $metadata['type'] ?? '';
    $source_type = $metadata['sourceType'] ?? '';
    $is_enum = !empty($metadata['isEnum']);
    $max_chars = isset($metadata['maxChars']) ? (int) $metadata['maxChars'] : NULL;
    $is_multi = str_ends_with($type, '[]');
    $cardinality = $is_multi ? -1 : 1;
    $definition = [
      'path' => $path,
      'label' => $this->names->getDisplayLabel($piece),
      'field_name' => $this->names->getFieldName($piece),
      'description' => (string) ($metadata['description'] ?? ''),
      'piece' => $piece,
      'field_type' => '',
      'storage_settings' => [],
      'instance_settings' => [],
      'cardinality' => $cardinality,
      'destination_property' => NULL,
      'required' => $this->isRequiredField($path),
      'selectable' => TRUE,
      'reason' => '',
      'is_enum' => $is_enum,
      'type_label' => '',
      'display_type_label' => '',
      'group_only' => FALSE,
      'details' => [],
    ];

    if ($path === $this->getTitleFieldPath()) {
      $definition['destination_property'] = 'title';
      $definition['type_label'] = 'title';
      $definition['display_type_label'] = 'title';
    }

    if ($source_type === 'STRUCT') {
      $structured_definition = $this->resolveStructuredFieldDefinition($path);
      if ($structured_definition) {
        return array_merge($definition, $structured_definition);
      }

      $definition['selectable'] = FALSE;
      $definition['reason'] = 'Unsupported structure in Phase 2.';
      $definition['type_label'] = 'unsupported';
      $definition['display_type_label'] = 'unsupported';
      if ($this->supportsFieldGroups() && $this->supportsNestedFieldGroup($metadata)) {
        $definition['field_name'] = $this->names->getGroupName($piece);
        $definition['field_type'] = 'field_group';
        $definition['type_label'] = 'field_group';
        $definition['display_type_label'] = 'field group';
        $definition['group_only'] = TRUE;
        $definition['selectable'] = TRUE;
        $definition['reason'] = '';
      }
      return $definition;
    }

    if ($is_enum) {
      $definition['field_type'] = 'list_string';
      $definition['storage_settings'] = [
        'allowed_values' => $this->studyManager->getEnumAsAllowedValues($type),
        'allowed_values_function' => '',
      ];
      $definition['type_label'] = 'list_string';
      $definition['display_type_label'] = $this->buildDisplayTypeLabel('list_string', $cardinality);
      return $definition;
    }

    if ($source_type === 'BOOLEAN' || $type === 'boolean') {
      $definition['field_type'] = 'boolean';
      $definition['type_label'] = 'boolean';
      $definition['display_type_label'] = $this->buildDisplayTypeLabel('boolean', $cardinality);
      return $definition;
    }

    if ($source_type === 'NUMERIC' || $type === 'integer') {
      $definition['field_type'] = 'integer';
      $definition['type_label'] = 'integer';
      $definition['display_type_label'] = $this->buildDisplayTypeLabel('integer', $cardinality);
      return $definition;
    }

    if ($source_type === 'DATE') {
      $definition['field_type'] = 'datetime';
      $definition['storage_settings'] = ['datetime_type' => 'date'];
      $definition['type_label'] = 'date';
      $definition['display_type_label'] = $this->buildDisplayTypeLabel('date', $cardinality);
      return $definition;
    }

    $definition['field_type'] = ($source_type === 'MARKUP') ? 'text_long' : 'string';
    if ($definition['field_type'] === 'string' && $max_chars) {
      $definition['storage_settings']['max_length'] = (int) $max_chars;
    }
    $definition['type_label'] = $definition['field_type'];
    $definition['display_type_label'] = $this->buildDisplayTypeLabel($definition['field_type'], $cardinality);

    return $definition;
  }

  /**
   * {@inheritdoc}
   */
  public function resolveStructuredFieldDefinition(string $path): ?array {
    return $this->customFieldManager->resolveStructuredFieldDefinition($path);
  }

  /**
   * {@inheritdoc}
   */
  public function getFieldDefinition(string $path): array {
    $definition = $this->resolveFieldDefinition($path);
    $available = in_array($path, $this->getAvailableFieldKeys());

    $definition['available'] = $available;
    if (!$available) {
      $definition['selectable'] = FALSE;
      $definition['reason'] = 'Not included in the vetted field list.';
    }

    return $definition;
  }

  /**
   * {@inheritdoc}
   */
  public function getFieldDefinitions(array $paths): array {
    $definitions = [];

    foreach ($paths as $path) {
      if (!is_string($path) || !$path) {
        continue;
      }
      $definitions[$path] = $this->getFieldDefinition($path);
    }

    return $definitions;
  }

  /**
   * Returns the configured title metadata path.
   */
  protected function getTitleFieldPath(): string {
    return (string) $this->configFactory->get('clinical_trials_gov.settings')->get('title_path');
  }

  /**
   * Determines whether a nested structure can be represented as a field group.
   */
  protected function supportsNestedFieldGroup(array $metadata): bool {
    return !empty($metadata['children']) && is_array($metadata['children']);
  }

  /**
   * Returns whether the field_group module can be used for nested structs.
   */
  protected function supportsFieldGroups(): bool {
    return $this->moduleHandler->moduleExists('field_group');
  }

  /**
   * Builds a human-readable field type label for the mapping table.
   */
  protected function buildDisplayTypeLabel(string $type_label, int $cardinality): string {
    return ($cardinality === -1) ? ($type_label . ' (multiple)') : $type_label;
  }

  /**
   * Determines whether a field is required in the wizard UI.
   */
  protected function isRequiredField(string $path): bool {
    return ($path === $this->getTitleFieldPath()) || in_array($path, $this->getRequiredFieldKeys());
  }

}
