<?php

declare(strict_types=1);

namespace Drupal\Tests\drupal_playground_ai_mcp_tool_bridge\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\node\Entity\NodeType;
use Drupal\Tests\user\Traits\UserCreationTrait;
use Drupal\user\Entity\Role;
use Mcp\Schema\Result\CallToolResult;
use Mcp\Server\ClientGateway;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests the Tool Belt content MCP bridge.
 */
#[Group('drupal_playground_ai_mcp_tool_bridge')]
class ToolBeltContentBridgeToolTest extends KernelTestBase {

  use UserCreationTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'field',
    'filter',
    'mcp_server',
    'node',
    'serialization',
    'system',
    'text',
    'tool',
    'tool_belt',
    'tool_belt_content',
    'tool_belt_entity',
    'tool_belt_system',
    'tool_belt_user',
    'user',
    'drupal_playground_ai_mcp_tool_bridge',
  ];

  /**
   * Tests that Tool Belt content tools are exposed through MCP.
   */
  public function testToolBeltContentBridge(): void {
    $this->installEntitySchema('node');
    $this->installEntitySchema('user');
    $this->installSchema('node', ['node_access']);
    $this->installConfig(['field', 'filter', 'node', 'system']);

    NodeType::create([
      'type' => 'article',
      'name' => 'Article',
    ])->save();

    $administrator = Role::create([
      'id' => 'administrator',
      'label' => 'Administrator',
    ]);
    $administrator->grantPermission('access mcp server');
    $administrator->grantPermission('create article content');
    $administrator->grantPermission('edit any article content');
    $administrator->grantPermission('administer nodes');
    $administrator->save();

    $this->setUpCurrentUser([], [
      'access mcp server',
      'create article content',
      'edit any article content',
      'administer nodes',
    ]);

    // Check that the MCP plugin manager discovers the bridge tools.
    $mcp_tool_manager = $this->container->get('plugin.manager.mcp_server.tool');
    $definitions = $mcp_tool_manager->getDefinitions();
    $this->assertArrayHasKey('tool_belt_content_field_definitions', $definitions);
    $this->assertArrayHasKey('tool_belt_content_create_entity', $definitions);
    $this->assertArrayHasKey('tool_belt_dynamic.tool_belt__entity_list', $definitions);
    $this->assertArrayHasKey('tool_belt_dynamic.tool_belt__entity_load_by_id', $definitions);
    $this->assertArrayHasKey('tool_belt_dynamic.tool_belt__entity_delete', $definitions);
    $this->assertArrayHasKey('tool_belt_dynamic.tool_belt__system_status', $definitions);
    $this->assertSame([
      'entity_type_id',
      'bundle',
      'base_fields',
    ], $definitions['tool_belt_content_create_entity']->inputSchema['required']);

    // Check that field definitions are bridged through Tool Belt.
    $field_tool = $mcp_tool_manager->createInstance('tool_belt_content_field_definitions');
    $this->assertTrue($field_tool->isEnabled());
    $field_result = $field_tool->execute([
      'entity_type_id' => 'node',
      'bundle' => 'article',
    ], $this->createMock(ClientGateway::class));
    $this->assertTrue($field_result['success']);
    $this->assertArrayHasKey('base_field_definitions', $field_result['outputs']);
    $this->assertArrayHasKey('field_definitions', $field_result['outputs']);

    // Check that Codex can create a content entity through an MCP JSON call.
    $create_tool = $mcp_tool_manager->createInstance('tool_belt_content_create_entity');
    $this->assertTrue($create_tool->isEnabled());
    $create_result = $create_tool->execute([
      'entity_type_id' => 'node',
      'bundle' => 'article',
      'base_fields' => [
        'title' => [
          'value' => 'Tool Belt Bridge Test Article',
        ],
        'status' => [
          'value' => TRUE,
        ],
      ],
      'field_values' => [],
    ], $this->createMock(ClientGateway::class));

    $this->assertTrue($create_result['success'], $create_result['message']);
    $this->assertSame('node', $create_result['entity_type_id']);
    $this->assertSame('article', $create_result['bundle']);
    $this->assertSame('Tool Belt Bridge Test Article', $create_result['label']);
    $this->assertSame(1, $create_result['status']);

    /** @var \Drupal\node\NodeInterface $node */
    $node = $this->container->get('entity_type.manager')
      ->getStorage('node')
      ->load($create_result['entity_id']);
    $this->assertSame('Tool Belt Bridge Test Article', $node->label());
    $this->assertTrue($node->isPublished());

    // Check that a dynamically exposed read tool can run through MCP.
    $list_tool = $mcp_tool_manager->createInstance('tool_belt_dynamic.tool_belt__entity_list');
    $list_result = $list_tool->execute([
      'entity_type_id' => 'node',
      'bundle' => 'article',
      'amount' => 5,
      'fields' => ['nid', 'title'],
    ], $this->createMock(ClientGateway::class));
    $list_result = $this->getStructuredContent($list_result);
    $this->assertTrue($list_result['success'], $list_result['message']);
    $this->assertSame('tool_belt:entity_list', $list_result['tool_id']);
    $this->assertArrayHasKey('results', $list_result['outputs']);

    // Check that dynamic tools adapt saved entity references.
    $field_values_tool = $mcp_tool_manager->createInstance('tool_belt_dynamic.tool_belt__entity_field_values');
    $field_values_result = $field_values_tool->execute([
      'entity' => [
        'entity_type_id' => 'node',
        'entity_id' => (int) $node->id(),
      ],
      'fields' => ['title'],
    ], $this->createMock(ClientGateway::class));
    $field_values_result = $this->getStructuredContent($field_values_result);
    $this->assertTrue($field_values_result['success'], $field_values_result['message']);
    $this->assertArrayHasKey('field_values', $field_values_result['outputs']);
  }

  /**
   * Gets structured content from an MCP tool result.
   *
   * @param mixed $result
   *   The MCP tool result.
   *
   * @return array
   *   The structured content.
   */
  protected function getStructuredContent(mixed $result): array {
    if ($result instanceof CallToolResult) {
      return $result->structuredContent;
    }
    return $result;
  }

}
