<?php

declare(strict_types=1);

namespace Drupal\Tests\clinical_trials_gov\Functional;

use Drupal\Tests\BrowserTestBase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Functional tests for the ClinicalTrials.gov import wizard.
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
    'readonly_field_widget',
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
      'access content overview',
      'administer clinical_trials_gov',
    ]));
  }

  /**
   * Tests the basic wizard flow.
   */
  public function testWizardFlow(): void {
    $this->drupalGet('admin/config/services/clinical-trials-gov');

    // Check that the overview page renders the tasks and next-step message.
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->pageTextContains('Please go to Find and build your query.');
    $this->assertSession()->linkByHrefExists('/admin/config/services/clinical-trials-gov/find');
    $this->assertSession()->linkExists('Find');
    $this->assertSession()->pageTextContains('1. Find');
    $this->assertSession()->pageTextContains('2. Review');
    $this->assertSession()->pageTextContains('3. Configure');
    $this->assertSession()->pageTextContains('4. Import');
    $this->assertSession()->pageTextContains('5. Manage');
    $this->assertSession()->pageTextContains('Settings');
    $this->assertSession()->linkByHrefExists('/admin/config/services/clinical-trials-gov/settings');

    $this->drupalGet('admin/config/services/clinical-trials-gov/settings');

    // Check that Settings starts with editable defaults and guidance text.
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->fieldValueEquals('Content type machine name', 'trial');
    $this->assertSession()->fieldValueEquals('Field prefix', 'trial');
    $this->assertSession()->pageTextContains('trial');
    $this->assertSession()->pageTextContains('study');
    $this->assertSession()->pageTextContains('nct');
    $this->assertNotNull($this->getSession()->getPage()->findField('Content type machine name'));
    $this->assertNotNull($this->getSession()->getPage()->findField('Field prefix'));
    $this->assertSession()->checkboxNotChecked('Read-only imported fields');

    $this->drupalGet('admin/config/services/clinical-trials-gov/manage');

    // Check that Manage redirects to Configure without a message when no query is saved.
    $this->assertSession()->addressEquals('admin/config/services/clinical-trials-gov/configure');
    $this->assertSession()->pageTextNotContains('Create the destination content type before managing imported studies.');

    $this->drupalGet('admin/config/services/clinical-trials-gov/find');

    // Check that Find starts without preview results when no query is saved.
    $this->assertSession()->pageTextContains('Use Update preview to preview the current query without saving it.');

    $this->container->get('config.factory')->getEditable('clinical_trials_gov.settings')
      ->set('query', 'query.cond=cancer')
      ->set('query_paths', [
        'protocolSection.conditionsModule.conditions',
        'protocolSection.designModule.designInfo.maskingInfo.masking',
        'protocolSection.sponsorCollaboratorsModule.responsibleParty',
        'protocolSection.statusModule.overallStatus',
        'protocolSection.identificationModule.nctId',
        'protocolSection.identificationModule.briefTitle',
        'protocolSection.descriptionModule.briefSummary',
      ])
      ->save();

    $this->drupalGet('admin/config/services/clinical-trials-gov/manage');

    // Check that Manage shows the message when a query is saved but no content type exists.
    $this->assertSession()->addressEquals('admin/config/services/clinical-trials-gov/configure');
    $this->assertSession()->pageTextContains('Create the destination content type before managing imported studies.');

    $this->drupalGet('admin/config/services/clinical-trials-gov/review');

    // Check that a saved query with discovered paths redirects to Review and shows studies.
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->linkExists('query');
    $this->assertSession()->linkExists('configuring the destination content type');
    $this->assertSession()->linkNotExists('Continue to Configure');
    $this->assertSession()->linkExists('NCT05088187');
    $this->assertSession()->elementExists('css', 'table');

    $this->drupalGet('admin/config/services/clinical-trials-gov/review/metadata');

    // Check that Review Metadata only shows configured metadata paths.
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->pageTextContains('Showing 7 fields');
    $this->assertSession()->pageTextContains('protocolSection.identificationModule.nctId');
    $this->assertSession()->pageTextContains('protocolSection.sponsorCollaboratorsModule.responsibleParty');
    $this->assertSession()->pageTextNotContains('protocolSection.contactsLocationsModule.centralContacts');

    $this->drupalGet('admin/config/services/clinical-trials-gov/review');

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

    // Check that Configure shows the new Settings guidance before structure exists.
    $this->assertSession()->pageTextContains('Review the content type and fields that will be created below.');
    $this->assertSession()->linkByHrefExists('/admin/config/services/clinical-trials-gov/settings');
    $this->assertSession()->linkExists('Settings');

    // Check that the field mapping table uses the updated columns and values.
    $this->assertSession()->pageTextContains('Piece');
    $this->assertSession()->pageTextContains('Field type');
    $this->assertSession()->pageTextContains('Protocol Section');
    $this->assertSession()->pageTextContains('Responsible Party');
    $this->assertSession()->pageTextContains('Design Masking Info');
    $this->assertSession()->pageTextContains('field group');
    $this->assertSession()->pageTextContains('custom field');
    $this->assertSession()->pageTextContains('ProtocolSection');
    $this->assertSession()->pageTextContains('ResponsibleParty');
    $this->assertSession()->elementExists('css', 'td ul li');
    $this->assertSession()->pageTextContains('inv_full_name');
    $this->assertSession()->pageTextNotContains('Details');
    $this->assertSession()->elementExists('css', 'th.select-all');
    $this->assertSession()->fieldExists('field_mapping[rows][' . md5('protocolSection.sponsorCollaboratorsModule.responsibleParty') . '][selected]');
    $this->assertSession()->checkboxChecked('field_mapping[rows][' . md5('protocolSection.sponsorCollaboratorsModule.responsibleParty') . '][selected]');
    $this->assertSession()->checkboxChecked('field_mapping[rows][' . md5('protocolSection.statusModule.overallStatus') . '][selected]');
    $this->assertSession()->fieldNotExists('field_mapping[rows][' . md5('protocolSection') . '][selected]');
    $this->assertSession()->pageTextNotContains('Responsible Party Investigator Full Name');
    $this->assertSession()->pageTextNotContains('NCTIdAlias');
    $this->assertSession()->pageTextNotContains('trial_nct_id_alias');
    $this->assertSession()->pageTextNotContains('protocolSection.identificationModule.nctIdAliases');
    $this->assertSession()->pageTextNotContains('No description.');
    $this->assertSession()->fieldValueEquals('Description', 'Imported ClinicalTrials.gov studies.');
    $this->assertSession()->elementAttributeContains('css', 'textarea[name="description"]', 'rows', '3');

    if ($this->getSession()->getPage()->findField('Label')) {
      $this->getSession()->getPage()->fillField('Label', 'Trial');
    }
    if ($this->getSession()->getPage()->findButton('Save configuration')) {
      $this->getSession()->getPage()->pressButton('Save configuration');
    }
    else {
      $this->getSession()->getPage()->pressButton('Save');
    }

    $saved_fields = $this->container->get('config.factory')->get('clinical_trials_gov.settings')->get('fields');

    // Check that Configure redirects to Import and the summary is ready.
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->pageTextContains('Selected fields');
    $this->assertSession()->pageTextContains('trial');
    $this->assertSession()->buttonExists('Run Import');
    $this->assertIsArray($saved_fields);
    $this->assertFalse(array_is_list($saved_fields));
    $this->assertSame('protocolSection.identificationModule.nctId', $saved_fields['trial_nct_id']);
    $this->assertSame('protocolSection.identificationModule.briefTitle', $saved_fields['trial_brief_title']);
    $this->assertSame('protocolSection.statusModule.overallStatus', $saved_fields['trial_over_status']);
    $this->assertSame('protocolSection.sponsorCollaboratorsModule.responsibleParty', $saved_fields['trial_resp_party']);

    $this->drupalGet('admin/config/services/clinical-trials-gov');

    // Check that the overview page links to Import when the wizard is ready.
    $this->assertSession()->pageTextContains('Your query and field mapping are ready. Continue to Import when you are ready to sync studies.');
    $this->assertSession()->linkByHrefExists('/admin/config/services/clinical-trials-gov/import');
    $this->assertSession()->linkExists('Import');

    $this->drupalGet('admin/config/services/clinical-trials-gov/settings');

    // Check that machine-name settings lock after structure creation.
    $this->assertTrue($this->getSession()->getPage()->findField('Content type machine name')->hasAttribute('disabled'));
    $this->assertTrue($this->getSession()->getPage()->findField('Field prefix')->hasAttribute('disabled'));

    $this->drupalGet('admin/config/services/clinical-trials-gov/manage');

    // Check that Manage redirects to the filtered content overview once the type exists.
    $this->assertStringContainsString('/admin/content?title=&type=trial', $this->getSession()->getCurrentUrl());

    $this->container->get('config.factory')->getEditable('clinical_trials_gov.settings')
      ->set('query_paths', [])
      ->save();

    $this->drupalGet('admin/config/services/clinical-trials-gov/configure');

    // Check that Configure is blocked when no paths have been discovered.
    $this->assertSession()->pageTextContains('Save a studies query from the Find step before configuring the destination content type and fields.');
    $this->assertSession()->linkByHrefExists('/admin/config/services/clinical-trials-gov/find');
    $this->assertSession()->linkExists('Find');
    $this->assertSession()->pageTextNotContains('Field mapping');

    $this->container->get('config.factory')->getEditable('clinical_trials_gov.settings')
      ->set('query_paths', [
        'protocolSection.statusModule.overallStatus',
        'protocolSection.identificationModule.nctId',
        'protocolSection.identificationModule.briefTitle',
        'protocolSection.descriptionModule.briefSummary',
      ])
      ->save();

    $this->drupalGet('admin/config/services/clinical-trials-gov/configure');

    // Check that Configure reflects the saved path allow-list.
    $this->assertSession()->pageTextContains('protocolSection.statusModule.overallStatus');
    $this->assertSession()->pageTextNotContains('Responsible Party');
  }

}
