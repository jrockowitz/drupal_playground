<?php

declare(strict_types=1);

namespace Drupal\clinical_trials_gov;

/**
 * Interface for the ClinicalTrials.gov API HTTP client.
 */
interface ClinicalTrialsGovApiInterface {

  /**
   * Performs a GET request to the API.
   *
   * @param string $path
   *   The API path, e.g. '/studies' or '/studies/metadata'.
   * @param array $parameters
   *   Query parameters to include in the request.
   *
   * @return array
   *   Decoded JSON response, or an empty array if the response is null.
   */
  public function get(string $path, array $parameters = []): array;

}
