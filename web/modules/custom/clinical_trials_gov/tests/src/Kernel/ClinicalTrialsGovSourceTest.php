<?php

declare(strict_types=1);

namespace Drupal\Tests\clinical_trials_gov\Kernel;

use Drupal\clinical_trials_gov_test\ClinicalTrialsGovManagerStub;
use Drupal\KernelTests\KernelTestBase;
use PHPUnit\Framework\Attributes\Group;

/**
 * Kernel tests for the ClinicalTrialsGov source plugin.
 *
 * @group clinical_trials_gov
 */
#[Group('clinical_trials_gov')]
class ClinicalTrialsGovSourceTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
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
    $source->rewind();
    $rows = array_values(iterator_to_array($source));
    $manager = $this->container->get('clinical_trials_gov.manager');

    // Check that the first source row contains both flattened values and preserved structured parents.
    $this->assertNotNull($row);
    $this->assertSame([
      'nctId' => [
        'type' => 'string',
      ],
    ], $source->getIds());
    $this->assertSame('NCT05088187', $row->getSourceProperty('nctId'));
    $this->assertSame('NCT05088187', $row->getSourceProperty('protocolSection.identificationModule.nctId'));
    $this->assertIsArray($row->getSourceProperty('protocolSection.identificationModule.organization'));
    $this->assertSame([
      'Thyroid Nodule',
      'Thyroid Cancer',
      'Cognitive Decline',
      'Survivorship',
      'Symptoms, Cognitive',
    ], $row->getSourceProperty('protocolSection.conditionsModule.conditions'));

    // Check that the source plugin paginates through all studies.
    $this->assertCount(3, $rows);
    $this->assertSame('NCT01205711', $rows[2]->getSourceProperty('nctId'));
    $this->assertSame('NCT01205711', $rows[2]->getSourceProperty('protocolSection.identificationModule.nctId'));

    // Check that paginated requests use pageSize and nextPageToken.
    $this->assertInstanceOf(ClinicalTrialsGovManagerStub::class, $manager);
    $this->assertSame([
      [
        'query.cond' => 'cancer',
        'pageSize' => '1000',
      ],
      [
        'query.cond' => 'cancer',
        'pageSize' => '1000',
        'pageToken' => 'page-2',
      ],
    ], $manager->getStudiesRequests());

  }

}
