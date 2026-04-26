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

    $build = $this->builder->buildStudy($study, $nct_id);

    // Check that the top-level element is a container.
    $this->assertSame('container', $build['#type']);

    // Check that the summary section is present.
    $this->assertArrayHasKey('summary', $build);
    $this->assertSame('details', $build['summary']['#type']);
    $this->assertTrue($build['summary']['#open']);
    $this->assertSame('container', $build['summary']['content']['#type']);

    // Check that the flattened data table is present inside a closed details.
    $this->assertArrayHasKey('data_table', $build);
    $this->assertSame('details', $build['data_table']['#type']);
    $this->assertFalse($build['data_table']['#open']);
    $this->assertSame('table', $build['data_table']['table']['#type']);

    // Check that the flattened data table has the expected column headers.
    $header_labels = array_map(
      fn($header) => (string) $header,
      $build['data_table']['table']['#header']
    );
    $this->assertContains('Field', $header_labels);
    $this->assertContains('Value', $header_labels);

    // Check that the flattened data table has one row per study field.
    $this->assertCount(count($study), $build['data_table']['table']['#rows']);

    // Check that the raw data details widget is no longer present.
    $this->assertArrayNotHasKey('raw_data', $build);

    // Check that the study API URL is present.
    $this->assertArrayHasKey('api_url', $build);
    $this->assertStringContainsString('/studies/' . $nct_id, (string) $build['api_url']['#markup']);

    // Check that the study build attaches the report library.
    $this->assertContains('clinical_trials_gov/study_report', $build['#attached']['library']);
  }

}
