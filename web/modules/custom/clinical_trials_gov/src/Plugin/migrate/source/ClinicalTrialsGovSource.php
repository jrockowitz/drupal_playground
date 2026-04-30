<?php

declare(strict_types=1);

namespace Drupal\clinical_trials_gov\Plugin\migrate\source;

use Drupal\clinical_trials_gov\ClinicalTrialsGovApiInterface;
use Drupal\clinical_trials_gov\Element\ClinicalTrialsGovStudiesQuery;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\migrate\Attribute\MigrateSource;
use Drupal\migrate\Plugin\migrate\source\SourcePluginBase;
use Drupal\migrate\Plugin\MigrationInterface;
use Drupal\migrate\Row;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a ClinicalTrials.gov migrate source.
 */
#[MigrateSource(id: 'clinical_trials_gov')]
class ClinicalTrialsGovSource extends SourcePluginBase implements ContainerFactoryPluginInterface {

  public function __construct(
    array $configuration,
    string $plugin_id,
    mixed $plugin_definition,
    MigrationInterface $migration,
    protected ClinicalTrialsGovApiInterface $api,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $migration);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition, ?MigrationInterface $migration = NULL): static {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $migration,
      $container->get('clinical_trials_gov.api'),
    );
  }

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
  protected function initializeIterator(): \Traversable {
    $parameters = ClinicalTrialsGovStudiesQuery::parseQueryString((string) ($this->configuration['query'] ?? ''));
    $parameters['fields'] = 'NCTId';
    $parameters['pageSize'] = '1000';
    $rows = [];

    do {
      $response = $this->api->get('/studies', $parameters);
      foreach (($response['studies'] ?? []) as $study) {
        if (!is_array($study)) {
          continue;
        }
        $nct_id = (string) ($study['protocolSection']['identificationModule']['nctId'] ?? '');
        if (!$nct_id) {
          continue;
        }
        $rows[] = [
          'nctId' => $nct_id,
        ];
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
   * {@inheritdoc}
   */
  public function prepareRow(Row $row) {
    $nct_id = (string) $row->getSourceProperty('nctId');
    if (!$nct_id) {
      return FALSE;
    }

    $study = $this->api->get('/studies/' . $nct_id);
    if ($study === []) {
      return FALSE;
    }

    foreach ($this->flattenStudy($study) as $path => $value) {
      if (!$path) {
        continue;
      }
      $row->setSourceProperty($path, $value);
    }

    $row->setSourceProperty('nctId', $nct_id);

    return parent::prepareRow($row);
  }

  /**
   * Flattens a study while preserving nested containers on their parent keys.
   */
  protected function flattenStudy(mixed $data, string $prefix = ''): array {
    if (is_array($data) && !array_is_list($data)) {
      $result = [];
      if ($prefix) {
        $result[$prefix] = $data;
      }
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
