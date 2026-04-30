<?php

declare(strict_types=1);

namespace Drupal\clinical_trials_gov\Batch;

use Drupal\clinical_trials_gov\Element\ClinicalTrialsGovStudiesQuery;
use Drupal\Core\Url;

/**
 * Batch callbacks for discovering available study paths from a saved query.
 */
class ClinicalTrialsGovPathDiscoveryBatch {

  /**
   * Maximum studies to scan per query.
   */
  protected const MAX_STUDIES = 500;

  /**
   * Search page size while collecting NCT IDs.
   */
  protected const SEARCH_PAGE_SIZE = 100;

  /**
   * Number of full studies to inspect per batch request.
   */
  protected const STUDY_DISCOVERY_CHUNK_SIZE = 100;

  /**
   * Discovers metadata paths from studies returned by the saved query.
   */
  public static function discover(string $query, array &$context): void {
    if (!isset($context['sandbox']['stage'])) {
      $context['sandbox']['stage'] = 'collect_ids';
      $context['sandbox']['page_token'] = '';
      $context['sandbox']['study_ids'] = [];
      $context['sandbox']['current_index'] = 0;
      $context['sandbox']['discovered_paths'] = [];
      $context['results']['query'] = $query;
      $context['results']['study_count'] = 0;
      $context['results']['path_count'] = 0;
    }

    /** @var \Drupal\clinical_trials_gov\ClinicalTrialsGovManagerInterface $manager */
    $manager = \Drupal::service('clinical_trials_gov.manager');

    if ($context['sandbox']['stage'] === 'collect_ids') {
      $parameters = ClinicalTrialsGovStudiesQuery::parseQueryString($query);
      $parameters['fields'] = 'NCTId';
      $parameters['pageSize'] = (string) self::SEARCH_PAGE_SIZE;
      if ($context['sandbox']['page_token']) {
        $parameters['pageToken'] = (string) $context['sandbox']['page_token'];
      }

      $response = $manager->getStudies($parameters);
      foreach (($response['studies'] ?? []) as $study) {
        if (!is_array($study)) {
          continue;
        }

        $nct_id = (string) ($study['protocolSection']['identificationModule']['nctId'] ?? '');
        if (!$nct_id) {
          continue;
        }

        if (!in_array($nct_id, $context['sandbox']['study_ids'])) {
          $context['sandbox']['study_ids'][] = $nct_id;
        }

        if (count($context['sandbox']['study_ids']) >= self::MAX_STUDIES) {
          break;
        }
      }

      $next_page_token = (string) ($response['nextPageToken'] ?? '');
      if ($next_page_token && count($context['sandbox']['study_ids']) < self::MAX_STUDIES) {
        $context['sandbox']['page_token'] = $next_page_token;
        $context['message'] = (string) t('Collecting study identifiers (@count found so far).', [
          '@count' => count($context['sandbox']['study_ids']),
        ]);
        $context['finished'] = 0.001;
        return;
      }

      $context['sandbox']['stage'] = 'discover_paths';
      $context['sandbox']['current_index'] = 0;

      if ($context['sandbox']['study_ids'] === []) {
        $context['results']['study_count'] = 0;
        $context['results']['path_count'] = 0;
        $context['results']['paths'] = [];
        $context['finished'] = 1;
        return;
      }
    }

    $study_ids = $context['sandbox']['study_ids'];
    $study_count = count($study_ids);
    $start = (int) $context['sandbox']['current_index'];
    $end = min($start + self::STUDY_DISCOVERY_CHUNK_SIZE, $study_count);

    for ($index = $start; $index < $end; $index++) {
      $study = $manager->getStudy($study_ids[$index]);
      foreach (array_keys($study) as $path) {
        if (!is_string($path) || !$path) {
          continue;
        }
        $context['sandbox']['discovered_paths'][$path] = TRUE;
      }
    }

    $context['sandbox']['current_index'] = $end;
    $context['results']['study_count'] = $study_count;
    $context['results']['path_count'] = count($context['sandbox']['discovered_paths']);
    $context['results']['paths'] = array_keys($context['sandbox']['discovered_paths']);
    $context['message'] = (string) t('Discovering fields from studies (@current of @total).', [
      '@current' => $end,
      '@total' => $study_count,
    ]);
    $context['finished'] = ($study_count === 0) ? 1 : ($end / $study_count);
  }

  /**
   * Saves discovered paths and refreshes the generated migration.
   */
  public static function finish(bool $success, array $results, array $operations): void {
    /** @var \Drupal\Core\Config\ConfigFactoryInterface $config_factory */
    $config_factory = \Drupal::service('config.factory');
    /** @var \Drupal\clinical_trials_gov\ClinicalTrialsGovFieldManagerInterface $field_manager */
    $field_manager = \Drupal::service('clinical_trials_gov.field_manager');
    /** @var \Drupal\clinical_trials_gov\ClinicalTrialsGovManagerInterface $manager */
    $manager = \Drupal::service('clinical_trials_gov.manager');
    /** @var \Drupal\clinical_trials_gov\ClinicalTrialsGovMigrationManagerInterface $migration_manager */
    $migration_manager = \Drupal::service('clinical_trials_gov.migration_manager');
    /** @var \Drupal\Core\Messenger\MessengerInterface $messenger */
    $messenger = \Drupal::messenger();

    if (!$success) {
      $messenger->addError((string) t('Path discovery did not complete successfully. Please try the Find step again.'));
      return;
    }

    $metadata_paths = array_keys($manager->getMetadataByPath());
    $discovered_paths = array_values(array_filter($results['paths'] ?? [], 'is_string'));
    $discovered_paths = array_values(array_intersect($metadata_paths, $discovered_paths));
    if ($discovered_paths) {
      $expanded_paths = [];
      foreach ($discovered_paths as $path) {
        $expanded_paths[$path] = TRUE;
        $parent_path = $path;
        $last_dot = strrpos($parent_path, '.');
        while ($last_dot !== FALSE) {
          $parent_path = substr($parent_path, 0, $last_dot);
          if (in_array($parent_path, $metadata_paths)) {
            $expanded_paths[$parent_path] = TRUE;
          }
          $last_dot = strrpos($parent_path, '.');
        }
      }

      $ordered_paths = [];
      foreach ($metadata_paths as $path) {
        if (isset($expanded_paths[$path])) {
          $ordered_paths[] = $path;
        }
      }

      $discovered_paths = array_values(array_unique(array_merge($ordered_paths, $field_manager->getRequiredFieldKeys())));
    }

    $config_factory->getEditable('clinical_trials_gov.settings')
      ->set('paths', $discovered_paths)
      ->save();
    $migration_manager->updateMigration();

    $study_count = (int) ($results['study_count'] ?? 0);
    $path_count = count($discovered_paths);
    if ($study_count === 0 || $path_count === 0) {
      $messenger->addWarning((string) t('No studies were found for the saved query, so available fields could not be discovered.'));
      return;
    }

    $messenger->addStatus(t('Discovered @path_count fields (aka paths) that are used to determine which study/trial fields should be created and imported. Review the field <a href=":metadata_url">metadata</a>.', [
      '@path_count' => $path_count,
      ':metadata_url' => Url::fromRoute('clinical_trials_gov.review.metadata')->toString(),
    ]));
  }

}
