<?php

declare(strict_types=1);

namespace Drupal\clinical_trials_gov;

use Drupal\Core\StringTranslation\StringTranslationTrait;

/**
 * Converts ClinicalTrials.gov API study data into Drupal render arrays.
 */
class ClinicalTrialsGovBuilder implements ClinicalTrialsGovBuilderInterface {

  use StringTranslationTrait;

  public function __construct(
    protected ClinicalTrialsGovManagerInterface $manager,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function buildStudy(array $study): array {
    $metadata = $this->manager->getStudyMetadata();
    $nested = $this->nestStudy($study);

    $build = ['#type' => 'container'];
    foreach ($nested as $section_key => $section_data) {
      $build[$section_key] = $this->buildNode($section_key, $section_data, $metadata);
    }
    $build['raw_data'] = $this->buildRawDataTable($study, $metadata);

    return $build;
  }

  /**
   * Re-nests a flat dot-notation array into a nested associative array.
   */
  protected function nestStudy(array $flat): array {
    $nested = [];
    foreach ($flat as $key => $value) {
      $this->setNestedValue($nested, explode('.', $key), $value);
    }
    return $nested;
  }

  /**
   * Sets a value at the given key path inside a nested array.
   */
  protected function setNestedValue(array &$array, array $keys, mixed $value): void {
    $key = array_shift($keys);
    if (empty($keys)) {
      $array[$key] = $value;
      return;
    }
    if (!isset($array[$key]) || !is_array($array[$key])) {
      $array[$key] = [];
    }
    $this->setNestedValue($array[$key], $keys, $value);
  }

  /**
   * Recursively builds a render element for a node in the nested study tree.
   *
   * Nodes with sourceType STRUCT in the metadata become #type => details.
   * All other nodes become #type => item.
   */
  protected function buildNode(string $key, mixed $value, array $metadata): array {
    $field_metadata = $metadata[$key] ?? [];
    $is_struct = ($field_metadata['sourceType'] ?? '') === 'STRUCT';
    $title = $field_metadata['title'] ?? $key;

    if ($is_struct && is_array($value) && !array_is_list($value)) {
      $build = [
        '#type' => 'details',
        '#title' => $this->t('@title', ['@title' => $title]),
        '#open' => TRUE,
      ];
      foreach ($value as $child_key => $child_value) {
        $build[$child_key] = $this->buildNode($key . '.' . $child_key, $child_value, $metadata);
      }
      return $build;
    }

    return [
      '#type' => 'item',
      '#title' => $this->t('@title', ['@title' => $title]),
      '#markup' => $this->renderValue($value),
    ];
  }

  /**
   * Renders a leaf value to a safe HTML string.
   */
  protected function renderValue(mixed $value): string {
    if ($value === NULL) {
      return '—';
    }
    if (is_bool($value)) {
      return (string) ($value ? $this->t('Yes') : $this->t('No'));
    }
    if (is_array($value)) {
      if (empty($value)) {
        return '—';
      }
      if (array_is_list($value)) {
        $items = array_map(
          fn($item) => '<li>' . htmlspecialchars(is_scalar($item) ? (string) $item : json_encode($item)) . '</li>',
          $value
        );
        return '<ul>' . implode('', $items) . '</ul>';
      }
      return htmlspecialchars(json_encode($value));
    }
    return htmlspecialchars((string) $value);
  }

  /**
   * Builds the collapsed raw data table showing all flat key-value pairs.
   */
  protected function buildRawDataTable(array $study, array $metadata): array {
    $rows = [];
    foreach ($study as $key => $value) {
      $field_metadata = $metadata[$key] ?? [];
      $rows[] = [
        htmlspecialchars($key),
        htmlspecialchars($field_metadata['name'] ?? ''),
        htmlspecialchars($field_metadata['piece'] ?? ''),
        htmlspecialchars($field_metadata['title'] ?? ''),
        htmlspecialchars($field_metadata['sourceType'] ?? ''),
        $this->renderValue($value),
      ];
    }
    return [
      '#type' => 'details',
      '#title' => $this->t('Raw data'),
      '#open' => FALSE,
      'table' => [
        '#type' => 'table',
        '#header' => [
          $this->t('Field path'),
          $this->t('Name'),
          $this->t('Piece'),
          $this->t('Title'),
          $this->t('Source type'),
          $this->t('Value'),
        ],
        '#rows' => $rows,
      ],
    ];
  }

}
