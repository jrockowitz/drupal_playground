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
   * Tests the full process → validate lifecycle of the element.
   */
  public function testProcessAndValidate(): void {
    $element = [
      '#type' => 'clinical_trials_gov_studies_query',
      '#default_value' => 'query.cond=cancer&filter.overallStatus=RECRUITING',
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

    // Check that query parameter sub-elements are built.
    $this->assertArrayHasKey('query_parameters', $processed);
    $this->assertArrayHasKey('query__cond', $processed['query_parameters']);

    // Check that the default value is populated into the sub-element.
    $this->assertSame('cancer', $processed['query_parameters']['query__cond']['#default_value']);

    // Check that filter sub-elements are built.
    $this->assertArrayHasKey('filters', $processed);
    $this->assertArrayHasKey('filter__overallStatus', $processed['filters']);
    $this->assertSame('RECRUITING', $processed['filters']['filter__overallStatus']['#default_value']);

    // Check that pagination sub-elements are built.
    $this->assertArrayHasKey('pagination', $processed);

    // Simulate submission: set #value on each relevant sub-element.
    $processed['query_parameters']['query__cond']['#value'] = 'cancer';
    $processed['filters']['filter__overallStatus']['#value'] = 'RECRUITING';
    $processed['pagination']['pageSize']['#value'] = '';

    ClinicalTrialsGovStudiesQuery::validateStudiesQuery($processed, $form_state, $complete_form);

    $assembled = $form_state->getValue(['studies_query']);

    // Check that non-empty values are assembled into the query string.
    $this->assertStringContainsString('query.cond=cancer', $assembled);
    $this->assertStringContainsString('filter.overallStatus=RECRUITING', $assembled);

    // Check that empty values are omitted.
    $this->assertStringNotContainsString('pageSize', $assembled);
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
