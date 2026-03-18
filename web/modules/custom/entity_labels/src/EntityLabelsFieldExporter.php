<?php

declare(strict_types=1);

namespace Drupal\entity_labels;

use Drupal\Core\Entity\EntityDisplayRepositoryInterface;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\Field\Entity\BaseFieldOverride;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Component\Utility\Unicode;
use Drupal\field\Entity\FieldStorageConfig;

/**
 * Exports field label metadata across entity types and bundles.
 */
class EntityLabelsFieldExporter implements EntityLabelsFieldExporterInterface {

  /**
   * Constructs an EntityLabelsFieldExporter.
   */
  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly EntityFieldManagerInterface $fieldManager,
    private readonly ModuleHandlerInterface $moduleHandler,
    private readonly EntityDisplayRepositoryInterface $entityDisplayRepository,
    private readonly EntityTypeBundleInfoInterface $bundleInfoManager,
    private readonly LanguageManagerInterface $languageManager,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function getData(
    ?string $entity_type_id = NULL,
    ?string $bundle = NULL,
  ): array {
    $langcode = $this->languageManager->getCurrentLanguage()->getId();
    $is_bundle_view = ($entity_type_id !== NULL && $bundle !== NULL);
    $rows = [];

    foreach ($this->entityTypeManager->getDefinitions() as $type_id => $entity_type) {
      if ($entity_type->getBundleEntityType() === NULL) {
        continue;
      }
      if ($entity_type_id !== NULL && $type_id !== $entity_type_id) {
        continue;
      }

      $bundles_to_process = $is_bundle_view
        ? [$bundle]
        : array_keys($this->bundleInfoManager->getBundleInfo($type_id));

      // For bundle view: pre-build multi-bundle field set and sibling defs.
      $multi_bundle_fields = [];
      $sibling_field_definitions = [];
      if ($is_bundle_view) {
        foreach ($this->fieldManager->getFieldMap()[$type_id] ?? [] as $field_name => $info) {
          if (count($info['bundles']) > 1) {
            $multi_bundle_fields[$field_name] = $info;
          }
        }
        // Pre-load sibling bundle field definitions (for summary row diffs).
        foreach (array_keys($this->bundleInfoManager->getBundleInfo($type_id)) as $sibling) {
          if ($sibling !== $bundle) {
            $sibling_field_definitions[$sibling] = $this->fieldManager
              ->getFieldDefinitions($type_id, $sibling);
          }
        }
      }

      foreach ($bundles_to_process as $bundle_id) {
        $field_definitions = $this->fieldManager->getFieldDefinitions($type_id, $bundle_id);

        foreach ($field_definitions as $field_name => $field_definition) {
          $is_base_field = $field_definition instanceof BaseFieldDefinition
            && !($field_definition instanceof BaseFieldOverride);

          // Summary row — bundle view only, multi-bundle fields only.
          if ($is_bundle_view && isset($multi_bundle_fields[$field_name])) {
            $rows[] = $this->buildSummaryRow(
              $langcode,
              $type_id,
              $field_name,
              $field_definition,
              $sibling_field_definitions,
            );
          }

          $rows[] = [
            'langcode'       => $langcode,
            'entity_type'    => $type_id,
            'bundle'         => $bundle_id,
            'field_name'     => $field_name,
            'field_column'   => '',
            'field_type'     => $field_definition->getType(),
            'label'          => (string) $field_definition->getLabel(),
            'description'    => (string) $field_definition->getDescription(),
            'allowed_values' => $this->serializeAllowedValues(
              $field_definition->getSetting('allowed_values') ?? [],
            ),
            'is_base_field'  => $is_base_field,
            'is_summary_row' => FALSE,
            'notes'          => '',
          ];

          // custom_field 4.x column rows.
          if ($is_bundle_view
            && $field_definition->getType() === 'custom'
            && $this->isCustomFieldInstalled()
          ) {
            $field_settings = $field_definition->getSetting('field_settings') ?? [];
            foreach ($field_settings as $column_name => $column_settings) {
              $rows[] = [
                'langcode'       => $langcode,
                'entity_type'    => $type_id,
                'bundle'         => $bundle_id,
                'field_name'     => $field_name,
                'field_column'   => $column_name,
                'field_type'     => $field_definition->getType(),
                'label'          => $column_settings['label'] ?? '',
                'description'    => $column_settings['description'] ?? '',
                'allowed_values' => '',
                'is_base_field'  => FALSE,
                'is_summary_row' => FALSE,
                'notes'          => '',
              ];
            }
          }
        }

        // field_group rows — bundle view only.
        if ($is_bundle_view && $this->moduleHandler->moduleExists('field_group')) {
          $form_display = $this->entityDisplayRepository
            ->getFormDisplay($type_id, $bundle_id, 'default');
          foreach ($form_display->getThirdPartySettings('field_group') as $group_name => $group_settings) {
            $rows[] = [
              'langcode'       => $langcode,
              'entity_type'    => $type_id,
              'bundle'         => $bundle_id,
              'field_name'     => $group_name,
              'field_column'   => '',
              'field_type'     => 'field_group',
              'label'          => $group_settings['label'] ?? '',
              'description'    => $group_settings['format_settings']['description'] ?? '',
              'allowed_values' => '',
              'is_base_field'  => FALSE,
              'is_summary_row' => FALSE,
              'notes'          => 'Field group — default form mode',
            ];
          }
        }
      }
    }

    if ($is_bundle_view) {
      // Sort by field_name ASC; within a group: summary first, then
      // per-bundle row, then custom_field columns (non-empty field_column).
      usort($rows, static function (array $a, array $b): int {
        $comparison = strcmp($a['field_name'], $b['field_name']);
        if ($comparison !== 0) {
          return $comparison;
        }
        $summary_cmp = (int) $b['is_summary_row'] - (int) $a['is_summary_row'];
        if ($summary_cmp !== 0) {
          return $summary_cmp;
        }
        return strcmp($a['field_column'], $b['field_column']);
      });
    }
    else {
      usort($rows, static function (array $a, array $b): int {
        return [
          $a['entity_type'],
          $a['bundle'],
          $a['field_name'],
        ] <=> [
          $b['entity_type'],
          $b['bundle'],
          $b['field_name'],
        ];
      });
    }

    return $rows;
  }

