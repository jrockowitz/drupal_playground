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
use Drupal\Core\Language\LanguageManagerInterface;
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

      foreach ($bundles_to_process as $bundle_id) {
        $field_definitions = $this->fieldManager->getFieldDefinitions($type_id, $bundle_id);

        foreach ($field_definitions as $field_name => $field_definition) {
          $is_base_field = $field_definition instanceof BaseFieldDefinition
            && !($field_definition instanceof BaseFieldOverride);

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
              'notes'          => 'Field group — default form mode',
            ];
          }
        }
      }
    }

    if ($is_bundle_view) {
      usort($rows, static function (array $a, array $b): int {
        $comparison = strcmp($a['field_name'], $b['field_name']);
        if ($comparison !== 0) {
          return $comparison;
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
      $csv_bundle = $row['bundle'];

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
    $count = 0;
    foreach ($allowed_values as $key => $label) {
      if ($count === 10) {
        $parts[] = '...';
        break;
      }
      $parts[] = $key . '|' . $label;
      $count++;
    }
    return implode(';', $parts);
  }

}
