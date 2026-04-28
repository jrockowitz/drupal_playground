<?php

declare(strict_types=1);

namespace Drupal\Tests\clinical_trials_gov\Kernel;

use Drupal\KernelTests\KernelTestBase;
use PHPUnit\Framework\Attributes\Group;

/**
 * Kernel tests for ClinicalTrialsGovMigrationManager.
 *
 * @group clinical_trials_gov
 */
#[Group('clinical_trials_gov')]
class ClinicalTrialsGovMigrationManagerTest extends KernelTestBase {

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

  /**
   * Tests that migration config is generated from saved wizard settings.
   */
  public function testUpdateMigration(): void {
    $this->config('clinical_trials_gov.settings');
    $this->container->get('config.factory')->getEditable('clinical_trials_gov.settings')
      ->set('query', 'query.cond=cancer&filter.overallStatus=RECRUITING')
      ->set('type', 'trial')
      ->set('fields', [
        'protocolSection.contactsLocationsModule.locations',
        'protocolSection.sponsorCollaboratorsModule.responsibleParty',
        'protocolSection.conditionsModule.conditions',
        'protocolSection.identificationModule.briefTitle',
        'protocolSection.identificationModule.nctId',
        'protocolSection.identificationModule.nctIdAliases',
        'protocolSection.statusModule.overallStatus',
      ])
      ->save();

    $this->container->get('clinical_trials_gov.migration_manager')->updateMigration();
    $config = $this->container->get('config.factory')->get('migrate_plus.migration.clinical_trials_gov');

    // Check that the generated migration stores the expected source and destination settings.
    $this->assertSame('clinical_trials_gov', $config->get('id'));
    $this->assertSame('clinical_trials_gov', $config->get('source.plugin'));
    $this->assertSame('query.cond=cancer&filter.overallStatus=RECRUITING', $config->get('source.query'));
    $this->assertSame('trial', $config->get('destination.default_bundle'));

    // Check that title mapping and generated field mapping are present.
    $this->assertSame('protocolSection.identificationModule.briefTitle', $config->get('process.title'));
    $entity_manager = $this->container->get('clinical_trials_gov.entity_manager');
    $this->assertSame('protocolSection.identificationModule.nctId', $config->get('process.' . $entity_manager->generateFieldName('protocolSection.identificationModule.nctId')));
    $this->assertSame('protocolSection.conditionsModule.conditions', $config->get('process.' . $entity_manager->generateFieldName('protocolSection.conditionsModule.conditions')));
    $this->assertNull($config->get('process.' . $entity_manager->generateFieldName('protocolSection.identificationModule.nctIdAliases')));
    $this->assertNull($config->get('process.group_location'));
    $this->assertSame('protocolSection.sponsorCollaboratorsModule.responsibleParty', $config->get('process.field_responsible_party'));
  }

}
