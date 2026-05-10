<?php

declare(strict_types=1);

namespace Drupal\clinical_trials_gov;

/**
 * Defines entity-display helpers for the import wizard.
 */
interface ClinicalTrialsGovEntityDisplayManagerInterface {

  /**
   * Creates default form, view, and teaser display components.
   *
   * @param string $type
   *   The destination bundle machine name.
   * @param array $field_definitions
   *   The generated field definitions keyed by metadata path or field name.
   */
  public function createFieldDisplayComponents(string $type, array $field_definitions): void;

  /**
   * Creates default form and view field groups.
   *
   * @param string $type
   *   The destination bundle machine name.
   * @param array $fields
   *   The selected metadata paths.
   * @param array $field_definitions
   *   The generated field definitions keyed by metadata path or field name.
   */
  public function createFieldGroups(string $type, array $fields, array $field_definitions): void;

}
