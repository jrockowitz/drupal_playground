<?php

declare(strict_types=1);

namespace Drupal\clinical_trials_gov;

/**
 * Runs the reusable ClinicalTrials.gov setup workflow.
 */
interface ClinicalTrialsGovSetupManagerInterface {

  /**
   * Applies overrides and provisions the ClinicalTrials.gov import workflow.
   */
  public function setUp(array $overrides): array;

}
