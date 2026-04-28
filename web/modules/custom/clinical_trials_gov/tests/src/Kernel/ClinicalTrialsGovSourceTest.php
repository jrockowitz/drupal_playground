<?php

declare(strict_types=1);

namespace Drupal\Tests\clinical_trials_gov\Kernel;

use Drupal\KernelTests\KernelTestBase;
use PHPUnit\Framework\Attributes\Group;

/**
 * Kernel tests for the ClinicalTrialsGov source plugin.
 *
 * @group clinical_trials_gov
 */
#[Group('clinical_trials_gov')]
class ClinicalTrialsGovSourceTest extends KernelTestBase {

  protected static $modules = [
    'clinical_trials_gov',
    'clinical_trials_gov_test',
    'node',
    'field',
    'text',
    'options',
    'datetime',
    'filter',
    'user',
    'system',
    'migrate',
    'migrate_plus',
    'migrate_tools',
    'json_field',
    'custom_field',
  ];

  /**
   * Tests that the source plugin yields flattened rows.
   */
  public function testSourceRows(): void {
    $this->container->get('config.factory')->getEditable('clinical_trials_gov.settings')
      ->set('query', 'query.cond=cancer')
      ->set('type', 'trial')
      ->set('fields', ['protocolSection.identificationModule.nctId'])
      ->save();
    $this->container->get('clinical_trials_gov.migration_manager')->updateMigration();

    $migration = $this->container->get('plugin.manager.migration')->createInstance('clinical_trials_gov');
    $source = $migration->getSourcePlugin();
    $source->rewind();
    $row = $source->current();

    // Check that the first source row contains both flattened values and preserved structured parents.
    $this->assertNotNull($row);
    $this->assertSame('NCT05088187', $row->getSourceProperty('protocolSection.identificationModule.nctId'));
    $this->assertIsArray($row->getSourceProperty('protocolSection.identificationModule.organization'));
    $this->assertSame([
      'Thyroid Nodule',
      'Thyroid Cancer',
      'Cognitive Decline',
      'Survivorship',
      'Symptoms, Cognitive',
    ], $row->getSourceProperty('protocolSection.conditionsModule.conditions'));
  }

}
