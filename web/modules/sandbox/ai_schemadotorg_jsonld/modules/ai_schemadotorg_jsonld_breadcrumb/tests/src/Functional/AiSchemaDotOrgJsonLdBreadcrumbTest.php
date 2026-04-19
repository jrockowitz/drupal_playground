<?php

declare(strict_types=1);

namespace Drupal\Tests\ai_schemadotorg_jsonld_breadcrumb\Functional;

use Drupal\ai_schemadotorg_jsonld\AiSchemaDotOrgJsonLdBuilderInterface;
use Drupal\node\Entity\Node;
use Drupal\Tests\BrowserTestBase;

/**
 * Tests breadcrumb JSON-LD page attachments.
 *
 * @group ai_schemadotorg_jsonld_breadcrumb
 */
class AiSchemaDotOrgJsonLdBreadcrumbTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'node',
    'field',
    'file',
    'options',
    'field_ui',
    'field_widget_actions',
    'json_field',
    'ai',
    'ai_automators',
    'ai_schemadotorg_jsonld',
    'ai_schemadotorg_jsonld_breadcrumb',
  ];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * Tests the breadcrumb submodule attaches BreadcrumbList JSON-LD.
   */
  public function testBreadcrumbAttachment(): void {
    $this->drupalCreateContentType(['type' => 'page', 'name' => 'Basic page']);

    $admin = $this->drupalCreateUser([
      'access content',
      'create page content',
      'edit any page content',
      'administer site configuration',
    ]);
    $this->drupalLogin($admin);
    $this->drupalGet('/admin/config/ai/schemadotorg-jsonld');
    $this->submitForm([
      'enabled_entity_types[entity_types][node]' => 'node',
      'entity_types[node][bundles][page]' => TRUE,
    ], 'Save configuration');

    $this->container->get(AiSchemaDotOrgJsonLdBuilderInterface::class)
      ->addFieldToEntity('node', 'page');
    $this->container->get('entity_field.manager')->clearCachedFieldDefinitions();

    $node = Node::create([
      'type' => 'page',
      'title' => 'Breadcrumb page',
      'status' => 1,
    ]);
    $node->save();

    $this->drupalGet($node->toUrl());

    // Check that breadcrumb JSON-LD is attached when the submodule is enabled.
    $this->assertSession()->responseContains('"@type":"BreadcrumbList"');
    $this->assertSession()->responseContains('"name":"Breadcrumb page"');
  }

}
