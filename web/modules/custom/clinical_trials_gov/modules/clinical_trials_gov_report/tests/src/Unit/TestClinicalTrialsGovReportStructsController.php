<?php

declare(strict_types=1);

namespace Drupal\Tests\clinical_trials_gov_report\Unit;

use Drupal\clinical_trials_gov_report\Controller\ClinicalTrialsGovReportStructsController;

/**
 * Testable structs controller subclass.
 */
class TestClinicalTrialsGovReportStructsController extends ClinicalTrialsGovReportStructsController {

  /**
   * Exposes buildStructRows() for testing.
   */
  public function exposedBuildStructRows(array $metadata, array $used_paths = []): array {
    return $this->buildStructRows($metadata, $used_paths);
  }

  /**
   * Exposes buildStructsTable() for testing.
   */
  public function exposedBuildStructsTable(array $struct_rows): array {
    return $this->buildStructsTable($struct_rows);
  }

  /**
   * Exposes buildSubPropertiesCell() for testing.
   */
  public function exposedBuildSubPropertiesCell(array $values): array|string {
    return $this->buildSubPropertiesCell($values);
  }

}