  /**
   * {@inheritdoc}
   */
  public function getHeader(): array {
    $columns = ['langcode', 'entity_type', 'bundle', 'field_name'];
    if ($this->isCustomFieldInstalled()) {
      $columns[] = 'field_column';
    }
    array_push($columns, 'field_type', 'label', 'description', 'allowed_values');
    return $columns;
  }

  /**
   * {@inheritdoc}
   */
  public function export(
    ?string $entity_type_id = NULL,
    ?string $bundle = NULL,
  ): array {
    $rows = [[...$this->getHeader(), 'notes']];

    foreach ($this->getData($entity_type_id, $bundle) as $row) {
      $csv_bundle = $row['bundle'] ?? '(default / all bundles)';

      $csv_row = [
        $row['langcode'],
        $row['entity_type'],
        $csv_bundle,
        $row['field_name'],
      ];
      if ($this->isCustomFieldInstalled()) {
        $csv_row[] = $row['field_column'];
      }
      array_push(
        $csv_row,
        $row['field_type'],
        $row['label'],
        $row['description'],
        $row['allowed_values'],
        $row['notes'],
      );
      $rows[] = $csv_row;
    }

    return $rows;
  }

  /**
   * Returns TRUE when the custom_field module (4.x) is installed.
   *
   * Detection uses the presence of the attribute class introduced in 4.x
   * (PHP attribute-based plugin discovery replaced annotations in 4.x).
   */
  protected function isCustomFieldInstalled(): bool {
    return $this->moduleHandler->moduleExists('custom_field')
      && class_exists('\Drupal\custom_field\Attribute\CustomFieldType');
  }

