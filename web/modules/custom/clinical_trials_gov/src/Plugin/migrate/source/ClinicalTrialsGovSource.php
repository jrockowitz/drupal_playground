<?php

declare(strict_types=1);

namespace Drupal\clinical_trials_gov\Plugin\migrate\source;

use Drupal\clinical_trials_gov\ClinicalTrialsGovManagerInterface;
use Drupal\clinical_trials_gov\Element\ClinicalTrialsGovStudiesQuery;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\migrate\Attribute\MigrateSource;
use Drupal\migrate\Plugin\migrate\source\SourcePluginBase;
use Drupal\migrate\Plugin\MigrationInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a ClinicalTrials.gov migrate source.
 */
#[MigrateSource(id: 'clinical_trials_gov')]
class ClinicalTrialsGovSource extends SourcePluginBase implements ContainerFactoryPluginInterface {

  /**
   * Maximum number of studies to fetch per API request.
   */
  protected const PAGE_SIZE = '1000';

  public function __construct(
    array $configuration,
    string $plugin_id,
    mixed $plugin_definition,
    MigrationInterface $migration,
    protected ClinicalTrialsGovManagerInterface $manager,
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
      $container->get('clinical_trials_gov.manager'),
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
    $parameters['pageSize'] = self::PAGE_SIZE;
    $rows = [];

    do {
      $response = $this->manager->getStudies($parameters);
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
