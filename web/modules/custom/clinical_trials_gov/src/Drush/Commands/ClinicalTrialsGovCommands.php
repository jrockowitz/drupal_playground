<?php

declare(strict_types=1);

namespace Drupal\clinical_trials_gov\Drush\Commands;

use Drupal\clinical_trials_gov\ClinicalTrialsGovEntityManagerInterface;
use Drupal\clinical_trials_gov\ClinicalTrialsGovMigrationManagerInterface;
use Drupal\clinical_trials_gov\ClinicalTrialsGovPathsManagerInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drush\Attributes as CLI;
use Drush\Commands\AutowireTrait;
use Drush\Commands\DrushCommands;

/**
 * Drush commands for ClinicalTrials.gov setup tasks.
 */
class ClinicalTrialsGovCommands extends DrushCommands {

  use AutowireTrait;

  /**
   * Constructs a new ClinicalTrialsGovCommands instance.
   */
  public function __construct(
    protected ConfigFactoryInterface $configFactory,
    protected ClinicalTrialsGovPathsManagerInterface $pathsManager,
    protected ClinicalTrialsGovEntityManagerInterface $entityManager,
    protected ClinicalTrialsGovMigrationManagerInterface $migrationManager,
  ) {
    parent::__construct();
  }

  /**
   * Discovers paths and prepares the ClinicalTrials.gov import structure.
   */
  #[CLI\Command(name: 'clinical-trials-gov:setup')]
  #[CLI\Argument(name: 'query', description: 'ClinicalTrials.gov studies query string.')]
  #[CLI\Usage(name: 'drush clinical-trials-gov:setup query.cond=Cancer', description: 'Prepare the ClinicalTrials.gov import workflow from a saved query string.')]
  public function setup(string $query): void {
    $paths = $this->pathsManager->discoverQueryPaths($query);

    $this->configFactory->getEditable('clinical_trials_gov.settings')
      ->set('query', $query)
      ->set('query_paths', $paths)
      ->save();
    $field_mappings = $this->entityManager->buildDefaultFieldMappings();
    $this->entityManager->saveFieldMappings($field_mappings);
    $this->entityManager->createConfiguredContentType();
    $this->entityManager->createConfiguredFields();
    $this->migrationManager->updateMigration();

    $type = (string) $this->configFactory->get('clinical_trials_gov.settings')->get('type');

    $this->io()->writeln('ClinicalTrials.gov setup complete.');
    $this->io()->writeln('Query: ' . $query);
    $this->io()->writeln('Bundle: ' . $type);
    $this->io()->writeln('Discovery sample: first 250 most recently updated studies');
    $this->io()->writeln('Discovered paths: ' . (string) count($paths));
    $this->io()->writeln('Saved fields: ' . (string) count($field_mappings));
    $this->io()->writeln('Run `drush migrate:import clinical_trials_gov` next.');
  }

}
