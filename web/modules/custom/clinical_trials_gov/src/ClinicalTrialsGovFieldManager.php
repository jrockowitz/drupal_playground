<?php

declare(strict_types=1);

namespace Drupal\clinical_trials_gov;

use Drupal\Core\Extension\ModuleHandlerInterface;

/**
 * Curates wizard field options from a vetted set of study field indexes.
 */
class ClinicalTrialsGovFieldManager implements ClinicalTrialsGovFieldManagerInterface {

  /**
   * ClinicalTrials.gov field key mapped to the node title property.
   */
  protected const TITLE_FIELD_PATH = 'protocolSection.identificationModule.briefTitle';

  /**
   * Field indexes observed in the two reference studies.
   */
  protected const AVAILABLE_FIELD_KEYS = [
    'annotationSection.annotationModule.unpostedAnnotation.unpostedEvents',
    'annotationSection.annotationModule.unpostedAnnotation.unpostedResponsibleParty',
    'derivedSection.conditionBrowseModule.ancestors',
    'derivedSection.conditionBrowseModule.meshes',
    'derivedSection.interventionBrowseModule.ancestors',
    'derivedSection.interventionBrowseModule.meshes',
    'derivedSection.miscInfoModule.submissionTracking.estimatedResultsFirstSubmitDate',
    'derivedSection.miscInfoModule.submissionTracking.submissionInfos',
    'derivedSection.miscInfoModule.versionHolder',
    'hasResults',
    'protocolSection.armsInterventionsModule.armGroups',
    'protocolSection.armsInterventionsModule.interventions',
    'protocolSection.conditionsModule.conditions',
    'protocolSection.conditionsModule.keywords',
    'protocolSection.contactsLocationsModule.centralContacts',
    'protocolSection.contactsLocationsModule.locations',
    'protocolSection.contactsLocationsModule.overallOfficials',
    'protocolSection.descriptionModule.briefSummary',
    'protocolSection.descriptionModule.detailedDescription',
    'protocolSection.designModule.designInfo.allocation',
    'protocolSection.designModule.designInfo.interventionModel',
    'protocolSection.designModule.designInfo.maskingInfo.masking',
    'protocolSection.designModule.designInfo.maskingInfo.whoMasked',
    'protocolSection.designModule.designInfo.observationalModel',
    'protocolSection.designModule.designInfo.primaryPurpose',
    'protocolSection.designModule.designInfo.timePerspective',
    'protocolSection.designModule.enrollmentInfo.count',
    'protocolSection.designModule.enrollmentInfo.type',
    'protocolSection.designModule.patientRegistry',
    'protocolSection.designModule.phases',
    'protocolSection.designModule.studyType',
    'protocolSection.eligibilityModule.eligibilityCriteria',
    'protocolSection.eligibilityModule.healthyVolunteers',
    'protocolSection.eligibilityModule.maximumAge',
    'protocolSection.eligibilityModule.minimumAge',
    'protocolSection.eligibilityModule.samplingMethod',
    'protocolSection.eligibilityModule.sex',
    'protocolSection.eligibilityModule.stdAges',
    'protocolSection.eligibilityModule.studyPopulation',
    'protocolSection.identificationModule.acronym',
    'protocolSection.identificationModule.briefTitle',
    'protocolSection.identificationModule.nctId',
    'protocolSection.identificationModule.officialTitle',
    'protocolSection.identificationModule.orgStudyIdInfo.id',
    'protocolSection.identificationModule.organization.class',
    'protocolSection.identificationModule.organization.fullName',
    'protocolSection.ipdSharingStatementModule.ipdSharing',
    'protocolSection.outcomesModule.primaryOutcomes',
    'protocolSection.oversightModule.isFdaRegulatedDevice',
    'protocolSection.oversightModule.isFdaRegulatedDrug',
    'protocolSection.oversightModule.oversightHasDmc',
    'protocolSection.sponsorCollaboratorsModule.collaborators',
    'protocolSection.sponsorCollaboratorsModule.leadSponsor.class',
    'protocolSection.sponsorCollaboratorsModule.leadSponsor.name',
    'protocolSection.sponsorCollaboratorsModule.responsibleParty.type',
    'protocolSection.statusModule.completionDateStruct.date',
    'protocolSection.statusModule.completionDateStruct.type',
    'protocolSection.statusModule.expandedAccessInfo.hasExpandedAccess',
    'protocolSection.statusModule.lastKnownStatus',
    'protocolSection.statusModule.lastUpdatePostDateStruct.date',
    'protocolSection.statusModule.lastUpdatePostDateStruct.type',
    'protocolSection.statusModule.lastUpdateSubmitDate',
    'protocolSection.statusModule.overallStatus',
    'protocolSection.statusModule.primaryCompletionDateStruct.date',
    'protocolSection.statusModule.primaryCompletionDateStruct.type',
    'protocolSection.statusModule.startDateStruct.date',
    'protocolSection.statusModule.startDateStruct.type',
    'protocolSection.statusModule.statusVerifiedDate',
    'protocolSection.statusModule.studyFirstPostDateStruct.date',
    'protocolSection.statusModule.studyFirstPostDateStruct.type',
    'protocolSection.statusModule.studyFirstSubmitDate',
    'protocolSection.statusModule.studyFirstSubmitQcDate',
  ];

