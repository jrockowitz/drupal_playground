<?php

declare(strict_types=1);

namespace Drupal\Tests\ai_schemadotorg_jsonld_log\Functional;

use Drupal\ai_schemadotorg_jsonld\AiSchemaDotOrgJsonLdBuilderInterface;
use Drupal\node\Entity\Node;
use Drupal\Tests\ai_schemadotorg_jsonld\Functional\AiSchemaDotOrgJsonLdTestBase;

/**
 * Tests the log admin UI.
 *
 * @group ai_schemadotorg_jsonld
 */
class AiSchemaDotOrgJsonLdLogTest extends AiSchemaDotOrgJsonLdTestBase {

  /**
   * The logged node ID.
   */
  protected int $nodeId;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'json_field_widget',
    'token',
    'ai_schemadotorg_jsonld_log',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    // Add the node page AI schema.org JSON-LD automator field.
    $this->container->get(AiSchemaDotOrgJsonLdBuilderInterface::class)
      ->addFieldToBundle('node', 'page');

    // Clear cached field definitions to ensure that the new schema.org JSON-LD
    // automator is used.
    $this->container->get('entity_field.manager')->clearCachedFieldDefinitions();

    // Create a saved page used for filtered log assertions.
    $node = Node::create([
      'type' => 'page',
      'title' => 'Log node',
      'status' => 1,
      AiSchemaDotOrgJsonLdBuilderInterface::FIELD_NAME => '{"@type":"WebPage"}',
    ]);
    $node->save();
    $this->nodeId = (int) $node->id();

    for ($index = 1; $index <= 25; $index++) {
      $label = sprintf('%02d', $index);
      $this->container->get('database')
        ->insert('ai_schemadotorg_jsonld_log')
        ->fields([
          'entity_type' => 'node',
          'entity_id' => (string) $this->nodeId,
          'entity_label' => 'Log node',
          'bundle' => 'page',
          'url' => 'https://example.com/node/' . $this->nodeId,
          'prompt' => 'Valid prompt ' . $label,
          'response' => '{"@type":"Thing","name":"Valid ' . $label . '"}',
          'valid' => 1,
          'created' => 1700000000 + $index,
        ])
        ->execute();
    }

    $this->container->get('database')
      ->insert('ai_schemadotorg_jsonld_log')
      ->fields([
        'entity_type' => 'node',
        'entity_id' => '5555',
        'entity_label' => 'Stored orphan node',
        'bundle' => 'page',
        'url' => 'https://example.com/node/5555',
        'prompt' => 'Stored orphan prompt',
        'response' => '{"@type":"Thing","name":"Stored orphan"}',
        'valid' => 1,
        'created' => 1790000000,
      ])
      ->execute();

