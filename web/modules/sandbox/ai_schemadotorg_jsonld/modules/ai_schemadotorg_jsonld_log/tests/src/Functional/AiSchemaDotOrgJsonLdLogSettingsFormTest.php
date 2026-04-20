<?php

declare(strict_types=1);

namespace Drupal\Tests\ai_schemadotorg_jsonld_log\Functional;

use Drupal\Tests\ai_schemadotorg_jsonld\Functional\AiSchemaDotOrgJsonLdTestBase;

/**
 * Tests the log settings added to the core settings form.
 *
 * @group ai_schemadotorg_jsonld
 */
class AiSchemaDotOrgJsonLdLogSettingsFormTest extends AiSchemaDotOrgJsonLdTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'ai_schemadotorg_jsonld_log',
  ];

  /**
   * Tests the log module alters the main settings form.
   */
  public function testDevelopmentSettingsAreAddedToCoreForm(): void {
    $this->drupalLogin($this->adminUser);

    $this->drupalGet('/admin/config/ai/schemadotorg-jsonld');
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->pageTextContains('Development settings');
    $this->assertSession()->fieldExists('log');
  }

}
