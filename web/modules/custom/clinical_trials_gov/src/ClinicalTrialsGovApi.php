<?php

declare(strict_types=1);

namespace Drupal\clinical_trials_gov;

use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Exception\RequestException;
use Psr\Log\LoggerInterface;

/**
 * Low-level HTTP client for the ClinicalTrials.gov API v2.
 */
class ClinicalTrialsGovApi implements ClinicalTrialsGovApiInterface {

  /**
   * The ClinicalTrials.gov API base URL.
   */
  public const BASE_URL = 'https://clinicaltrials.gov/api/v2';

  /**
   * The maximum number of attempts for a rate-limited request.
   */
  protected const MAX_ATTEMPTS = 3;

  /**
   * The default retry delay in microseconds.
   */
  protected const DEFAULT_RETRY_DELAY_MICROSECONDS = 500000;

  /**
   * Constructs a new ClinicalTrialsGovApi client.
   */
  public function __construct(
    protected ClientInterface $httpClient,
    protected LoggerInterface $logger,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function get(string $path, array $parameters = []): array {
    for ($attempt = 1; $attempt <= self::MAX_ATTEMPTS; $attempt++) {
      try {
        $response = $this->httpClient->request('GET', self::BASE_URL . $path, [
          'query' => $parameters,
          'headers' => ['Accept' => 'application/json'],
        ]);
        return json_decode((string) $response->getBody(), TRUE) ?? [];
      }
      catch (GuzzleException $exception) {
        if ($this->shouldRetryRequest($exception, $attempt)) {
          $this->delayRequest($this->getRetryDelayMicroseconds($exception, $attempt));
          continue;
        }

        $this->logger->error('ClinicalTrials.gov API request failed for @path: @message', [
          '@path' => $path,
          '@message' => $exception->getMessage(),
        ]);
        return [];
      }
    }

    return [];
  }

  /**
   * Determines whether the failed request should be retried.
   */
  protected function shouldRetryRequest(GuzzleException $exception, int $attempt): bool {
    if ($attempt >= self::MAX_ATTEMPTS) {
      return FALSE;
    }

    if (!$exception instanceof RequestException) {
      return FALSE;
    }

    $response = $exception->getResponse();
    return ($response && ($response->getStatusCode() === 429));
  }

  /**
   * Returns the retry delay for a failed request.
   */
  protected function getRetryDelayMicroseconds(GuzzleException $exception, int $attempt): int {
    if ($exception instanceof RequestException) {
      $response = $exception->getResponse();
      $retry_after = $response?->getHeaderLine('Retry-After');
      if (is_numeric($retry_after)) {
        return max(0, (int) $retry_after) * 1000000;
      }
    }

    return self::DEFAULT_RETRY_DELAY_MICROSECONDS * $attempt;
  }

  /**
   * Pauses execution before retrying a rate-limited request.
   */
  protected function delayRequest(int $microseconds): void {
    if ($microseconds > 0) {
      usleep($microseconds);
    }
  }

}
