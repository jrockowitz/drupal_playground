<?php

declare(strict_types=1);

namespace Drupal\clinical_trials_gov;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\migrate\Plugin\MigrationPluginManagerInterface;

/**
 * Manages generated migration configuration for the import wizard.
 */
class ClinicalTrialsGovMigrationManager implements ClinicalTrialsGovMigrationManagerInterface {

  /**
   * The generated migration config name.
   */
  protected const MIGRATION_CONFIG_NAME = 'migrate_plus.migration.clinical_trials_gov';

  /**
   * Maximum node title length.
   */
  protected const TITLE_MAX_LENGTH = 255;

  /**
   * ClinicalTrials.gov study URL prefix.
   */
  protected const STUDY_URL_PREFIX = 'https://clinicaltrials.gov/study/';

  /**
   * ClinicalTrials.gov study API URL prefix.
   */
  protected const STUDY_API_URL_PREFIX = 'https://clinicaltrials.gov/api/v2/studies/';

  public function __construct(
    protected ConfigFactoryInterface $configFactory,
    protected MigrationPluginManagerInterface $migrationPluginManager,
    protected ClinicalTrialsGovFieldManagerInterface $fieldManager,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function updateMigration(): void {
    $settings = $this->configFactory->get('clinical_trials_gov.settings');
    $query = (string) ($settings->get('query') ?? '');
    $paths = array_values(array_filter($settings->get('paths') ?? [], 'is_string'));
    $type = (string) ($settings->get('type') ?? '');
    $field_mappings = array_filter($settings->get('fields') ?? [], 'is_string');
    $fields = array_values($field_mappings);
    $migration_config = $this->configFactory->getEditable(self::MIGRATION_CONFIG_NAME);

    if ($query === '' || $paths === [] || $type === '' || $fields === []) {
      $migration_config->delete();
      $this->clearMigrationPluginDefinitions();
      return;
    }

    $process = [];
    foreach ($fields as $path) {
      $definition = $this->fieldManager->getFieldDefinition($path);
      if (empty($definition['selectable'])) {
        continue;
      }
      if (!empty($definition['group_only'])) {
        continue;
      }
      if (($definition['destination_property'] ?? NULL) === 'title') {
        $process['title'] = [
          [
            'plugin' => 'callback',
            'callable' => '\\Drupal\\Component\\Utility\\Unicode::truncate',
            'unpack_source' => TRUE,
            'source' => [
              $path,
              'constants/title_max_length',
              'constants/title_wordsafe',
              'constants/title_add_ellipsis',
            ],
          ],
        ];
      }

      $process[$definition['field_name']] = $path;
    }

    $process['field_nct_url/uri'] = [
      [
        'plugin' => 'concat',
        'source' => [
          'constants/study_url_prefix',
          'nctId',
        ],
      ],
    ];
    $process['field_nct_api/uri'] = [
      [
        'plugin' => 'concat',
        'source' => [
          'constants/study_api_url_prefix',
          'nctId',
        ],
      ],
    ];

    $migration_config->setData([
      'id' => 'clinical_trials_gov',
      'label' => 'ClinicalTrials.gov',
      'status' => TRUE,
      'migration_group' => 'default',
      'migration_tags' => ['clinical_trials_gov'],
      'source' => [
        'plugin' => 'clinical_trials_gov',
        'query' => $query,
        'constants' => [
          'title_max_length' => self::TITLE_MAX_LENGTH,
          'title_wordsafe' => FALSE,
          'title_add_ellipsis' => TRUE,
          'study_url_prefix' => self::STUDY_URL_PREFIX,
          'study_api_url_prefix' => self::STUDY_API_URL_PREFIX,
        ],
      ],
      'process' => $process,
      'destination' => [
        'plugin' => 'entity:node',
        'default_bundle' => $type,
      ],
    ])->save();

    $this->migrationPluginManager->clearCachedDefinitions();
  }

  /**
   * Clears cached migration plugin definitions after config changes.
   */
  protected function clearMigrationPluginDefinitions(): void {
    $this->migrationPluginManager->clearCachedDefinitions();
  }

}
