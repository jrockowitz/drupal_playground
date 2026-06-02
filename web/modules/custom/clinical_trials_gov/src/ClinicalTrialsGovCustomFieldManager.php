<?php

declare(strict_types=1);

namespace Drupal\clinical_trials_gov;

use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\link\LinkItemInterface;

/**
 * Builds custom-field definitions for supported structured metadata.
 */
class ClinicalTrialsGovCustomFieldManager implements ClinicalTrialsGovCustomFieldManagerInterface {
  use StringTranslationTrait;

  /**
   * Policy-backed max character overrides missing from study metadata.
   */
  protected const MAX_CHAR_OVERRIDES = [
    'protocolSection.referencesModule.references.citation' => 2000,
  ];

  /**
   * Supported structure keys mapped to their metadata type.
   *
   * CSpell ignore: Ipds, AvailIpd.
   */
  protected const STRUCTURE_WHITELIST = [
    'protocolSection.identificationModule.organization' => 'Organization',
    'protocolSection.statusModule.expandedAccessInfo' => 'ExpandedAccessInfo',
    'protocolSection.designModule.enrollmentInfo' => 'EnrollmentInfo',
    'protocolSection.armsInterventionsModule.armGroups' => 'ArmGroup[]',
    'protocolSection.armsInterventionsModule.interventions' => 'Intervention[]',
    'protocolSection.contactsLocationsModule.centralContacts' => 'Contact[]',
    'protocolSection.contactsLocationsModule.locations' => 'Location[]',
    'protocolSection.contactsLocationsModule.locations.contacts' => 'Contact[]',
    'protocolSection.contactsLocationsModule.overallOfficials' => 'Official[]',
    'protocolSection.referencesModule.references' => 'Reference[]',
    'protocolSection.referencesModule.seeAlsoLinks' => 'SeeAlsoLink[]',
    'protocolSection.referencesModule.availIpds' => 'AvailIpd[]',
  ];

