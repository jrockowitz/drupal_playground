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
      'protocolSection.identificationModule.nctId' => [
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
    $response = $manager->getStudies(ClinicalTrialsGovStudiesQuery::parseQueryString((string) ($this->configuration['query'] ?? '')));
    $rows = [];
    foreach (($response['studies'] ?? []) as $study) {
      if (!is_array($study)) {
        continue;
      }
      $rows[] = $this->flattenStudy($study);
    }
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
