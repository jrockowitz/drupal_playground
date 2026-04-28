<?php

declare(strict_types=1);

namespace Drupal\clinical_trials_gov\Hook;

use Drupal\clinical_trials_gov\Element\ClinicalTrialsGovStudiesQuerySummary;
use Drupal\Core\Hook\Attribute\Hook;

/**
 * Hook implementations for the ClinicalTrials.gov module.
 */
class ClinicalTrialsGovHooks {

  /**
   * Implements hook_gin_ignore_sticky_form_actions().
   */
  #[Hook('gin_ignore_sticky_form_actions')]
  public function ginIgnoreStickyFormActions(): array {
    return [
      'clinical_trials_gov_find_form',
      'clinical_trials_gov_config_form',
      'clinical_trials_gov_import_form',
    ];
  }

  /**
   * Implements hook_element_info().
   */
  #[Hook('element_info')]
  public function elementInfo(): array {
    return [
      'clinical_trials_gov_studies_query_summary' => (new ClinicalTrialsGovStudiesQuerySummary())->getInfo(),
    ];
  }

}
