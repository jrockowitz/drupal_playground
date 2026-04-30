<?php

declare(strict_types=1);

namespace Drupal\Tests\clinical_trials_gov\Unit;

use Drupal\clinical_trials_gov\Form\ClinicalTrialsGovConfigForm;

/**
 * Testable config form helper subclass.
 */
class TestClinicalTrialsGovConfigForm extends ClinicalTrialsGovConfigForm {

  /**
   * Exposes calculateHierarchyDepth() for testing.
   */
  public function exposedCalculateHierarchyDepth(string $path): int {
    return $this->calculateHierarchyDepth($path);
  }

  /**
   * Exposes buildIndentStyle() for testing.
   */
  public function exposedBuildIndentStyle(int $depth): string {
    return $this->buildIndentStyle($depth);
  }

  /**
   * Exposes buildLabelCell() for testing.
   */
  public function exposedBuildLabelCell(string $label, string $description, int $depth): array {
    return $this->buildLabelCell($label, $description, $depth);
  }

  /**
   * Exposes buildFieldNameCell() for testing.
   */
  public function exposedBuildFieldNameCell(string $field_name, mixed $details, int $depth): array {
    return $this->buildFieldNameCell($field_name, $details, $depth);
  }

}
