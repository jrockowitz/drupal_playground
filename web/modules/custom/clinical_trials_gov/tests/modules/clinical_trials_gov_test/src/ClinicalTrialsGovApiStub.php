<?php

declare(strict_types=1);

namespace Drupal\clinical_trials_gov_test;

use Drupal\clinical_trials_gov\ClinicalTrialsGovApiInterface;

/**
 * Stub ClinicalTrials.gov API client that returns fixture data for testing.
 */
class ClinicalTrialsGovApiStub implements ClinicalTrialsGovApiInterface {

  /**
   * Records the API requests keyed by path and parameters.
   */
  protected array $requests = [];

  /**
   * {@inheritdoc}
   */
  public function get(string $path, array $parameters = []): array {
    $this->requests[] = [
      'path' => $path,
      'parameters' => $parameters,
    ];

    return match ($path) {
      '/version' => $this->loadFixture('version'),
      '/studies/metadata' => $this->loadFixture('metadata'),
      '/studies/enums' => $this->loadFixture('enums'),
      '/studies' => $this->getStudiesResponse($parameters),
      default => $this->getStudyResponse($path),
    };
  }

  /**
   * Returns the recorded API requests.
   */
  public function getRequests(): array {
    return $this->requests;
  }

  /**
   * Returns a paged studies fixture response.
   */
  protected function getStudiesResponse(array $parameters): array {
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
   * Returns a single study fixture response.
   */
  protected function getStudyResponse(string $path): array {
    if (!str_starts_with($path, '/studies/')) {
      return [];
    }

    $nct_id = substr($path, strlen('/studies/'));
    $fixture_name = match ($nct_id) {
      'NCT05088187' => 'study-NCT05088187',
      'NCT05189171' => 'study-NCT05189171',
      'NCT01205711' => 'study-NCT01205711',
      default => NULL,
    };

    return ($fixture_name) ? $this->loadFixture($fixture_name) : [];
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

}
