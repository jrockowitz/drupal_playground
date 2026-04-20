<?php

declare(strict_types=1);

namespace Drupal\Tests\ai_schemadotorg_jsonld_breadcrumb\Functional;

use Drupal\node\Entity\Node;
use Drupal\Tests\ai_schemadotorg_jsonld\Functional\AiSchemaDotOrgJsonLdTestBase;

/**
 * Tests breadcrumb JSON-LD page attachments.
 *
 * @group ai_schemadotorg_jsonld
 */
class AiSchemaDotOrgJsonLdBreadcrumbTest extends AiSchemaDotOrgJsonLdTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'ai_schemadotorg_jsonld_breadcrumb',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    // Log in the administrator user to configure the node page automator.
    $this->drupalLogin($this->adminUser);

    // Enable node support.
    $this->drupalGet('/admin/config/ai/schemadotorg-jsonld');
    $this->submitForm([
      'entity_types[node][bundles][page]' => TRUE,
    ], 'Save configuration');

    // Enable node:page AI schema.org JSON-LD automator.
    $this->submitForm([
      'enabled_entity_types[entity_types][node]' => 'node',
    ], 'Save configuration');

    // Clear cached field definitions to ensure that the new schema.org JSON-LD
    // automator is used.
    $this->container->get('entity_field.manager')->clearCachedFieldDefinitions();
  }

  /**
   * Tests the breadcrumb submodule attaches BreadcrumbList JSON-LD.
   */
  public function testBreadcrumbAttachment(): void {
    // Create a saved page so breadcrumb JSON-LD can be attached to its output.
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
