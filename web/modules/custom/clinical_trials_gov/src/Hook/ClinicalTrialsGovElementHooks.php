<?php

declare(strict_types=1);

namespace Drupal\clinical_trials_gov\Hook;

use Drupal\clinical_trials_gov\Element\ClinicalTrialsGovStudiesQuerySummary;
use Drupal\Core\Hook\Attribute\Hook;

/**
 * Element-related hook implementations for the ClinicalTrials.gov module.
 */
class ClinicalTrialsGovElementHooks {

  /**
   * Implements hook_element_info().
   */
  #[Hook('element_info')]
  public function elementInfo(): array {
    return [
      'clinical_trials_gov_studies_query_summary' => [
        '#query' => '',
        '#pre_render' => [[ClinicalTrialsGovStudiesQuerySummary::class, 'preRenderSummary']],
      ],
    ];
  }

}
