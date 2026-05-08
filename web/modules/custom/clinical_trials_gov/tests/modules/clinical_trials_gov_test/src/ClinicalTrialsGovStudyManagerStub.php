<?php

declare(strict_types=1);

namespace Drupal\clinical_trials_gov_test;

use Drupal\clinical_trials_gov\ClinicalTrialsGovStudyManagerInterface;

/**
 * Stub ClinicalTrials.gov manager that returns fixture data for testing.
 *
 * Installed by clinical_trials_gov_test to replace clinical_trials_gov.study_manager
 * in the service container, eliminating live API calls in all test types.
 */
class ClinicalTrialsGovStudyManagerStub implements ClinicalTrialsGovStudyManagerInterface {

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
    $studies = array_values(array_filter($fixture['studies'], 'is_array'));
    $page_token = (string) ($parameters['pageToken'] ?? '');
    $page_size = (int) ($parameters['pageSize'] ?? 10);

    if ($page_token === 'page-2') {
      return [
        'studies' => array_slice($studies, 2),
        'totalCount' => $fixture['totalCount'],
      ];
    }

    $response = [
      'studies' => array_slice($studies, 0, min($page_size, 2)),
      'totalCount' => $fixture['totalCount'],
    ];
    if (count($studies) > 2) {
      $response['nextPageToken'] = 'page-2';
    }

    return $response;
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
        $metadata_piece = $metadata['piece'];
        if (!$metadata_piece) {
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
    $enum_type = str_replace('[]', '', $enum_type);

    foreach ($this->getEnums() as $enum) {
      if ($enum['type'] === $enum_type) {
        return array_column($enum['values'], 'value');
      }
    }
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function getEnumAsAllowedValues(string $enum_type, bool $key_label = FALSE): array {
    $enum_type = str_replace('[]', '', $enum_type);

    foreach ($this->getEnums() as $enum_definition) {
      if ($enum_definition['type'] !== $enum_type) {
        continue;
      }

      $allowed_values = [];
      foreach ($enum_definition['values'] as $value) {
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
      throw new \RuntimeException(sprintf('Missing ClinicalTrials.gov fixture "%s".', $name));
    }
    $fixture = json_decode(file_get_contents($path), TRUE);
    if (!is_array($fixture)) {
      throw new \RuntimeException(sprintf('Invalid ClinicalTrials.gov fixture "%s".', $name));
    }
    return $fixture;
  }

  /**
   * Mirrors ClinicalTrialsGovStudyManager::flattenStudy().
   */
  protected function flattenStudy(mixed $data, string $prefix = ''): array {
    if (is_array($data) && !array_is_list($data)) {
      $result = [];
      foreach ($data as $key => $value) {
        $child_key = ($prefix) ? $prefix . '.' . $key : (string) $key;
        $result += $this->flattenStudy($value, $child_key);
      }
      return $result;
    }

    if (is_array($data) && array_is_list($data)) {
      $result = [$prefix => $data];
      foreach ($data as $item) {
        if (!is_array($item) || array_is_list($item)) {
          continue;
        }
        $result += $this->flattenStudy($item, $prefix);
      }
      return $result;
    }

    return [$prefix => $data];
  }

  /**
   * Mirrors ClinicalTrialsGovStudyManager::flattenMetadata().
   */
  protected function flattenMetadata(array $items, string $parent = ''): array {
    $rows = [];
    foreach ($items as $item) {
      $name = (string) $item['name'];
      $path = ($parent && $name) ? $parent . '.' . $name : $name;
      $children = [];
      foreach ($item['children'] ?? [] as $child) {
        $child_name = (string) $child['name'];
        if (!$child_name) {
          continue;
        }
        $children[] = ($path) ? $path . '.' . $child_name : $child_name;
      }
      $rows[$path] = [
        'path' => $path,
        'parent' => $parent,
        'name' => $name,
        'piece' => (string) $item['piece'],
        'title' => (string) ($item['title'] ?? ''),
        'type' => (string) $item['type'],
        'sourceType' => (string) $item['sourceType'],
        'maxChars' => $item['maxChars'] ?? NULL,
        'isEnum' => (bool) ($item['isEnum'] ?? FALSE),
        'description' => (string) ($item['description'] ?? ''),
        'children' => $children,
        'rules' => (string) ($item['rules'] ?? ''),
        'altPieceNames' => array_values(array_filter(
          $item['altPieceNames'] ?? [],
          fn(mixed $value): bool => is_string($value) && $value !== ''
        )),
        'synonyms' => (bool) ($item['synonyms'] ?? FALSE),
        'dedLinkLabel' => (string) ($item['dedLink']['label'] ?? ''),
        'dedLinkUrl' => (string) ($item['dedLink']['url'] ?? ''),
      ];
      if (($item['children'] ?? []) !== []) {
        $rows += $this->flattenMetadata($item['children'], $path);
      }
    }
    return $rows;
  }

}
