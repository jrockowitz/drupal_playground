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
   * Cached per service instance (keyed by NCT ID) so title() and view() share
   * a single API call per request.
   *
   * NOTE: The returned format is incompatible with entries from getStudies().
   * getStudies() returns nested raw API data; this method returns a flat
   * dot-notation array. Do not pass a getStudies() entry to buildStudy().
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
   * Cached per service instance for the request lifetime.
   *
   * @return array
   *   Flat array keyed by metadata path. Each value has keys:
   *   path, parent, name, piece, title, type, sourceType, description,
   *   and children.
   */
  public function getMetadataByPath(?string $path = NULL): array;

  /**
   * Returns metadata rows keyed by ClinicalTrials.gov piece.
   */
  public function getMetadataByPiece(?string $piece = NULL): array;

  /**
   * Fetches all enumeration types and their allowed values.
   *
   * Cached per service instance for the request lifetime.
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

  /**
   * Returns allowed values for a single enum type in consumer-ready formats.
   *
   * @param string $enum_type
   *   The enum type name, e.g. 'OverallStatus'.
   * @param bool $key_label
   *   TRUE to return custom-field rows with key/label pairs. FALSE to return
   *   Drupal core-style allowed values keyed by the enum value.
   *
   * @return array
   *   Allowed values for the enum type, or an empty array if not found.
   */
  public function getEnumAsAllowedValues(string $enum_type, bool $key_label = FALSE): array;

}
