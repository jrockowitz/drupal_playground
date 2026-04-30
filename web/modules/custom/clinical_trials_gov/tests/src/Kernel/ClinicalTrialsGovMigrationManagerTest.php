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

  /**
   * Modules required for these kernel tests.
   *
   * @var array<string>
   */
  protected static $modules = [
    'clinical_trials_gov',
    'clinical_trials_gov_test',
    'node',
    'field',
    'text',
    'link',
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
      ->set('paths', [
        'protocolSection.contactsLocationsModule.locations',
        'protocolSection.sponsorCollaboratorsModule.responsibleParty',
        'protocolSection.conditionsModule.conditions',
        'protocolSection.identificationModule.briefTitle',
        'protocolSection.identificationModule.nctId',
        'protocolSection.identificationModule.nctIdAliases',
        'protocolSection.statusModule.overallStatus',
      ])
      ->set('type', 'trial')
      ->set('fields', [
        'group_location' => 'protocolSection.contactsLocationsModule.locations',
        'field_trial_resp_party' => 'protocolSection.sponsorCollaboratorsModule.responsibleParty',
        'field_trial_condition' => 'protocolSection.conditionsModule.conditions',
        'field_trial_brief_title' => 'protocolSection.identificationModule.briefTitle',
        'field_trial_nct_id' => 'protocolSection.identificationModule.nctId',
        'field_trial_nct_id_alias' => 'protocolSection.identificationModule.nctIdAliases',
        'field_trial_over_status' => 'protocolSection.statusModule.overallStatus',
      ])
      ->save();

    $this->container->get('clinical_trials_gov.migration_manager')->updateMigration();
    $config = $this->container->get('config.factory')->get('migrate_plus.migration.clinical_trials_gov');

    // Check that the generated migration stores the expected source and destination settings.
    $this->assertSame('clinical_trials_gov', $config->get('id'));
    $this->assertSame('clinical_trials_gov', $config->get('source.plugin'));
    $this->assertSame('query.cond=cancer&filter.overallStatus=RECRUITING', $config->get('source.query'));
    $this->assertSame('trial', $config->get('destination.default_bundle'));

    // Check that title mapping truncates the source value for the node title.
    $this->assertSame([
      [
        'plugin' => 'callback',
        'callable' => '\\Drupal\\Component\\Utility\\Unicode::truncate',
        'unpack_source' => TRUE,
        'source' => [
          'protocolSection.identificationModule.briefTitle',
          'constants/title_max_length',
          'constants/title_wordsafe',
          'constants/title_add_ellipsis',
        ],
      ],
    ], $config->get('process.title'));
    $entity_manager = $this->container->get('clinical_trials_gov.entity_manager');
    $this->assertSame('protocolSection.identificationModule.briefTitle', $config->get('process.' . $entity_manager->generateFieldName('protocolSection.identificationModule.briefTitle')));
    $this->assertSame('protocolSection.identificationModule.nctId', $config->get('process.' . $entity_manager->generateFieldName('protocolSection.identificationModule.nctId')));
    $this->assertSame('protocolSection.conditionsModule.conditions', $config->get('process.' . $entity_manager->generateFieldName('protocolSection.conditionsModule.conditions')));
    $this->assertSame('protocolSection.identificationModule.nctIdAliases', $config->get('process.' . $entity_manager->generateFieldName('protocolSection.identificationModule.nctIdAliases')));
    $this->assertSame([
      [
        'plugin' => 'concat',
        'source' => [
          'constants/study_url_prefix',
          'nctId',
        ],
      ],
    ], $config->get('process.' . $entity_manager->getStudyUrlFieldName() . '/uri'));
    $this->assertSame([
      [
        'plugin' => 'concat',
        'source' => [
          'constants/study_api_url_prefix',
          'nctId',
        ],
      ],
    ], $config->get('process.' . $entity_manager->getStudyApiFieldName() . '/uri'));
    $this->assertNull($config->get('process.group_location'));
    $this->assertSame('protocolSection.sponsorCollaboratorsModule.responsibleParty', $config->get('process.' . $entity_manager->generateFieldName('protocolSection.sponsorCollaboratorsModule.responsibleParty')));

    // Check that title truncation constants are available to the migration.
    $this->assertSame(255, $config->get('source.constants.title_max_length'));
    $this->assertFalse($config->get('source.constants.title_wordsafe'));
    $this->assertTrue($config->get('source.constants.title_add_ellipsis'));
    $this->assertSame('https://clinicaltrials.gov/study/', $config->get('source.constants.study_url_prefix'));
    $this->assertSame('https://clinicaltrials.gov/api/v2/studies/', $config->get('source.constants.study_api_url_prefix'));

    // Check that updating the saved query refreshes an already-cached migration definition.
    $this->container->get('plugin.manager.migration')->createInstance('clinical_trials_gov');
    $this->container->get('config.factory')->getEditable('clinical_trials_gov.settings')
      ->set('query', 'query.cond=diabetes')
      ->save();
    $this->container->get('clinical_trials_gov.migration_manager')->updateMigration();
    $migration = $this->container->get('plugin.manager.migration')->createInstance('clinical_trials_gov');
    $this->assertSame('query.cond=diabetes', $migration->getSourceConfiguration()['query']);

    // Check that clearing discovered paths removes the generated migration.
    $this->container->get('config.factory')->getEditable('clinical_trials_gov.settings')
      ->set('paths', [])
      ->save();
    $this->container->get('clinical_trials_gov.migration_manager')->updateMigration();
    $this->assertNull($this->container->get('config.factory')->get('migrate_plus.migration.clinical_trials_gov')->get('id'));
  }

}
