<?php

declare(strict_types=1);

namespace Drupal\Tests\clinical_trials_gov\Kernel;

use Drupal\clinical_trials_gov\ClinicalTrialsGovEntityManagerInterface;
use Drupal\clinical_trials_gov\ClinicalTrialsGovMigrationManagerInterface;
use Drupal\migrate\Plugin\MigrationPluginManagerInterface;
use PHPUnit\Framework\Attributes\Group;

/**
 * Kernel tests for ClinicalTrialsGovMigrationManager.
 *
 * @group clinical_trials_gov
 */
#[Group('clinical_trials_gov')]
class ClinicalTrialsGovMigrationManagerTest extends ClinicalTrialsGovContentTestBase {

  /**
   * The migration manager under test.
   */
  protected ClinicalTrialsGovMigrationManagerInterface $migrationManager;

  /**
   * The entity manager under test.
   */
  protected ClinicalTrialsGovEntityManagerInterface $entityManager;

  /**
   * The migration plugin manager under test.
   */
  protected MigrationPluginManagerInterface $migrationPluginManager;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installConfig('clinical_trials_gov');
    $this->migrationManager = $this->container->get('clinical_trials_gov.migration_manager');
    $this->entityManager = $this->container->get('clinical_trials_gov.entity_manager');
    $this->migrationPluginManager = $this->container->get('plugin.manager.migration');
  }

  /**
   * Tests that migration config is generated from saved wizard settings.
   */
  public function testUpdateMigration(): void {
    $this->config('clinical_trials_gov.settings');
    $this->config('clinical_trials_gov.settings')
      ->set('query', 'query.cond=cancer&filter.overallStatus=RECRUITING')
      ->set('title_path', 'protocolSection.identificationModule.briefTitle')
      ->set('required_paths', [
        'protocolSection.identificationModule.nctId',
        'protocolSection.identificationModule.briefTitle',
        'protocolSection.descriptionModule.briefSummary',
      ])
      ->set('query_paths', [
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
        'trial_resp_party' => 'protocolSection.sponsorCollaboratorsModule.responsibleParty',
        'trial_condition' => 'protocolSection.conditionsModule.conditions',
        'trial_brief_title' => 'protocolSection.identificationModule.briefTitle',
        'trial_nct_id' => 'protocolSection.identificationModule.nctId',
        'trial_nct_id_alias' => 'protocolSection.identificationModule.nctIdAliases',
        'trial_over_status' => 'protocolSection.statusModule.overallStatus',
      ])
      ->save();

    $this->migrationManager->updateMigration();
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
    $this->assertSame('protocolSection.identificationModule.briefTitle', $config->get('process.' . $this->entityManager->generateFieldName('protocolSection.identificationModule.briefTitle')));
    $this->assertSame('protocolSection.identificationModule.nctId', $config->get('process.' . $this->entityManager->generateFieldName('protocolSection.identificationModule.nctId')));
    $this->assertSame('protocolSection.conditionsModule.conditions', $config->get('process.' . $this->entityManager->generateFieldName('protocolSection.conditionsModule.conditions')));
    $this->assertSame('protocolSection.identificationModule.nctIdAliases', $config->get('process.' . $this->entityManager->generateFieldName('protocolSection.identificationModule.nctIdAliases')));
    $this->assertSame([
      [
        'plugin' => 'concat',
        'source' => [
          'constants/study_url_prefix',
          'nctId',
        ],
      ],
    ], $config->get('process.' . $this->entityManager->getStudyUrlFieldName() . '/uri'));
    $this->assertSame([
      [
        'plugin' => 'concat',
        'source' => [
          'constants/study_api_url_prefix',
          'nctId',
        ],
      ],
    ], $config->get('process.' . $this->entityManager->getStudyApiFieldName() . '/uri'));
    $this->assertNull($config->get('process.group_location'));
    $this->assertSame([
      [
        'plugin' => 'clinical_trials_gov_custom_field',
        'source' => [
          'protocolSection.contactsLocationsModule.locations',
          'constants/' . $this->entityManager->generateFieldName('protocolSection.contactsLocationsModule.locations') . '_yaml_columns',
        ],
      ],
    ], $config->get('process.' . $this->entityManager->generateFieldName('protocolSection.contactsLocationsModule.locations')));
    $this->assertSame('protocolSection.sponsorCollaboratorsModule.responsibleParty', $config->get('process.' . $this->entityManager->generateFieldName('protocolSection.sponsorCollaboratorsModule.responsibleParty')));

    // Check that title truncation constants are available to the migration.
    $this->assertSame(255, $config->get('source.constants.title_max_length'));
    $this->assertFalse($config->get('source.constants.title_wordsafe'));
    $this->assertTrue($config->get('source.constants.title_add_ellipsis'));
    $this->assertSame('https://clinicaltrials.gov/study/', $config->get('source.constants.study_url_prefix'));
    $this->assertSame('https://clinicaltrials.gov/api/v2/studies/', $config->get('source.constants.study_api_url_prefix'));
    $this->assertSame([
      'contacts',
      'geoPoint',
    ], $config->get('source.constants.' . $this->entityManager->generateFieldName('protocolSection.contactsLocationsModule.locations') . '_yaml_columns'));

    // Check that updating the saved query refreshes an already-cached migration definition.
    $this->migrationPluginManager->createInstance('clinical_trials_gov');
    $this->config('clinical_trials_gov.settings')
      ->set('query', 'query.cond=diabetes')
      ->save();
    $this->migrationManager->updateMigration();
    $migration = $this->migrationPluginManager->createInstance('clinical_trials_gov');
    $this->assertSame('query.cond=diabetes', $migration->getSourceConfiguration()['query']);

    // Check that clearing discovered paths removes the generated migration.
    $this->config('clinical_trials_gov.settings')
      ->set('query_paths', [])
      ->save();
    $this->migrationManager->updateMigration();
    $this->assertNull($this->container->get('config.factory')->get('migrate_plus.migration.clinical_trials_gov')->get('id'));
  }

}
