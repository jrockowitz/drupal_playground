<?php

declare(strict_types=1);

namespace Drupal\Tests\clinical_trials_gov\Kernel;

use Drupal\clinical_trials_gov\ClinicalTrialsGovBuilderInterface;
use Drupal\KernelTests\KernelTestBase;
use PHPUnit\Framework\Attributes\Group;

/**
 * Kernel tests for ClinicalTrialsGovBuilder.
 *
 * @group clinical_trials_gov
 */
#[Group('clinical_trials_gov')]
class ClinicalTrialsGovBuilderTest extends KernelTestBase {

  // phpcs:ignore
  protected static $modules = [
    'clinical_trials_gov',
    'clinical_trials_gov_test',
  ];

  /**
   * The builder service under test.
   */
  protected ClinicalTrialsGovBuilderInterface $builder;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->builder = $this->container->get('clinical_trials_gov.builder');
  }

  /**
   * Tests that buildStudy() produces the expected render array structure.
   */
  public function testBuildStudy(): void {
    $nct_id = 'NCT05088187';

    $study = $this->container->get('clinical_trials_gov.manager')->getStudy($nct_id);
    $this->assertNotEmpty($study, 'Stub returned a non-empty study array.');

    $build = $this->builder->buildStudy($study);

    // Check that the top-level element is a container.
    $this->assertSame('container', $build['#type']);

    // Check that at least one top-level section exists as a details element.
    $has_details = FALSE;
    foreach ($build as $value) {
      if (is_array($value) && ($value['#type'] ?? '') === 'details') {
        $has_details = TRUE;
        break;
      }
    }
    $this->assertTrue($has_details, 'At least one top-level details element expected.');

    // Check that the raw data details widget is appended.
    $this->assertArrayHasKey('raw_data', $build);
    $this->assertSame('details', $build['raw_data']['#type']);
    $this->assertArrayHasKey('table', $build['raw_data']);
    $this->assertSame('table', $build['raw_data']['table']['#type']);

    // Check that the raw data table has the expected column headers.
    $header_labels = array_map(
      fn($header) => (string) $header,
      $build['raw_data']['table']['#header']
    );
    $this->assertContains('Field path', $header_labels);
    $this->assertContains('Value', $header_labels);

    // Check that the raw data table has one row per study field.
    $this->assertCount(count($study), $build['raw_data']['table']['#rows']);
  }

}
