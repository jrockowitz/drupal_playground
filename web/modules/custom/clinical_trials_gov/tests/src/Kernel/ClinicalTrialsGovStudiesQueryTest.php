<?php

declare(strict_types=1);

namespace Drupal\Tests\clinical_trials_gov\Kernel;

use Drupal\clinical_trials_gov\Element\ClinicalTrialsGovStudiesQuery;
use Drupal\Core\Form\FormState;
use Drupal\KernelTests\KernelTestBase;
use PHPUnit\Framework\Attributes\Group;

/**
 * Kernel tests for ClinicalTrialsGovStudiesQuery form element.
 *
 * @group clinical_trials_gov
 */
#[Group('clinical_trials_gov')]
class ClinicalTrialsGovStudiesQueryTest extends KernelTestBase {

  // phpcs:ignore
  protected static $modules = [
    'clinical_trials_gov',
    'clinical_trials_gov_test',
  ];

  /**
   * Tests the full process and validate lifecycle of the element.
   */
  public function testProcessAndValidate(): void {
    $element = [
      '#type' => 'clinical_trials_gov_studies_query',
      '#default_value' => 'query.cond=cancer&query.patient=heart disease&filter.overallStatus=RECRUITING|COMPLETED&filter.synonyms=ConditionSearch:1651367|BasicSearch:2013558&postFilter.overallStatus=COMPLETED&geoDecay=func:linear,scale:100km,offset:10km,decay:0.1&fields=NCTId|BriefTitle&sort=%40relevance|LastUpdatePostDate&pageSize=20',
      '#parents' => ['studies_query'],
      '#array_parents' => ['studies_query'],
    ];
    $form_state = new FormState();
    $complete_form = [];

    $processed = ClinicalTrialsGovStudiesQuery::processStudiesQuery(
      $element,
      $form_state,
      $complete_form
    );

    // Check that the search details wrapper and groups are built.
    $this->assertArrayHasKey('search', $processed);
    $this->assertArrayHasKey('query_parameters', $processed['search']);
    $this->assertArrayHasKey('filters', $processed['search']);
    $this->assertArrayHasKey('post_filters', $processed['search']);
    $this->assertArrayHasKey('aggregation', $processed['search']);
    $this->assertArrayHasKey('output', $processed['search']);

    // Check that representative new fields exist and defaults are populated.
    $this->assertArrayHasKey('query__patient', $processed['search']['query_parameters']);
    $this->assertSame('heart disease', $processed['search']['query_parameters']['query__patient']['#default_value']);
    $this->assertArrayHasKey('filter__synonyms', $processed['search']['filters']);
    $this->assertSame('ConditionSearch:1651367|BasicSearch:2013558', $processed['search']['filters']['filter__synonyms']['#default_value']);
    $this->assertArrayHasKey('postFilter__overallStatus', $processed['search']['post_filters']);
    $this->assertArrayHasKey('geoDecay', $processed['search']['aggregation']);
    $this->assertArrayHasKey('fields', $processed['search']['output']);

    // Check that progressive enhancement assets are attached.
    $this->assertContains('clinical_trials_gov/studies_query', $processed['#attached']['library']);

    // Check that omitted API-default parameters are not rendered.
    $this->assertArrayNotHasKey('format', $processed['search']['output']);
    $this->assertArrayNotHasKey('markupFormat', $processed['search']['output']);

    // Check that descriptions render ClinicalTrials.gov links.
    $query_description = $this->container->get('renderer')->renderInIsolation($processed['search']['query_parameters']['query__cond']['#description']);
    $this->assertStringContainsString('https://clinicaltrials.gov/data-api/about-api/search-areas#ConditionSearch', (string) $query_description);
    $fields_description = $this->container->get('renderer')->renderInIsolation($processed['search']['output']['fields']['#description']);
    $this->assertStringContainsString('https://clinicaltrials.gov/data-api/about-api/study-data-structure', (string) $fields_description);

    // Simulate submission with representative scalar and multivalue values.
    $processed['search']['query_parameters']['query__cond']['#value'] = 'cancer';
    $processed['search']['query_parameters']['query__patient']['#value'] = 'heart disease';
    $processed['search']['filters']['filter__overallStatus']['#value'] = "RECRUITING,\nCOMPLETED";
    $processed['search']['filters']['filter__synonyms']['#value'] = 'ConditionSearch:1651367|BasicSearch:2013558';
    $processed['search']['post_filters']['postFilter__overallStatus']['#value'] = 'COMPLETED';
    $processed['search']['aggregation']['geoDecay']['#value'] = 'func:linear,scale:100km,offset:10km,decay:0.1';
    $processed['search']['output']['fields']['#value'] = "NCTId,\nBriefTitle";
    $processed['search']['output']['sort']['#value'] = '@relevance|LastUpdatePostDate';
    $processed['search']['output']['pageSize']['#value'] = '20';
    $processed['search']['output']['countTotal']['#value'] = '';

    ClinicalTrialsGovStudiesQuery::validateStudiesQuery($processed, $form_state, $complete_form);

    $assembled = $form_state->getValue(['studies_query']);

    // Check that representative values are assembled into the query string.
    $this->assertStringContainsString('query.cond=cancer', $assembled);
    $this->assertStringContainsString('query.patient=heart%20disease', $assembled);
    $this->assertStringContainsString('filter.overallStatus=RECRUITING%7CCOMPLETED', $assembled);
    $this->assertStringContainsString('filter.synonyms=ConditionSearch%3A1651367%7CBasicSearch%3A2013558', $assembled);
    $this->assertStringContainsString('postFilter.overallStatus=COMPLETED', $assembled);
    $this->assertStringContainsString('geoDecay=func%3Alinear%2Cscale%3A100km%2Coffset%3A10km%2Cdecay%3A0.1', $assembled);
    $this->assertStringContainsString('fields=NCTId%7CBriefTitle', $assembled);
    $this->assertStringContainsString('sort=%40relevance%7CLastUpdatePostDate', $assembled);
    $this->assertStringContainsString('pageSize=20', $assembled);

    // Check that empty values and omitted parameters stay out of the query.
    $this->assertStringNotContainsString('countTotal', $assembled);
    $this->assertStringNotContainsString('format', $assembled);
    $this->assertStringNotContainsString('markupFormat', $assembled);
  }

  /**
   * Tests that parseQueryString() preserves dot-notation keys.
   */
  public function testParseQueryStringPreservesDots(): void {
    $result = ClinicalTrialsGovStudiesQuery::parseQueryString(
      'query.cond=heart+disease&filter.overallStatus=RECRUITING&pageSize=20'
    );

    // Check that dot-notation keys survive the parse (unlike parse_str).
    $this->assertArrayHasKey('query.cond', $result);
    $this->assertSame('heart disease', $result['query.cond']);
    $this->assertArrayHasKey('filter.overallStatus', $result);
    $this->assertArrayHasKey('pageSize', $result);
    $this->assertSame('20', $result['pageSize']);
  }

  /**
   * Tests that an empty query string returns an empty array.
   */
  public function testParseQueryStringReturnsEmptyForEmptyInput(): void {
    // Check that an empty string produces an empty array.
    $this->assertSame([], ClinicalTrialsGovStudiesQuery::parseQueryString(''));
  }

}
