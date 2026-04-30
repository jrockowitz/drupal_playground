<?php

declare(strict_types=1);

namespace Drupal\Tests\clinical_trials_gov\Functional;

use Drupal\node\Entity\Node;
use Drupal\Tests\BrowserTestBase;
use PHPUnit\Framework\Attributes\Group;

/**
 * Functional tests for readonly ClinicalTrials.gov fields.
 *
 * @group clinical_trials_gov
 */
#[Group('clinical_trials_gov')]
class ClinicalTrialsGovReadonlyTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'clinical_trials_gov',
    'clinical_trials_gov_test',
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

    $this->container->get('clinical_trials_gov.entity_manager')->createContentType('trial', 'Trial', 'Clinical trial content type');
    $this->container->get('clinical_trials_gov.entity_manager')->createFields('trial', [
      'protocolSection.identificationModule.briefTitle',
      'protocolSection.identificationModule.nctId',
    ]);
    $this->container->get('config.factory')->getEditable('clinical_trials_gov.settings')
      ->set('type', 'trial')
      ->set('fields', [
        'field_trial_brief_title' => 'protocolSection.identificationModule.briefTitle',
        'field_trial_nct_id' => 'protocolSection.identificationModule.nctId',
      ])
      ->set('readonly', TRUE)
      ->save();

    $this->drupalLogin($this->drupalCreateUser([
      'access administration pages',
      'create trial content',
      'edit any trial content',
      'administer clinical_trials_gov',
    ]));
  }

  /**
   * Tests that readonly mode hides title and locks mapped fields.
   */
  public function testReadonlyFieldsOnEditForm(): void {
    $node = Node::create([
      'type' => 'trial',
      'title' => 'Editable title',
      'field_trial_brief_title' => [
        'value' => 'Readonly brief title',
      ],
      'field_trial_nct_id' => [
        'value' => 'NCT05088187',
      ],
    ]);
    $node->save();

    $this->drupalGet('node/' . $node->id() . '/edit');

    // Check that the title input is hidden when brief title is mapped.
    $this->assertSession()->fieldNotExists('Title');

    // Check that the mapped ClinicalTrials.gov fields render as readonly output.
    $this->assertSession()->fieldNotExists('Brief Title');
    $this->assertSession()->fieldNotExists('Nct Id');
    $this->assertSession()->pageTextContains('Readonly brief title');
    $this->assertSession()->pageTextContains('NCT05088187');
  }

}
