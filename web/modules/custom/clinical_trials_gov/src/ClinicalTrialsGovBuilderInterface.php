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
   * Fields with sourceType STRUCT in the metadata become #type => details.
   * Leaf fields become #type => item. A collapsed Raw data details widget
   * containing the full flat table is appended at the end.
   *
   * @param array $study
   *   Flat array keyed by Index field paths, as returned by
   *   ClinicalTrialsGovManagerInterface::getStudy().
   *
   * @return array
   *   Drupal render array using only native elements (no custom CSS).
   */
  public function buildStudy(array $study): array;

}
