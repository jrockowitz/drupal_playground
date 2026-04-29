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
    $this->container->get('config.factory')->getEditable('clinical_trials_gov.settings')
      ->set('paths', [
        'protocolSection.contactsLocationsModule.locations.contacts',
      ])
      ->save();
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
    $this->assertSession()->pageTextContains('Studies');
    $this->assertSession()->elementExists('css', 'form');
    $this->assertSession()->pageTextContains('This page displays ClinicalTrials.gov studies returned by the API for the current query-string parameters.');
    $this->assertSession()->pageTextContains('Version: 2.0.5 and Last Updated:');
    $this->assertSession()->pageTextContains('April 24 2026');
    $this->assertSession()->pageTextContains('Showing 1 to');
    $this->assertSession()->pageTextContains('trials');
    $this->assertSession()->pageTextContains('Query-string parameters');
    $this->assertSession()->elementExists('css', 'details[open] summary');
    $this->assertSession()->fieldExists('query__cond');
    $this->assertSession()->fieldExists('query__patient');
    $this->assertSession()->fieldExists('filter__synonyms');
    $this->assertSession()->fieldExists('postFilter__overallStatus');
    $this->assertSession()->fieldExists('geoDecay');
    $this->assertSession()->fieldExists('fields');
    $this->assertSession()->fieldValueEquals('countTotal', 'true');
    $this->assertSession()->elementNotExists('css', 'input[type="submit"][value="Reset"]');
    $this->assertSession()->pageTextContains('countTotal=true');
    $studies_page_html = $this->getSession()->getPage()->getContent();
    $this->assertNotFalse(strpos($studies_page_html, '<hr'));
    $this->assertGreaterThan(
      strpos($studies_page_html, 'ClinicalTrials.gov API:'),
      strpos($studies_page_html, 'Version: 2.0.5 and Last Updated:')
    );
    $this->assertGreaterThan(
      strpos($studies_page_html, '<hr'),
      strpos($studies_page_html, 'Version: 2.0.5 and Last Updated:')
    );

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
    $this->assertSession()->elementNotExists('css', 'details[open] summary');
    $this->assertSession()->pageTextContains('Showing');
    $this->assertSession()->elementExists('css', 'input[type="submit"][value="Reset"]');
    $this->assertSession()->pageTextContains('ClinicalTrials.gov API:');

    // Check that the metadata report loads and shows the expected columns.
    $this->drupalGet('admin/reports/status/clinical-trials-gov/metadata');
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->elementExists('css', 'table');
    $this->assertSession()->pageTextContains('This page displays flattened ClinicalTrials.gov fields metadata returned by the API.');
    $this->assertSession()->pageTextContains('Field Name');
    $this->assertSession()->pageTextContains('Piece Name');
    $this->assertSession()->pageTextContains('Classic Type');
    $this->assertSession()->pageTextContains('Data Type');
    $this->assertSession()->pageTextContains('Definition');
    $this->assertSession()->pageTextContains('Description');
    $this->assertSession()->pageTextContains('Notes');
    $this->assertSession()->pageTextContains('Index Field');
    $this->assertSession()->pageTextContains('briefTitle');
    $this->assertSession()->pageTextContains('BriefTitle');
    $this->assertSession()->pageTextContains('BRIEF-TITLE');
    $this->assertSession()->pageTextContains('TEXT (max 300 chars)');
    $this->assertSession()->pageTextContains('text');
    $this->assertSession()->linkExists('Brief Title');
    $this->assertSession()->pageTextContains('Required for INT/OBS/EA. Has to be unique in PRS');
    $this->assertSession()->pageTextContains('protocolSection.identificationModule.briefTitle');
    $this->assertSession()->elementExists('css', 'a[href="https://clinicaltrials.gov/policy/protocol-definitions#BriefTitle"]');
    $this->assertSession()->pageTextContains('ClinicalTrials.gov API:');
    $metadata_page_html = $this->getSession()->getPage()->getContent();
    $this->assertStringContainsString('clinical-trials-gov-report-metadata__row--unused', $metadata_page_html);
    $this->assertMatchesRegularExpression('/clinical-trials-gov-report-metadata__row--unused.*statusModule/s', $metadata_page_html);
    $this->assertNotFalse(strpos($metadata_page_html, '<hr'));
    $this->assertGreaterThan(
      strpos($metadata_page_html, 'ClinicalTrials.gov API:'),
      strpos($metadata_page_html, 'Version: 2.0.5 and Last Updated:')
    );
    $this->assertGreaterThan(
      strpos($metadata_page_html, '<hr'),
      strpos($metadata_page_html, 'Version: 2.0.5 and Last Updated:')
    );

    // Check that the enums report loads and shows the expected grouped table.
    $this->drupalGet('admin/reports/status/clinical-trials-gov/enums');
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->pageTextContains('Enums');
    $this->assertSession()->elementExists('css', 'table');
    $this->assertSession()->pageTextContains('This page displays ClinicalTrials.gov enumeration types and their allowed values returned by the API.');
    $this->assertSession()->pageTextContains('Enum Type');
    $this->assertSession()->pageTextContains('Values');
    $this->assertSession()->pageTextContains('Pieces');
    $this->assertSession()->pageTextContains('Status');
    $this->assertSession()->pageTextContains('Completed (COMPLETED)');
    $this->assertSession()->pageTextContains('Recruiting (RECRUITING)');
    $this->assertSession()->pageTextContains('OverallStatus');
    $this->assertSession()->pageTextContains('LastKnownStatus');
    $this->assertSession()->pageTextContains('ClinicalTrials.gov API:');
    $enums_page_html = $this->getSession()->getPage()->getContent();
    $this->assertNotFalse(strpos($enums_page_html, '<hr'));
    $this->assertGreaterThan(
      strpos($enums_page_html, 'ClinicalTrials.gov API:'),
      strpos($enums_page_html, 'Version: 2.0.5 and Last Updated:')
    );
    $this->assertGreaterThan(
      strpos($enums_page_html, '<hr'),
      strpos($enums_page_html, 'Version: 2.0.5 and Last Updated:')
    );

    // Check that the structs report loads and shows the expected hierarchy data.
    $this->drupalGet('admin/reports/status/clinical-trials-gov/structs');
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->pageTextContains('Structs');
    $this->assertSession()->elementExists('css', 'table');
    $this->assertSession()->pageTextContains('This page displays ClinicalTrials.gov struct metadata and hierarchy returned by the API.');
    $this->assertSession()->pageTextContains('Struct');
    $this->assertSession()->pageTextContains('Piece');
    $this->assertSession()->pageTextContains('Data type');
    $this->assertSession()->pageTextContains('Sub-properties');
    $this->assertSession()->pageTextContains('contactsLocationsModule');
    $this->assertSession()->pageTextContains('locations');
    $this->assertSession()->pageTextContains('contacts');
    $this->assertSession()->pageTextContains('Location');
    $this->assertSession()->pageTextContains('Location[]');
    $this->assertSession()->pageTextContains('LocationContact');
    $this->assertSession()->pageTextContains('status');
    $this->assertSession()->pageTextContains('contacts[]');
    $this->assertSession()->pageTextContains('geoPoint');
    $this->assertSession()->elementExists('css', 'small.clinical-trials-gov-report-structs__sub-properties ul');
    $this->assertSession()->pageTextContains('ClinicalTrials.gov API:');
    $structs_page_html = $this->getSession()->getPage()->getContent();
    $this->assertStringContainsString('clinical-trials-gov-report-structs__row--unused', $structs_page_html);
    $this->assertMatchesRegularExpression('/clinical-trials-gov-report-structs__row--unused.*statusModule/s', $structs_page_html);
    $this->assertNotFalse(strpos($structs_page_html, '<hr'));
    $this->assertGreaterThan(
      strpos($structs_page_html, 'ClinicalTrials.gov API:'),
      strpos($structs_page_html, 'Version: 2.0.5 and Last Updated:')
    );
    $this->assertGreaterThan(
      strpos($structs_page_html, '<hr'),
      strpos($structs_page_html, 'Version: 2.0.5 and Last Updated:')
    );

    // Return to the studies report before checking the NCT detail link.
    $this->drupalGet('admin/reports/status/clinical-trials-gov');

    // Check that an NCT ID link is present in the results.
    // The stub returns fixture studies — look for any NCT link.
    $nct_link = $this->getSession()->getPage()->find('css', 'table a[href*="clinical-trials-gov/NCT"]');
    $this->assertNotNull($nct_link, 'An NCT ID link should appear in the results table.');

    // Check that following an NCT link loads the study detail page.
    $nct_link->click();
    $this->assertSession()->statusCodeEquals(200);

    // Check that the study detail page shows summary content and a flat table.
    $this->assertSession()->pageTextContains('Conditions');
    $this->assertSession()->pageTextContains('Study overview');
    $this->assertSession()->pageTextContains('Eligibility');
    $this->assertSession()->pageTextContains('Study summary');
    $this->assertSession()->pageTextContains('ClinicalTrials.gov URL:');
    $this->assertSession()->pageTextContains('Study data');
    $this->assertSession()->elementExists('css', 'details summary');
    $this->assertSession()->elementExists('css', 'details[open] summary');
    $this->assertSession()->pageTextNotContains('Raw data');
    $this->assertSession()->pageTextContains('ClinicalTrials.gov API:');

    // Check that the reset action returns to the unfiltered report page.
    $this->drupalGet('admin/reports/status/clinical-trials-gov');
    $this->getSession()->getPage()->fillField('query__cond', 'cancer');
    $this->getSession()->getPage()->findButton('Search')->click();
    $this->getSession()->getPage()->findButton('Reset')->click();
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->elementNotExists('css', 'input[type="submit"][value="Reset"]');

    // Check that an unknown NCT ID renders the not-found message.
    $this->drupalGet('admin/reports/status/clinical-trials-gov/NCT00000000');
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->pageTextContains('Study NCT00000000 not found.');
  }

}
