<?php

declare(strict_types=1);

namespace Drupal\clinical_trials_gov\Batch;

use Drupal\Core\Url;

/**
 * Batch callbacks for discovering available study paths from a saved query.
 */
class ClinicalTrialsGovPathDiscoveryBatch {

  /**
   * Discovers metadata paths from studies returned by the saved query.
   */
  public static function discover(string $query, array &$context): void {
    /** @var \Drupal\clinical_trials_gov\ClinicalTrialsGovPathsManagerInterface $paths_manager */
    $paths_manager = \Drupal::service('clinical_trials_gov.paths_manager');
    $paths = $paths_manager->discoverQueryPaths($query);

    $context['results']['query'] = $query;
    $context['results']['paths'] = $paths;
    $context['results']['path_count'] = count($paths);
    $context['results']['study_count'] = 0;
    $context['message'] = (string) t('Discovered study fields from the saved query.');
    $context['finished'] = 1;
  }

  /**
   * Saves discovered paths and refreshes the generated migration.
   */
  public static function finish(bool $success, array $results, array $operations): void {
    /** @var \Drupal\Core\Config\ConfigFactoryInterface $config_factory */
    $config_factory = \Drupal::service('config.factory');
    /** @var \Drupal\clinical_trials_gov\ClinicalTrialsGovPathsManagerInterface $paths_manager */
    $paths_manager = \Drupal::service('clinical_trials_gov.paths_manager');
    /** @var \Drupal\clinical_trials_gov\ClinicalTrialsGovMigrationManagerInterface $migration_manager */
    $migration_manager = \Drupal::service('clinical_trials_gov.migration_manager');
    /** @var \Drupal\Core\Messenger\MessengerInterface $messenger */
    $messenger = \Drupal::messenger();

    if (!$success) {
      $messenger->addError((string) t('Path discovery did not complete successfully. Please try the Find step again.'));
      return;
    }

    $discovered_paths = $paths_manager->normalizeDiscoveredPaths($results['paths'] ?? []);

    $config_factory->getEditable('clinical_trials_gov.settings')
      ->set('query_paths', $discovered_paths)
      ->save();
    $migration_manager->updateMigration();

    $path_count = count($discovered_paths);
    if ($path_count === 0) {
      $messenger->addWarning((string) t('No studies were found for the saved query, so available fields could not be discovered.'));
      return;
    }

    $messenger->addStatus(t('Discovered @path_count fields (aka paths) that are used to determine which study/trial fields should be created and imported. Review the field <a href=":metadata_url">metadata</a>.', [
      '@path_count' => $path_count,
      ':metadata_url' => Url::fromRoute('clinical_trials_gov.review.metadata')->toString(),
    ]));
  }

}
