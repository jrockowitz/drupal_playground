<?php

declare(strict_types=1);

namespace Drupal\clinical_trials_gov;

/**
 * Defines field-selection helpers for the import wizard.
 */
interface ClinicalTrialsGovFieldManagerInterface {

  /**
   * Returns the required API keys for the wizard.
   */
  public function getRequiredFieldKeys(): array;

  /**
   * Returns available API keys for the saved query context.
   */
  public function getAvailableFieldKeysFromQuery(string $query): array;

  /**
   * Returns available field definitions for the saved query context.
   */
  public function getAvailableFieldDefinitionsFromQuery(string $query): array;

  /**
   * Returns a decorated field definition for one API key.
   */
  public function getFieldDefinition(string $api_key): array;

  /**
   * Returns decorated field definitions for multiple API keys.
   */
  public function getFieldDefinitions(array $api_keys): array;

}
