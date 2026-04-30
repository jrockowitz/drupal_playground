<?php

declare(strict_types=1);

namespace Drupal\Tests\clinical_trials_gov\Unit;

use Drupal\clinical_trials_gov\ClinicalTrialsGovBuilder;

/**
 * Testable builder subclass that exposes protected methods.
 */
class TestClinicalTrialsGovBuilder extends ClinicalTrialsGovBuilder {

  /**
   * Exposes buildValueElement() for testing.
   */
  public function exposedBuildValueElement(mixed $value): array {
    return $this->buildValueElement($value);
  }

  /**
   * Exposes normalizeStringList() for testing.
   */
  public function exposedNormalizeStringList(mixed $values): array {
    return $this->normalizeStringList($values);
  }

  /**
   * Exposes buildAssociativeList() for testing.
   */
  public function exposedBuildAssociativeList(array $values): array {
    return $this->buildAssociativeList($values);
  }

}
