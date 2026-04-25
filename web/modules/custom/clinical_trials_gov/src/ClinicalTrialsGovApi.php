<?php

declare(strict_types=1);

namespace Drupal\clinical_trials_gov;

use GuzzleHttp\ClientInterface;

/**
 * Low-level HTTP client for the ClinicalTrials.gov API v2.
 */
class ClinicalTrialsGovApi implements ClinicalTrialsGovApiInterface {

  const BASE_URL = 'https://clinicaltrials.gov/api/v2';

  public function __construct(
    protected ClientInterface $httpClient,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function get(string $path, array $parameters = []): array {
    $response = $this->httpClient->request('GET', self::BASE_URL . $path, [
      'query' => $parameters,
      'headers' => ['Accept' => 'application/json'],
    ]);
    return json_decode((string) $response->getBody(), TRUE) ?? [];
  }

}
