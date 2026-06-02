<?php

declare(strict_types=1);

namespace Drupal\clinical_trials_gov;

/**
 * Interface for the ClinicalTrials.gov builder service.
 */
interface ClinicalTrialsGovBuilderInterface {

  /**
   * Builds a studies results table render array.
   *
   * @param array $studies
   *   Raw studies array from ClinicalTrialsGovStudyManagerInterface::getStudies().
   * @param string|null $study_route
   *   Route name for study detail links. NULL renders NCT IDs as plain text.
   * @param array $options
   *   Supported options:
   *   - modal: Whether study links should open in a modal dialog.
   *
   * @return array
   *   Drupal render array representing a studies table.
   */
  public function buildStudiesList(array $studies, ?string $study_route = NULL, array $options = []): array;

  /**
   * Converts a flat Index-field study array into a Drupal render array.
   *
   * @param array $study
   *   Flat array keyed by Index field paths, as returned by
   *   ClinicalTrialsGovStudyManagerInterface::getStudy(). This is NOT compatible
   *   with the nested entries inside getStudies()['studies'].
   * @param string $nct_id
   *   The NCT ID used to build the upstream API URL.
   *
   * @return array
   *   Drupal render array containing a study summary, flattened data table,
   *   and upstream API URL.
   */
  public function buildStudy(array $study, string $nct_id): array;

}
