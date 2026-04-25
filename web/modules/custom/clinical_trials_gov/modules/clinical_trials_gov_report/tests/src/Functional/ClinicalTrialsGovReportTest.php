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

    // Check that submitting the form shows a results table.
    $this->getSession()->getPage()->fillField('query__cond', 'cancer');
    $this->assertSession()->elementExists('css', 'input[type="submit"][value="Search"]');
    $this->getSession()->getPage()->find('css', 'input[type="submit"][value="Search"]')->click();
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->elementExists('css', 'table');

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
