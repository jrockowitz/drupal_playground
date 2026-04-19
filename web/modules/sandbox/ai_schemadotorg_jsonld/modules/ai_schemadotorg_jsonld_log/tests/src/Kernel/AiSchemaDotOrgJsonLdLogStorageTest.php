<?php

declare(strict_types=1);

namespace Drupal\Tests\ai_schemadotorg_jsonld_log\Kernel;

use Drupal\ai_schemadotorg_jsonld_log\AiSchemaDotOrgJsonLdLogStorageInterface;
use Drupal\KernelTests\KernelTestBase;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;

/**
 * Tests log storage.
 *
 * @group ai_schemadotorg_jsonld_log
 */
class AiSchemaDotOrgJsonLdLogStorageTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'node',
    'field',
    'file',
    'options',
    'field_widget_actions',
    'json_field',
    'token',
    'ai',
    'ai_automators',
    'ai_schemadotorg_jsonld',
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
    $this->installSchema('ai_schemadotorg_jsonld_log', ['ai_schemadotorg_jsonld_log']);
    $this->installEntitySchema('user');
    $this->installEntitySchema('node');
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
    $node = Node::create([
      'type' => 'page',
      'title' => 'Stored node',
    ]);
    $node->save();

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

    $rows = $this->logStorage->loadAll();
    $this->assertCount(3, $rows);
    $this->assertSame('node', $rows[1]['entity_type']);
    $this->assertSame((string) $node->id(), $rows[1]['entity_id']);
    $this->assertSame('Stored node', $rows[1]['entity_label']);
    $this->assertSame('page', $rows[1]['bundle']);
    $this->assertSame('https://example.com/node/' . $node->id(), $rows[1]['url']);
    $this->assertSame('Prompt text 2', $rows[1]['prompt']);
    $this->assertSame('{"@type":"Thing","name":"Second"}', $rows[1]['response']);

    $paged_rows = $this->logStorage->loadMultiple('node', (string) $node->id());
    $this->assertCount(2, $paged_rows);
    $this->assertSame('Prompt text 2', $paged_rows[0]['prompt']);
    $this->assertSame('Prompt text', $paged_rows[1]['prompt']);

    $paged_rows = $this->logStorage->loadMultiple('node', '9999');
    $this->assertCount(1, $paged_rows);
    $this->assertSame('Other prompt', $paged_rows[0]['prompt']);

    $paged_rows = $this->logStorage->loadMultiple('node', 'not-found');
    $this->assertSame([], $paged_rows);

    $this->logStorage->deleteByEntity($node);
    $rows = $this->logStorage->loadAll();
    $this->assertCount(1, $rows);
    $this->assertSame('Other prompt', $rows[0]['prompt']);

    $this->logStorage->truncate();
    $this->assertSame([], $this->logStorage->loadAll());
  }

}
