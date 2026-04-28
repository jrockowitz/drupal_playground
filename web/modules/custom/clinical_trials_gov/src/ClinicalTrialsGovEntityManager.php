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

  /**
   * ClinicalTrials.gov field key mapped to the node title property.
   */
  protected const TITLE_FIELD_KEY = 'protocolSection.identificationModule.briefTitle';

  /**
   * Supported structure keys mapped to their metadata type.
   */
  protected const STRUCTURE_WHITELIST = [
    'protocolSection.identificationModule.organization' => 'Organization',
    'protocolSection.statusModule.expandedAccessInfo' => 'ExpandedAccessInfo',
    'protocolSection.designModule.enrollmentInfo' => 'EnrollmentInfo',
    'protocolSection.contactsLocationsModule.centralContacts' => 'Contact[]',
    'protocolSection.contactsLocationsModule.locations.contacts' => 'Contact[]',
    'protocolSection.contactsLocationsModule.overallOfficials' => 'Official[]',
    'protocolSection.referencesModule.references' => 'Reference[]',
    'protocolSection.referencesModule.seeAlsoLinks' => 'SeeAlsoLink[]',
    'protocolSection.referencesModule.availIpds' => 'AvailIpd[]',
  ];

  public function __construct(
    protected ClinicalTrialsGovManagerInterface $manager,
    protected ClinicalTrialsGovNamesInterface $names,
    protected ModuleHandlerInterface $moduleHandler,
    protected EntityTypeManagerInterface $entityTypeManager,
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

      $definition = $this->resolveFieldDefinition($path);
      $field_definitions[$path] = $definition;
      if (empty($definition['selectable']) || !empty($definition['destination_property']) || !empty($definition['group_only'])) {
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
    return $this->names->getFieldName($this->getPiece($path));
  }

  /**
   * {@inheritdoc}
   */
  public function resolveFieldDefinition(string $path): array {
    $metadata = $this->getMetadata($path);
    $piece = $this->getPiece($path, $metadata);
    $type = $metadata['type'] ?? '';
    $source_type = $metadata['sourceType'] ?? '';
    $is_enum = !empty($metadata['isEnum']);
    $max_chars = $metadata['maxChars'] ?? NULL;
    $is_multi = str_ends_with($type, '[]');
    $cardinality = $is_multi ? -1 : 1;
    $definition = [
      'path' => $path,
      'label' => $this->names->getDisplayLabel($piece),
      'field_name' => $this->generateFieldName($path),
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

    if ($path === self::TITLE_FIELD_KEY) {
      $definition['destination_property'] = 'title';
      $definition['type_label'] = 'title';
      $definition['display_type_label'] = 'title';
      return $definition;
    }

    if ($source_type === 'STRUCT') {
      $structured_definition = $this->resolveStructuredFieldDefinition($path);
      if ($structured_definition !== NULL) {
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
        'allowed_values' => $this->manager->getEnumAsAllowedValues($type),
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

    $is_long_text = ($source_type === 'MARKUP') || ($max_chars !== NULL && $max_chars > 255);
    $definition['field_type'] = $is_long_text ? 'text_long' : 'string';
    $definition['type_label'] = $definition['field_type'];
    $definition['display_type_label'] = $this->buildDisplayTypeLabel($definition['field_type'], $cardinality);

    return $definition;
  }

  /**
   * {@inheritdoc}
   */
  public function resolveStructuredFieldDefinition(string $path): ?array {
    $metadata = $this->getMetadata($path);
    if ($this->isSimpleCustomFieldStruct($metadata)) {
      return $this->buildCustomFieldDefinition($path, $metadata);
    }

    if (!array_key_exists($path, self::STRUCTURE_WHITELIST)) {
      return NULL;
    }

    return $this->buildCustomFieldDefinition($path, $metadata);
  }

  /**
   * Resolves a custom-field definition from a struct metadata row.
   */
  protected function buildCustomFieldDefinition(string $path, array $metadata): ?array {
    $children = $metadata['children'] ?? [];
    $columns = [];
    $field_settings = [];
    $details = [];
    $parent_piece = (string) ($metadata['piece'] ?? '');

    foreach ($children as $child_key) {
      if (!is_string($child_key)) {
        continue;
      }
      $child_metadata = $this->getMetadata($child_key);
      if (($child_metadata['sourceType'] ?? '') === 'STRUCT') {
        continue;
      }
      $column_definition = $this->buildCustomFieldColumnDefinition($child_key, $child_metadata);
      if ($column_definition === NULL) {
        continue;
      }
      $column_name = $column_definition['column_name'];
      $columns[$column_name] = $column_definition['storage'];
      $field_settings[$column_name] = $column_definition['instance'];
      $details[] = $this->names->getDetailLabel((string) ($child_metadata['piece'] ?? $child_metadata['name'] ?? ''), $parent_piece);
    }

    if ($columns === []) {
      return NULL;
    }

    return [
      'field_type' => 'custom',
      'storage_settings' => [
        'columns' => $columns,
      ],
      'instance_settings' => [
        'field_settings' => $field_settings,
      ],
      'type_label' => 'custom',
      'display_type_label' => $this->buildDisplayTypeLabel('custom field', (str_ends_with((string) ($metadata['type'] ?? ''), '[]') ? -1 : 1)),
      'details' => $details,
    ];
  }

  /**
   * Resolves the preferred study identifier for UI display and field names.
   */
  protected function isSimpleCustomFieldStruct(array $metadata): bool {
    $type = (string) ($metadata['type'] ?? '');
    $children = $metadata['children'] ?? [];

    if ($children === [] || str_ends_with($type, '[]')) {
      return FALSE;
    }

    foreach ($children as $child_key) {
      if (!is_string($child_key)) {
        return FALSE;
      }
      $child_metadata = $this->getMetadata($child_key);
      if (($child_metadata['sourceType'] ?? '') === 'STRUCT') {
        return FALSE;
      }
      if ($this->buildCustomFieldColumnDefinition($child_key, $child_metadata) === NULL) {
        return FALSE;
      }
    }

    return TRUE;
  }

  /**
   * Determines whether a nested structure can be represented as a field group.
   */
  protected function supportsNestedFieldGroup(array $metadata): bool {
    return !empty($metadata['children']) && is_array($metadata['children']);
  }

  /**
   * Builds a human-readable field type label for the mapping table.
   */
  protected function buildDisplayTypeLabel(string $type_label, int $cardinality): string {
    return ($cardinality === -1) ? ($type_label . ' (multiple)') : $type_label;
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
        $selected_fields[$field] = $this->resolveFieldDefinition($field);
      }
    }

    $group_definitions = array_filter($selected_fields, static fn(array $definition): bool => !empty($definition['group_only']));
    if ($group_definitions === []) {
      return;
    }

    $form_display_id = 'node.' . $type . '.default';
    $form_display_storage = $this->entityTypeManager->getStorage('entity_form_display');
    $form_display = $form_display_storage->load($form_display_id);
    if ($form_display === NULL) {
      $form_display = $form_display_storage->create([
        'targetEntityType' => 'node',
        'bundle' => $type,
        'mode' => 'default',
        'status' => TRUE,
      ]);
    }

    $view_display_id = 'node.' . $type . '.default';
    $view_display_storage = $this->entityTypeManager->getStorage('entity_view_display');
    $view_display = $view_display_storage->load($view_display_id);
    if ($view_display === NULL) {
      $view_display = $view_display_storage->create([
        'targetEntityType' => 'node',
        'bundle' => $type,
        'mode' => 'default',
        'status' => TRUE,
      ]);
    }

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

    $form_display->save();
    $view_display->save();
    $this->entityTypeManager->getStorage('entity_form_display')->resetCache([$form_display_id]);
    $this->entityTypeManager->getStorage('entity_view_display')->resetCache([$view_display_id]);
  }

  /**
   * Creates default form and view display components for generated fields.
   */
  protected function createFieldDisplayComponents(string $type, array $field_definitions): void {
    $form_display_id = 'node.' . $type . '.default';
    $form_display_storage = $this->entityTypeManager->getStorage('entity_form_display');
    $form_display = $form_display_storage->load($form_display_id);
    if ($form_display === NULL) {
      $form_display = $form_display_storage->create([
        'targetEntityType' => 'node',
        'bundle' => $type,
        'mode' => 'default',
        'status' => TRUE,
      ]);
    }

    $view_display_id = 'node.' . $type . '.default';
    $view_display_storage = $this->entityTypeManager->getStorage('entity_view_display');
    $view_display = $view_display_storage->load($view_display_id);
    if ($view_display === NULL) {
      $view_display = $view_display_storage->create([
        'targetEntityType' => 'node',
        'bundle' => $type,
        'mode' => 'default',
        'status' => TRUE,
      ]);
    }

    $weight = 0;
    foreach ($field_definitions as $definition) {
      if (empty($definition['selectable']) || !empty($definition['destination_property']) || !empty($definition['group_only']) || empty($definition['field_name'])) {
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

    $form_display->save();
    $view_display->save();
    $this->entityTypeManager->getStorage('entity_form_display')->resetCache([$form_display_id]);
    $this->entityTypeManager->getStorage('entity_view_display')->resetCache([$view_display_id]);
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
    $metadata = $this->getMetadata($path);
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
        $children[] = 'title';
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
    $metadata = $this->getMetadata($path);
    $parent = (string) ($metadata['parent'] ?? '');
    if ($parent === '' || !isset($selected_fields[$parent]) || empty($selected_fields[$parent]['group_only'])) {
      return '';
    }

    return (string) $selected_fields[$parent]['field_name'];
  }

  /**
   * Builds a custom-field column definition for a child metadata row.
   */
  protected function buildCustomFieldColumnDefinition(string $child_key, array $metadata): ?array {
    $column_name = (string) ($metadata['name'] ?? basename(str_replace('.', '/', $child_key)));
    $title = (string) ($metadata['title'] ?? $column_name);
    $type = (string) ($metadata['type'] ?? '');
    $source_type = (string) ($metadata['sourceType'] ?? '');
    $is_enum = !empty($metadata['isEnum']);
    $max_chars = $metadata['maxChars'] ?? NULL;

    $storage = [
      'name' => $column_name,
      'type' => 'string',
      'length' => 255,
    ];
    $instance = [
      'label' => $title,
      'check_empty' => FALSE,
      'required' => FALSE,
      'translatable' => FALSE,
      'description' => '',
      'description_display' => 'after',
    ];

    if ($is_enum) {
      $storage['type'] = 'string';
      $instance += [
        'prefix' => '',
        'suffix' => '',
        'allowed_values' => $this->manager->getEnumAsAllowedValues($type, TRUE),
      ];
      return [
        'column_name' => $column_name,
        'storage' => $storage,
        'instance' => $instance,
      ];
    }

    if ($source_type === 'MARKUP' || ($max_chars !== NULL && $max_chars > 255)) {
      $storage = [
        'name' => $column_name,
        'type' => 'string_long',
      ];
      $instance += [
        'formatted' => FALSE,
        'default_format' => 'plain_text',
        'format' => [
          'guidelines' => TRUE,
          'help' => TRUE,
        ],
      ];
      return [
        'column_name' => $column_name,
        'storage' => $storage,
        'instance' => $instance,
      ];
    }

    if ($source_type === 'NUMERIC' || $type === 'integer') {
      $storage = [
        'name' => $column_name,
        'type' => 'integer',
        'unsigned' => FALSE,
        'size' => 'normal',
      ];
      $instance += [
        'allowed_values' => [],
        'min' => NULL,
        'max' => NULL,
      ];
      return [
        'column_name' => $column_name,
        'storage' => $storage,
        'instance' => $instance,
      ];
    }

    if ($source_type === 'BOOLEAN' || $type === 'boolean') {
      return [
        'column_name' => $column_name,
        'storage' => [
          'name' => $column_name,
          'type' => 'boolean',
        ],
        'instance' => $instance,
      ];
    }

    if ($source_type === 'DATE') {
      return [
        'column_name' => $column_name,
        'storage' => [
          'name' => $column_name,
          'type' => 'datetime',
          'datetime_type' => 'date',
        ],
        'instance' => $instance + [
          'timezone_enabled' => FALSE,
        ],
      ];
    }

    if ($column_name === 'url') {
      $storage = [
        'name' => $column_name,
        'type' => 'uri',
      ];
      $instance += [
        'link_type' => 17,
        'field_prefix' => 'default',
        'field_prefix_custom' => '',
      ];
      return [
        'column_name' => $column_name,
        'storage' => $storage,
        'instance' => $instance,
      ];
    }

    $storage = [
      'name' => $column_name,
      'type' => 'string',
      'length' => ($max_chars !== NULL && $max_chars > 0 && $max_chars <= 255) ? $max_chars : 255,
    ];
    $instance += [
      'prefix' => '',
      'suffix' => '',
      'allowed_values' => [],
    ];

    return [
      'column_name' => $column_name,
      'storage' => $storage,
      'instance' => $instance,
    ];
  }

  /**
   * Determines whether a field is required in the wizard UI.
   */
  protected function isRequiredField(string $path): bool {
    return in_array($path, [
      'protocolSection.identificationModule.nctId',
      self::TITLE_FIELD_KEY,
      'protocolSection.descriptionModule.briefSummary',
    ], TRUE);
  }

  /**
   * Returns metadata for one path.
   */
  protected function getMetadata(string $path): array {
    return $this->manager->getMetadataByPath()[$path] ?? [];
  }

  /**
   * Returns the preferred piece for one metadata path.
   */
  protected function getPiece(string $path, array $metadata = []): string {
    if ($metadata === []) {
      $metadata = $this->getMetadata($path);
    }
    $piece = (string) ($metadata['piece'] ?? '');
    return ($piece !== '') ? $piece : $path;
  }

}
