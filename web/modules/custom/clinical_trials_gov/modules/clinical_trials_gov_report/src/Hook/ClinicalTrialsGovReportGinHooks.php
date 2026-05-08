<?php

declare(strict_types=1);

namespace Drupal\clinical_trials_gov_report\Hook;

use Drupal\Core\Hook\Attribute\Hook;

/**
 * Gin hook implementations for the ClinicalTrials.gov report module.
 */
class ClinicalTrialsGovReportGinHooks {

  /**
   * Implements hook_gin_ignore_sticky_form_actions().
   */
  #[Hook('gin_ignore_sticky_form_actions')]
  public function ginIgnoreStickyFormActions(): array {
    return [
      'clinical_trials_gov_report_studies_search',
    ];
  }

}
