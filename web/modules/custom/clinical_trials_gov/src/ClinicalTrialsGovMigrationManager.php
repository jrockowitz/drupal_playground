<?php

declare(strict_types=1);

namespace Drupal\clinical_trials_gov;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\migrate\Plugin\MigrationPluginManagerInterface;
use Psr\Log\LoggerInterface;

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
    protected ClinicalTrialsGovEntityManagerInterface $entityManager,
    protected LoggerInterface $logger,
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
    $source_constants = [
      'title_max_length' => self::TITLE_MAX_LENGTH,
      'title_wordsafe' => FALSE,
      'title_add_ellipsis' => TRUE,
      'study_url_prefix' => self::STUDY_URL_PREFIX,
      'study_api_url_prefix' => self::STUDY_API_URL_PREFIX,
    ];
    foreach ($fields as $path) {
      $definition = $this->fieldManager->getFieldDefinition($path);
      if (empty($definition['selectable'])) {
        $this->logger->warning('Skipped ClinicalTrials.gov field mapping for @path: @reason', [
          '@path' => $path,
          '@reason' => (string) ($definition['reason'] ?? 'Unsupported mapping.'),
        ]);
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

      if ($definition['field_type'] === 'custom' && !empty($definition['yaml_columns'])) {
        $source_constants[$definition['field_name'] . '_yaml_columns'] = $definition['yaml_columns'];
        $process[$definition['field_name']] = [
          [
            'plugin' => 'clinical_trials_gov_custom_field',
            'source' => [
              $path,
              'constants/' . $definition['field_name'] . '_yaml_columns',
            ],
          ],
        ];
        $this->logger->warning('ClinicalTrials.gov field @path uses YAML fallback for custom field properties: @columns', [
          '@path' => $path,
          '@columns' => implode(', ', $definition['yaml_columns']),
        ]);
        continue;
      }

      $process[$definition['field_name']] = $path;
    }

    $process[$this->entityManager->getStudyUrlFieldName() . '/uri'] = [
      [
        'plugin' => 'concat',
        'source' => [
          'constants/study_url_prefix',
          'nctId',
        ],
      ],
    ];
    $process[$this->entityManager->getStudyApiFieldName() . '/uri'] = [
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
        'constants' => $source_constants,
      ],
      'process' => $process,
      'destination' => [
        'plugin' => 'entity:node',
        'default_bundle' => $type,
      ],
    ])->save();

    if (method_exists($this->migrationPluginManager, 'clearCachedDefinitions')) {
      $this->migrationPluginManager->clearCachedDefinitions();
    }
  }

  /**
   * Clears cached migration plugin definitions after config changes.
   */
  protected function clearMigrationPluginDefinitions(): void {
    if (method_exists($this->migrationPluginManager, 'clearCachedDefinitions')) {
      $this->migrationPluginManager->clearCachedDefinitions();
    }
  }

}
