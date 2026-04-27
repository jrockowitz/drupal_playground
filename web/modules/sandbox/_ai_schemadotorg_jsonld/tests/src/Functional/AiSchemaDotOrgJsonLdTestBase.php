<?php

declare(strict_types=1);

namespace Drupal\Tests\ai_schemadotorg_jsonld\Functional;

use Drupal\Tests\BrowserTestBase;
use Drupal\user\UserInterface;

/**
 * Provides shared setup for AI Schema.org JSON-LD functional tests.
 */
abstract class AiSchemaDotOrgJsonLdTestBase extends BrowserTestBase {

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
    'ai',
    'ai_automators',
    'ai_schemadotorg_jsonld',
  ];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * The administrator user used across the test.
   */
  protected UserInterface $adminUser;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    // Create the page content type used across the functional tests.
    $this->drupalCreateContentType([
      'type' => 'page',
      'name' => 'Basic page',
    ]);

    // Create an administrator user that tests can log in when needed.
    $this->adminUser = $this->drupalCreateUser([
      'access content',
      'create page content',
      'edit any page content',
      'administer site configuration',
    ]);
  }

}
