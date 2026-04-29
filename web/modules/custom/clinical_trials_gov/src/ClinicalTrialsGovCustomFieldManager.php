<?php

declare(strict_types=1);

namespace Drupal\clinical_trials_gov;

/**
 * Builds custom-field definitions for supported structured metadata.
 */
class ClinicalTrialsGovCustomFieldManager implements ClinicalTrialsGovCustomFieldManagerInterface {
  /**
   * Generic link type for custom_field url columns (LinkItemInterface::LINK_GENERIC).
   */
  protected const LINK_TYPE_GENERIC = 17;

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
    'protocolSection.contactsLocationsModule.centralContacts' => 'Contact[]',
    'protocolSection.contactsLocationsModule.locations' => 'Location[]',
    'protocolSection.contactsLocationsModule.locations.contacts' => 'Contact[]',
    'protocolSection.contactsLocationsModule.overallOfficials' => 'Official[]',
    'protocolSection.referencesModule.references' => 'Reference[]',
    'protocolSection.referencesModule.seeAlsoLinks' => 'SeeAlsoLink[]',
    'protocolSection.referencesModule.availIpds' => 'AvailIpd[]',
  ];

  public function __construct(
    protected ClinicalTrialsGovManagerInterface $manager,
    protected ClinicalTrialsGovNamesInterface $names,
  ) {}

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
    $max_chars = $this->getEffectiveMaxChars($child_key, $metadata);
    $is_multi = str_ends_with($type, '[]');

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

    if ($is_multi && $this->supportsCustomFieldStringArray($source_type, $type, $is_enum)) {
      $instance += [
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
        'allowed_values' => $this->manager->getEnumAsAllowedValues($type, TRUE),
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
        'link_type' => self::LINK_TYPE_GENERIC,
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
   * Determines whether an array-valued child can use a map_string column.
   */
  protected function supportsCustomFieldStringArray(string $source_type, string $type, bool $is_enum): bool {
    if ($is_enum) {
      return TRUE;
    }

    return in_array($source_type, ['TEXT', 'MARKUP']);
  }

  /**
   * Returns the effective max character limit for one metadata row.
   */
  protected function getEffectiveMaxChars(string $path, array $metadata): ?int {
    if (isset(self::MAX_CHAR_OVERRIDES[$path])) {
      return self::MAX_CHAR_OVERRIDES[$path];
    }

    return isset($metadata['maxChars']) ? (int) $metadata['maxChars'] : NULL;
  }

}
