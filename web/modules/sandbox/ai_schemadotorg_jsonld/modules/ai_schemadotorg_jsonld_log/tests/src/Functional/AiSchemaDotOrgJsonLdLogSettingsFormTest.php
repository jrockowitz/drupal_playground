<?php

declare(strict_types=1);

namespace Drupal\Tests\ai_schemadotorg_jsonld_log\Functional;

use Drupal\Tests\BrowserTestBase;

/**
 * Tests the log settings added to the core settings form.
 *
 * @group ai_schemadotorg_jsonld_log
 */
class AiSchemaDotOrgJsonLdLogSettingsFormTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'node',
    'field',
    'field_ui',
    'file',
    'options',
    'field_widget_actions',
    'json_field',
    'json_field_widget',
    'ai',
    'ai_automators',
    'ai_schemadotorg_jsonld',
    'ai_schemadotorg_jsonld_log',
  ];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * Tests the log module alters the main settings form.
   */
  public function testDevelopmentSettingsAreAddedToCoreForm(): void {
    $admin = $this->drupalCreateUser(['administer site configuration']);
    $this->drupalLogin($admin);

    $this->drupalGet('/admin/config/ai/schemadotorg-jsonld');
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->pageTextContains('Development settings');
    $this->assertSession()->fieldExists('edit_prompt');
    $this->assertSession()->fieldExists('log');

    $this->assertSession()->fieldExists('log');
  }

}
