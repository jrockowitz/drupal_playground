<?php

declare(strict_types=1);

namespace Drupal\entity_labels;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\entity_labels\Exception\EntityLabelsCsvParseException;

/**
 * Imports entity type and bundle label metadata from CSV.
 */
class EntityLabelsEntityImporter implements EntityLabelsEntityImporterInterface {

  /**
   * Constructs an EntityLabelsEntityImporter.
   */
  public function __construct(
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

    $required = ['langcode', 'entity_type', 'bundle', 'label', 'description'];
    $missing = array_diff($required, $headers);
    if (!empty($missing)) {
      fclose($handle);
      throw new EntityLabelsCsvParseException(sprintf(
        'Missing required CSV headers: %s',
        implode(', ', $missing),
      ));
    }

    $updated = 0;
    $skipped = 0;
    $errors = [];
    $null_fields = [];

    $column_index = array_flip($headers);
    while ($row = fgetcsv($handle)) {
      // Skip blank rows.
      if (count($row) === 1 && $row[0] === NULL) {
        continue;
      }

      $entity_type_id = $row[$column_index['entity_type']] ?? '';
      $bundle_id = $row[$column_index['bundle']] ?? '';
      $label = $row[$column_index['label']] ?? '';
      $description = $row[$column_index['description']] ?? '';
      $help = $row[$column_index['help'] ?? NULL] ?? '';

      $entity_type = $this->entityTypeManager->getDefinition(
        $entity_type_id,
        FALSE,
      );
      $bundle_entity_type = $entity_type?->getBundleEntityType();

      $bundle_entity = NULL;
      if ($bundle_entity_type !== NULL) {
        /** @var \Drupal\Core\Config\Entity\ConfigEntityInterface|null $bundle_entity */
        $bundle_entity = $this->entityTypeManager
          ->getStorage($bundle_entity_type)
          ->load($bundle_id);
      }

      if (!$bundle_entity) {
        $skipped++;
        $null_fields[] = $entity_type_id . '.' . $bundle_id;
        continue;
      }

      // Label key belongs to the bundle entity type (e.g. 'name' on node_type,
      // not 'title' on node).
      $bundle_entity_type_definition = $this->entityTypeManager->getDefinition(
        $bundle_entity_type,
        FALSE,
      );
      $label_key = $bundle_entity_type_definition?->getKey('label') ?? 'label';
      $bundle_entity->set($label_key, $label);
      $bundle_entity->set('description', $description);
      $bundle_entity->set('help', $help);

      $bundle_entity->save();
      $updated++;
    }

    fclose($handle);

    return [
      'updated' => $updated,
      'skipped' => $skipped,
      'errors' => $errors,
      'null_fields' => $null_fields,
    ];
  }

}