  /**
   * Required fields for every import configuration.
   */
  protected const REQUIRED_FIELD_KEYS = [
    'protocolSection.identificationModule.nctId',
    'protocolSection.identificationModule.briefTitle',
    'protocolSection.descriptionModule.briefSummary',
  ];

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
   * Cached ordered list of curated field keys.
   */
  protected ?array $availableFieldKeys = NULL;

  public function __construct(
    protected ClinicalTrialsGovManagerInterface $manager,
    protected ClinicalTrialsGovNamesInterface $names,
    protected ModuleHandlerInterface $moduleHandler,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function getRequiredFieldKeys(): array {
    return self::REQUIRED_FIELD_KEYS;
  }

  /**
   * {@inheritdoc}
   */
  public function getAvailableFieldKeysFromQuery(string $query): array {
    if ($this->availableFieldKeys !== NULL) {
      return $this->availableFieldKeys;
    }

    $metadata = $this->manager->getMetadataByPath();
    $available_keys = [];

    foreach (array_merge(self::AVAILABLE_FIELD_KEYS, self::REQUIRED_FIELD_KEYS) as $path) {
      if (!isset($metadata[$path])) {
        continue;
      }
      $available_keys[$path] = TRUE;

      foreach ($this->getAncestorFieldKeys($path, $metadata) as $ancestor_key) {
        $available_keys[$ancestor_key] = TRUE;
      }
    }

    $ordered_keys = [];
    foreach (array_keys($metadata) as $path) {
      if (!isset($available_keys[$path])) {
        continue;
      }
      $ordered_keys[] = $path;
    }

    $this->availableFieldKeys = $ordered_keys;
    return $this->availableFieldKeys;
  }

  /**
   * {@inheritdoc}
   */
  public function getAvailableFieldDefinitionsFromQuery(string $query): array {
    $definitions = [];

    foreach ($this->getAvailableFieldKeysFromQuery($query) as $path) {
      $definitions[$path] = $this->getFieldDefinition($path);
    }

    return $definitions;
  }

  /**
   * {@inheritdoc}
   */
  public function resolveFieldDefinition(string $path): array {
    $metadata = $this->manager->getMetadataByPath($path);
    $piece = (string) ($metadata['piece'] ?? $path);
    $type = $metadata['type'] ?? '';
    $source_type = $metadata['sourceType'] ?? '';
    $is_enum = !empty($metadata['isEnum']);
    $max_chars = $metadata['maxChars'] ?? NULL;
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

    if ($path === self::TITLE_FIELD_PATH) {
      $definition['destination_property'] = 'title';
      $definition['type_label'] = 'title';
      $definition['display_type_label'] = 'title';
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
    $metadata = $this->manager->getMetadataByPath($path);
    if ($this->isSimpleCustomFieldStruct($metadata)) {
      return $this->buildCustomFieldDefinition($metadata);
    }

    if (!array_key_exists($path, self::STRUCTURE_WHITELIST)) {
      return NULL;
    }

    return $this->buildCustomFieldDefinition($metadata);
  }

  /**
   * {@inheritdoc}
   */
  public function getFieldDefinition(string $path): array {
    $definition = $this->resolveFieldDefinition($path);
    $available = in_array($path, $this->getAvailableFieldKeysFromQuery(''));

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
      if (!is_string($path) || $path === '') {
        continue;
      }
      $definitions[$path] = $this->getFieldDefinition($path);
    }

    return $definitions;
  }

  /**
   * Returns ancestor keys for one identifier path.
   */
  protected function getAncestorFieldKeys(string $path, array $metadata): array {
    $ancestor_keys = [];
    $last_dot = strrpos($path, '.');

    while ($last_dot !== FALSE) {
      $path = substr($path, 0, $last_dot);
      if (isset($metadata[$path])) {
        $ancestor_keys[] = $path;
      }
      $last_dot = strrpos($path, '.');
    }

    return array_reverse($ancestor_keys);
  }

  /**
   * Resolves a custom-field definition from a struct metadata row.
   */
  protected function buildCustomFieldDefinition(array $metadata): ?array {
    $children = $metadata['children'] ?? [];
    $columns = [];
    $field_settings = [];
    $details = [];
    $parent_piece = (string) ($metadata['piece'] ?? '');

    foreach ($children as $child_key) {
      if (!is_string($child_key)) {
        continue;
      }
      $child_metadata = $this->manager->getMetadataByPath($child_key);
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
      $child_metadata = $this->manager->getMetadataByPath($child_key);
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
    return in_array($path, self::REQUIRED_FIELD_KEYS);
  }

}
