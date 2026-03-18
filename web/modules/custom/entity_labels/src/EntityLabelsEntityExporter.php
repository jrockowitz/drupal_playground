<?php

declare(strict_types=1);

namespace Drupal\entity_labels;

use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Language\LanguageManagerInterface;

/**
 * Exports entity type and bundle label metadata.
 */
class EntityLabelsEntityExporter implements EntityLabelsEntityExporterInterface {

  /**
   * Constructs an EntityLabelsEntityExporter.
   */
  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly EntityTypeBundleInfoInterface $bundleInfoManager,
    private readonly LanguageManagerInterface $languageManager,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function getHeader(): array {
    return ['langcode', 'entity_type', 'bundle', 'label', 'description', 'help'];
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
      if ($entity_type->getBundleEntityType() === NULL) {
        continue;
      }

      if ($entity_type_id !== NULL && $type_id !== $entity_type_id) {
        continue;
      }

      $storage = $this->entityTypeManager
        ->getStorage($entity_type->getBundleEntityType());

      foreach (array_keys($this->bundleInfoManager->getBundleInfo($type_id)) as $bundle_id) {
        $bundle_entity = $storage->load($bundle_id);
        if ($bundle_entity === NULL) {
          continue;
        }

        $rows[] = [
          'langcode'    => $langcode,
          'entity_type' => $type_id,
          'bundle'      => $bundle_id,
          'label'       => (string) $bundle_entity->label(),
          'description' => (string) ($bundle_entity->get('description') ?? ''),
          'help'        => (string) ($bundle_entity->get('help') ?? ''),
          'notes'       => '',
        ];
      }
    }

    usort($rows, static function (array $a, array $b): int {
      return [$a['entity_type'], $a['bundle']]
        <=> [$b['entity_type'], $b['bundle']];
    });

    return $rows;
  }

  /**
   * {@inheritdoc}
   */
  public function export(
    ?string $entity_type_id = NULL,
    ?string $bundle = NULL,
  ): array {
    $header = $this->getHeader();
    $rows = [[...$header, 'notes']];

    foreach ($this->getData($entity_type_id, $bundle) as $row) {
      $values = array_map(fn(string $col) => (string) ($row[$col] ?? ''), $header);
      $values[] = (string) ($row['notes'] ?? '');
      $rows[] = $values;
    }

    return $rows;
  }

}
