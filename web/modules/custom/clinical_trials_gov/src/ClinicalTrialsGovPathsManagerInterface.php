<?php

declare(strict_types=1);

namespace Drupal\clinical_trials_gov;

/**
 * Defines metadata-path discovery and curation helpers.
 */
interface ClinicalTrialsGovPathsManagerInterface {

  /**
   * Returns the effective required metadata paths.
   */
  public function getRequiredPaths(): array;

  /**
   * Returns the configured required metadata paths without expansion.
   */
  public function getRequiredPathsRaw(): array;

  /**
   * Returns the effective configured query metadata paths.
   */
  public function getQueryPaths(): array;

  /**
   * Returns the configured query metadata paths without expansion.
   */
  public function getQueryPathsRaw(): array;

  /**
   * Discovers normalized metadata paths from a raw studies query string.
   */
  public function discoverQueryPaths(string $query): array;

}
