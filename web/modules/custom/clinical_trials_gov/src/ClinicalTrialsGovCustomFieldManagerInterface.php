<?php

declare(strict_types=1);

namespace Drupal\clinical_trials_gov;

/**
 * Defines custom-field resolution helpers for structured study metadata.
 */
interface ClinicalTrialsGovCustomFieldManagerInterface {

  /**
   * Resolves a supported structured path into a custom field definition.
   */
  public function resolveStructuredFieldDefinition(string $path): ?array;

}
