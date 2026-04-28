<?php

declare(strict_types=1);

namespace Drupal\clinical_trials_gov\Plugin\migrate\source;

use Drupal\clinical_trials_gov\Element\ClinicalTrialsGovStudiesQuery;
use Drupal\migrate\Attribute\MigrateSource;
use Drupal\migrate\Plugin\migrate\source\SourcePluginBase;
use Drupal\migrate\Row;

/**
 * Provides a ClinicalTrials.gov migrate source.
 *
 * @MigrateSource(
 *   id = "clinical_trials_gov"
 * )
 */
#[MigrateSource(id: 'clinical_trials_gov')]
class ClinicalTrialsGovSource extends SourcePluginBase {

  /**
   * Maximum number of studies to fetch per API request.
   */
  protected const PAGE_SIZE = '1000';

  /**
   * {@inheritdoc}
   */
  public function __toString(): string {
    return 'clinical_trials_gov';
  }

  /**
   * {@inheritdoc}
   */
  public function fields(): array {
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function getIds(): array {
    return [
      'nctId' => [
        'type' => 'string',
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function prepareRow(Row $row): bool {
    return parent::prepareRow($row);
  }

  /**
   * {@inheritdoc}
   */
  protected function initializeIterator(): \Traversable {
    /** @var \Drupal\clinical_trials_gov\ClinicalTrialsGovManagerInterface $manager */
    $manager = \Drupal::service('clinical_trials_gov.manager');
    $parameters = ClinicalTrialsGovStudiesQuery::parseQueryString((string) ($this->configuration['query'] ?? ''));
    $parameters['pageSize'] = self::PAGE_SIZE;
    $rows = [];

    do {
      $response = $manager->getStudies($parameters);
      foreach (($response['studies'] ?? []) as $study) {
        if (!is_array($study)) {
          continue;
        }
        $row = $this->flattenStudy($study);
        $row['nctId'] = (string) ($study['protocolSection']['identificationModule']['nctId'] ?? '');
        $rows[] = $row;
      }

      if (!empty($response['nextPageToken']) && is_string($response['nextPageToken'])) {
        $parameters['pageToken'] = $response['nextPageToken'];
      }
      else {
        unset($parameters['pageToken']);
      }
    } while (!empty($response['nextPageToken']));

    return new \ArrayIterator($rows);
  }

  /**
   * Flattens a study while preserving nested containers on their parent keys.
   */
  protected function flattenStudy(mixed $data, string $prefix = ''): array {
    if (is_array($data) && !array_is_list($data)) {
      $result = [];
      if ($prefix !== '') {
        $result[$prefix] = $data;
      }
      foreach ($data as $key => $value) {
        $child_key = ($prefix !== '') ? $prefix . '.' . $key : (string) $key;
        $result += $this->flattenStudy($value, $child_key);
      }
      return $result;
    }

    return [$prefix => $data];
  }

}
