<?php

declare(strict_types=1);

namespace Drupal\Tests\clinical_trials_gov\Kernel;

use Drupal\clinical_trials_gov\Controller\ClinicalTrialsGovReviewMetadataController;
use Drupal\KernelTests\KernelTestBase;
use PHPUnit\Framework\Attributes\Group;

/**
 * Kernel tests for ClinicalTrialsGovReviewMetadataController.
 *
 * @group clinical_trials_gov
 */
#[Group('clinical_trials_gov')]
class ClinicalTrialsGovReviewMetadataControllerTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'clinical_trials_gov',
    'clinical_trials_gov_test',
    'migrate',
  ];

  /**
   * Tests the filtered metadata page includes only configured paths.
   */
  public function testIndexFiltersByConfiguredPaths(): void {
    $this->container->get('config.factory')->getEditable('clinical_trials_gov.settings')
      ->set('query', 'query.cond=cancer')
      ->set('required_paths', [])
      ->set('query_paths', [
        'protocolSection.identificationModule.briefTitle',
        'protocolSection.statusModule.overallStatus',
      ])
      ->save();

    $controller = ClinicalTrialsGovReviewMetadataController::create($this->container);
    $build = $controller->index();

    // Check that the intro, query details, summary, and footer are present.
    $this->assertArrayHasKey('intro', $build);
    $this->assertArrayHasKey('studies_query', $build);
    $this->assertArrayHasKey('summary', $build);
    $this->assertArrayHasKey('footer', $build);
    $this->assertContains('clinical_trials_gov/clinical_trials_gov', $build['#attached']['library']);
    $this->assertSame('details', $build['studies_query']['#type']);
    $this->assertFalse($build['studies_query']['#open']);
    $this->assertSame('clinical_trials_gov_studies_query_summary', $build['studies_query']['summary']['#type']);
    $this->assertArrayHasKey('find', $build['studies_query']['links']);
    $this->assertSame('table', $build['results']['#type']);
    $this->assertContains('clinical-trials-gov-table', $build['results']['#attributes']['class']);
    $this->assertStringContainsString('Showing 5 fields', (string) $build['summary']['#markup']);
    $this->assertArrayHasKey('field_paths', $build['footer']);
    $this->assertSame('details', $build['footer']['field_paths']['#type']);
    $this->assertFalse($build['footer']['field_paths']['#open']);
    $this->assertCount(5, $build['footer']['field_paths']['paths']['#items']);
    $this->assertSame('small', $build['footer']['field_paths']['paths']['#items'][0]['#tag']);
    $this->assertSame('protocolSection', $build['footer']['field_paths']['paths']['#items'][0]['#value']);
    $this->assertSame('small', $build['footer']['field_paths']['paths']['#items'][4]['#tag']);
    $this->assertSame('protocolSection.statusModule.overallStatus', $build['footer']['field_paths']['paths']['#items'][4]['#value']);

    // Check that the query details section sits between the intro and results summary.
    $keys = array_values(array_filter(array_keys($build), static fn(string $key): bool => !str_starts_with($key, '#')));
    $this->assertSame(['intro', 'studies_query', 'summary'], array_slice($keys, 0, 3));

    // Check that only configured metadata rows are rendered.
    $this->assertCount(5, $build['results']['#rows']);
    $paths = array_map(static fn(array $row): string => (string) $row['data'][2]['data']['#value'], $build['results']['#rows']);
    $this->assertContains('protocolSection.identificationModule.briefTitle', $paths);
    $this->assertContains('protocolSection.statusModule.overallStatus', $paths);
  }

  /**
   * Tests that a missing saved query uses the same warning behavior as Studies.
   */
  public function testIndexWarnsWhenSavedQueryIsMissing(): void {
    $this->container->get('config.factory')->getEditable('clinical_trials_gov.settings')
      ->set('query', '')
      ->save();

    $controller = ClinicalTrialsGovReviewMetadataController::create($this->container);
    $build = $controller->index();
    $warning_messages = $this->container->get('messenger')->messagesByType('warning');

    // Check that the page warns through the messenger and returns an empty build.
    $this->assertSame([], $build);
    $this->assertCount(1, $warning_messages);
    $this->assertStringContainsString('No saved query was found.', (string) $warning_messages[0]);
  }

}
