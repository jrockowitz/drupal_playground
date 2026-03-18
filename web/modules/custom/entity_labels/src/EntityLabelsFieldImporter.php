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
    private readonly EntityDisplayRepositoryInterface $entityDisplayRepository,
    private readonly EntityTypeManagerInterface $entityTypeManager,
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

    $col = array_flip($headers);

    // Read all rows before sorting.
    $all_rows = [];
    while (($row = fgetcsv($handle)) !== FALSE) {
      if (count($row) === 1 && $row[0] === NULL) {
        continue;
      }
      $all_rows[] = $row;
    }
    fclose($handle);

    // Re-sort by entity_type, bundle, field_name.
    // '(default / all bundles)' starts with '(' (ASCII 40) which sorts before
    // any alphanumeric bundle name, so summary rows are indexed first.
    usort($all_rows, function (array $a, array $b) use ($col): int {
      return [
        $a[$col['entity_type']] ?? '',
        $a[$col['bundle']] ?? '',
        $a[$col['field_name']] ?? '',
      ] <=> [
        $b[$col['entity_type']] ?? '',
        $b[$col['bundle']] ?? '',
        $b[$col['field_name']] ?? '',
      ];
    });

    // Build defaults map from summary rows.
    $defaults = [];
    foreach ($all_rows as $row) {
      if (($row[$col['bundle']] ?? '') === '(default / all bundles)') {
        $key = ($row[$col['entity_type']] ?? '') . '.' . ($row[$col['field_name']] ?? '');
        $defaults[$key] = [
          'label'       => $row[$col['label']] ?? '',
          'description' => $row[$col['description']] ?? '',
        ];
      }
    }

    $updated = 0;
    $skipped = 0;
    $errors = [];
    $null_fields = [];

    foreach ($all_rows as $row) {
      $entity_type_id = $row[$col['entity_type']] ?? '';
      $bundle_id      = $row[$col['bundle']] ?? '';
      $field_name     = $row[$col['field_name']] ?? '';
      $label          = $row[$col['label']] ?? '';
      $description    = $row[$col['description']] ?? '';
      $field_column   = isset($col['field_column'])
        ? ($row[$col['field_column']] ?? '') : '';
      $field_type     = isset($col['field_type'])
        ? ($row[$col['field_type']] ?? '') : '';

      // Summary rows seed the defaults map only; skip further processing.
      if ($bundle_id === '(default / all bundles)') {
        $skipped++;
        continue;
      }

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

      // custom_field column rows.
      if ($field_column !== '') {
        if (!$this->moduleHandler->moduleExists('custom_field')) {
          $skipped++;
          $errors[] = sprintf(
            'Skipped custom_field column %s on %s.%s.%s: '
            . 'custom_field module not installed.',
            $field_column, $entity_type_id, $bundle_id, $field_name,
          );
          continue;
        }

        if (!$this->isCustomFieldVersion4()) {
          $skipped++;
          $errors[] = sprintf(
            'Skipped custom_field column %s on %s.%s.%s: '
            . 'custom_field 4.x required.',
            $field_column, $entity_type_id, $bundle_id, $field_name,
          );
          continue;
        }

        $field_config = $this->entityTypeManager->getStorage('field_config')->load(
          $entity_type_id . '.' . $bundle_id . '.' . $field_name,
        );
        if ($field_config === NULL) {
          $skipped++;
          $null_fields[] = $entity_type_id . '.' . $bundle_id . '.' . $field_name;
          continue;
        }

        $field_settings = $field_config->getSetting('field_settings') ?? [];
        if (!isset($field_settings[$field_column])) {
          $skipped++;
          $errors[] = sprintf(
            'Skipped custom_field column %s on %s.%s.%s: column not found.',
            $field_column, $entity_type_id, $bundle_id, $field_name,
          );
          continue;
        }

        $field_settings[$field_column]['label']       = $label;
        $field_settings[$field_column]['description'] = $description;
        $field_config->setSetting('field_settings', $field_settings);
        $field_config->save();
        $updated++;
        continue;
      }

      // Standard field row — apply summary-row defaults for empty values.
      $defaults_key = $entity_type_id . '.' . $field_name;
      if ($label === '' && isset($defaults[$defaults_key]['label'])) {
        $label = $defaults[$defaults_key]['label'];
      }
      if ($description === '' && isset($defaults[$defaults_key]['description'])) {
        $description = $defaults[$defaults_key]['description'];
      }

      // Load FieldConfig; fall back to BaseFieldOverride.
      $id = $entity_type_id . '.' . $bundle_id . '.' . $field_name;
      $field_entity = $this->entityTypeManager->getStorage('field_config')->load($id);
      if ($field_entity === NULL) {
        $field_entity = $this->entityTypeManager->getStorage('base_field_override')->load($id);
      }

      if ($field_entity === NULL) {
        $skipped++;
        $null_fields[] = $entity_type_id . '.' . $bundle_id . '.' . $field_name;
        continue;
      }

      // FieldConfig and BaseFieldOverride are config entities; label and
      // description are set directly (config translation is separate).
      $field_entity->setLabel($label);
      $field_entity->setDescription($description);
      $field_entity->save();
      $updated++;
    }

    return [
      'updated'     => $updated,
      'skipped'     => $skipped,
      'errors'      => $errors,
      'null_fields' => $null_fields,
    ];
  }

  /**
   * Returns TRUE when custom_field 4.x is the installed major version.
   *
   * Detection uses the presence of the attribute class introduced in 4.x.
   */
  protected function isCustomFieldVersion4(): bool {
    return class_exists('\Drupal\custom_field\Attribute\CustomFieldType');
  }

}
