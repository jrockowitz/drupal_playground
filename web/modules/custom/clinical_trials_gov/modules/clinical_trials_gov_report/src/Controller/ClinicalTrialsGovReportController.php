<?php

declare(strict_types=1);

namespace Drupal\clinical_trials_gov_report\Controller;

use Drupal\Core\Controller\ControllerBase;

/**
 * Controller for the ClinicalTrials.gov studies report.
 */
class ClinicalTrialsGovReportController extends ControllerBase {

  /**
   * Index page for browsing ClinicalTrials.gov studies.
   *
   * @return array
   *   A render array.
   */
  public function index(): array {
    return [
      '#markup' => 'ClinicalTrials.gov studies report',
    ];
  }

}
