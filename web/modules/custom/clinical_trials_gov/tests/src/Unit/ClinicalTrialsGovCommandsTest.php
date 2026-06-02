<?php

declare(strict_types=1);

namespace Drupal\Tests\clinical_trials_gov\Unit;

use Drupal\clinical_trials_gov\ClinicalTrialsGovSetupManagerInterface;
use Drupal\clinical_trials_gov\Drush\Commands\ClinicalTrialsGovCommands;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;

/**
 * Unit tests for ClinicalTrialsGovCommands.
 *
 * @group clinical_trials_gov
 */
#[Group('clinical_trials_gov')]
class ClinicalTrialsGovCommandsTest extends UnitTestCase {

  /**
   * Tests that the Drush setup command delegates to the setup manager.
   */
  public function testSetupDelegatesToSetupManager(): void {
    $setup_manager = $this->createMock(ClinicalTrialsGovSetupManagerInterface::class);
    $setup_manager
      ->expects($this->once())
      ->method('setUp')
      ->with(['query' => 'query.cond=lung'])
      ->willReturn([
        'query' => 'query.cond=lung',
        'type' => 'trial',
        'query_paths_count' => 10,
        'fields_count' => 8,
        'page_size' => 1000,
      ]);

    $command = new ClinicalTrialsGovCommands($setup_manager);
    $command->setInput(new ArrayInput([]));
    $command->setOutput(new BufferedOutput());
    $command->setup('query.cond=lung');
  }

}
