<?php

declare(strict_types=1);

namespace Drupal\Tests\ai_schemadotorg_jsonld\Functional;

use Drupal\ai_schemadotorg_jsonld\AiSchemaDotOrgJsonLdBuilderInterface;
use Drupal\node\Entity\Node;
use Drupal\Tests\BrowserTestBase;
use Drupal\user\UserInterface;

/**
 * Tests field access and page attachments.
 *
 * @group ai_schemadotorg_jsonld
 */
class AiSchemaDotOrgJsonLdTest extends BrowserTestBase {

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
  ];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * The default JSON-LD value used across the test.
   */
  protected string $defaultJsonld = '{"@context":"https://schema.org","@type":"Organization","name":"Test Site"}';

  /**
   * The administrator user used across the test.
   */
  protected UserInterface $adminUser;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->drupalCreateContentType(['type' => 'page', 'name' => 'Basic page']);

    $this->adminUser = $this->drupalCreateUser([
      'access content',
      'create page content',
      'edit any page content',
      'administer site configuration',
    ]);
    $this->drupalLogin($this->adminUser);
    $this->drupalGet('/admin/config/ai/schemadotorg-jsonld');
    $this->submitForm([
      'enabled_entity_types[entity_types][node]' => 'node',
      'entity_types[node][bundles][page]' => TRUE,
      'entity_types[node][default_jsonld]' => $this->defaultJsonld,
    ], 'Save configuration');

    $this->container->get(AiSchemaDotOrgJsonLdBuilderInterface::class)
      ->addFieldToEntity('node', 'page');
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
    $this->assertSession()->pageTextContains('Schema.org JSON-LD can be generated after the content item is saved.');
    $this->assertSession()->pageTextNotContains('Generate Schema.org JSON-LD');

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
    $this->assertSession()->fieldValueEquals($field_name . '[0][value]', $node_jsonld);
    $this->assertSession()->linkExists('Edit Schema.org JSON-LD prompt');

    // Check that disabling the development setting hides the edit prompt link.
    $this->config('ai_schemadotorg_jsonld.settings')
      ->set('development.edit_prompt', FALSE)
      ->save();
    $this->drupalGet($node->toUrl('edit-form'));
    $this->assertSession()->linkNotExists('Edit Schema.org JSON-LD prompt');

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
    $this->assertSession()->linkNotExists('Edit Schema.org JSON-LD prompt');

    $this->drupalLogin($this->adminUser);

    $this->drupalGet($node->toUrl());
    $this->assertSession()->statusCodeEquals(200);

    // Check that entity JSON-LD is attached to the canonical page.
    $this->assertSession()->responseContains(
      '<script type="application/ld+json">' . $node_jsonld . '</script>'
    );

    $this->drupalGet('/');

    // Check that canonical-route JSON-LD does not appear on other pages.
    $this->assertSession()->responseNotContains(
      '<script type="application/ld+json">' . $node_jsonld . '</script>'
    );
  }

}
