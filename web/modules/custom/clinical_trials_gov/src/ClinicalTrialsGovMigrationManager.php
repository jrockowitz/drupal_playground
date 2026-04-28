<?php

declare(strict_types=1);

namespace Drupal\clinical_trials_gov;

use Drupal\Core\Config\ConfigFactoryInterface;

/**
 * Manages generated migration configuration for the import wizard.
 */
class ClinicalTrialsGovMigrationManager implements ClinicalTrialsGovMigrationManagerInterface {

  /**
   * The generated migration config name.
   */
  protected const MIGRATION_CONFIG_NAME = 'migrate_plus.migration.clinical_trials_gov';

  public function __construct(
    protected ConfigFactoryInterface $configFactory,
    protected ClinicalTrialsGovFieldManagerInterface $fieldManager,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function updateMigration(): void {
    $settings = $this->configFactory->get('clinical_trials_gov.settings');
    $query = (string) ($settings->get('query') ?? '');
    $type = (string) ($settings->get('type') ?? '');
    $fields = array_values(array_filter($settings->get('fields') ?? [], 'is_string'));
    $migration_config = $this->configFactory->getEditable(self::MIGRATION_CONFIG_NAME);

    if ($query === '' || $type === '' || $fields === []) {
      $migration_config->delete();
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
        $process['title'] = $path;
        continue;
      }

      $process[$definition['field_name']] = $path;
    }

    $migration_config->setData([
      'id' => 'clinical_trials_gov',
      'label' => 'ClinicalTrials.gov',
      'status' => TRUE,
      'migration_group' => 'default',
      'migration_tags' => ['clinical_trials_gov'],
      'source' => [
        'plugin' => 'clinical_trials_gov',
        'query' => $query,
      ],
      'process' => $process,
      'destination' => [
        'plugin' => 'entity:node',
        'default_bundle' => $type,
      ],
    ])->save();
  }

}
