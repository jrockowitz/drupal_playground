<?php

declare(strict_types=1);

namespace Drupal\Tests\clinical_trials_gov\Functional;

use Drupal\node\Entity\Node;
use Drupal\Tests\BrowserTestBase;
use PHPUnit\Framework\Attributes\Group;

/**
 * Functional tests for blocking manual trial creation.
 *
 * @group clinical_trials_gov
 */
#[Group('clinical_trials_gov')]
class ClinicalTrialsGovCreateAccessTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'clinical_trials_gov',
    'clinical_trials_gov_test',
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
    $this->container->get('config.factory')->getEditable('clinical_trials_gov.settings')
      ->set('type', 'trial')
      ->save();
    $this->drupalCreateContentType([
      'type' => 'page',
      'name' => 'Page',
    ], FALSE);
  }

  /**
   * Tests that regular users cannot manually create trials.
   */
  public function testTrialCreationBlockedForRegularUsers(): void {
    $this->drupalLogin($this->drupalCreateUser([
      'access content',
      'create trial content',
      'create page content',
    ]));

    $this->drupalGet('node/add');

    // Check that the add page skips trial and redirects to the remaining bundle.
    $this->assertSession()->addressEquals('node/add/page');
    $this->assertSession()->pageTextNotContains('Trial');

    // Check that the trial add form is forbidden directly.
    $this->drupalGet('node/add/trial');
    $this->assertSession()->statusCodeEquals(403);

    // Check that unrelated bundles remain creatable.
    $this->drupalGet('node/add/page');
    $this->assertSession()->statusCodeEquals(200);
  }

  /**
   * Tests that privileged users also cannot manually create trials.
   */
  public function testTrialCreationBlockedForBypassUsers(): void {
    $this->drupalLogin($this->drupalCreateUser([
      'access content',
      'create trial content',
      'create page content',
      'bypass node access',
    ]));

    $this->drupalGet('node/add');

    // Check that bypass users still do not see the trial bundle.
    $this->assertSession()->addressEquals('node/add/page');
    $this->assertSession()->pageTextNotContains('Trial');

    // Check that bypass users are also forbidden from the trial add form.
    $this->drupalGet('node/add/trial');
    $this->assertSession()->statusCodeEquals(403);

    // Check that programmatic creation still works for the import workflow.
    $node = Node::create([
      'type' => 'trial',
      'title' => 'Imported trial',
    ]);
    $node->save();
    $this->assertNotNull($node->id());
  }

}
