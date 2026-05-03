<?php

declare(strict_types=1);

namespace Drupal\clinical_trials_gov;

use Drupal\Core\Config\ConfigFactoryInterface;

/**
 * Runs the reusable ClinicalTrials.gov setup workflow.
 */
class ClinicalTrialsGovSetupManager implements ClinicalTrialsGovSetupManagerInterface {

  /**
   * Config keys that setup accepts as direct overrides.
   */
  protected const ALLOWED_OVERRIDE_KEYS = [
    'query',
    'type',
    'field_prefix',
    'readonly',
    'title_path',
    'required_paths',
  ];

  /**
   * The discovery sample size reported to callers.
   */
  protected const DISCOVERY_PAGE_SIZE = 1000;

  /**
   * Constructs a new ClinicalTrialsGovSetupManager.
   */
  public function __construct(
    protected ConfigFactoryInterface $configFactory,
    protected ClinicalTrialsGovPathsManagerInterface $pathsManager,
    protected ClinicalTrialsGovEntityManagerInterface $entityManager,
    protected ClinicalTrialsGovMigrationManagerInterface $migrationManager,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function setUp(array $overrides): array {
    $query = trim((string) ($overrides['query'] ?? ''));
    if ($query === '') {
      throw new \InvalidArgumentException('The ClinicalTrials.gov setup workflow requires a query.');
    }

    $config = $this->configFactory->getEditable('clinical_trials_gov.settings');
    foreach (self::ALLOWED_OVERRIDE_KEYS as $key) {
      if (array_key_exists($key, $overrides)) {
        $config->set($key, $overrides[$key]);
      }
    }
    $config->set('query', $query);
    $config->save();

    $paths = $this->pathsManager->discoverQueryPaths($query);
    $this->configFactory->getEditable('clinical_trials_gov.settings')
      ->set('query_paths', $paths)
      ->save();

    $field_mappings = $this->entityManager->buildDefaultFieldMappings();
    $this->entityManager->saveFieldMappings($field_mappings);
    $this->entityManager->createConfiguredContentType();
    $this->entityManager->createConfiguredFields();
    $this->migrationManager->updateMigration();

    return [
      'query' => $query,
      'type' => (string) $this->configFactory->get('clinical_trials_gov.settings')->get('type'),
      'query_paths_count' => count($paths),
      'fields_count' => count($field_mappings),
      'page_size' => self::DISCOVERY_PAGE_SIZE,
    ];
  }

}
