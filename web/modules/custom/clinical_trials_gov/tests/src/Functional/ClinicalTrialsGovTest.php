<?php

declare(strict_types=1);

namespace Drupal\Tests\clinical_trials_gov\Functional;

use Drupal\Tests\BrowserTestBase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Functional tests for the ClinicalTrials.gov import wizard.
 *
 * @group clinical_trials_gov
 */
#[Group('clinical_trials_gov')]
#[RunTestsInSeparateProcesses]
class ClinicalTrialsGovTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'clinical_trials_gov',
    'clinical_trials_gov_test',
    'clinical_trials_gov_report',
    'field_group',
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
    $this->drupalLogin($this->drupalCreateUser([
      'access administration pages',
      'administer clinical_trials_gov',
    ]));
  }

  /**
   * Tests the basic wizard flow.
   */
  public function testWizardFlow(): void {
    $this->drupalGet('admin/config/services/clinical-trials-gov');

    // Check that the overview page renders the four tasks and next-step message.
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->pageTextContains('Please go to Find and build your query.');
    $this->assertSession()->linkByHrefExists('/admin/config/services/clinical-trials-gov/find');
    $this->assertSession()->linkExists('Find');
    $this->assertSession()->pageTextContains('1. Find');
    $this->assertSession()->pageTextContains('2. Review');
    $this->assertSession()->pageTextContains('3. Configure');
    $this->assertSession()->pageTextContains('4. Import');

    $this->drupalGet('admin/config/services/clinical-trials-gov/find');

    // Check that Find starts without preview results when no query is saved.
    $this->assertSession()->pageTextContains('Use Update preview to preview the current query without saving it.');

    $this->getSession()->getPage()->fillField('query__cond', 'cancer');
    $this->getSession()->getPage()->pressButton('Save configuration');

    // Check that submitting Find redirects to Review and shows studies.
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->linkExists('query');
    $this->assertSession()->linkExists('configuring the destination content type');
    $this->assertSession()->linkNotExists('Continue to Configure');
    $this->assertSession()->linkExists('NCT05088187');
    $this->assertSession()->elementExists('css', 'table');

    $this->clickLink('NCT05088187');

    // Check that review detail pages stay inside the wizard route space.
    $this->assertSession()->addressEquals('admin/config/services/clinical-trials-gov/review/NCT05088187');
    $this->assertSession()->pageTextContains('Study summary');

    $this->drupalGet('admin/config/services/clinical-trials-gov/review/INVALID');

    // Check that invalid identifiers fall back to the review list with a warning.
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->pageTextContains('The study identifier');
    $this->assertSession()->pageTextContains('INVALID');
    $this->assertSession()->pageTextContains('is not valid.');
    $this->assertSession()->pageTextContains('Showing 1 - 2 of 3 trials.');

    $this->drupalGet('admin/config/services/clinical-trials-gov/find');

    // Check that Find auto-loads preview results when a saved query exists.
    $this->assertSession()->pageTextContains('Showing 1 - 2 of 3 trials.');
    $this->assertSession()->linkExists('NCT05088187');

    $this->drupalGet('admin/config/services/clinical-trials-gov');

    // Check that the overview page links to Configure when the query is saved.
    $this->assertSession()->pageTextContains('Your query is saved. Go to Configure and select the destination content type and fields.');
    $this->assertSession()->linkByHrefExists('/admin/config/services/clinical-trials-gov/configure');
    $this->assertSession()->linkExists('Configure');

    $this->drupalGet('admin/config/services/clinical-trials-gov/configure');

    // Check that the field mapping table uses the updated columns and values.
    $this->assertSession()->pageTextContains('Piece');
    $this->assertSession()->pageTextContains('Field type');
    $this->assertSession()->pageTextContains('Protocol Section');
    $this->assertSession()->pageTextContains('Responsible Party');
    $this->assertSession()->pageTextContains('Design Masking Info');
    $this->assertSession()->pageTextContains('string (multiple)');
    $this->assertSession()->pageTextContains('field group');
    $this->assertSession()->pageTextContains('custom field');
    $this->assertSession()->pageTextContains('ProtocolSection');
    $this->assertSession()->pageTextContains('ResponsibleParty');
    $this->assertSession()->elementExists('css', 'td ul li');
    $this->assertSession()->pageTextContains('investigator_full_name');
    $this->assertSession()->pageTextNotContains('Details');
    $this->assertSession()->elementExists('css', 'th.select-all');
    $this->assertSession()->fieldExists('field_mapping[rows][' . md5('protocolSection.sponsorCollaboratorsModule.responsibleParty') . '][selected]');
    $this->assertSession()->checkboxChecked('field_mapping[rows][' . md5('protocolSection.sponsorCollaboratorsModule.responsibleParty') . '][selected]');
    $this->assertSession()->checkboxChecked('field_mapping[rows][' . md5('protocolSection.statusModule.overallStatus') . '][selected]');
    $this->assertSession()->fieldNotExists('field_mapping[rows][' . md5('protocolSection') . '][selected]');
    $this->assertSession()->pageTextNotContains('Responsible Party Investigator Full Name');
    $this->assertSession()->pageTextNotContains('NCTIdAlias');
    $this->assertSession()->pageTextNotContains('field_nct_id_alias');
    $this->assertSession()->pageTextNotContains('protocolSection.identificationModule.nctIdAliases');
    $this->assertSession()->pageTextNotContains('No description.');
    $this->assertSession()->fieldValueEquals('Description', 'Imported ClinicalTrials.gov studies.');
    $this->assertSession()->elementAttributeContains('css', 'textarea[name="description"]', 'rows', '3');

    if ($this->getSession()->getPage()->findField('Label') !== NULL) {
      $this->getSession()->getPage()->fillField('Label', 'Trial');
      $this->getSession()->getPage()->fillField('Machine name', 'trial');
    }
    if ($this->getSession()->getPage()->findButton('Save configuration') !== NULL) {
      $this->getSession()->getPage()->pressButton('Save configuration');
    }
    else {
      $this->getSession()->getPage()->pressButton('Save');
    }

    // Check that Configure redirects to Import and the summary is ready.
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->pageTextContains('Selected fields');
    $this->assertSession()->pageTextContains('trial');
    $this->assertSession()->buttonExists('Run Import');

    $this->drupalGet('admin/config/services/clinical-trials-gov');

    // Check that the overview page links to Import when the wizard is ready.
    $this->assertSession()->pageTextContains('Your query and field mapping are ready. Continue to Import when you are ready to sync studies.');
    $this->assertSession()->linkByHrefExists('/admin/config/services/clinical-trials-gov/import');
    $this->assertSession()->linkExists('Import');
  }

}