  /**
   * Constructs a new ClinicalTrialsGovCustomFieldManager.
   */
  public function __construct(
    protected ClinicalTrialsGovStudyManagerInterface $studyManager,
    protected ClinicalTrialsGovNamesInterface $names,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function resolveStructuredFieldDefinition(string $path): ?array {
    $metadata = $this->studyManager->getMetadataByPath($path);
    return ($this->supportsCustomFieldStruct($metadata))
      ? $this->buildCustomFieldDefinition($metadata)
      : NULL;
  }

  /**
   * Resolves a custom-field definition from a struct metadata row.
   */
  protected function buildCustomFieldDefinition(array $metadata): ?array {
    $children = $metadata['children'];
    $columns = [];
    $field_settings = [];
    $details = [];
    $field_details = [];
    $yaml_columns = [];
    $source_key_map = [];
    $parent_piece = $metadata['piece'];

    foreach ($children as $child_key) {
      $child_metadata = $this->studyManager->getMetadataByPath($child_key);
      $detail_piece = $this->names->getDetailLabel(
        $child_metadata['piece'] ?? $child_metadata['name'] ?? '',
        $parent_piece
      );
      $column_definition = $this->buildCustomFieldColumnDefinition($child_key, $child_metadata, $detail_piece);
      if (!$column_definition) {
        continue;
      }
      $column_name = $column_definition['column_name'];
      $columns[$column_name] = $column_definition['storage'];
      $field_settings[$column_name] = $column_definition['instance'];
      $details[] = $detail_piece;
      $field_details[] = $this->names->normalizePiece($detail_piece);
      $source_key = $child_metadata['name'] ?? NULL;
      if (is_string($source_key) && $source_key !== '') {
        $source_key_map[$source_key] = $column_name;
      }
      if (!empty($column_definition['yaml_fallback'])) {
        $yaml_columns[] = $column_name;
      }
    }

    if (!$columns) {
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
      'display_type_label' => $this->buildDisplayTypeLabel('custom field', ($metadata['isMultiple'] ? -1 : 1)),
      'details' => $details,
      'field_details' => $field_details,
      'yaml_columns' => $yaml_columns,
      'source_key_map' => $source_key_map,
    ];
  }

  /**
   * Determines whether a struct can be represented as a custom field.
   */
  protected function supportsCustomFieldStruct(array $metadata): bool {
    $path = $metadata['path'];
    if (array_key_exists($path, self::STRUCTURE_WHITELIST)) {
      return TRUE;
    }

    if ($metadata['isMultiple']) {
      return FALSE;
    }

    $children = $metadata['children'];
    if (empty($children)) {
      return FALSE;
    }

    foreach ($children as $child_key) {
      $child_metadata = $this->studyManager->getMetadataByPath($child_key);
      $detail_piece = $this->names->getDetailLabel(
        $child_metadata['piece'] ?? $child_metadata['name'] ?? '',
        $metadata['piece'],
      );
      if ($child_metadata['sourceType'] === 'STRUCT') {
        return FALSE;
      }
      if (!$this->buildCustomFieldColumnDefinition($child_key, $child_metadata, $detail_piece)) {
        return FALSE;
      }
    }

    return TRUE;
  }

  /**
   * Builds a human-readable field type label for the mapping table.
   */
  protected function buildDisplayTypeLabel(string $type_label, int $cardinality): string {
    return ($cardinality === -1)
      ? $type_label . ' (' . $this->t('multiple') . ')'
      : $type_label;
  }

  /**
   * Builds a custom-field column definition for a child metadata row.
   */
  protected function buildCustomFieldColumnDefinition(string $child_key, array $metadata, string $detail_piece): ?array {
    $column_name = $this->names->normalizePiece($detail_piece);
    $title = $metadata['title'] ?? $column_name;
    $type = $metadata['type'];
    $source_type = $metadata['sourceType'];
    $is_enum = !empty($metadata['isEnum']);
    $max_chars = $this->getEffectiveMaxChars($child_key, $metadata);
    $is_multiple = $metadata['isMultiple'];

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

    if ($this->requiresYamlFallback($metadata)) {
      return [
        'column_name' => $column_name,
        'storage' => [
          'name' => $column_name,
          'type' => 'string_long',
        ],
        'instance' => array_replace($instance, [
          'label' => $title . ' (YAML)',
        ]),
        'yaml_fallback' => TRUE,
      ];
    }

    if ($is_multiple && $this->supportsCustomFieldStringArray($source_type, $type, $is_enum)) {
      $instance += [
        'allowed_values' => $is_enum ? $this->studyManager->getEnumAsAllowedValues($type, TRUE) : [],
        'table_empty' => '',
      ];

      return [
        'column_name' => $column_name,
        'storage' => [
          'name' => $column_name,
          'type' => 'map_string',
        ],
        'instance' => $instance,
      ];
    }

    if ($is_enum) {
      $storage['type'] = 'string';
      $instance += [
        'prefix' => '',
        'suffix' => '',
        'allowed_values' => $this->studyManager->getEnumAsAllowedValues($type, TRUE),
      ];
      return [
        'column_name' => $column_name,
        'storage' => $storage,
        'instance' => $instance,
      ];
    }

    if ($source_type === 'MARKUP') {
      $storage = [
        'name' => $column_name,
        'type' => 'string_long',
      ];
      $instance += [
        'formatted' => TRUE,
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

    if ($max_chars !== NULL && $max_chars > 255) {
      return [
        'column_name' => $column_name,
        'storage' => [
          'name' => $column_name,
          'type' => 'string_long',
        ],
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
        'link_type' => LinkItemInterface::LINK_GENERIC,
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
      'length' => ($max_chars !== NULL && $max_chars > 0) ? $max_chars : 255,
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
   * Determines whether one child value should fall back to YAML storage.
   */
  protected function requiresYamlFallback(array $metadata): bool {
    $source_type = $metadata['sourceType'];

    if ($source_type === 'STRUCT') {
      return TRUE;
    }
    if (in_array($source_type, ['TEXT', 'MARKUP', 'NUMERIC', 'BOOLEAN', 'DATE'])) {
      return FALSE;
    }

    $type = $metadata['type'];
    if (in_array($type, ['text', 'boolean', 'integer', 'date', 'long'])) {
      return FALSE;
    }

    if ($metadata['isMultiple']) {
      return FALSE;
    }

    return !in_array($type, [
      'RecruitmentStatus',
      'ContactRole',
      'OfficialRole',
      'GeoName',
      'NormalizedTime',
    ]);
  }

  /**
   * Determines whether an array-valued child can use a map_string column.
   */
  protected function supportsCustomFieldStringArray(string $source_type, string $type, bool $is_enum): bool {
    return $is_enum || in_array($source_type, ['TEXT', 'MARKUP']);
  }

  /**
   * Returns the effective max character limit for one metadata row.
   */
  protected function getEffectiveMaxChars(string $path, array $metadata): ?int {
    $max_chars = self::MAX_CHAR_OVERRIDES[$path] ?? $metadata['maxChars'] ?? NULL;
    return (!is_null($max_chars))
      ? (int) $max_chars
      : NULL;
  }

}
