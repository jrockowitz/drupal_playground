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
   * The maximum number of studies to scan during discovery.
   */
  protected const DISCOVERY_PAGE_SIZE = 1000;

  /**
   * The discovery sort order used to favor recently updated studies.
   */
  protected const DISCOVERY_SORT = 'LastUpdatePostDate:desc';

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
    $parameters = ClinicalTrialsGovStudiesQuery::parseQueryString($query);
    $parameters['pageSize'] = self::DISCOVERY_PAGE_SIZE;
    $parameters['sort'] = self::DISCOVERY_SORT;

    $response = $this->studyManager->getStudies($parameters);

    $discovered_paths = [];
    foreach (($response['studies'] ?? []) as $study) {
      if (!is_array($study)) {
        continue;
      }

      foreach (array_keys($this->flattenStudy($study)) as $path) {
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
  protected function normalizeDiscoveredPaths(array $discovered_paths): array {
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
   * Flattens one study payload into dotted field paths.
   */
  protected function flattenStudy(mixed $data, string $prefix = ''): array {
    if (is_array($data) && !array_is_list($data)) {
      $result = [];
      foreach ($data as $key => $value) {
        $child_key = ($prefix) ? $prefix . '.' . $key : (string) $key;
        $result += $this->flattenStudy($value, $child_key);
      }
      return $result;
    }

    if (is_array($data) && array_is_list($data)) {
      $result = [$prefix => $data];
      foreach ($data as $item) {
        if (!is_array($item) || array_is_list($item)) {
          continue;
        }
        $result += $this->flattenStudy($item, $prefix);
      }
      return $result;
    }

    return [$prefix => $data];
  }

}
