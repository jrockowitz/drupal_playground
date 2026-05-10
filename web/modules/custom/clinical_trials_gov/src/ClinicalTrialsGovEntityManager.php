<?php

declare(strict_types=1);

namespace Drupal\clinical_trials_gov;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;

/**
 * Manages wizard-created content types and fields.
 */
class ClinicalTrialsGovEntityManager implements ClinicalTrialsGovEntityManagerInterface {

  /**
   * Constructs a new ClinicalTrialsGovEntityManager.
   */
  public function __construct(
    protected ModuleHandlerInterface $moduleHandler,
    protected ConfigFactoryInterface $configFactory,
    protected EntityTypeManagerInterface $entityTypeManager,
    protected ClinicalTrialsGovPathsManagerInterface $pathsManager,
    protected ClinicalTrialsGovFieldManagerInterface $fieldManager,
    protected ClinicalTrialsGovEntityDisplayManagerInterface $entityDisplayManager,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function createContentType(string $type, string $label, string $description): void {
    $node_type_storage = $this->entityTypeManager->getStorage('node_type');
    if ($node_type_storage->load($type)) {
      return;
    }

    $node_type_storage->create([
      'type' => $type,
      'name' => $label,
      'description' => $description,
      'display_submitted' => FALSE,
    ])->save();
  }

  /**
   * {@inheritdoc}
   */
  public function createFields(string $type, array $fields): void {
    $field_definitions = [];

    foreach ($fields as $path) {
      if (!is_string($path) || !$path) {
        continue;
      }

      $definition = $this->fieldManager->resolveFieldDefinition($path);
      $field_definitions[$path] = $definition;
      if (empty($definition['selectable']) || !empty($definition['group_only'])) {
        continue;
      }

      $field_name = $definition['field_name'];
      if (!FieldStorageConfig::loadByName('node', $field_name)) {
        FieldStorageConfig::create([
          'field_name' => $field_name,
          'entity_type' => 'node',
          'type' => $definition['field_type'],
          'settings' => $definition['storage_settings'],
          'cardinality' => $definition['cardinality'],
          'translatable' => TRUE,
        ])->save();
      }

      if (FieldConfig::loadByName('node', $type, $field_name)) {
        continue;
      }

      FieldConfig::create([
        'field_name' => $field_name,
        'entity_type' => 'node',
        'bundle' => $type,
        'label' => $definition['label'],
        'description' => $definition['description'],
        'required' => !empty($definition['required']),
        'settings' => $definition['instance_settings'],
      ])->save();
    }

    $this->entityDisplayManager->createFieldDisplayComponents($type, $field_definitions);
    $this->entityDisplayManager->createFieldGroups($type, $fields, $field_definitions);
  }

  /**
   * {@inheritdoc}
   */
  public function buildSelectedRows(array $rows, ?string $type = NULL): array {
    $definitions = $this->fieldManager->getAvailableFieldDefinitions();
    $type ??= $this->getConfiguredType();
    $selected_rows = [];

    foreach ($rows as $row) {
      if (!is_array($row) || empty($row['path'])) {
        continue;
      }

      $path = (string) $row['path'];
      $definition = $definitions[$path] ?? $this->fieldManager->getFieldDefinition($path);
      $is_required = !empty($definition['required']) || $this->hasRequiredDescendant($path, $definitions);
      $existing = (($definition['destination_property'] ?? NULL) === 'title')
        || (!empty($definition['field_name']) && FieldConfig::loadByName('node', $type, $definition['field_name']));
      $selected_rows[$path] = $is_required || $existing || !empty($row['selected']);
    }

    return $selected_rows;
  }

  /**
   * {@inheritdoc}
   */
  public function getDisplayedFieldDefinitions(): array {
    $definitions = $this->fieldManager->getAvailableFieldDefinitions();

    foreach (array_keys($definitions) as $path) {
      if ($this->shouldHideFieldRow($path, $definitions) || $this->shouldHideEmptyGroupRow($path, $definitions)) {
        unset($definitions[$path]);
      }
    }

    return $definitions;
  }

  /**
   * {@inheritdoc}
   */
  public function buildFieldMappings(array $selected_rows): array {
    $selected_fields = [];
    $normalized_rows = $this->normalizeSelectedRows($selected_rows);

    foreach (array_keys($normalized_rows) as $path) {
      if (!empty($normalized_rows[$path])) {
        $ancestor_path = $path;
        $last_dot = strrpos($ancestor_path, '.');

        while ($last_dot !== FALSE) {
          $ancestor_path = substr($ancestor_path, 0, $last_dot);
          $ancestor_definition = $this->fieldManager->getFieldDefinition($ancestor_path);

          if ($this->isPromotedCustomFieldDefinition($ancestor_definition)) {
            $selected_fields[(string) $ancestor_definition['field_name']] = $ancestor_path;
            continue 2;
          }

          $last_dot = strrpos($ancestor_path, '.');
        }
      }

      $definition = $this->fieldManager->getFieldDefinition($path);
      $field_name = (string) ($definition['field_name'] ?? '');
      if (!$field_name || empty($definition['available']) || empty($definition['selectable'])) {
        continue;
      }

      if (!empty($definition['group_only'])) {
        if ($this->hasSelectedDescendant($path, $normalized_rows)) {
          $selected_fields[$field_name] = $path;
        }
        continue;
      }

      if (!empty($normalized_rows[$path])) {
        $selected_fields[$field_name] = $path;
      }
    }

    return $selected_fields;
  }

  /**
   * {@inheritdoc}
   */
  public function buildDefaultSelectedRows(?string $type = NULL, ?array $saved_field_mappings = NULL): array {
    $definitions = $this->getDisplayedFieldDefinitions();
    $type ??= $this->getConfiguredType();
    $saved_field_mappings ??= $this->getConfiguredFieldMappings();
    $saved_fields = array_values(array_filter($saved_field_mappings, 'is_string'));
    $selected_rows = [];

    foreach ($definitions as $path => $definition) {
      $is_required = !empty($definition['required']) || $this->hasRequiredDescendant($path, $definitions);
      $existing = (($definition['destination_property'] ?? NULL) === 'title')
        || (!empty($definition['field_name']) && FieldConfig::loadByName('node', $type, $definition['field_name']));
      $selected_rows[$path] = $is_required
        || in_array($path, $saved_fields)
        || !empty($definition['required'])
        || $existing;
    }

    return $selected_rows;
  }

  /**
   * {@inheritdoc}
   */
  public function buildDefaultFieldMappings(?string $type = NULL, ?array $saved_field_mappings = NULL): array {
    return $this->buildFieldMappings($this->buildDefaultSelectedRows($type, $saved_field_mappings));
  }

  /**
   * {@inheritdoc}
   */
  public function saveFieldMappings(array $field_mappings): void {
    $this->configFactory->getEditable('clinical_trials_gov.settings')
      ->set('fields', $field_mappings)
      ->save();
  }

  /**
   * {@inheritdoc}
   */
  public function createConfiguredContentType(): void {
    $type = $this->getConfiguredType();
    if (!$type) {
      return;
    }

    $this->createContentType(
      $type,
      $this->getConfiguredContentTypeLabel($type),
      $this->getConfiguredContentTypeDescription($type),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function createConfiguredFields(): void {
    $type = $this->getConfiguredType();
    $field_mappings = $this->getConfiguredFieldMappings();
    if (!$type || !$field_mappings) {
      return;
    }

    $this->createFields($type, array_values($field_mappings));
  }

  /**
   * {@inheritdoc}
   */
  public function supportsFieldGroups(): bool {
    return $this->moduleHandler->moduleExists('field_group');
  }

  /**
   * {@inheritdoc}
   */
  public function generateFieldName(string $path): string {
    return $this->fieldManager->resolveFieldDefinition($path)['field_name'];
  }

  /**
   * {@inheritdoc}
   */
  public function resolveFieldDefinition(string $path): array {
    return $this->fieldManager->resolveFieldDefinition($path);
  }

  /**
   * {@inheritdoc}
   */
  public function resolveStructuredFieldDefinition(string $path): ?array {
    return $this->fieldManager->resolveStructuredFieldDefinition($path);
  }

  /**
   * Returns the configured destination bundle machine name.
   */
  protected function getConfiguredType(): string {
    return $this->configFactory->get('clinical_trials_gov.settings')->get('type');
  }

  /**
   * Returns saved field mappings from module settings.
   */
  protected function getConfiguredFieldMappings(): array {
    return $this->configFactory->get('clinical_trials_gov.settings')->get('fields');
  }

  /**
   * Returns the configured title metadata path.
   */
  protected function getTitleFieldPath(): string {
    return $this->configFactory->get('clinical_trials_gov.settings')->get('title_path');
  }

  /**
   * Returns the required metadata paths.
   */
  protected function getRequiredPaths(): array {
    return $this->pathsManager->getRequiredPaths();
  }

  /**
   * Returns the label to use for the configured content type.
   */
  protected function getConfiguredContentTypeLabel(string $type): string {
    $node_type = $this->entityTypeManager->getStorage('node_type')->load($type);
    if ($node_type) {
      return $node_type->label();
    }

    return ClinicalTrialsGovEntityManagerInterface::DEFAULT_CONTENT_TYPE_LABEL;
  }

  /**
   * Returns the description to use for the configured content type.
   */
  protected function getConfiguredContentTypeDescription(string $type): string {
    $node_type = $this->entityTypeManager->getStorage('node_type')->load($type);
    if ($node_type) {
      return (string) $node_type->getDescription();
    }

    return ClinicalTrialsGovEntityManagerInterface::DEFAULT_CONTENT_TYPE_DESCRIPTION;
  }

  /**
   * Ensures selected rows include required and title paths.
   */
  protected function normalizeSelectedRows(array $selected_rows): array {
    foreach (array_merge($this->getRequiredPaths(), [$this->getTitleFieldPath()]) as $path) {
      if (!is_string($path) || !$path) {
        continue;
      }
      $selected_rows[$path] = TRUE;
    }

    return $selected_rows;
  }

  /**
   * Determines whether a metadata path has any required descendants.
   */
  protected function hasRequiredDescendant(string $path, array $definitions): bool {
    $prefix = $path . '.';

    foreach ($definitions as $candidate_path => $candidate_definition) {
      if (!str_starts_with($candidate_path, $prefix)) {
        continue;
      }
      if (!empty($candidate_definition['required'])) {
        return TRUE;
      }
    }

    return FALSE;
  }

  /**
   * Determines whether a metadata row has any selected descendants.
   */
  protected function hasSelectedDescendant(string $path, array $selected_rows): bool {
    $prefix = $path . '.';

    foreach ($selected_rows as $candidate_key => $selected) {
      if ($selected && str_starts_with($candidate_key, $prefix)) {
        return TRUE;
      }
    }

    return FALSE;
  }

  /**
   * Returns whether a field definition should collapse child paths into itself.
   */
  protected function isPromotedCustomFieldDefinition(array $definition): bool {
    return !empty($definition['available'])
      && !empty($definition['selectable'])
      && (($definition['field_type'] ?? '') === 'custom')
      && empty($definition['group_only']);
  }

  /**
   * Determines whether a row should be hidden beneath a promoted custom field.
   */
  protected function shouldHideFieldRow(string $path, array $definitions): bool {
    $last_dot = strrpos($path, '.');
    while ($last_dot !== FALSE) {
      $parent_path = substr($path, 0, $last_dot);
      $parent_definition = $definitions[$parent_path] ?? $this->fieldManager->getFieldDefinition($parent_path);
      if ($this->isPromotedCustomFieldDefinition($parent_definition)) {
        return TRUE;
      }
      $path = $parent_path;
      $last_dot = strrpos($parent_path, '.');
    }

    return FALSE;
  }

  /**
   * Determines whether a group-only row has any visible children.
   */
  protected function shouldHideEmptyGroupRow(string $path, array $definitions): bool {
    $definition = $definitions[$path] ?? NULL;
    if (empty($definition['group_only'])) {
      return FALSE;
    }

    $prefix = $path . '.';
    foreach (array_keys($definitions) as $candidate_path) {
      if (!str_starts_with($candidate_path, $prefix)) {
        continue;
      }
      if ($this->shouldHideFieldRow($candidate_path, $definitions)) {
        continue;
      }
      if ($this->shouldHideEmptyGroupRow($candidate_path, $definitions)) {
        continue;
      }
      return FALSE;
    }

    return TRUE;
  }

}
