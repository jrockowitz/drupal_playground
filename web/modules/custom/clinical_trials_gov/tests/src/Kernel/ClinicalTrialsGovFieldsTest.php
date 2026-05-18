<?php

declare(strict_types=1);

namespace Drupal\Tests\clinical_trials_gov\Kernel;

use Drupal\clinical_trials_gov\ClinicalTrialsGovMigrationManagerInterface;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\migrate\Plugin\MigrationPluginManagerInterface;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use PHPUnit\Framework\Attributes\Group;

/**
 * Kernel tests for the ClinicalTrials.gov fields integration.
 *
 * @group clinical_trials_gov
 */
#[Group('clinical_trials_gov')]
class ClinicalTrialsGovFieldsTest extends ClinicalTrialsGovContentTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'clinical_trials_gov',
    'clinical_trials_gov_fields',
    'clinical_trials_gov_test',
    'migrate',
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
   * The migration manager under test.
   */
  protected ClinicalTrialsGovMigrationManagerInterface $migrationManager;

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
    $this->migrationPluginManager = $this->container->get('plugin.manager.migration');
  }

  /**
   * Tests normalized source values and generated migration process mappings.
   */
  public function testNormalizedSourceValuesAndMigrationMappings(): void {
    $this->config('clinical_trials_gov.settings')
      ->set('query', 'query.cond=cancer')
      ->set('query_paths', ['protocolSection.identificationModule.nctId'])
      ->set('type', 'trial')
      ->set('fields', ['field_nct_id' => 'protocolSection.identificationModule.nctId'])
      ->save();
    $this->migrationManager->updateMigration();

    $migration = $this->migrationPluginManager->createInstance('clinical_trials_gov');

    // Check that the migration swaps in the fields-aware source plugin.
    $this->assertSame('clinical_trials_gov_fields', $migration->getSourceConfiguration()['plugin']);
    $this->assertSame([
      [
        'plugin' => 'get',
        'source' => 'normalized_trial_phase',
      ],
    ], $migration->getProcess()['field_trial_phase']);
    $this->assertSame([
      [
        'plugin' => 'get',
        'source' => 'normalized_trial_study_type',
      ],
    ], $migration->getProcess()['field_trial_study_type']);
    $this->assertSame([
      [
        'plugin' => 'get',
        'source' => 'normalized_trial_status',
      ],
    ], $migration->getProcess()['field_trial_status']);
    $this->assertSame([
      [
        'plugin' => 'get',
        'source' => 'normalized_trial_sex',
      ],
    ], $migration->getProcess()['field_trial_sex']);
    $this->assertSame([
      [
        'plugin' => 'get',
        'source' => 'normalized_trial_full_title',
      ],
    ], $migration->getProcess()['field_trial_full_title']);
    $this->assertSame([
      [
        'plugin' => 'get',
        'source' => 'normalized_trial_age',
      ],
    ], $migration->getProcess()['field_trial_age']);
    $this->assertSame([
      [
        'plugin' => 'get',
        'source' => 'normalized_trial_condition',
      ],
    ], $migration->getProcess()['field_trial_condition']);
    $this->assertSame([
      [
        'plugin' => 'get',
        'source' => 'normalized_trial_contact',
      ],
    ], $migration->getProcess()['field_trial_contact']);
    $this->assertSame([
      [
        'plugin' => 'get',
        'source' => 'normalized_trial_location',
      ],
    ], $migration->getProcess()['field_trial_location']);
    $this->assertSame([
      [
        'plugin' => 'get',
        'source' => 'normalized_trial_nct_id',
      ],
    ], $migration->getProcess()['field_trials_nct_id']);
    $this->assertSame([
      [
        'plugin' => 'get',
        'source' => 'normalized_trial_nct_url',
      ],
    ], $migration->getProcess()['field_trials_nct_url']);

    $source = $migration->getSourcePlugin();
    $source->rewind();
    $rows = iterator_to_array($source, FALSE);
    $rows_by_nct_id = [];

    foreach ($rows as $row) {
      $rows_by_nct_id[$row->getSourceProperty('nctId')] = $row;
    }

    $thyroid_study = $rows_by_nct_id['NCT05088187'];
    $colorectal_study = $rows_by_nct_id['NCT01205711'];

    // Check that normalized scalar and list values are populated.
    $this->assertNull($thyroid_study->getSourceProperty('normalized_trial_phase'));
    $this->assertSame('OBSERVATIONAL', $thyroid_study->getSourceProperty('normalized_trial_study_type'));
    $this->assertSame('RECRUITING', $thyroid_study->getSourceProperty('normalized_trial_status'));
    $this->assertSame('ALL', $thyroid_study->getSourceProperty('normalized_trial_sex'));
    $this->assertSame('Longitudinal Evaluation of Objective Cognitive Function and Quality of Life in Patients Undergoing Surgery for Malignant and Benign Thyroid Nodules', $thyroid_study->getSourceProperty('normalized_trial_full_title'));
    $this->assertSame('NCT05088187', $thyroid_study->getSourceProperty('normalized_trial_nct_id'));
    $this->assertSame([
      'uri' => 'https://clinicaltrials.gov/study/NCT05088187',
    ], $thyroid_study->getSourceProperty('normalized_trial_nct_url'));
    $this->assertSame([
      'ADULT',
      'OLDER_ADULT',
    ], $thyroid_study->getSourceProperty('normalized_trial_age'));
    $this->assertSame([
      'Thyroid Nodule',
      'Thyroid Cancer',
      'Cognitive Decline',
      'Survivorship',
      'Symptoms, Cognitive',
    ], $thyroid_study->getSourceProperty('normalized_trial_condition'));
    $this->assertSame([
      'PHASE2',
    ], $colorectal_study->getSourceProperty('normalized_trial_phase'));
    $this->assertSame('INTERVENTIONAL', $colorectal_study->getSourceProperty('normalized_trial_study_type'));

    // Check that normalized contact values remap phoneExt and keep only needed keys.
    $this->assertSame([
      [
        'name' => 'Renske Altena, MD PhD',
        'role' => 'CONTACT',
        'phone' => '+46724698719',
        'email' => 'renske.altena@ki.se',
      ],
      [
        'name' => 'Cia Ihre Lundgren, MD Ass Professor',
        'role' => 'CONTACT',
      ],
    ], $thyroid_study->getSourceProperty('normalized_trial_contact'));

    // Check that normalized locations skip nested contacts and geoPoint data.
    $this->assertSame([
      [
        'facility' => 'Medical Unit Breast-, Endocrine tumors and Sarcoma',
        'city' => 'Stockholm',
        'zip' => '17176',
        'country' => 'Sweden',
        'status' => 'RECRUITING',
      ],
    ], $thyroid_study->getSourceProperty('normalized_trial_location'));

    NodeType::create([
      'type' => 'trial',
      'name' => 'Trial',
    ])->save();
    FieldStorageConfig::create([
      'field_name' => 'field_trial_title',
      'entity_type' => 'node',
      'type' => 'string',
      'settings' => [
        'max_length' => 255,
      ],
    ])->save();
    FieldConfig::create([
      'field_name' => 'field_trial_title',
      'entity_type' => 'node',
      'bundle' => 'trial',
      'label' => 'Title',
    ])->save();

    $node = Node::create([
      'type' => 'trial',
      'title' => 'Original title',
      'field_trial_title' => 'Patient-friendly title',
    ]);
    $node->save();

    // Check that saving a node copies the patient title into the node title.
    $this->assertSame('Patient-friendly title', $node->label());
  }

}
