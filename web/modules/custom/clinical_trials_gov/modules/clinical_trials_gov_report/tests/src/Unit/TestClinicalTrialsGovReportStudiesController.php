<?php

declare(strict_types=1);

namespace Drupal\Tests\clinical_trials_gov_report\Unit;

use Drupal\clinical_trials_gov_report\Controller\ClinicalTrialsGovReportStudiesController;

/**
 * Testable studies controller subclass.
 */
class TestClinicalTrialsGovReportStudiesController extends ClinicalTrialsGovReportStudiesController {

  /**
   * Exposes normalizeCountTotal() for testing.
   */
  public function exposedNormalizeCountTotal(mixed $value): string {
    return $this->normalizeCountTotal($value);
  }

  /**
   * Exposes buildVersionMarkup() for testing.
   */
  public function exposedBuildVersionMarkup(array $version): string {
    return $this->buildVersionMarkup($version);
  }

}
