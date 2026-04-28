<?php

declare(strict_types=1);

namespace Drupal\clinical_trials_gov_report;

use Drupal\Core\Hook\Attribute\Hook;

/**
 * Hook implementations for the ClinicalTrials.gov report module.
 */
class Hooks {

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
