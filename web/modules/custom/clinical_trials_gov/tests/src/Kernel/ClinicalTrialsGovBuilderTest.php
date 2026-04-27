<?php

declare(strict_types=1);

namespace Drupal\Tests\clinical_trials_gov\Kernel;

use Drupal\clinical_trials_gov\ClinicalTrialsGovBuilderInterface;
use Drupal\Core\Url;
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
    'clinical_trials_gov_report',
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

    // Check that the duplicate runtime fixture copy is not present.
    $this->assertDirectoryDoesNotExist(DRUPAL_ROOT . '/modules/custom/clinical_trials_gov/modules/clinical_trials_gov_test/fixtures');

    $study = $this->container->get('clinical_trials_gov.manager')->getStudy($nct_id);
    $this->assertNotEmpty($study, 'Stub returned a non-empty study array.');

    $build = $this->builder->buildStudy($study, $nct_id);

    // Check that the top-level element is a container.
    $this->assertSame('container', $build['#type']);

    // Check that the summary section is present.
    $this->assertArrayHasKey('study_link', $build);
    $this->assertStringContainsString('https://clinicaltrials.gov/study/' . $nct_id, (string) $build['study_link']['#markup']);
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

    // Check that the study build uses the report-specific wrapper class.
    $this->assertContains('clinical-trials-gov-report-study', $build['#attributes']['class']);
  }

  /**
   * Tests that buildStudiesList() produces a reusable studies table.
   */
  public function testBuildStudiesList(): void {
    $studies = $this->container->get('clinical_trials_gov.manager')->getStudies([
      'query.cond' => 'cancer',
    ])['studies'] ?? [];

    $build = $this->builder->buildStudiesList($studies, 'clinical_trials_gov_report.study');

    // Check that the top-level element is a table with the expected headers.
    $this->assertSame('table', $build['#type']);
    $header_labels = array_map(static fn($header): string => (string) $header, $build['#header']);
    $this->assertSame([
      'NCT ID',
      'Title',
      'Overall status',
      'Phases',
      'Conditions',
    ], $header_labels);

    // Check that one row is rendered per returned study.
    $this->assertCount(count($studies), $build['#rows']);

    // Check that the NCT ID is linked when a route name is provided.
    $first_study = $studies[0];
    $first_identification = $first_study['protocolSection']['identificationModule'] ?? [];
    $expected_nct_id = $first_identification['nctId'] ?? '';
    $expected_title = $first_identification['briefTitle'] ?? '';
    $expected_url = Url::fromRoute('clinical_trials_gov_report.study', ['nctId' => $expected_nct_id])->toString();
    $first_row = $build['#rows'][0];
    $this->assertStringContainsString($expected_url, (string) $first_row[0]);
    $this->assertSame($expected_title, (string) $first_row[1]);
  }

}
