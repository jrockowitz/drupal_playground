<?php

declare(strict_types=1);

namespace Drupal\Tests\ai_schemadotorg_jsonld\Functional;

use Drupal\ai_schemadotorg_jsonld\AiSchemaDotOrgJsonLdBuilderInterface;
use Drupal\node\Entity\Node;

/**
 * Tests field access and page attachments.
 *
 * @group ai_schemadotorg_jsonld
 */
class AiSchemaDotOrgJsonLdTest extends AiSchemaDotOrgJsonLdTestBase {

  /**
   * The default JSON-LD value used across the test.
   */
  protected string $defaultJsonld = '{"@context":"https://schema.org","@type":"Organization","name":"Test Site"}';

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    // Log in the administrator user to configure the node page automator.
    $this->drupalLogin($this->adminUser);

    // Enable node page AI schema.org JSON-LD automator.
    $this->drupalGet('/admin/config/ai/schemadotorg-jsonld');
    $this->submitForm([
      'enabled_entity_types[entity_types][node]' => 'node',
      'entity_types[node][bundles][page]' => TRUE,
      'entity_types[node][default_jsonld]' => $this->defaultJsonld,
    ], 'Save configuration');

    // Clear cached field definitions to ensure that the new schema.org JSON-LD
    // automator is used.
    $this->container->get('entity_field.manager')->clearCachedFieldDefinitions();
  }

  /**
   * Tests node field access and page attachments.
   */
  public function testNodeFlow(): void {
    $field_name = AiSchemaDotOrgJsonLdBuilderInterface::FIELD_NAME;
    $node_jsonld = '{"@context":"https://schema.org","@type":"WebPage","name":"Test page"}';

    $this->drupalGet('/node/add/page');

    // Check that the JSON-LD field is replaced with a status message on unsaved entities.
    $this->assertSession()->fieldNotExists($field_name . '[0][value]');
    $this->assertSession()->responseNotContains('Generate Schema.org JSON-LD');

    // Create a saved page with JSON-LD so the edit form can expose the widget.
    $node = Node::create([
      'type' => 'page',
      'title' => 'Test page',
      'status' => 1,
      $field_name => $node_jsonld,
    ]);
    $node->save();

    $this->drupalGet($node->toUrl('edit-form'));

    // Check that the JSON-LD field is editable on saved entities.
    $this->assertSession()->fieldExists($field_name . '[0][value]');
    $this->assertSession()->responseContains('Generate Schema.org JSON-LD');
    $this->assertSession()->fieldValueEquals($field_name . '[0][value]', $node_jsonld);
    $this->assertSession()->buttonExists('Copy JSON-LD');
    $this->assertSession()->linkExists('Edit prompt');

    // Check that the description appears before the buttons.
    $page_content = $this->getSession()->getPage()->getContent();
    $this->assertLessThan(
      strpos($page_content, 'Copy JSON-LD'),
      strpos($page_content, 'Please copy and paste the above Schema.org JSON-LD')
    );
    $this->assertLessThan(
      strpos($page_content, 'Edit prompt'),
      strpos($page_content, 'Copy JSON-LD')
    );

    // Check that disabling the development setting hides the edit prompt link.
    $this->config('ai_schemadotorg_jsonld.settings')
      ->set('development.edit_prompt', FALSE)
      ->save();
    $this->drupalGet($node->toUrl('edit-form'));
    $this->assertSession()->linkNotExists('Edit prompt');

    // Check that users without site configuration access do not see the link.
    $this->config('ai_schemadotorg_jsonld.settings')
      ->set('development.edit_prompt', TRUE)
      ->save();
    $editor = $this->drupalCreateUser([
      'access content',
      'edit any page content',
    ]);
    $this->drupalLogin($editor);
    $this->drupalGet($node->toUrl('edit-form'));
    $this->assertSession()->linkNotExists('Edit prompt');

    $viewer = $this->drupalCreateUser([
      'access content',
    ]);
    $this->drupalLogin($viewer);
    $this->drupalGet($node->toUrl('edit-form'));
    $this->assertSession()->linkNotExists('Edit prompt');

    $this->drupalLogin($this->adminUser);

    // Check that entity JSON-LD is attached to the canonical page.
    $this->drupalGet($node->toUrl());
    $this->assertSession()->responseContains(
      '<script type="application/ld+json">' . $node_jsonld . '</script>'
    );

    // Check that canonical-route JSON-LD does not appear on other pages.
    $this->drupalGet('/');
    $this->assertSession()->responseNotContains(
      '<script type="application/ld+json">' . $node_jsonld . '</script>'
    );
  }

}
