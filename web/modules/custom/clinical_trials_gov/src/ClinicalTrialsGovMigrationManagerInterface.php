<?php

declare(strict_types=1);

namespace Drupal\clinical_trials_gov;

/**
 * Defines migration-management helpers for the import wizard.
 */
interface ClinicalTrialsGovMigrationManagerInterface {

  /**
   * Updates the generated migration definition.
   */
  public function updateMigration(): void;

}
