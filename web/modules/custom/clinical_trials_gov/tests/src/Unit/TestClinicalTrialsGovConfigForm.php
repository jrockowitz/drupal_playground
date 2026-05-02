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

  /**
   * Exposes isFieldSelectedByDefault() for testing.
   */
  public function exposedIsFieldSelectedByDefault(string $path, array $definition, array $saved_fields, bool $existing): bool {
    return $this->isFieldSelectedByDefault($path, $definition, $saved_fields, $existing);
  }

  /**
   * Exposes shouldHideFieldRow() for testing.
   */
  public function exposedShouldHideFieldRow(string $path, array $definitions): bool {
    return $this->shouldHideFieldRow($path, $definitions);
  }

  /**
   * Exposes shouldHideEmptyGroupRow() for testing.
   */
  public function exposedShouldHideEmptyGroupRow(string $path, array $definitions): bool {
    return $this->shouldHideEmptyGroupRow($path, $definitions);
  }

  /**
   * Exposes hasSelectedDescendant() for testing.
   */
  public function exposedHasSelectedDescendant(string $path, array $selected_rows): bool {
    return $this->hasSelectedDescendant($path, $selected_rows);
  }

  /**
   * Exposes hasRequiredDescendant() for testing.
   */
  public function exposedHasRequiredDescendant(string $path, array $definitions): bool {
    return $this->hasRequiredDescendant($path, $definitions);
  }

}
