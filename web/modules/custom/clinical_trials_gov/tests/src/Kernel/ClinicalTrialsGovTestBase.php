<?php

declare(strict_types=1);

namespace Drupal\Tests\clinical_trials_gov\Kernel;

use Drupal\KernelTests\KernelTestBase;

/**
 * Base Kernel test for ClinicalTrials.gov module tests.
 */
abstract class ClinicalTrialsGovTestBase extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'clinical_trials_gov',
    'clinical_trials_gov_test',
    'migrate',
  ];

}
