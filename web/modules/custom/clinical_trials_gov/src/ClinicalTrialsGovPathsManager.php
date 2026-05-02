<?php

declare(strict_types=1);

namespace Drupal\clinical_trials_gov;

use Drupal\clinical_trials_gov\Element\ClinicalTrialsGovStudiesQuery;
use Drupal\Core\Config\ConfigFactoryInterface;

/**
 * Discovers and curates metadata paths for the import workflow.
 */
class ClinicalTrialsGovPathsManager implements ClinicalTrialsGovPathsManagerInterface {

  /**
   * The delay between requests is 15 seconds.
   */
  protected const STUDY_REQUEST_DELAY_MICROSECONDS = 15000;

  /**
   * Cached effective required paths.
   */
  protected ?array $requiredPaths = NULL;

  /**
   * Cached effective query paths.
   */
  protected ?array $queryPaths = NULL;

  /**
   * Constructs a new ClinicalTrialsGovPathsManager.
   */
  public function __construct(
    protected ConfigFactoryInterface $configFactory,
    protected ClinicalTrialsGovStudyManagerInterface $studyManager,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function getRequiredPaths(): array {
    if ($this->requiredPaths !== NULL) {
      return $this->requiredPaths;
    }

    $this->requiredPaths = $this->expandAndOrderPaths($this->getRequiredPathsRaw());
    return $this->requiredPaths;
  }

  /**
   * {@inheritdoc}
   */
  public function getRequiredPathsRaw(): array {
    return $this->configFactory->get('clinical_trials_gov.settings')->get('required_paths');
  }

  /**
   * {@inheritdoc}
   */
  public function getQueryPaths(): array {
    if ($this->queryPaths !== NULL) {
      return $this->queryPaths;
    }

    if ($this->getQueryPathsRaw() === []) {
      $this->queryPaths = [];
      return $this->queryPaths;
    }

    $this->queryPaths = $this->normalizeDiscoveredPaths(array_merge(
      $this->getQueryPathsRaw(),
      [$this->getTitleFieldPath()],
      $this->getRequiredPathsRaw(),
    ));
    return $this->queryPaths;
  }

  /**
   * {@inheritdoc}
   */
  public function getQueryPathsRaw(): array {
    return $this->configFactory->get('clinical_trials_gov.settings')->get('query_paths') ?? [];
  }

  /**
   * {@inheritdoc}
   */
  public function discoverQueryPaths(string $query): array {
    $study_ids = [];
    $page_token = '';

    do {
      $parameters = ClinicalTrialsGovStudiesQuery::parseQueryString($query);
      $parameters['fields'] = 'NCTId';
      $parameters['pageSize'] = '100';
      if ($page_token) {
        $parameters['pageToken'] = $page_token;
      }

      $response = $this->studyManager->getStudies($parameters);
      foreach (($response['studies'] ?? []) as $study) {
        if (!is_array($study)) {
          continue;
        }

        $nct_id = (string) ($study['protocolSection']['identificationModule']['nctId'] ?? '');
        if (!$nct_id || in_array($nct_id, $study_ids)) {
          continue;
        }

        $study_ids[] = $nct_id;
        if (count($study_ids) >= 500) {
          break;
        }
      }

      $page_token = (count($study_ids) < 500) ? (string) ($response['nextPageToken'] ?? '') : '';
    } while ($page_token);

    $discovered_paths = [];
    foreach ($study_ids as $delta => $nct_id) {
      if ($delta > 0) {
        $this->delayBetweenStudyRequests();
      }

      foreach (array_keys($this->studyManager->getStudy($nct_id)) as $path) {
        if (!is_string($path) || !$path) {
          continue;
        }
        $discovered_paths[$path] = TRUE;
      }
    }

    return $this->normalizeDiscoveredPaths(array_keys($discovered_paths));
  }

  /**
   * {@inheritdoc}
   */
  public function normalizeDiscoveredPaths(array $discovered_paths): array {
    $metadata_paths = array_keys($this->studyManager->getMetadataByPath());
    $ordered_paths = $this->expandAndOrderPaths($discovered_paths, $metadata_paths);

    if ($ordered_paths === []) {
      return [];
    }

    return array_values(array_unique(array_merge($ordered_paths, $this->getRequiredPaths())));
  }

  /**
   * Expands, filters, and orders a path list against metadata.
   */
  protected function expandAndOrderPaths(array $paths, ?array $metadata_paths = NULL): array {
    $metadata_paths ??= array_keys($this->studyManager->getMetadataByPath());
    $paths = array_values(array_filter($paths, 'is_string'));
    $paths = array_values(array_unique($paths));
    $paths = array_values(array_intersect($metadata_paths, $paths));

    if ($paths === []) {
      return [];
    }

    $expanded_paths = [];
    foreach ($paths as $path) {
      $expanded_paths[$path] = TRUE;
      foreach ($this->getAncestorFieldKeys($path, $metadata_paths) as $ancestor_path) {
        $expanded_paths[$ancestor_path] = TRUE;
      }
    }

    $ordered_paths = [];
    foreach ($metadata_paths as $path) {
      if (isset($expanded_paths[$path])) {
        $ordered_paths[] = $path;
      }
    }

    return $ordered_paths;
  }

  /**
   * Returns the configured title metadata path.
   */
  protected function getTitleFieldPath(): string {
    return (string) $this->configFactory->get('clinical_trials_gov.settings')->get('title_path');
  }

  /**
   * Returns ancestor keys for one identifier path.
   */
  protected function getAncestorFieldKeys(string $path, array $metadata_paths): array {
    $ancestor_keys = [];
    $known_paths = array_fill_keys($metadata_paths, TRUE);
    $last_dot = strrpos($path, '.');

    while ($last_dot !== FALSE) {
      $path = substr($path, 0, $last_dot);
      if (isset($known_paths[$path])) {
        $ancestor_keys[] = $path;
      }
      $last_dot = strrpos($path, '.');
    }

    return array_reverse($ancestor_keys);
  }

  /**
   * Pauses execution between individual study-detail requests.
   */
  protected function delayBetweenStudyRequests(): void {
    usleep(self::STUDY_REQUEST_DELAY_MICROSECONDS);
  }

}
