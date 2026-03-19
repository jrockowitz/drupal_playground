<?php

declare(strict_types=1);

namespace Drupal\entity_labels;

use Drupal\Core\Entity\EntityDisplayRepositoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\entity_labels\Exception\EntityLabelsCsvParseException;

/**
 * Imports field label metadata from CSV.
 */
class EntityLabelsFieldImporter implements EntityLabelsFieldImporterInterface {

  /**
   * Constructs an EntityLabelsFieldImporter.
   */
  public function __construct(
    private readonly ModuleHandlerInterface $moduleHandler,
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly EntityDisplayRepositoryInterface $entityDisplayRepository,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function import(string $csv): array {
    $handle = fopen('php://memory', 'r+b');
    if ($handle === FALSE) {
      throw new EntityLabelsCsvParseException('Could not open memory stream.');
    }
    fwrite($handle, $csv);
    rewind($handle);

    $headers = fgetcsv($handle);
    if ($headers === FALSE) {
      fclose($handle);
      throw new EntityLabelsCsvParseException('CSV is empty or malformed.');
    }

    $required = [
      'langcode', 'entity_type', 'bundle', 'field_name', 'label', 'description',
    ];
    $missing = array_diff($required, $headers);
    if (!empty($missing)) {
      fclose($handle);
      throw new EntityLabelsCsvParseException(sprintf(
        'Missing required CSV headers: %s',
        implode(', ', $missing),
      ));
    }

    $column_lookup = array_flip($headers);

    // Read all rows before sorting.
    $rows = [];
    while ($row = fgetcsv($handle)) {
      $rows[] = $row;
    }
    fclose($handle);

    $updated = 0;
    $skipped = 0;
    $errors = [];
    $null_fields = [];

    foreach ($rows as $row) {
      $entity_type_id = $row[$column_lookup['entity_type']] ?? '';
      $bundle_id = $row[$column_lookup['bundle']] ?? '';
      $field_name = $row[$column_lookup['field_name']] ?? '';
      $label = $row[$column_lookup['label']] ?? '';
      $description = $row[$column_lookup['description']] ?? '';
      $field_column = isset($column_lookup['field_column'])
        ? ($row[$column_lookup['field_column']] ?? '') : '';
      $field_type = isset($column_lookup['field_type'])
        ? ($row[$column_lookup['field_type']] ?? '') : '';

      // field_group rows.
      if ($field_type === 'field_group') {
        if (!$this->moduleHandler->moduleExists('field_group')) {
          $skipped++;
          $errors[] = sprintf(
            'Skipped field_group row %s.%s.%s: field_group module not installed.',
            $entity_type_id, $bundle_id, $field_name,
          );
          continue;
        }

        $form_display = $this->entityDisplayRepository
          ->getFormDisplay($entity_type_id, $bundle_id, 'default');
        $groups = $form_display->getThirdPartySettings('field_group');

        if (!isset($groups[$field_name])) {
          $skipped++;
          $errors[] = sprintf(
            'Skipped field_group row: group %s not found on %s.%s.',
            $field_name, $entity_type_id, $bundle_id,
          );
          continue;
        }

        // Update only label and description; never replace the full settings.
        $settings = $groups[$field_name];
        $settings['label'] = $label;
        if (isset($settings['format_settings'])) {
          $settings['format_settings']['description'] = $description;
        }
        $form_display->setThirdPartySetting('field_group', $field_name, $settings);
        $form_display->save();
        $updated++;
        continue;
      }

      $field_config_id = "$entity_type_id.$bundle_id.$field_name";
      $field_config = $this->entityTypeManager
        ->getStorage('field_config')
        ->load($field_config_id);

      // custom_field column rows.
      if ($field_column !== '') {
        if (!$this->moduleHandler->moduleExists('custom_field')) {
          $skipped++;
          $errors[] = sprintf(
            'Skipped custom_field column %s on %s: custom_field module not installed.',
            $field_column, $field_config_id,
          );
          continue;
        }

        if (!$this->isCustomFieldInstalled()) {
          $skipped++;
          $errors[] = sprintf(
            'Skipped custom_field column %s on %s: custom_field 4.x required.',
            $field_column, $field_config_id,
          );
          continue;
        }

        if (!$field_config) {
          $skipped++;
          $null_fields[] = $field_config_id;
          continue;
        }

        $field_settings = $field_config->getSetting('field_settings') ?? [];
        if (!isset($field_settings[$field_column])) {
          $skipped++;
          $errors[] = sprintf(
            'Skipped custom_field column %s on %s: column not found.',
            $field_column, $field_config_id,
          );
          continue;
        }

        $field_settings[$field_column]['label'] = $label;
        $field_settings[$field_column]['description'] = $description;
        $field_config->setSetting('field_settings', $field_settings);
        $field_config->save();
        $updated++;
        continue;
      }

      if (!$field_config) {
        $skipped++;
        $null_fields[] = $field_config_id;
        continue;
      }

      // FieldConfig and BaseFieldOverride are config entities; label and
      // description are set directly (config translation is separate).
      $field_config->setLabel($label);
      $field_config->setDescription($description);
      $field_config->save();
      $updated++;
    }

    return [
      'updated' => $updated,
      'skipped' => $skipped,
      'errors' => $errors,
      'null_fields' => $null_fields,
    ];
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
