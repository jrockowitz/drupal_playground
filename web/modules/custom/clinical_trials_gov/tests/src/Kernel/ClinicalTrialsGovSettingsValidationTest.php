<?php

declare(strict_types=1);

namespace Drupal\Tests\clinical_trials_gov\Kernel;

use Drupal\Core\Config\TypedConfigManagerInterface;
use Drupal\KernelTests\KernelTestBase;
use PHPUnit\Framework\Attributes\Group;

/**
 * Kernel tests for ClinicalTrials.gov settings validation.
 *
 * @group clinical_trials_gov
 */
#[Group('clinical_trials_gov')]
class ClinicalTrialsGovSettingsValidationTest extends KernelTestBase {

  /**
   * The typed config manager.
   */
  protected TypedConfigManagerInterface $typedConfigManager;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'clinical_trials_gov',
    'clinical_trials_gov_test',
    'migrate',
    'migrate_plus',
    'migrate_tools',
    'system',
    'user',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installConfig('clinical_trials_gov');
    $this->typedConfigManager = $this->container->get('config.typed');
  }

  /**
   * Tests valid settings pass typed config validation.
   */
  public function testValidSettings(): void {
    $typed_config = $this->typedConfigManager->createFromNameAndData('clinical_trials_gov.settings', [
      'query' => '',
      'query_paths' => [
        'protocolSection.identificationModule.briefTitle',
      ],
      'title_path' => 'protocolSection.identificationModule.briefTitle',
      'required_paths' => [
        'protocolSection.identificationModule.nctId',
        'protocolSection.identificationModule.briefTitle',
        'protocolSection.descriptionModule.briefSummary',
      ],
      'type' => 'trial',
      'field_prefix' => 'trial',
      'readonly' => FALSE,
      'fields' => [],
    ]);

    // Check that the default metadata paths are accepted.
    $this->assertCount(0, $typed_config->validate());
  }

  /**
   * Tests invalid metadata paths are rejected.
   */
  public function testInvalidMetadataPaths(): void {
    $typed_config = $this->typedConfigManager->createFromNameAndData('clinical_trials_gov.settings', [
      'query' => '',
      'query_paths' => [
        'protocolSection.identificationModule.notARealPath',
      ],
      'title_path' => 'protocolSection.identificationModule.notARealPath',
      'required_paths' => [
        'protocolSection.identificationModule.nctId',
        'protocolSection.identificationModule.alsoNotReal',
      ],
      'type' => 'trial',
      'field_prefix' => 'trial',
      'readonly' => FALSE,
      'fields' => [],
    ]);

    $violations = iterator_to_array($typed_config->validate());
    $invalid_values = array_map(static fn ($violation): string => (string) $violation->getInvalidValue(), $violations);

    // Check that invalid query, title, and required metadata paths are rejected.
    $this->assertCount(3, $violations);
    $this->assertContains('protocolSection.identificationModule.notARealPath', $invalid_values);
    $this->assertContains('protocolSection.identificationModule.notARealPath', $invalid_values);
    $this->assertContains('protocolSection.identificationModule.alsoNotReal', $invalid_values);
  }

}
