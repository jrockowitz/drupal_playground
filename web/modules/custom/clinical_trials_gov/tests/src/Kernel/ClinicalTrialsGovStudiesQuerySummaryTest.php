<?php

declare(strict_types=1);

namespace Drupal\Tests\clinical_trials_gov\Kernel;

use Drupal\clinical_trials_gov\Element\ClinicalTrialsGovStudiesQuerySummary;
use Drupal\KernelTests\KernelTestBase;
use PHPUnit\Framework\Attributes\Group;

/**
 * Kernel tests for the ClinicalTrialsGovStudiesQuerySummary render element.
 *
 * @group clinical_trials_gov
 */
#[Group('clinical_trials_gov')]
class ClinicalTrialsGovStudiesQuerySummaryTest extends KernelTestBase {

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
   * Tests the studies query summary render element.
   */
  public function testRenderElementSummary(): void {
    $build = ClinicalTrialsGovStudiesQuerySummary::preRenderSummary([
      '#type' => 'clinical_trials_gov_studies_query_summary',
      '#query' => 'filter.overallStatus=RECRUITING|COMPLETED&query.cond=cancer&unknown.parameter=custom',
    ]);

    // Check that known parameters render in the defined query order.
    $this->assertCount(3, $build['#rows']);
    $this->assertSame('Condition or disease', $build['#rows'][0][0]['data']['#context']['title']);
    $this->assertSame('Overall status', $build['#rows'][1][0]['data']['#context']['title']);

    // Check that the title and raw key both render in the first column.
    $this->assertSame('query.cond', $build['#rows'][0][0]['data']['#context']['key']);

    // Check that multi-value parameters render as a readable list.
    $this->assertSame('RECRUITING, COMPLETED', $build['#rows'][1][1]);

    // Check that unknown parameters fall back to the raw key.
    $this->assertSame('unknown.parameter', $build['#rows'][2][0]['data']['#context']['key']);
    $this->assertSame('custom', $build['#rows'][2][1]);
  }

}
