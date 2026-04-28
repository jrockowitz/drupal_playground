<?php

declare(strict_types=1);

namespace Drupal\clinical_trials_gov;

use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\GuzzleException;
use Psr\Log\LoggerInterface;

/**
 * Low-level HTTP client for the ClinicalTrials.gov API v2.
 */
class ClinicalTrialsGovApi implements ClinicalTrialsGovApiInterface {

  const BASE_URL = 'https://clinicaltrials.gov/api/v2';

  public function __construct(
    protected ClientInterface $httpClient,
    protected LoggerInterface $logger,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function get(string $path, array $parameters = []): array {
    try {
      $response = $this->httpClient->request('GET', self::BASE_URL . $path, [
        'query' => $parameters,
        'headers' => ['Accept' => 'application/json'],
      ]);
      return json_decode((string) $response->getBody(), TRUE) ?? [];
    }
    catch (GuzzleException $exception) {
      $this->logger->error('ClinicalTrials.gov API request failed for @path: @message', [
        '@path' => $path,
        '@message' => $exception->getMessage(),
      ]);
      return [];
    }
  }

}
