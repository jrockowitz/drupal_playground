<?php

declare(strict_types=1);

namespace Drupal\clinical_trials_gov\Hook;

use Drupal\Core\Hook\Attribute\Hook;

/**
 * Gin-related hook implementations for the ClinicalTrials.gov module.
 */
class ClinicalTrialsGovGinHooks {

  /**
   * Implements hook_gin_ignore_sticky_form_actions().
   */
  #[Hook('gin_ignore_sticky_form_actions')]
  public function ginIgnoreStickyFormActions(): array {
    return [
      'clinical_trials_gov_find_form',
      'clinical_trials_gov_config_form',
      'clinical_trials_gov_import_form',
      'clinical_trials_gov_settings_form',
    ];
  }

}
