<?php

declare(strict_types=1);

namespace Drupal\Tests\clinical_trials_gov\Unit;

use Drupal\clinical_trials_gov\Form\ClinicalTrialsGovSettingsForm;

/**
 * Testable settings form helper subclass.
 */
class TestClinicalTrialsGovSettingsForm extends ClinicalTrialsGovSettingsForm {

  /**
   * Exposes formatRequiredPaths() for testing.
   */
  public function exposedFormatRequiredPaths(?array $paths): string {
    return $this->formatRequiredPaths($paths);
  }

  /**
   * Exposes parseRequiredPaths() for testing.
   */
  public function exposedParseRequiredPaths(string $paths): array {
    return $this->parseRequiredPaths($paths);
  }

}
