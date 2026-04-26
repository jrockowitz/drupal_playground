<?php

declare(strict_types=1);

namespace Drupal\clinical_trials_gov;

/**
 * Interface for the ClinicalTrials.gov manager service.
 */
interface ClinicalTrialsGovManagerInterface {

  /**
   * Fetches a list of studies from the API.
   *
   * @param array $parameters
   *   Raw API parameters assembled from a query string.
   *
   * @return array
   *   Raw API response: ['studies' => [...], 'nextPageToken' => '...', 'totalCount' => N].
   */
  public function getStudies(array $parameters): array;

  /**
   * Fetches version metadata for the ClinicalTrials.gov API dataset.
   *
   * @return array
   *   Raw response from /version, typically including apiVersion and
   *   dataTimestamp.
   */
  public function getVersion(): array;

  /**
   * Fetches a single study by NCT ID and returns a flat Index-field array.
   *
   * Associative arrays (objects) in the response are recursed into using
   * dot-notation keys. Lists and scalar values are stored as-is.
   *
   * @param string $nct_id
   *   The NCT ID, e.g. 'NCT04001699'.
   *
   * @return array
   *   Flat array keyed by Index field paths.
   */
  public function getStudy(string $nct_id): array;

  /**
   * Fetches and flattens the full study metadata tree.
   *
   * Cached statically for the request lifetime.
   *
   * @return array
   *   Flat array keyed by Index field path. Each value has keys:
   *   key, name, piece, title, type, sourceType, description, children.
   */
  public function getStudyMetadata(): array;

  /**
   * Returns metadata for a single Index field path.
   *
   * @param string $index_field
   *   Dot-notation Index field path.
   *
   * @return array|null
   *   Metadata array, or NULL if not found.
   */
  public function getStudyFieldMetadata(string $index_field): ?array;

  /**
   * Fetches all enumeration types and their allowed values.
   *
   * Cached statically for the request lifetime.
   *
   * @return array
   *   Raw response from /studies/enums.
   */
  public function getEnums(): array;

  /**
   * Returns allowed values for a single enum type.
   *
   * @param string $enum_type
   *   The enum type name, e.g. 'OverallStatus'.
   *
   * @return array
   *   List of allowed string values, or an empty array if not found.
   */
  public function getEnum(string $enum_type): array;

}
