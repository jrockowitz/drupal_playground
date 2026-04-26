<?php

declare(strict_types=1);

namespace Drupal\Tests\clinical_trials_gov_report\Functional;

use Drupal\Tests\BrowserTestBase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Functional tests for the ClinicalTrials.gov report.
 *
 * Uses clinical_trials_gov_test to replace the live API manager with a stub,
 * ensuring tests are deterministic and require no network access.
 *
 * @group clinical_trials_gov_report
 */
#[Group('clinical_trials_gov_report')]
#[RunTestsInSeparateProcesses]
class ClinicalTrialsGovReportTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'clinical_trials_gov',
    'clinical_trials_gov_test',
    'clinical_trials_gov_report',
  ];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->drupalLogin($this->drupalCreateUser(['access administration pages']));
  }

  /**
   * Tests the full report flow: list page, search, and study detail.
   *
   * A single test method covers the whole flow so the Drupal install runs once.
   */
  public function testReportFlow(): void {
    // Check that the report page loads with the search form.
    $this->drupalGet('admin/reports/status/clinical-trials-gov');
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->elementExists('css', 'form');
    $this->assertSession()->fieldExists('query__cond');
    $this->assertSession()->fieldExists('query__patient');
    $this->assertSession()->fieldExists('filter__synonyms');
    $this->assertSession()->fieldExists('postFilter__overallStatus');
    $this->assertSession()->fieldExists('geoDecay');
    $this->assertSession()->fieldExists('fields');

    // Check that submitting the form shows a results table.
    $this->getSession()->getPage()->fillField('query__cond', 'cancer');
    $this->getSession()->getPage()->fillField('query__patient', 'heart disease');
    $this->getSession()->getPage()->fillField('filter__synonyms', 'ConditionSearch:1651367|BasicSearch:2013558');
    $this->getSession()->getPage()->fillField('postFilter__overallStatus', 'RECRUITING|COMPLETED');
    $this->getSession()->getPage()->fillField('geoDecay', 'func:linear,scale:100km,offset:10km,decay:0.1');
    $this->getSession()->getPage()->fillField('fields', 'NCTId|BriefTitle');
    $this->assertSession()->elementExists('css', 'input[type="submit"][value="Search"]');
    $this->getSession()->getPage()->find('css', 'input[type="submit"][value="Search"]')->click();
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->elementExists('css', 'table');
    $this->assertSession()->fieldValueEquals('query__patient', 'heart disease');
    $this->assertSession()->fieldValueEquals('postFilter__overallStatus', 'RECRUITING|COMPLETED');

    // Check that an NCT ID link is present in the results.
    // The stub returns fixture studies — look for any NCT link.
    $nct_link = $this->getSession()->getPage()->find('css', 'table a[href*="clinical-trials-gov/NCT"]');
    $this->assertNotNull($nct_link, 'An NCT ID link should appear in the results table.');

    // Check that following an NCT link loads the study detail page.
    $nct_link->click();
    $this->assertSession()->statusCodeEquals(200);

    // Check that the study detail page contains at least one details element.
    $this->assertSession()->elementExists('css', 'details');

    // Check that the raw data details widget is present.
    $this->assertSession()->pageTextContains('Raw data');
  }

}
