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

  /**
   * Friendly field-name stems keyed by ClinicalTrials.gov piece identifier.
   */
  protected const FIELD_NAMES = [
    'NCTId' => 'nct_id',
    'NCTIdAlias' => 'nct_id_alias',
    'BriefTitle' => 'brief_title',
    'BriefSummary' => 'brief_summary',
    'OfficialTitle' => 'official_title',
    'OrgStudyIdInfo' => 'org_study_id_info',
    'IPDSharingStatement' => 'ipd_sharing_statement',
    'IPDSharingTimeFrame' => 'ipd_sharing_time_frame',
    'IPDSharingAccessCriteria' => 'ipd_sharing_access_criteria',
  ];

  public function __construct(
    protected ClinicalTrialsGovManagerInterface $manager,
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

    foreach ($fields as $api_key) {
      if (!is_string($api_key) || $api_key === '') {
        continue;
      }

      $definition = $this->resolveFieldDefinition($api_key);
      $field_definitions[$api_key] = $definition;
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
  public function generateFieldName(string $api_key): string {
    $metadata = $this->manager->getStudyFieldMetadata($api_key) ?? [];
    $source_name = $this->resolveStudyIdentifier($api_key, $metadata);
    $normalized = $this->normalizeFieldStem($source_name);
    $field_name = 'field_' . $normalized;

    if (strlen($field_name) <= 32) {
      return $field_name;
    }

    $hash = substr(hash('sha256', $api_key), 0, 8);
    $prefix_length = 32 - 1 - strlen($hash);
    $prefix = substr($field_name, 0, $prefix_length);

    return rtrim($prefix, '_') . '_' . $hash;
  }

  /**
   * {@inheritdoc}
   */
  public function resolveFieldDefinition(string $api_key): array {
    $metadata = $this->manager->getStudyFieldMetadata($api_key) ?? [];
    $study_identifier = $this->resolveStudyIdentifier($api_key, $metadata);
    $title = (string) ($metadata['title'] ?? '');
    $type = $metadata['type'] ?? '';
    $source_type = $metadata['sourceType'] ?? '';
    $is_enum = !empty($metadata['isEnum']);
    $max_chars = $metadata['maxChars'] ?? NULL;
    $is_multi = str_ends_with($type, '[]');
    $cardinality = $is_multi ? -1 : 1;
    $definition = [
      'api_key' => $api_key,
      'label' => $this->buildDisplayLabel($title !== '' ? $title : $study_identifier),
      'field_name' => $this->generateFieldName($api_key),
      'description' => (string) ($metadata['description'] ?? ''),
      'piece' => (string) ($metadata['piece'] ?? ''),
      'study_identifier' => $study_identifier,
      'field_type' => '',
      'storage_settings' => [],
      'instance_settings' => [],
      'cardinality' => $cardinality,
      'destination_property' => NULL,
      'required' => $this->isRequiredField($api_key),
      'selectable' => TRUE,
      'reason' => '',
      'is_enum' => $is_enum,
      'type_label' => '',
      'display_type_label' => '',
      'group_only' => FALSE,
      'details' => [],
    ];

    if ($api_key === self::TITLE_FIELD_KEY) {
      $definition['destination_property'] = 'title';
      $definition['type_label'] = 'title';
      $definition['display_type_label'] = 'title';
      return $definition;
    }

    if ($source_type === 'STRUCT') {
      $structured_definition = $this->resolveStructuredFieldDefinition($api_key);
      if ($structured_definition !== NULL) {
        return array_merge($definition, $structured_definition);
      }

      $definition['selectable'] = FALSE;
      $definition['reason'] = 'Unsupported structure in Phase 2.';
      $definition['type_label'] = 'unsupported';
      $definition['display_type_label'] = 'unsupported';
      if ($this->supportsFieldGroups() && $this->supportsNestedFieldGroup($metadata)) {
        $definition['field_name'] = $this->generateGroupName($api_key);
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
        'allowed_values' => $this->resolveAllowedValues($type),
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
  public function resolveStructuredFieldDefinition(string $api_key): ?array {
    $metadata = $this->manager->getStudyFieldMetadata($api_key) ?? [];
    if ($this->isSimpleCustomFieldStruct($metadata)) {
      return $this->buildCustomFieldDefinition($api_key, $metadata);
    }

    if (!array_key_exists($api_key, self::STRUCTURE_WHITELIST)) {
      return NULL;
    }

    return $this->buildCustomFieldDefinition($api_key, $metadata);
  }

  /**
   * Resolves a custom-field definition from a struct metadata row.
   */
  protected function buildCustomFieldDefinition(string $api_key, array $metadata): ?array {
    $children = $metadata['children'] ?? [];
    $columns = [];
    $field_settings = [];
    $details = [];
    $parent_piece = (string) ($metadata['piece'] ?? '');

    foreach ($children as $child_key) {
      if (!is_string($child_key)) {
        continue;
      }
      $child_metadata = $this->manager->getStudyFieldMetadata($child_key) ?? [];
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
      $details[] = $this->buildCustomFieldDetailLabel($child_metadata, $parent_piece);
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
  protected function resolveStudyIdentifier(string $api_key, array $metadata): string {
    $piece = (string) ($metadata['piece'] ?? '');
    return ($piece !== '') ? $piece : $api_key;
  }

  /**
   * Determines whether a struct can be represented as a simple custom field.
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
      $child_metadata = $this->manager->getStudyFieldMetadata($child_key) ?? [];
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
   * Generates a deterministic field-group machine name for an API key.
   */
  protected function generateGroupName(string $api_key): string {
    $metadata = $this->manager->getStudyFieldMetadata($api_key) ?? [];
    $identifier = $this->resolveStudyIdentifier($api_key, $metadata);
    $group_name = 'group_' . $this->normalizeFieldStem($identifier);

    if (strlen($group_name) <= 64) {
      return $group_name;
    }

    $hash = substr(hash('sha256', $api_key), 0, 8);
    $prefix_length = 64 - 1 - strlen($hash);
    $prefix = substr($group_name, 0, $prefix_length);

    return rtrim($prefix, '_') . '_' . $hash;
  }

  /**
   * Normalizes an identifier into a Drupal field-name stem.
   */
  protected function normalizeFieldStem(string $identifier): string {
    if (isset(self::FIELD_NAMES[$identifier])) {
      return self::FIELD_NAMES[$identifier];
    }

    $identifier = preg_replace('/(?<=[A-Z])(?=[A-Z][a-z])/', '_', $identifier) ?? $identifier;
    $identifier = preg_replace('/(?<=[a-z0-9])(?=[A-Z])/', '_', $identifier) ?? $identifier;
    $identifier = strtolower(preg_replace('/[^a-zA-Z0-9]+/', '_', $identifier) ?? '');

    return trim($identifier, '_');
  }

  /**
   * Builds a display label for a custom-field child property.
   */
  protected function buildCustomFieldDetailLabel(array $metadata, string $parent_piece): string {
    $piece = (string) ($metadata['piece'] ?? $metadata['name'] ?? '');
    if ($parent_piece !== '' && str_starts_with($piece, $parent_piece)) {
      $piece = substr($piece, strlen($parent_piece)) ?: $piece;
    }

    return $this->normalizeFieldStem($piece);
  }

  /**
   * Builds a human-readable label from metadata titles and identifiers.
   */
  protected function buildDisplayLabel(string $label): string {
    if ($label === '') {
      return '';
    }

    if (str_contains($label, ' ') || str_contains($label, '-')) {
      return $label;
    }

    $label = preg_replace('/(?<=[A-Z])(?=[A-Z][a-z])/', ' ', $label) ?? $label;
    $label = preg_replace('/(?<=[a-z0-9])(?=[A-Z])/', ' ', $label) ?? $label;

    return trim($label);
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

    foreach ($group_definitions as $api_key => $definition) {
      $children = $this->resolveFieldGroupChildren($api_key, $selected_fields);
      if ($children === []) {
        continue;
      }

      $form_display->setThirdPartySetting('field_group', $definition['field_name'], [
        'children' => $children,
        'label' => $definition['label'],
        'parent_name' => $this->resolveParentGroupName($api_key, $selected_fields),
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
        'parent_name' => $this->resolveParentGroupName($api_key, $selected_fields),
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
  protected function resolveFieldGroupChildren(string $api_key, array $selected_fields): array {
    $metadata = $this->manager->getStudyFieldMetadata($api_key) ?? [];
    $children = [];

    foreach (($metadata['children'] ?? []) as $child_api_key) {
      if (!is_string($child_api_key) || !isset($selected_fields[$child_api_key])) {
        continue;
      }

      $child_definition = $selected_fields[$child_api_key];
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
  protected function resolveParentGroupName(string $api_key, array $selected_fields): string {
    $last_dot = strrpos($api_key, '.');
    if ($last_dot === FALSE) {
      return '';
    }

    $parent_api_key = substr($api_key, 0, $last_dot);
    if (!isset($selected_fields[$parent_api_key]) || empty($selected_fields[$parent_api_key]['group_only'])) {
      return '';
    }

    return (string) $selected_fields[$parent_api_key]['field_name'];
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
        'allowed_values' => $this->normalizeCustomFieldAllowedValues($this->resolveAllowedValues($type)),
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
  protected function isRequiredField(string $api_key): bool {
    return in_array($api_key, [
      'protocolSection.identificationModule.nctId',
      self::TITLE_FIELD_KEY,
      'protocolSection.descriptionModule.briefSummary',
    ], TRUE);
  }

  /**
   * Resolves enum values from the ClinicalTrials.gov enum fixtures.
   */
  protected function resolveAllowedValues(string $enum_type): array {
    foreach ($this->manager->getEnums() as $enum_definition) {
      if (!is_array($enum_definition) || ($enum_definition['type'] ?? '') !== $enum_type) {
        continue;
      }

      $allowed_values = [];
      foreach (($enum_definition['values'] ?? []) as $value) {
        if (!is_array($value) || !isset($value['value'])) {
          continue;
        }
        $allowed_values[(string) $value['value']] = (string) ($value['legacyValue'] ?? $value['value']);
      }
      return $allowed_values;
    }

    return [];
  }

  /**
   * Converts core-style allowed values into custom_field row format.
   */
  protected function normalizeCustomFieldAllowedValues(array $allowed_values): array {
    $normalized = [];
    foreach ($allowed_values as $key => $label) {
      $normalized[] = [
        'key' => $key,
        'label' => $label,
      ];
    }
    return $normalized;
  }

}
