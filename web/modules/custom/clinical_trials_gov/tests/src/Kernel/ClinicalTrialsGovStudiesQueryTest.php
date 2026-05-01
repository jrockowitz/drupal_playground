<?php

declare(strict_types=1);

namespace Drupal\Tests\clinical_trials_gov\Kernel;

use Drupal\clinical_trials_gov\Element\ClinicalTrialsGovStudiesQuery;
use Drupal\Core\Form\FormState;
use Drupal\Core\StringTranslation\TranslatableMarkup;
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
    'migrate',
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

    // Check that the element is a flat list in the live API parameter order.
    $element_keys = array_values(array_filter(array_keys($processed), static fn(string $key): bool => !str_starts_with($key, '#')));
    $this->assertSame([
      'query__cond',
      'query__term',
      'query__locn',
      'query__titles',
      'query__intr',
      'query__outc',
      'query__spons',
      'query__lead',
      'query__id',
      'query__patient',
      'filter__overallStatus',
      'filter__geo',
      'filter__ids',
      'filter__advanced',
      'filter__synonyms',
      'postFilter__overallStatus',
      'postFilter__geo',
      'postFilter__ids',
      'postFilter__advanced',
      'postFilter__synonyms',
      'aggFilters',
      'geoDecay',
      'fields',
      'sort',
      'countTotal',
      'pageSize',
      'pageToken',
    ], $element_keys);

    // Check that representative new fields exist and defaults are populated.
    $this->assertArrayHasKey('query__patient', $processed);
    $this->assertSame('heart disease', $processed['query__patient']['#default_value']);
    $this->assertArrayHasKey('filter__synonyms', $processed);
    $this->assertSame('ConditionSearch:1651367|BasicSearch:2013558', $processed['filter__synonyms']['#default_value']);
    $this->assertArrayHasKey('postFilter__overallStatus', $processed);
    $this->assertArrayHasKey('geoDecay', $processed);
    $this->assertArrayHasKey('fields', $processed);
    $this->assertSame('true', $processed['countTotal']['#default_value']);

    // Check that progressive enhancement assets are attached.
    $this->assertContains('clinical_trials_gov/studies_query', $processed['#attached']['library']);

    // Check that omitted API-default parameters are not rendered.
    $this->assertArrayNotHasKey('format', $processed);
    $this->assertArrayNotHasKey('markupFormat', $processed);

    // Check that labels and field metadata render separately.
    $this->assertInstanceOf(TranslatableMarkup::class, $processed['query__cond']['#title']);
    $this->assertSame('Condition or disease', (string) $processed['query__cond']['#title']);
    $this->assertSame('Sort', (string) $processed['sort']['#title']);
    $this->assertStringContainsString('filter.overallStatus', $processed['filter__overallStatus']['#field_prefix']);
    $this->assertStringContainsString('(array of string)', $processed['filter__overallStatus']['#field_prefix']);

    // Check that descriptions render ClinicalTrials.gov links and omit CSV text.
    $query_description = $this->container->get('renderer')->renderInIsolation($processed['query__cond']['#description']);
    $this->assertStringContainsString('https://clinicaltrials.gov/data-api/about-api/search-areas#ConditionSearch', (string) $query_description);
    $this->assertStringContainsString('Examples:', (string) $query_description);
    $fields_description = $this->container->get('renderer')->renderInIsolation($processed['fields']['#description']);
    $this->assertStringContainsString('https://clinicaltrials.gov/data-api/about-api/study-data-structure', (string) $fields_description);
    $this->assertStringNotContainsString('CSV', (string) $fields_description);
    $page_token_description = $this->container->get('renderer')->renderInIsolation($processed['pageToken']['#description']);
    $this->assertStringNotContainsString('x-next-page-token', (string) $page_token_description);
    $this->assertStringNotContainsString('Do not specify it for first page', (string) $page_token_description);
    $this->assertInstanceOf(TranslatableMarkup::class, $processed['countTotal']['#options']['true']);
    $this->assertSame('Yes', (string) $processed['countTotal']['#options']['true']);

    // Simulate submission with representative scalar and multivalue values.
    $processed['query__cond']['#value'] = 'cancer';
    $processed['query__patient']['#value'] = 'heart disease';
    $processed['filter__overallStatus']['#value'] = "RECRUITING,\nCOMPLETED";
    $processed['filter__synonyms']['#value'] = 'ConditionSearch:1651367|BasicSearch:2013558';
    $processed['postFilter__overallStatus']['#value'] = 'COMPLETED';
    $processed['geoDecay']['#value'] = 'func:linear,scale:100km,offset:10km,decay:0.1';
    $processed['fields']['#value'] = "NCTId,\nBriefTitle";
    $processed['sort']['#value'] = '@relevance|LastUpdatePostDate';
    $processed['pageSize']['#value'] = '20';
    $processed['countTotal']['#value'] = 'true';

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

    // Check that the default total-count behavior is preserved in the query.
    $this->assertStringContainsString('countTotal=true', $assembled);
    $this->assertStringNotContainsString('format', $assembled);
    $this->assertStringNotContainsString('markupFormat', $assembled);
  }

  /**
   * Tests that prefix-based include fields limit the rendered query inputs.
   */
  public function testIncludeFieldsPrefix(): void {
    $element = [
      '#type' => 'clinical_trials_gov_studies_query',
      '#default_value' => 'query.cond=cancer&query.patient=heart disease&filter.overallStatus=RECRUITING&pageSize=20&fields=NCTId',
      '#include_fields' => [
        'query.',
      ],
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

    // Check that only query-prefixed fields are rendered.
    $this->assertArrayHasKey('query__cond', $processed);
    $this->assertArrayHasKey('query__patient', $processed);
    $this->assertArrayNotHasKey('filter__overallStatus', $processed);
    $this->assertArrayNotHasKey('pageSize', $processed);
    $this->assertArrayNotHasKey('fields', $processed);

    $processed['query__cond']['#value'] = 'cancer';
    $processed['query__patient']['#value'] = 'heart disease';
    ClinicalTrialsGovStudiesQuery::validateStudiesQuery($processed, $form_state, $complete_form);

    $assembled = $form_state->getValue(['studies_query']);

    // Check that only query-prefixed fields are reassembled into the query string.
    $this->assertStringContainsString('query.cond=cancer', $assembled);
    $this->assertStringContainsString('query.patient=heart%20disease', $assembled);
    $this->assertStringNotContainsString('filter.overallStatus', $assembled);
    $this->assertStringNotContainsString('pageSize', $assembled);
    $this->assertStringNotContainsString('fields', $assembled);
  }

  /**
   * Tests that exact include fields render only those keys.
   */
  public function testIncludeFieldsExactMatch(): void {
    $element = [
      '#type' => 'clinical_trials_gov_studies_query',
      '#default_value' => 'query.cond=cancer&pageSize=20&query.patient=heart disease',
      '#include_fields' => [
        'query.cond',
        'pageSize',
      ],
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

    // Check that exact includes render only the requested keys.
    $this->assertArrayHasKey('query__cond', $processed);
    $this->assertArrayHasKey('pageSize', $processed);
    $this->assertArrayNotHasKey('query__patient', $processed);
    $this->assertArrayNotHasKey('fields', $processed);

    $processed['query__cond']['#value'] = 'cancer';
    $processed['pageSize']['#value'] = '20';
    ClinicalTrialsGovStudiesQuery::validateStudiesQuery($processed, $form_state, $complete_form);

    $assembled = $form_state->getValue(['studies_query']);

    // Check that only exact included keys are reassembled.
    $this->assertStringContainsString('query.cond=cancer', $assembled);
    $this->assertStringContainsString('pageSize=20', $assembled);
    $this->assertStringNotContainsString('query.patient', $assembled);
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
