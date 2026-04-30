<?php

declare(strict_types=1);

namespace Drupal\Tests\clinical_trials_gov\Functional;

use Drupal\clinical_trials_gov\Entity\ClinicalTrialsGovTrashNodeAccessControlHandler;
use Drupal\Tests\BrowserTestBase;
use PHPUnit\Framework\Attributes\Group;

/**
 * Functional tests for blocking manual trial creation with Trash enabled.
 *
 * @group clinical_trials_gov
 */
#[Group('clinical_trials_gov')]
class ClinicalTrialsGovTrashCreateAccessTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'clinical_trials_gov',
    'clinical_trials_gov_test',
    'trash',
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

    $this->container->get('router.builder')->rebuild();
    $this->container->get('clinical_trials_gov.entity_manager')->createContentType('trial', 'Trial', 'Clinical trial content type');
    $this->container->get('config.factory')->getEditable('clinical_trials_gov.settings')
      ->set('type', 'trial')
      ->save();
    $this->drupalCreateContentType([
      'type' => 'page',
      'name' => 'Page',
    ], FALSE);
  }

  /**
   * Tests that the Trash-aware handler still blocks manual trial creation.
   */
  public function testTrialCreationBlockedWithTrashEnabled(): void {
    $this->assertInstanceOf(ClinicalTrialsGovTrashNodeAccessControlHandler::class, $this->container->get('entity_type.manager')->getAccessControlHandler('node'));

    $this->drupalLogin($this->drupalCreateUser([
      'access content',
      'create trial content',
      'create page content',
      'bypass node access',
    ]));

    $this->drupalGet('node/add');

    // Check that the remaining creatable bundle still wins the add-page redirect.
    $this->assertSession()->addressEquals('node/add/page');
    $this->assertSession()->pageTextNotContains('Trial');

    // Check that the direct add form is still forbidden with Trash enabled.
    $this->drupalGet('node/add/trial');
    $this->assertSession()->statusCodeEquals(403);
  }

}
