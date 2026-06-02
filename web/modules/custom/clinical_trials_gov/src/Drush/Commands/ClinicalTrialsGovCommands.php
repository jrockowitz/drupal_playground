<?php

declare(strict_types=1);

namespace Drupal\clinical_trials_gov\Drush\Commands;

use Drupal\clinical_trials_gov\ClinicalTrialsGovSetupManagerInterface;
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
    protected ClinicalTrialsGovSetupManagerInterface $setupManager,
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
    $summary = $this->setupManager->setUp(['query' => $query]);

    $this->io()->writeln('ClinicalTrials.gov setup complete.');
    $this->io()->writeln('Query: ' . $summary['query']);
    $this->io()->writeln('Bundle: ' . $summary['type']);
    $this->io()->writeln('Discovery sample: first ' . $summary['page_size'] . ' most recently updated studies');
    $this->io()->writeln('Discovered paths: ' . $summary['query_paths_count']);
    $this->io()->writeln('Saved fields: ' . $summary['fields_count']);
    $this->io()->writeln('Run `drush migrate:import clinical_trials_gov` next.');
  }

}
