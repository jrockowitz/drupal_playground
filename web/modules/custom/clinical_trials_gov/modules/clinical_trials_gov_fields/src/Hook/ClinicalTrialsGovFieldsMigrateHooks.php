<?php

declare(strict_types=1);

namespace Drupal\clinical_trials_gov_fields\Hook;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Hook\Attribute\Hook;

/**
 * Migration hook implementations for ClinicalTrials.gov fixed fields.
 */
class ClinicalTrialsGovFieldsMigrateHooks {

  /**
   * Constructs a new ClinicalTrialsGovFieldsMigrateHooks instance.
   */
  public function __construct(
    protected ConfigFactoryInterface $configFactory,
  ) {}

  /**
   * Implements hook_migration_plugins_alter().
   */
  #[Hook('migration_plugins_alter')]
  public function migrationPluginsAlter(array &$definitions): void {
    $type = $this->configFactory->get('clinical_trials_gov.settings')->get('type');
    if (!is_string($type) || $type === '') {
      return;
    }

    foreach ($definitions as &$definition) {
      if (!is_array($definition)) {
        continue;
      }
      if (!in_array('clinical_trials_gov', $definition['migration_tags'] ?? [])) {
        continue;
      }
      if (($definition['destination']['default_bundle'] ?? NULL) !== $type) {
        continue;
      }

      $definition['source']['plugin'] = 'clinical_trials_gov_fields';
      $definition['process']['field_trial_phase'] = 'normalized_trial_phase';
      $definition['process']['field_trial_study_type'] = 'normalized_trial_study_type';
      $definition['process']['field_trial_status'] = 'normalized_trial_status';
      $definition['process']['field_trial_sex'] = 'normalized_trial_sex';
      $definition['process']['field_trial_full_title'] = 'normalized_trial_full_title';
      $definition['process']['field_trials_nct_id'] = 'normalized_trial_nct_id';
      $definition['process']['field_trials_nct_url'] = 'normalized_trial_nct_url';
      $definition['process']['field_trial_age'] = 'normalized_trial_age';
      $definition['process']['field_trial_condition'] = 'normalized_trial_condition';
      $definition['process']['field_trial_contact'] = 'normalized_trial_contact';
      $definition['process']['field_trial_location'] = 'normalized_trial_location';
    }
  }

}
