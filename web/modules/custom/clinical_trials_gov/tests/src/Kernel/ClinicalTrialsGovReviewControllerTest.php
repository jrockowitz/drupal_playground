<?php

declare(strict_types=1);

namespace Drupal\Tests\clinical_trials_gov\Kernel;

use Drupal\clinical_trials_gov\Controller\ClinicalTrialsGovReviewStudiesController;
use Drupal\KernelTests\KernelTestBase;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\HttpFoundation\Request;

/**
 * Kernel tests for ClinicalTrialsGovReviewStudiesController.
 *
 * @group clinical_trials_gov
 */
#[Group('clinical_trials_gov')]
class ClinicalTrialsGovReviewControllerTest extends KernelTestBase {

  /**
   * Modules required for these kernel tests.
   *
   * @var array<string>
   */
  protected static $modules = [
    'clinical_trials_gov',
    'clinical_trials_gov_test',
    'migrate',
  ];

  /**
   * Tests the review page includes the studies query summary details section.
   */
  public function testBuildListPageIncludesStudiesQuerySummary(): void {
    $this->container->get('config.factory')->getEditable('clinical_trials_gov.settings')
      ->set('query', 'query.cond=cancer&filter.overallStatus=RECRUITING')
      ->save();

    $controller = ClinicalTrialsGovReviewStudiesController::create($this->container);
    $build = $controller->index(new Request());

    // Check that the studies query details section is present and closed.
    $this->assertArrayHasKey('studies_query', $build);
    $this->assertSame('details', $build['studies_query']['#type']);
    $this->assertFalse($build['studies_query']['#open']);

    // Check that the summary element and action links are included.
    $this->assertSame('clinical_trials_gov_studies_query_summary', $build['studies_query']['summary']['#type']);
    $this->assertArrayHasKey('find', $build['studies_query']['links']);
    $this->assertArrayNotHasKey('review', $build['studies_query']['links']);

    // Check that the details section sits between the intro and results summary.
    $keys = array_values(array_filter(array_keys($build), static fn(string $key): bool => !str_starts_with($key, '#')));
    $this->assertSame(['intro', 'studies_query', 'summary'], array_slice($keys, 0, 3));

    // Check that the study route title callback uses the study brief title.
    $this->assertSame('Cognition and QoL After Thyroid Surgery', $controller->title('NCT05088187'));

    $this->container->get('messenger')->deleteAll();
    $this->container->get('config.factory')->getEditable('clinical_trials_gov.settings')
      ->set('query', '')
      ->save();
    $empty_build = $controller->index(new Request());
    $warning_messages = $this->container->get('messenger')->messagesByType('warning');

    // Check that a missing saved query uses the page messenger instead of an inline render message.
    $this->assertSame([], $empty_build);
    $this->assertCount(1, $warning_messages);
    $this->assertStringContainsString('No saved query was found.', (string) $warning_messages[0]);
  }

}
