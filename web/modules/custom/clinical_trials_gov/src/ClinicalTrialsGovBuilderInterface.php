<?php

declare(strict_types=1);

namespace Drupal\clinical_trials_gov;

/**
 * Interface for the ClinicalTrials.gov builder service.
 */
interface ClinicalTrialsGovBuilderInterface {

  /**
   * Converts a flat Index-field study array into a Drupal render array.
   *
   * @param array $study
   *   Flat array keyed by Index field paths, as returned by
   *   ClinicalTrialsGovManagerInterface::getStudy().
   * @param string $nct_id
   *   The NCT ID used to build the upstream API URL.
   *
   * @return array
   *   Drupal render array containing a study summary, flattened data table,
   *   and upstream API URL.
   */
  public function buildStudy(array $study, string $nct_id): array;

}
