<?php

declare(strict_types=1);

namespace Drupal\Tests\clinical_trials_gov\Kernel;

use Drupal\clinical_trials_gov_test\ClinicalTrialsGovApiStub;
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
    'custom_field',
  ];

  /**
   * Tests that the source plugin yields flattened rows.
   */
  public function testSourceRows(): void {
    $this->container->get('config.factory')->getEditable('clinical_trials_gov.settings')
      ->set('query', 'query.cond=cancer')
      ->set('paths', ['protocolSection.identificationModule.nctId'])
      ->set('type', 'trial')
      ->set('fields', ['field_nct_id' => 'protocolSection.identificationModule.nctId'])
      ->save();
    $this->container->get('clinical_trials_gov.migration_manager')->updateMigration();

    $migration = $this->container->get('plugin.manager.migration')->createInstance('clinical_trials_gov');
    $source = $migration->getSourcePlugin();
    $source->rewind();
    $row = $source->current();
    $source->rewind();
    $rows = array_values(iterator_to_array($source));
    $api = $this->container->get('clinical_trials_gov.api');

    // Check that the first source row is hydrated with flattened study values.
    $this->assertNotNull($row);
    $this->assertSame([
      'nctId' => [
        'type' => 'string',
      ],
    ], $source->getIds());
    $this->assertSame('NCT05088187', $row->getSourceProperty('nctId'));
    $this->assertSame('NCT05088187', $row->getSourceProperty('protocolSection.identificationModule.nctId'));
    $this->assertIsArray($row->getSourceProperty('protocolSection.identificationModule.organization'));
    $this->assertIsArray($row->getSourceProperty('protocolSection.contactsLocationsModule.locations'));
    $this->assertSame('Medical Unit Breast-, Endocrine tumors and Sarcoma', $row->getSourceProperty('protocolSection.contactsLocationsModule.locations.facility'));
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

    // Check that the source plugin pages through IDs, then loads studies per row.
    $this->assertInstanceOf(ClinicalTrialsGovApiStub::class, $api);
    $requests = $api->getRequests();
    $this->assertSame([
      [
        'path' => '/studies',
        'parameters' => [
          'query.cond' => 'cancer',
          'fields' => 'NCTId',
          'pageSize' => '1000',
        ],
      ],
      [
        'path' => '/studies',
        'parameters' => [
          'query.cond' => 'cancer',
          'fields' => 'NCTId',
          'pageSize' => '1000',
          'pageToken' => 'page-2',
        ],
      ],
    ], array_values(array_filter($requests, static fn(array $request): bool => $request['path'] === '/studies')));

    $study_request_paths = array_values(array_map(static fn(array $request): string => $request['path'], array_filter($requests, static fn(array $request): bool => $request['path'] !== '/studies' && str_starts_with($request['path'], '/studies/'))));
    $this->assertContains('/studies/NCT05088187', $study_request_paths);
    $this->assertContains('/studies/NCT05189171', $study_request_paths);
    $this->assertContains('/studies/NCT01205711', $study_request_paths);

  }

}
