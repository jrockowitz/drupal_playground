<?php

declare(strict_types=1);

namespace Drupal\Tests\clinical_trials_gov\Kernel;

use Drupal\clinical_trials_gov\Controller\ClinicalTrialsGovReviewController;
use Drupal\KernelTests\KernelTestBase;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\HttpFoundation\Request;

/**
 * Kernel tests for ClinicalTrialsGovReviewController.
 *
 * @group clinical_trials_gov
 */
#[Group('clinical_trials_gov')]
class ClinicalTrialsGovReviewControllerTest extends KernelTestBase {

  /**
   * Modules required for these kernel tests.
   *
   * @var array
   */
  protected static $modules = [
    'clinical_trials_gov',
    'clinical_trials_gov_test',
  ];

  /**
   * Tests the review page includes the studies query summary details section.
   */
  public function testBuildListPageIncludesStudiesQuerySummary(): void {
    $this->container->get('config.factory')->getEditable('clinical_trials_gov.settings')
      ->set('query', 'query.cond=cancer&filter.overallStatus=RECRUITING')
      ->save();

    $controller = ClinicalTrialsGovReviewController::create($this->container);
    $build = $controller->index(new Request());

    // Check that the studies query details section is present and closed.
    $this->assertArrayHasKey('studies_query', $build);
    $this->assertSame('details', $build['studies_query']['#type']);
    $this->assertFalse($build['studies_query']['#open']);

    // Check that the summary element and action links are included.
    $this->assertSame('clinical_trials_gov_studies_query_summary', $build['studies_query']['summary']['#type']);
    $this->assertArrayHasKey('find', $build['studies_query']['links']);
    $this->assertArrayHasKey('review', $build['studies_query']['links']);

    // Check that the details section sits between the intro and results summary.
    $keys = array_values(array_filter(array_keys($build), static fn(string $key): bool => !str_starts_with($key, '#')));
    $this->assertSame(['intro', 'studies_query', 'summary'], array_slice($keys, 0, 3));
  }

}