  /**
   * Loads a FieldStorageConfig by ID (overridable for testing).
   *
   * @param string $id
   *   The storage config ID (entity_type.field_name).
   *
   * @return \Drupal\field\Entity\FieldStorageConfig|null
   *   The field storage config or NULL.
   */
  protected function loadFieldStorageConfig(string $id): ?FieldStorageConfig {
    return FieldStorageConfig::load($id);
  }

  /**
   * Serializes an allowed-values array to "key|Label;key2|Label2" format.
   *
   * @return string
   *   Semicolon-delimited pairs, or an empty string when there are no values.
   */
  private function serializeAllowedValues(array $allowed_values): string {
    if (empty($allowed_values)) {
      return '';
    }
    $parts = [];
    foreach ($allowed_values as $key => $label) {
      $parts[] = $key . '|' . $label;
    }
    return Unicode::truncate(implode(';', $parts), 500, FALSE, TRUE);
  }

  /**
   * Builds a cross-bundle summary row for a multi-bundle field.
   *
   * @param string $langcode
   *   Current language code.
   * @param string $entity_type_id
   *   Entity type machine name.
   * @param string $field_name
   *   Field machine name.
   * @param \Drupal\Core\Field\FieldDefinitionInterface $field_definition
   *   Field definition for the currently-viewed bundle.
   * @param array $sibling_field_definitions
   *   Map of bundle_id => field definitions for all sibling bundles.
   *
   * @return array
   *   Summary row array.
   */
  private function buildSummaryRow(
    string $langcode,
    string $entity_type_id,
    string $field_name,
    FieldDefinitionInterface $field_definition,
    array $sibling_field_definitions,
  ): array {
    // Resolve storage-level default label.
    $is_base = $field_definition instanceof BaseFieldDefinition
      && !($field_definition instanceof BaseFieldOverride);

    if ($is_base) {
      $base_field_definitions = $this->fieldManager->getBaseFieldDefinitions($entity_type_id);
      $default_label = isset($base_field_definitions[$field_name])
        ? (string) $base_field_definitions[$field_name]->getLabel()
        : (string) $field_definition->getLabel();
    }
    else {
      $storage = $this->loadFieldStorageConfig($entity_type_id . '.' . $field_name);
      $default_label = $storage
        ? (string) $storage->getLabel()
        : (string) $field_definition->getLabel();
    }

    // Check each sibling bundle for label divergence.
    $differs = [];
    foreach ($sibling_field_definitions as $sibling_bundle => $sibling_bundle_defs) {
      if (!isset($sibling_bundle_defs[$field_name])) {
        continue;
      }
      $sibling_label = (string) $sibling_bundle_defs[$field_name]->getLabel();
      if ($sibling_label !== $default_label) {
        $differs[] = $sibling_bundle . ' → \'' . $sibling_label . '\'';
      }
    }

    $notes = 'Default label and description for all instances of this field';
    $label = '';
    $description = '';

    if (!empty($differs)) {
      $label = $default_label;
      if ($is_base) {
        $base_field_definitions = $base_field_definitions ?? $this->fieldManager->getBaseFieldDefinitions($entity_type_id);
        $description = isset($base_field_definitions[$field_name])
          ? (string) $base_field_definitions[$field_name]->getDescription()
          : '';
      }
      else {
        $storage = $storage ?? $this->loadFieldStorageConfig($entity_type_id . '.' . $field_name);
        $description = $storage ? (string) $storage->getDescription() : '';
      }
      $notes .= '. Differs: ' . implode(', ', $differs);
    }

    return [
      'langcode'       => $langcode,
      'entity_type'    => $entity_type_id,
      'bundle'         => NULL,
      'field_name'     => $field_name,
      'field_column'   => '',
      'field_type'     => $field_definition->getType(),
      'label'          => $label,
      'description'    => $description,
      'allowed_values' => '',
      'is_base_field'  => FALSE,
      'is_summary_row' => TRUE,
      'notes'          => $notes,
    ];
  }

}