    $this->container->get('database')
      ->insert('ai_schemadotorg_jsonld_log')
      ->fields([
        'entity_type' => 'node',
        'entity_id' => '9999',
        'entity_label' => '',
        'bundle' => '',
        'url' => '',
        'prompt' => 'Orphan prompt',
        'response' => '{not valid json}',
        'valid' => 0,
        'created' => 1800000000,
      ])
      ->execute();
  }

  /**
   * Tests the log page, CSV download, and clear flow.
   */
  public function testLogPageShowsRowsAndOperations(): void {
    $this->drupalLogin($this->adminUser);

    // Check that the View log link appears on the node edit form.
    $this->drupalGet('/node/' . $this->nodeId . '/edit');
    $this->assertSession()->linkExists('View log');

    // Check that administrators can access the global log and see recent data.
    $this->drupalGet('/admin/config/ai/schemadotorg-jsonld/log');
    $this->assertSession()->statusCodeEquals(200);

    // Verify that global log operations are present.
    $this->assertSession()->linkExists('Download CSV');
    $this->assertSession()->linkExists('Clear log');

    // Verify that the table contains expected data from the test setup.
    $this->assertSession()->pageTextContains('Valid prompt 25');
    $this->assertSession()->pageTextContains('"name": "Valid 25"');
    $this->assertSession()->pageTextContains('Stored orphan prompt');
    $this->assertSession()->pageTextContains('Orphan prompt');

    // Verify that valid and invalid entries are correctly labeled in the UI.
    $this->assertSession()->pageTextContains('Yes');
    $this->assertSession()->pageTextContains('No');

    // Verify that the pager is functioning by checking for older records.
    $this->assertSession()->pageTextNotContains('Valid prompt 05');
    $this->assertSession()->linkExists('Next ›');

    // Check that the global CSV download includes the logged rows.
    $this->drupalGet('/admin/config/ai/schemadotorg-jsonld/log/download');
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->responseContains('Valid prompt 25');
    $this->assertSession()->responseContains('Stored orphan prompt');
    $this->assertSession()->responseContains('Orphan prompt');
    $this->assertSession()->responseContains(',No,');
    $this->assertSession()->responseHeaderEquals('Content-Disposition', 'attachment; filename="ai-schemadotorg-jsonld-log.csv"');

    $this->config('ai_schemadotorg_jsonld_log.settings')
      ->set('enable', FALSE)
      ->save();

    // Check that disabling logging hides the View log link and the log actions.
    $this->drupalGet('/node/' . $this->nodeId . '/edit');
    $this->assertSession()->linkNotExists('View log');

    $this->drupalGet('/admin/config/ai/schemadotorg-jsonld/log');
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->pageTextContains('Prompt and response logging is disabled.');
    $this->assertSession()->linkNotExists('Download CSV');
    $this->assertSession()->linkNotExists('Clear log');

    $this->config('ai_schemadotorg_jsonld_log.settings')
      ->set('enable', TRUE)
      ->save();

    $editor = $this->drupalCreateUser([
      'access content',
      'edit any page content',
    ]);
    $this->drupalLogin($editor);

    // Check that entity editors can open the filtered log and see the edit-form
    // View log link without site configuration access.
    $this->drupalGet('/node/' . $this->nodeId . '/edit');
    $this->assertSession()->linkExists('View log');

    $this->drupalGet('/admin/config/ai/schemadotorg-jsonld/log');
    $this->assertSession()->statusCodeEquals(403);

    $this->drupalGet('/admin/config/ai/schemadotorg-jsonld/log/download');
    $this->assertSession()->statusCodeEquals(403);

    // Check that entity editors can access the filtered log and only see rows
    // for the current entity.
    $this->drupalGet('/admin/config/ai/schemadotorg-jsonld/log', [
      'query' => [
        'entity_type' => 'node',
        'entity_id' => $this->nodeId,
      ],
    ]);
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->pageTextContains('Log node');
    $this->assertSession()->pageTextContains('Valid prompt 25');
    $this->assertSession()->pageTextNotContains('Stored orphan prompt');
    $this->assertSession()->pageTextNotContains('Orphan prompt');
    $this->assertSession()->pageTextContains('Yes');
    $this->assertSession()->linkExists('Download CSV');
    $this->assertSession()->linkNotExists('Clear log');

    // Check that the filtered CSV download only includes the current entity.
    $this->drupalGet('/admin/config/ai/schemadotorg-jsonld/log/download', [
      'query' => [
        'entity_type' => 'node',
        'entity_id' => $this->nodeId,
      ],
    ]);
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->responseContains('Valid prompt 25');
    $this->assertSession()->responseNotContains('Stored orphan prompt');
    $this->assertSession()->responseNotContains('Orphan prompt');
    $this->assertSession()->responseContains(',Yes,');
    $this->assertSession()->responseNotContains(',No,');
    $this->assertSession()->responseHeaderEquals('Content-Disposition', 'attachment; filename="ai-schemadotorg-jsonld-node-' . $this->nodeId . '-log.csv"');

    $viewer = $this->drupalCreateUser([
      'access content',
    ]);
    $this->drupalLogin($viewer);

    // Check that users without update access cannot access the filtered log.
    $this->drupalGet('/node/' . $this->nodeId . '/edit');
    $this->assertSession()->statusCodeEquals(403);

    $this->drupalGet('/admin/config/ai/schemadotorg-jsonld/log', [
      'query' => [
        'entity_type' => 'node',
        'entity_id' => $this->nodeId,
      ],
    ]);
    $this->assertSession()->statusCodeEquals(403);

    $this->drupalGet('/admin/config/ai/schemadotorg-jsonld/log/download', [
      'query' => [
        'entity_type' => 'node',
        'entity_id' => $this->nodeId,
      ],
    ]);
    $this->assertSession()->statusCodeEquals(403);

    $this->drupalLogin($editor);
    $this->drupalGet('/admin/config/ai/schemadotorg-jsonld/log', [
      'query' => [
        'entity_type' => 'node',
        'entity_id' => '999999',
      ],
    ]);
    $this->assertSession()->statusCodeEquals(403);

    $this->drupalGet('/admin/config/ai/schemadotorg-jsonld/log/download', [
      'query' => [
        'entity_type' => 'node',
        'entity_id' => '999999',
      ],
    ]);
    $this->assertSession()->statusCodeEquals(403);

    $this->drupalLogin($this->adminUser);

    $node = Node::load($this->nodeId);
    $node->delete();
    $this->drupalGet('/admin/config/ai/schemadotorg-jsonld/log');
    $this->assertSession()->pageTextContains('Orphan prompt');
    $this->assertSession()->pageTextContains('Stored orphan prompt');
    $this->assertSession()->pageTextContains('node:9999 (node)');
    $this->assertSession()->pageTextNotContains('Log node');
    $this->assertSession()->pageTextContains('Stored orphan node (node:page)');
    $this->assertSession()->linkExists('Stored orphan node');
    $this->assertSession()->linkByHrefExists('https://example.com/node/5555');

    $this->drupalGet('/admin/config/ai/schemadotorg-jsonld/log/clear');
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->pageTextContains('Are you sure you want to clear the AI Schema.org JSON-LD log?');

    $this->submitForm([], 'Clear log');
    $this->drupalGet('/admin/config/ai/schemadotorg-jsonld/log');
    $this->assertSession()->pageTextContains('No log entries available.');
  }

}
