<?php

declare(strict_types=1);

namespace Drupal\Tests\ai_schemadotorg_jsonld_log\Kernel;

use Drupal\ai_schemadotorg_jsonld_log\AiSchemaDotOrgJsonLdLogStorageInterface;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use Drupal\Tests\ai_schemadotorg_jsonld\Kernel\AiSchemaDotOrgJsonLdTestBase;

/**
 * Tests log storage.
 *
 * @group ai_schemadotorg_jsonld
 */
class AiSchemaDotOrgJsonLdLogStorageTest extends AiSchemaDotOrgJsonLdTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'ai_schemadotorg_jsonld_log',
  ];

  /**
   * The log storage.
   */
  protected AiSchemaDotOrgJsonLdLogStorageInterface $logStorage;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    // Install the log table schema used by the storage test.
    $this->installSchema('ai_schemadotorg_jsonld_log', ['ai_schemadotorg_jsonld_log']);

    // Create the page bundle used by the storage test.
    NodeType::create([
      'type' => 'page',
      'name' => 'Basic page',
    ])->save();

    $this->logStorage = $this->container->get(AiSchemaDotOrgJsonLdLogStorageInterface::class);
  }

  /**
   * Tests log rows persist metadata, filtering, deletion, and clearing.
   */
  public function testLogStorageInsertAndLoad(): void {
    // Create a stored node used by the log storage test.
    $node = Node::create([
      'type' => 'page',
      'title' => 'Stored node',
    ]);
    $node->save();

    // Insert log rows for the stored node and an unrelated entity.
    $this->logStorage->insert([
      'entity_type' => 'node',
      'entity_id' => (string) $node->id(),
      'entity_label' => 'Stored node',
      'bundle' => 'page',
      'url' => 'https://example.com/node/' . $node->id(),
      'prompt' => 'Prompt text',
      'response' => '{"@type":"Thing"}',
      'created' => 100,
    ]);
    $this->logStorage->insert([
      'entity_type' => 'node',
      'entity_id' => (string) $node->id(),
      'entity_label' => 'Stored node',
      'bundle' => 'page',
      'url' => 'https://example.com/node/' . $node->id(),
      'prompt' => 'Prompt text 2',
      'response' => '{"@type":"Thing","name":"Second"}',
      'created' => 200,
    ]);
    $this->logStorage->insert([
      'entity_type' => 'node',
      'entity_id' => '9999',
      'entity_label' => 'Other node',
      'bundle' => 'page',
      'url' => 'https://example.com/node/9999',
      'prompt' => 'Other prompt',
      'response' => '{"@type":"Thing","name":"Other"}',
      'created' => 300,
    ]);

    // Check that all rows are persisted with the expected metadata.
    $rows = $this->logStorage->loadAll();
    $this->assertCount(3, $rows);
    $this->assertSame('node', $rows[1]['entity_type']);
    $this->assertSame((string) $node->id(), $rows[1]['entity_id']);
    $this->assertSame('Stored node', $rows[1]['entity_label']);
    $this->assertSame('page', $rows[1]['bundle']);
    $this->assertSame('https://example.com/node/' . $node->id(), $rows[1]['url']);
    $this->assertSame('Prompt text 2', $rows[1]['prompt']);
    $this->assertSame('{"@type":"Thing","name":"Second"}', $rows[1]['response']);

    // Check that filtered loads return the stored node rows in descending order.
    $paged_rows = $this->logStorage->loadMultiple('node', (string) $node->id());
    $this->assertCount(2, $paged_rows);
    $this->assertSame('Prompt text 2', $paged_rows[0]['prompt']);
    $this->assertSame('Prompt text', $paged_rows[1]['prompt']);

    // Check that filtering by another entity returns only its rows.
    $paged_rows = $this->logStorage->loadMultiple('node', '9999');
    $this->assertCount(1, $paged_rows);
    $this->assertSame('Other prompt', $paged_rows[0]['prompt']);

    // Check that filtering by a missing entity returns no rows.
    $paged_rows = $this->logStorage->loadMultiple('node', 'not-found');
    $this->assertSame([], $paged_rows);

    // Check that deleting by entity removes only that entity's rows.
    $this->logStorage->deleteByEntity($node);
    $rows = $this->logStorage->loadAll();
    $this->assertCount(1, $rows);
    $this->assertSame('Other prompt', $rows[0]['prompt']);

    // Check that truncating the log removes all remaining rows.
    $this->logStorage->truncate();
    $this->assertSame([], $this->logStorage->loadAll());
  }

}
