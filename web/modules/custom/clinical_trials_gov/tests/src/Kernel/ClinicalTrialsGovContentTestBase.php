<?php

declare(strict_types=1);

namespace Drupal\Tests\clinical_trials_gov\Kernel;

/**
 * Base Kernel test for content and entity ClinicalTrials.gov tests.
 */
abstract class ClinicalTrialsGovContentTestBase extends ClinicalTrialsGovTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'node',
    'field',
    'text',
    'link',
    'options',
    'datetime',
    'filter',
    'user',
    'system',
    'migrate_plus',
    'migrate_tools',
    'custom_field',
    'field_group',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('node');
    $this->installEntitySchema('user');
    $this->installConfig(['field', 'filter', 'node', 'system']);
    $this->installSchema('node', ['node_access']);
  }

}
