<?php

declare(strict_types=1);

namespace Drupal\clinical_trials_gov;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;

/**
 * Manages wizard-created content types and fields.
 */
class ClinicalTrialsGovEntityManager implements ClinicalTrialsGovEntityManagerInterface {

  public function __construct(
    protected ModuleHandlerInterface $moduleHandler,
    protected EntityTypeManagerInterface $entityTypeManager,
    protected ClinicalTrialsGovManagerInterface $manager,
    protected ClinicalTrialsGovFieldManagerInterface $fieldManager,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function createContentType(string $type, string $label, string $description): void {
    $node_type_storage = $this->entityTypeManager->getStorage('node_type');
    if ($node_type_storage->load($type) !== NULL) {
      return;
    }

    $node_type_storage->create([
      'type' => $type,
      'name' => $label,
      'description' => $description,
    ])->save();
  }

  /**
   * {@inheritdoc}
   */
  public function createFields(string $type, array $fields): void {
    $field_definitions = [];

    foreach ($fields as $path) {
      if (!is_string($path) || $path === '') {
        continue;
      }

      $definition = $this->fieldManager->resolveFieldDefinition($path);
      $field_definitions[$path] = $definition;
      if (empty($definition['selectable']) || !empty($definition['group_only'])) {
        continue;
      }

      $field_name = $definition['field_name'];
      if (FieldStorageConfig::loadByName('node', $field_name) === NULL) {
        FieldStorageConfig::create([
          'field_name' => $field_name,
          'entity_type' => 'node',
          'type' => $definition['field_type'],
          'settings' => $definition['storage_settings'],
          'cardinality' => $definition['cardinality'],
          'translatable' => TRUE,
        ])->save();
      }

      if (FieldConfig::loadByName('node', $type, $field_name) !== NULL) {
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

    $this->createFieldDisplayComponents($type, $field_definitions);
    $this->createFieldGroups($type, $fields);
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
   * Creates field groups for selected nested structures.
   */
  protected function createFieldGroups(string $type, array $fields): void {
    if (!$this->supportsFieldGroups()) {
      return;
    }

    $selected_fields = [];
    foreach ($fields as $field) {
      if (is_string($field) && $field !== '') {
        $selected_fields[$field] = $this->fieldManager->resolveFieldDefinition($field);
      }
    }

    $group_definitions = array_filter($selected_fields, static fn(array $definition): bool => !empty($definition['group_only']));
    if ($group_definitions === []) {
      return;
    }

    $form_display = $this->loadOrCreateFormDisplay($type);
    $view_display = $this->loadOrCreateViewDisplay($type);

    foreach ($group_definitions as $path => $definition) {
      $children = $this->resolveFieldGroupChildren($path, $selected_fields);
      if ($children === []) {
        continue;
      }

      $form_display->setThirdPartySetting('field_group', $definition['field_name'], [
        'children' => $children,
        'label' => $definition['label'],
        'parent_name' => $this->resolveParentGroupName($path, $selected_fields),
        'weight' => 0,
        'format_type' => 'details',
        'format_settings' => [
          'label' => $definition['label'],
          'classes' => '',
          'id' => '',
          'open' => TRUE,
          'description' => $definition['description'],
          'required_fields' => FALSE,
          'show_empty_fields' => FALSE,
          'label_as_html' => FALSE,
        ],
        'region' => 'content',
      ]);

      $view_display->setThirdPartySetting('field_group', $definition['field_name'], [
        'children' => $children,
        'label' => $definition['label'],
        'parent_name' => $this->resolveParentGroupName($path, $selected_fields),
        'weight' => 0,
        'format_type' => 'fieldset',
        'format_settings' => [
          'label' => $definition['label'],
          'classes' => '',
          'id' => '',
          'description' => $definition['description'],
          'required_fields' => FALSE,
          'label_as_html' => FALSE,
        ],
        'region' => 'content',
      ]);
    }

    $display_id = 'node.' . $type . '.default';
    $form_display->save();
    $view_display->save();
    $this->entityTypeManager->getStorage('entity_form_display')->resetCache([$display_id]);
    $this->entityTypeManager->getStorage('entity_view_display')->resetCache([$display_id]);
  }

  /**
   * Creates default form and view display components for generated fields.
   */
  protected function createFieldDisplayComponents(string $type, array $field_definitions): void {
    $form_display = $this->loadOrCreateFormDisplay($type);
    $view_display = $this->loadOrCreateViewDisplay($type);

    $weight = 0;
    foreach ($field_definitions as $definition) {
      if (empty($definition['selectable']) || !empty($definition['group_only']) || empty($definition['field_name'])) {
        continue;
      }

      $field_name = $definition['field_name'];
      if ($form_display->getComponent($field_name) === NULL) {
        $form_display->setComponent($field_name, [
          'type' => $this->getFormDisplayWidget($definition),
          'weight' => $weight,
          'region' => 'content',
        ]);
      }

      if ($view_display->getComponent($field_name) === NULL) {
        $view_display->setComponent($field_name, [
          'type' => $this->getViewDisplayFormatter($definition),
          'label' => 'above',
          'weight' => $weight,
          'region' => 'content',
        ]);
      }

      $weight++;
    }

    $display_id = 'node.' . $type . '.default';
    $form_display->save();
    $view_display->save();
    $this->entityTypeManager->getStorage('entity_form_display')->resetCache([$display_id]);
    $this->entityTypeManager->getStorage('entity_view_display')->resetCache([$display_id]);
  }

  /**
   * Loads or creates the default entity form display for a node bundle.
   */
  protected function loadOrCreateFormDisplay(string $type): object {
    $display_id = 'node.' . $type . '.default';
    $storage = $this->entityTypeManager->getStorage('entity_form_display');
    return $storage->load($display_id) ?? $storage->create([
      'targetEntityType' => 'node',
      'bundle' => $type,
      'mode' => 'default',
      'status' => TRUE,
    ]);
  }

  /**
   * Loads or creates the default entity view display for a node bundle.
   */
  protected function loadOrCreateViewDisplay(string $type): object {
    $display_id = 'node.' . $type . '.default';
    $storage = $this->entityTypeManager->getStorage('entity_view_display');
    return $storage->load($display_id) ?? $storage->create([
      'targetEntityType' => 'node',
      'bundle' => $type,
      'mode' => 'default',
      'status' => TRUE,
    ]);
  }

  /**
   * Resolves the default form widget for a generated field.
   */
  protected function getFormDisplayWidget(array $definition): string {
    return match ($definition['field_type']) {
      'boolean' => 'boolean_checkbox',
      'datetime' => 'datetime_default',
      'integer' => 'number',
      'json' => 'json_textarea',
      'list_string' => 'options_select',
      'text_long' => 'text_textarea',
      'custom' => 'custom_stacked',
      default => 'string_textfield',
    };
  }

  /**
   * Resolves the default view formatter for a generated field.
   */
  protected function getViewDisplayFormatter(array $definition): string {
    return match ($definition['field_type']) {
      'boolean' => 'boolean',
      'datetime' => 'datetime_default',
      'integer' => 'number_integer',
      'json' => 'json',
      'list_string' => 'list_default',
      'text_long' => 'text_default',
      'custom' => 'custom_formatter',
      default => 'string',
    };
  }

  /**
   * Resolves the direct children for a field group.
   */
  protected function resolveFieldGroupChildren(string $path, array $selected_fields): array {
    $metadata = $this->manager->getMetadataByPath($path);
    $children = [];

    foreach (($metadata['children'] ?? []) as $child_path) {
      if (!is_string($child_path) || !isset($selected_fields[$child_path])) {
        continue;
      }

      $child_definition = $selected_fields[$child_path];
      if (!empty($child_definition['group_only'])) {
        $children[] = $child_definition['field_name'];
        continue;
      }
      if (($child_definition['destination_property'] ?? NULL) === 'title') {
        if (!empty($child_definition['field_name'])) {
          $children[] = $child_definition['field_name'];
        }
        continue;
      }
      if (!empty($child_definition['field_name'])) {
        $children[] = $child_definition['field_name'];
      }
    }

    return array_values(array_unique($children));
  }

  /**
   * Resolves the parent field-group name for a nested group.
   */
  protected function resolveParentGroupName(string $path, array $selected_fields): string {
    $metadata = $this->manager->getMetadataByPath($path);
    $parent = (string) ($metadata['parent'] ?? '');
    if ($parent === '' || !isset($selected_fields[$parent]) || empty($selected_fields[$parent]['group_only'])) {
      return '';
    }

    return (string) $selected_fields[$parent]['field_name'];
  }

}
