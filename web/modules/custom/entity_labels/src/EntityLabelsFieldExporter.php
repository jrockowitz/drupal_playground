<?php

declare(strict_types=1);

namespace Drupal\entity_labels;

use Drupal\Component\Utility\SortArray;
use Drupal\Core\Entity\EntityDisplayRepositoryInterface;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\field\Entity\FieldConfig;

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
  public function getData(
    ?string $entity_type_id = NULL,
    ?string $bundle = NULL,
  ): array {
    $langcode = $this->languageManager->getCurrentLanguage()->getId();

    // Bundle view: form-display-ordered rows for a single bundle.
    if ($entity_type_id !== NULL && $bundle !== NULL) {
      return $this->getBundleData($entity_type_id, $bundle, $langcode);
    }

    $rows = [];

    if ($entity_type_id !== NULL) {
      // Entity-type view: all bundles for one entity type.
      $rows = $this->getEntityTypeData($entity_type_id, $langcode);
    }
    else {
      // Global view: all entity types that have a dedicated bundle entity type.
      foreach ($this->entityTypeManager->getDefinitions() as $type_id => $entity_type) {
        if ($entity_type->getBundleEntityType() === NULL) {
          continue;
        }
        array_push($rows, ...$this->getEntityTypeData($type_id, $langcode));
      }
    }

    $this->sortData($rows);
    return $rows;
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
   * Returns one or more rows for a single FieldConfig.
   *
   * Returns the field row plus any custom_field sub-column rows.
   *
   * @return array[]
   *   One or more row arrays.
   */
  private function getFieldRows(
    FieldConfig $field_definition,
    string $entity_type_id,
    string $bundle_id,
    string $langcode,
    string $notes = '',
  ): array {
    $field_name = $field_definition->getName();
    $rows = [];

    $rows[] = [
      'langcode'       => $langcode,
      'entity_type'    => $entity_type_id,
      'bundle'         => $bundle_id,
      'field_name'     => $field_name,
      'field_column'   => '',
      'field_type'     => $field_definition->getType(),
      'label'          => (string) $field_definition->getLabel(),
      'description'    => (string) $field_definition->getDescription(),
      'allowed_values' => $this->serializeAllowedValues(
        $field_definition->getSetting('allowed_values') ?? [],
      ),
      'notes'          => $notes,
    ];

    if ($field_definition->getType() === 'custom' && $this->isCustomFieldInstalled()) {
      foreach ($field_definition->getSetting('field_settings') ?? [] as $column_name => $column_settings) {
        $rows[] = [
          'langcode'       => $langcode,
          'entity_type'    => $entity_type_id,
          'bundle'         => $bundle_id,
          'field_name'     => $field_name,
          'field_column'   => $column_name,
          'field_type'     => $field_definition->getType(),
          'label'          => $column_settings['label'] ?? '',
          'description'    => $column_settings['description'] ?? '',
          'allowed_values' => '',
          'notes'          => $notes,
        ];
      }
    }

    return $rows;
  }

  /**
   * Builds field rows for all bundles of a single entity type.
   *
   * Rows are returned in arbitrary order; the caller is responsible
   * for sorting.
   *
   * @return array[]
   *   Row arrays ready for getData().
   */
  private function getEntityTypeData(string $entity_type_id, string $langcode): array {
    $rows = [];
    foreach (array_keys($this->bundleInfoManager->getBundleInfo($entity_type_id)) as $bundle_id) {
      $field_definitions = $this->fieldManager->getFieldDefinitions($entity_type_id, $bundle_id);
      foreach ($field_definitions as $field_definition) {
        if (!($field_definition instanceof FieldConfig)) {
          continue;
        }
        array_push($rows, ...$this->getFieldRows($field_definition, $entity_type_id, $bundle_id, $langcode));
      }
    }
    return $rows;
  }

  /**
   * Sorts field rows by entity_type → bundle → field_name → field_column.
   *
   * @param array[] $rows
   *   The rows to sort in place.
   */
  private function sortData(array &$rows): void {
    usort($rows, static function (array $a, array $b): int {
      return [
        $a['entity_type'],
        $a['bundle'],
        $a['field_name'],
        $a['field_column'],
      ] <=> [
        $b['entity_type'],
        $b['bundle'],
        $b['field_name'],
        $b['field_column'],
      ];
    });
  }

  /**
   * Builds form-display-ordered rows for a single bundle.
   *
   * Order: field groups (by weight) → their children (by weight) →
   * ungrouped components (by weight) → fields absent from the form display.
   *
   * @return array[]
   *   Row arrays ready for getData().
   */
  private function getBundleData(
    string $entity_type_id,
    string $bundle_id,
    string $langcode,
  ): array {
    $field_definitions = $this->fieldManager->getFieldDefinitions($entity_type_id, $bundle_id);
    $form_display = $this->entityDisplayRepository->getFormDisplay($entity_type_id, $bundle_id, 'default');
    $components = $form_display->getComponents();
    $rows = [];
    $processed = [];

    if ($this->moduleHandler->moduleExists('field_group')) {
      $field_groups = $form_display->getThirdPartySettings('field_group');
      if ($field_groups) {
        uasort($field_groups, [SortArray::class, 'sortByWeightElement']);

        foreach ($field_groups as $group_name => $group_settings) {
          $rows[] = [
            'langcode'       => $langcode,
            'entity_type'    => $entity_type_id,
            'bundle'         => $bundle_id,
            'field_name'     => $group_name,
            'field_column'   => '',
            'field_type'     => 'field_group',
            'label'          => $group_settings['label'] ?? '',
            'description'    => $group_settings['format_settings']['description'] ?? '',
            'allowed_values' => '',
            'notes'          => 'Field group — default form mode',
          ];

          $children_keys = array_combine($group_settings['children'], $group_settings['children']);
          $children = array_intersect_key($components, $children_keys);
          uasort($children, [SortArray::class, 'sortByWeightElement']);

          foreach (array_keys($children) as $field_name) {
            $definition = $field_definitions[$field_name] ?? NULL;
            if (!($definition instanceof FieldConfig)) {
              continue;
            }
            array_push($rows, ...$this->getFieldRows($definition, $entity_type_id, $bundle_id, $langcode));
            $processed[$field_name] = TRUE;
          }
        }
      }
    }

    // Ungrouped components in the form display, sorted by weight.
    $remaining = array_diff_key($components, $processed);
    uasort($remaining, [SortArray::class, 'sortByWeightElement']);
    foreach (array_keys($remaining) as $field_name) {
      $definition = $field_definitions[$field_name] ?? NULL;
      if (!($definition instanceof FieldConfig)) {
        continue;
      }
      array_push($rows, ...$this->getFieldRows($definition, $entity_type_id, $bundle_id, $langcode));
      $processed[$field_name] = TRUE;
    }

    // Fields absent from the form display go last.
    foreach ($field_definitions as $field_name => $definition) {
      if (!($definition instanceof FieldConfig) || isset($processed[$field_name])) {
        continue;
      }
      array_push($rows, ...$this->getFieldRows(
        $definition,
        $entity_type_id,
        $bundle_id,
        $langcode,
        'Not displayed in default form mode',
      ));
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
   * Serializes an allowed-values array to "Label;Label2" format.
   *
   * @return string
   *   Semicolon-delimited labels, or an empty string when there are no values.
   */
  private function serializeAllowedValues(array $allowed_values): string {
    if (empty($allowed_values)) {
      return '';
    }
    $parts = [];
    $count = 0;
    foreach ($allowed_values as $label) {
      if ($count === 10) {
        $parts[] = '...';
        break;
      }
      $parts[] = (string) $label;
      $count++;
    }
    return implode(';', $parts);
  }

}
