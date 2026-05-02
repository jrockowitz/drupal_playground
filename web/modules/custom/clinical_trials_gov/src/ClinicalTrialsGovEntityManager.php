<?php

declare(strict_types=1);

namespace Drupal\clinical_trials_gov;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\Display\EntityFormDisplayInterface;
use Drupal\Core\Entity\Display\EntityViewDisplayInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\link\LinkItemInterface;

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
    protected ClinicalTrialsGovStudyManagerInterface $studyManager,
    protected ClinicalTrialsGovPathsManagerInterface $pathsManager,
    protected ClinicalTrialsGovFieldManagerInterface $fieldManager,
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

    foreach ($this->getSystemLinkFieldDefinitions() as $field_name => $definition) {
      $field_definitions[$field_name] = $definition;

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
        'required' => FALSE,
        'settings' => $definition['instance_settings'],
      ])->save();
    }

    $this->createFieldDisplayComponents($type, $field_definitions);
    $this->createFieldGroups($type, $fields);
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
    if (!$type || $field_mappings === []) {
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
  public function getStudyUrlFieldName(): string {
    return $this->buildSystemFieldName('nct_url');
  }

  /**
   * {@inheritdoc}
   */
  public function getStudyApiFieldName(): string {
    return $this->buildSystemFieldName('nct_api');
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
      if (is_string($field) && $field) {
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
        'format_type' => 'details',
        'format_settings' => [
          'label' => $definition['label'],
          'classes' => '',
          'id' => '',
          'open' => TRUE,
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
      if (!$form_display->getComponent($field_name)) {
        $form_display->setComponent($field_name, [
          'type' => $this->getFormDisplayWidget($definition),
          'weight' => $weight,
          'region' => 'content',
        ]);
      }

      if (!$view_display->getComponent($field_name)) {
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
  protected function loadOrCreateFormDisplay(string $type): EntityFormDisplayInterface {
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
  protected function loadOrCreateViewDisplay(string $type): EntityViewDisplayInterface {
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
      'link' => 'link_default',
      'datetime' => 'datetime_default',
      'integer' => 'number',
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
      'link' => 'link',
      'datetime' => 'datetime_default',
      'integer' => 'number_integer',
      'list_string' => 'list_default',
      'text_long' => 'text_default',
      'custom' => 'custom_formatter',
      default => 'string',
    };
  }

  /**
   * Returns the built-in generated Trial link field definitions.
   */
  protected function getSystemLinkFieldDefinitions(): array {
    $definitions = [
      $this->getStudyUrlFieldName() => [
        'field_name' => $this->getStudyUrlFieldName(),
        'label' => 'ClinicalTrials.gov URL',
        'description' => '',
        'field_type' => 'link',
        'storage_settings' => [],
        'instance_settings' => [
          'title' => DRUPAL_DISABLED,
          'link_type' => LinkItemInterface::LINK_EXTERNAL,
        ],
        'cardinality' => 1,
        'selectable' => TRUE,
        'group_only' => FALSE,
      ],
      $this->getStudyApiFieldName() => [
        'field_name' => $this->getStudyApiFieldName(),
        'label' => 'ClinicalTrials.gov API',
        'description' => '',
        'field_type' => 'link',
        'storage_settings' => [],
        'instance_settings' => [
          'title' => DRUPAL_DISABLED,
          'link_type' => LinkItemInterface::LINK_EXTERNAL,
        ],
        'cardinality' => 1,
        'selectable' => TRUE,
        'group_only' => FALSE,
      ],
    ];

    return $definitions;
  }

  /**
   * Returns the configured destination bundle machine name.
   */
  protected function getConfiguredType(): string {
    return (string) $this->configFactory->get('clinical_trials_gov.settings')->get('type');
  }

  /**
   * Returns saved field mappings from module settings.
   */
  protected function getConfiguredFieldMappings(): array {
    return $this->configFactory->get('clinical_trials_gov.settings')->get('fields') ?? [];
  }

  /**
   * Returns the configured title metadata path.
   */
  protected function getTitleFieldPath(): string {
    return (string) $this->configFactory->get('clinical_trials_gov.settings')->get('title_path');
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
   * Determines whether a row should be hidden beneath a promoted custom field.
   */
  protected function shouldHideFieldRow(string $path, array $definitions): bool {
    $last_dot = strrpos($path, '.');
    while ($last_dot !== FALSE) {
      $parent_path = substr($path, 0, $last_dot);
      $parent_definition = $definitions[$parent_path] ?? $this->fieldManager->getFieldDefinition($parent_path);
      if (!empty($parent_definition['available']) && (($parent_definition['field_type'] ?? '') === 'custom') && empty($parent_definition['group_only'])) {
        return TRUE;
      }
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

  /**
   * Builds a generated system field name using the configured prefix.
   */
  protected function buildSystemFieldName(string $suffix): string {
    $field_name = $this->fieldManager->resolveFieldDefinition('protocolSection.identificationModule.nctId')['field_name'];
    $prefix = preg_replace('/nct_id$/', '', $field_name) ?? $field_name;

    return $prefix . $suffix;
  }

  /**
   * Resolves the direct children for a field group.
   */
  protected function resolveFieldGroupChildren(string $path, array $selected_fields): array {
    $metadata = $this->studyManager->getMetadataByPath($path);
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
    $metadata = $this->studyManager->getMetadataByPath($path);
    $parent = (string) ($metadata['parent'] ?? '');
    if (!$parent || !isset($selected_fields[$parent]) || empty($selected_fields[$parent]['group_only'])) {
      return '';
    }

    return (string) $selected_fields[$parent]['field_name'];
  }

}
