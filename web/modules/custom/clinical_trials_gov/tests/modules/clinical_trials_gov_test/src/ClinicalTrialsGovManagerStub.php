<?php

declare(strict_types=1);

namespace Drupal\clinical_trials_gov_test;

use Drupal\clinical_trials_gov\ClinicalTrialsGovManagerInterface;

/**
 * Stub ClinicalTrials.gov manager that returns fixture data for testing.
 *
 * Installed by clinical_trials_gov_test to replace clinical_trials_gov.manager
 * in the service container, eliminating live API calls in all test types.
 */
class ClinicalTrialsGovManagerStub implements ClinicalTrialsGovManagerInterface {

  /**
   * Records the study-search parameters passed to getStudies().
   */
  protected array $studiesRequests = [];

  /**
   * Records the NCT IDs passed to getStudy().
   */
  protected array $studyRequests = [];

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
    $this->studiesRequests[] = $parameters;
    $fixture = $this->loadFixture('studies');
    $studies = array_values(array_filter($fixture['studies'] ?? [], 'is_array'));
    $page_token = (string) ($parameters['pageToken'] ?? '');
    $page_size = (int) ($parameters['pageSize'] ?? 10);

    if ($page_token === 'page-2') {
      return [
        'studies' => array_slice($studies, 2),
        'nextPageToken' => NULL,
        'totalCount' => $fixture['totalCount'] ?? count($studies),
      ];
    }

    return [
      'studies' => array_slice($studies, 0, min($page_size, 2)),
      'nextPageToken' => (count($studies) > 2) ? 'page-2' : NULL,
      'totalCount' => $fixture['totalCount'] ?? count($studies),
    ];
  }

  /**
   * Returns the recorded getStudies() requests.
   */
  public function getStudiesRequests(): array {
    return $this->studiesRequests;
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
    $this->studyRequests[] = $nct_id;
    $fixture_name = $this->studyFixtureMap[$nct_id] ?? NULL;
    if ($fixture_name === NULL) {
      return [];
    }
    $data = $this->loadFixture($fixture_name);
    return $this->flattenStudy($data);
  }

  /**
   * Returns the recorded getStudy() requests.
   */
  public function getStudyRequests(): array {
    return $this->studyRequests;
  }

  /**
   * {@inheritdoc}
   */
  public function getMetadataByPath(?string $path = NULL): array {
    static $metadata_by_path = NULL;
    if ($metadata_by_path === NULL) {
      $metadata_by_path = $this->flattenMetadata($this->loadFixture('metadata'));
    }
    if ($path === NULL) {
      return $metadata_by_path;
    }
    return $metadata_by_path[$path] ?? [];
  }

  /**
   * {@inheritdoc}
   */
  public function getMetadataByPiece(?string $piece = NULL): array {
    static $metadata_by_piece = NULL;
    if ($metadata_by_piece === NULL) {
      $metadata_by_piece = [];
      foreach ($this->getMetadataByPath() as $metadata) {
        if (!is_array($metadata)) {
          continue;
        }
        $metadata_piece = (string) ($metadata['piece'] ?? '');
        if ($metadata_piece === '') {
          continue;
        }
        $metadata_by_piece[$metadata_piece] = $metadata;
      }
    }
    if ($piece === NULL) {
      return $metadata_by_piece;
    }
    return $metadata_by_piece[$piece] ?? [];
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
   * {@inheritdoc}
   */
  public function getEnumAsAllowedValues(string $enum_type, bool $key_label = FALSE): array {
    foreach ($this->getEnums() as $enum_definition) {
      if (!is_array($enum_definition) || ($enum_definition['type'] ?? '') !== $enum_type) {
        continue;
      }

      $allowed_values = [];
      foreach (($enum_definition['values'] ?? []) as $value) {
        if (!is_array($value) || !isset($value['value'])) {
          continue;
        }

        $allowed_values[(string) $value['value']] = (string) ($value['legacyValue'] ?? $value['value']);
      }

      if (!$key_label) {
        return $allowed_values;
      }

      $normalized = [];
      foreach ($allowed_values as $key => $label) {
        $normalized[] = [
          'key' => $key,
          'label' => $label,
        ];
      }
      return $normalized;
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
  protected function flattenMetadata(array $items, string $parent = ''): array {
    $rows = [];
    foreach ($items as $item) {
      if (!is_array($item)) {
        continue;
      }
      $name = (string) ($item['name'] ?? '');
      $path = ($parent !== '' && $name !== '') ? $parent . '.' . $name : $name;
      $children = [];
      foreach (($item['children'] ?? []) as $child) {
        if (!is_array($child)) {
          continue;
        }
        $child_name = (string) ($child['name'] ?? '');
        if ($child_name === '') {
          continue;
        }
        $children[] = ($path !== '') ? $path . '.' . $child_name : $child_name;
      }
      $rows[$path] = [
        'path' => $path,
        'parent' => $parent,
        'name' => $name,
        'piece' => (string) ($item['piece'] ?? ''),
        'title' => (string) ($item['title'] ?? ''),
        'type' => (string) ($item['type'] ?? ''),
        'sourceType' => (string) ($item['sourceType'] ?? ''),
        'maxChars' => isset($item['maxChars']) ? (int) $item['maxChars'] : NULL,
        'isEnum' => !empty($item['isEnum']),
        'description' => (string) ($item['description'] ?? ''),
        'children' => $children,
        'rules' => (string) ($item['rules'] ?? ''),
        'altPieceNames' => array_values(array_filter(
          is_array($item['altPieceNames'] ?? NULL) ? $item['altPieceNames'] : [],
          fn(mixed $value): bool => is_string($value) && $value !== ''
        )),
        'synonyms' => !empty($item['synonyms']),
        'dedLinkLabel' => (string) (($item['dedLink']['label'] ?? '')),
        'dedLinkUrl' => (string) (($item['dedLink']['url'] ?? '')),
      ];
      if (!empty($item['children']) && is_array($item['children'])) {
        $rows += $this->flattenMetadata($item['children'], $path);
      }
    }
    return $rows;
  }

}
