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
    private readonly ModuleHandlerInterface $moduleHandler,
    private readonly LanguageManagerInterface $languageManager,
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly EntityTypeBundleInfoInterface $bundleInfoManager,
    private readonly EntityFieldManagerInterface $fieldManager,
    private readonly EntityDisplayRepositoryInterface $entityDisplayRepository,
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

    $rows = [];

    foreach ($this->entityTypeManager->getDefinitions() as $type_id => $entity_type) {
      if (!$entity_type->getBundleEntityType()) {
        continue;
      }

      if (!$entity_type_id || $type_id === $entity_type_id) {
        $bundle_ids = array_keys($this->bundleInfoManager->getBundleInfo($type_id));
        foreach ($bundle_ids as $bundle_id) {
          if (!$bundle || $bundle_id === $bundle) {
            array_push($rows, ...$this->getEntityBundleData($type_id, $bundle_id, $langcode));
          }
        }
      }
    }
    return $rows;
  }

  /**
   * {@inheritdoc}
   */
  public function export(?string $entity_type_id = NULL, ?string $bundle = NULL): array {
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
   * Retrieves data for entity bundles, including field definitions and groups.
   *
   * @param string $entity_type_id
   *   The ID of the entity type for which the bundle data is being retrieved.
   * @param string $bundle_id
   *   The ID of the bundle within the entity type.
   * @param string $langcode
   *   The language code used for retrieving translatable field labels and descriptions.
   *
   * @return array
   *   An array of metadata for fields and field groups, including their form
   *   display configuration and other relevant attributes.
   */
  private function getEntityBundleData(string $entity_type_id, string $bundle_id, string $langcode): array {
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
            'langcode' => $langcode,
            'entity_type' => $entity_type_id,
            'bundle' => $bundle_id,
            'field_name' => $group_name,
            'field_column' => '',
            'field_type' => 'field_group',
            'label' => $group_settings['label'] ?? '',
            'description' => $group_settings['format_settings']['description'] ?? '',
            'allowed_values' => '',
            'notes' => 'Field group — default form mode',
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
   * Returns one or more rows for a single FieldConfig.
   *
   * Returns the field row plus any custom_field sub-column rows.
   *
   * @return array[]
   *   One or more row arrays.
   */
  private function getFieldRows(FieldConfig $field_definition, string $entity_type_id, string $bundle_id, string $langcode, string $notes = ''): array {
    $field_name = $field_definition->getName();
    $field_type = $field_definition->getType();

    $rows = [];

    $rows[] = [
      'langcode' => $langcode,
      'entity_type' => $entity_type_id,
      'bundle' => $bundle_id,
      'field_name' => $field_name,
      'field_column' => '',
      'field_type' => $field_type,
      'label' => (string) $field_definition->getLabel(),
      'description' => (string) $field_definition->getDescription(),
      'allowed_values' => $this->getFieldDefinitionAllowedValues($field_definition),
      'notes' => $notes,
    ];

    if ($field_type === 'custom' && $this->isCustomFieldInstalled()) {
      $field_settings = $field_definition->getSetting('field_settings') ?? [];
      foreach ($field_settings as $column_name => $column_settings) {
        $rows[] = [
          'langcode' => $langcode,
          'entity_type' => $entity_type_id,
          'bundle' => $bundle_id,
          'field_name' => $field_name,
          'field_column' => $column_name,
          'field_type' => $field_type,
          'label' => $column_settings['label'] ?? '',
          'description' => $column_settings['description'] ?? '',
          'allowed_values' => '',
          'notes' => $notes,
        ];
      }
    }

    return $rows;
  }

  /**
   * Retrieves a formatted string of allowed values for a field.
   *
   * @param \Drupal\field\Entity\FieldConfig $field_definition
   *   The field configuration object from which to retrieve allowed values.
   *
   * @return string
   *   A semicolon-separated string of allowed values. If there are more than
   *   10 values, the list will be truncated and "..." will be appended.
   *   Returns an empty string if the field has no allowed values.
   */
  private function getFieldDefinitionAllowedValues(FieldConfig $field_definition): string {
    $field_storage_definition = $field_definition->getFieldStorageDefinition();

    $has_allowed_values = $field_storage_definition->getSetting('allowed_values')
      || $field_storage_definition->getSetting('allowed_values_function');

    if (!$has_allowed_values) {
      return '';
    }

    $allowed_values = options_allowed_values($field_storage_definition);

    $values = [];
    $count = 0;
    foreach ($allowed_values as $label) {
      if ($count === 10) {
        $values[] = '...';
        break;
      }
      $values[] = (string) $label;
      $count++;
    }
    return implode('; ', $values);
  }

  /**
   * Checks whether the custom field module and its associated class are installed.
   *
   * @return bool
   *   TRUE if the 'custom_field' module is installed and the required class
   *   '\Drupal\custom_field\Attribute\CustomFieldType' exists; otherwise, FALSE.
   */
  protected function isCustomFieldInstalled(): bool {
    return $this->moduleHandler->moduleExists('custom_field')
      && class_exists('\Drupal\custom_field\Attribute\CustomFieldType');
  }

}
