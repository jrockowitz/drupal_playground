<?php

declare(strict_types=1);

namespace Drupal\clinical_trials_gov\Traits;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;

/**
 * Shared create-access logic for the ClinicalTrials.gov bundle.
 */
trait ClinicalTrialsGovNodeAccessTrait {

  /**
   * Returns create access for the configured ClinicalTrials.gov bundle.
   */
  protected function checkClinicalTrialsGovCreateAccess(?string $entityBundle): AccessResultInterface {
    $settings = \Drupal::config('clinical_trials_gov.settings');
    $configured_bundle = (string) $settings->get('type');

    if ($entityBundle && $entityBundle === $configured_bundle) {
      return AccessResult::forbidden()->addCacheableDependency($settings);
    }

    return AccessResult::neutral()->addCacheableDependency($settings);
  }

}
