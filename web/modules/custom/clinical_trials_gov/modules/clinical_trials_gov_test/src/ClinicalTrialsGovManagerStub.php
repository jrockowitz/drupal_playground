<?php

declare(strict_types=1);

namespace Drupal\clinical_trials_gov_test;

use Drupal\clinical_trials_gov\ClinicalTrialsGovManagerInterface;

/**
 * Stub manager that returns fixture data for testing.
 *
 * Installed by clinical_trials_gov_test to replace clinical_trials_gov.manager
 * in the service container, eliminating live API calls in all test types.
 */
class ClinicalTrialsGovManagerStub implements ClinicalTrialsGovManagerInterface {

  /**
   * Map of NCT ID to fixture filename (without extension).
   */
  protected array $studyFixtureMap = [
    'NCT05088187' => 'study-NCT05088187',
    'NCT05189171' => 'study-NCT05189171',
    'NCT01205711' => 'study-NCT01205711',
  ];

  /**
   * {@inheritdoc}
   */
  public function getStudies(array $parameters): array {
    return $this->loadFixture('studies');
  }

  /**
   * {@inheritdoc}
   */
  public function getVersion(): array {
    return $this->loadFixture('version');
  }

  /**
   * {@inheritdoc}
   */
  public function getStudy(string $nct_id): array {
    $fixture_name = $this->studyFixtureMap[$nct_id] ?? NULL;
    if ($fixture_name === NULL) {
      return [];
    }
    $data = $this->loadFixture($fixture_name);
    return $this->flattenStudy($data);
  }

  /**
   * {@inheritdoc}
   */
  public function getStudyMetadata(): array {
    static $metadata = NULL;
    if ($metadata === NULL) {
      $metadata = $this->flattenMetadata($this->loadFixture('metadata'));
    }
    return $metadata;
  }

  /**
   * {@inheritdoc}
   */
  public function getStudyFieldMetadata(string $index_field): ?array {
    return $this->getStudyMetadata()[$index_field] ?? NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function getEnums(): array {
    return $this->loadFixture('enums');
  }

  /**
   * {@inheritdoc}
   */
  public function getEnum(string $enum_type): array {
    foreach ($this->getEnums() as $enum) {
      if (!is_array($enum)) {
        continue;
      }
      if (($enum['type'] ?? '') === $enum_type) {
        return array_values(array_filter(
          array_map(
            fn($item) => is_array($item) ? ($item['value'] ?? NULL) : (is_string($item) ? $item : NULL),
            is_array($enum['values'] ?? NULL) ? $enum['values'] : []
          ),
          fn($value) => $value !== NULL
        ));
      }
    }
    return [];
  }

  /**
   * Loads and decodes a fixture JSON file by name.
   */
  protected function loadFixture(string $name): array {
    $path = dirname(__DIR__) . '/fixtures/' . $name . '.json';
    if (!file_exists($path)) {
      return [];
    }
    return json_decode(file_get_contents($path), TRUE) ?? [];
  }

  /**
   * Mirrors ClinicalTrialsGovManager::flattenStudy().
   */
  protected function flattenStudy(mixed $data, string $prefix = ''): array {
    if (is_array($data) && !array_is_list($data)) {
      $result = [];
      foreach ($data as $key => $value) {
        $child_key = ($prefix !== '') ? $prefix . '.' . $key : (string) $key;
        $result += $this->flattenStudy($value, $child_key);
      }
      return $result;
    }
    return [$prefix => $data];
  }

  /**
   * Mirrors ClinicalTrialsGovManager::flattenMetadata().
   */
  protected function flattenMetadata(array $items, string $prefix = ''): array {
    $rows = [];
    foreach ($items as $item) {
      if (!is_array($item)) {
        continue;
      }
      $name = (string) ($item['name'] ?? '');
      $key = ($prefix !== '' && $name !== '') ? $prefix . '.' . $name : $name;
      $children = [];
      foreach (($item['children'] ?? []) as $child) {
        if (!is_array($child)) {
          continue;
        }
        $child_name = (string) ($child['name'] ?? '');
        if ($child_name === '') {
          continue;
        }
        $children[] = ($key !== '') ? $key . '.' . $child_name : $child_name;
      }
      $rows[$key] = [
        'key' => $key,
        'name' => $name,
        'piece' => (string) ($item['piece'] ?? ''),
        'title' => (string) ($item['title'] ?? ''),
        'type' => (string) ($item['type'] ?? ''),
        'sourceType' => (string) ($item['sourceType'] ?? ''),
        'description' => (string) ($item['description'] ?? ''),
        'children' => $children,
      ];
      if (!empty($item['children']) && is_array($item['children'])) {
        $rows += $this->flattenMetadata($item['children'], $key);
      }
    }
    return $rows;
  }

}
