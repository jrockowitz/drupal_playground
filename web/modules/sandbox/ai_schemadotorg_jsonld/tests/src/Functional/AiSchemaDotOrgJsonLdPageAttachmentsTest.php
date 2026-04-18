<?php

declare(strict_types=1);

namespace Drupal\Tests\ai_schemadotorg_jsonld\Functional;

use Drupal\ai_schemadotorg_jsonld\AiSchemaDotOrgJsonLdBuilderInterface;
use Drupal\node\Entity\Node;
use Drupal\taxonomy\Entity\Term;
use Drupal\taxonomy\Entity\Vocabulary;
use Drupal\Tests\BrowserTestBase;

/**
 * Tests that JSON-LD is attached to the page header.
 *
 * @group ai_schemadotorg_jsonld
 */
class AiSchemaDotOrgJsonLdPageAttachmentsTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'node',
    'field',
    'file',
    'options',
    'taxonomy',
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
   * The taxonomy term JSON-LD value used across the test.
   */
  protected string $taxonomyDefaultJsonld = '{"@context":"https://schema.org","@type":"DefinedTermSet","name":"Taxonomy terms"}';

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->drupalCreateContentType(['type' => 'page', 'name' => 'Basic page']);
    Vocabulary::create([
      'vid' => 'tags',
      'name' => 'Tags',
    ])->save();

    // Create the field via the settings form (HTTP) to avoid DDL in this
    // process, which would conflict with BrowserTestBase transaction management.
    $admin = $this->drupalCreateUser(['administer site configuration']);
    $this->drupalLogin($admin);
    $this->drupalGet('/admin/config/ai/schemadotorg-jsonld');
    $this->submitForm([
      'enabled_entity_types[entity_types][taxonomy_term]' => TRUE,
    ], 'Save configuration');
    $this->drupalGet('/admin/config/ai/schemadotorg-jsonld');
    $this->submitForm([
      'entity_types[node][bundles][page]' => TRUE,
      'entity_types[node][default_jsonld]' => $this->defaultJsonld,
      'entity_types[taxonomy_term][bundles][tags]' => TRUE,
      'entity_types[taxonomy_term][default_jsonld]' => $this->taxonomyDefaultJsonld,
      'breadcrumb_jsonld' => TRUE,
    ], 'Save configuration');
    $this->drupalLogout();

    // Refresh entity field definitions after the HTTP request that created
    // the field and automator config so direct entity saves in this process
    // see the new field definition.
    $this->container->get('entity_field.manager')->clearCachedFieldDefinitions();
  }

  /**
   * Tests JSON-LD tags in the page head.
   */
  public function testPageAttachments(): void {
    $default_jsonld = $this->defaultJsonld;

    // Create a node with a field_schemadotorg_jsonld value.
    $node_jsonld = '{"@context":"https://schema.org","@type":"WebPage","name":"Test page"}';
    $node = Node::create([
      'type' => 'page',
      'title' => 'Test page',
      'status' => 1,
      AiSchemaDotOrgJsonLdBuilderInterface::FIELD_NAME => $node_jsonld,
    ]);
    $node->save();

    // Visit the node canonical URL.
    $this->drupalGet('/node/' . $node->id());
    $this->assertSession()->statusCodeEquals(200);

    // Check that the default JSON-LD tag is in the page head.
    $this->assertSession()->responseContains(
      '<script type="application/ld+json">' . $default_jsonld . '</script>'
    );

    // Check that the node JSON-LD tag is in the page head.
    $this->assertSession()->responseContains(
      '<script type="application/ld+json">' . $node_jsonld . '</script>'
    );

    // Visit the front page and check that entity-specific tags are absent.
    $this->drupalGet('/');

    // Check that the node entity JSON-LD key is not present on non-canonical pages.
    $this->assertSession()->responseNotContains(
      'ai_schemadotorg_jsonld_node_' . $node->id()
    );

    // Check that node-specific default JSON-LD does not appear off the node
    // canonical route.
    $this->assertSession()->responseNotContains(
      '<script type="application/ld+json">' . $default_jsonld . '</script>'
    );

    $term_jsonld = '{"@context":"https://schema.org","@type":"DefinedTerm","name":"Test term"}';
    $term = Term::create([
      'vid' => 'tags',
      'name' => 'Test term',
      'status' => 1,
      AiSchemaDotOrgJsonLdBuilderInterface::FIELD_NAME => $term_jsonld,
    ]);
    $term->save();

    // Visit the taxonomy term canonical URL.
    $this->drupalGet('/taxonomy/term/' . $term->id());
    $this->assertSession()->statusCodeEquals(200);

    // Check that the taxonomy default JSON-LD tag is in the page head.
    $this->assertSession()->responseContains(
      '<script type="application/ld+json">' . $this->taxonomyDefaultJsonld . '</script>'
    );

    // Check that the taxonomy term JSON-LD tag is in the page head.
    $this->assertSession()->responseContains(
      '<script type="application/ld+json">' . $term_jsonld . '</script>'
    );
  }

}
