<?php

declare(strict_types=1);

namespace Drupal\clinical_trials_gov_report\Controller;

use Drupal\Core\Controller\ControllerBase;

/**
 * Controller for viewing individual ClinicalTrials.gov studies.
 */
class ClinicalTrialsGovStudyController extends ControllerBase {

  /**
   * View page for a single ClinicalTrials.gov study.
   *
   * @param string $nctId
   *   The NCT ID of the study.
   *
   * @return array
   *   A render array.
   */
  public function view(string $nctId): array {
    return [
      '#markup' => 'Study: ' . $nctId,
    ];
  }

  /**
   * Title callback for the study view page.
   *
   * @param string $nctId
   *   The NCT ID of the study.
   *
   * @return string
   *   The page title.
   */
  public function title(string $nctId): string {
    return 'Study ' . $nctId;
  }

}
