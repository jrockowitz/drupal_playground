<?php

declare(strict_types=1);

namespace Drupal\drupal_playground_ai_mcp_tool_bridge\Plugin\Tool;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\mcp_server\Attribute\Tool;
use Mcp\Server\ClientGateway;

/**
 * Exposes Tool Belt field definition discovery as an MCP tool.
 */
#[Tool(
  id: 'tool_belt_content_field_definitions',
  label: new TranslatableMarkup('Tool Belt Content Field Definitions'),
  description: new TranslatableMarkup('Gets Tool Belt field value definitions for a content entity type and bundle.'),
  module_dependencies: ['tool_belt_content'],
  inputSchema: [
    'type' => 'object',
    'properties' => [
      'entity_type_id' => [
        'type' => 'string',
        'description' => 'The content entity type ID, such as node.',
      ],
      'bundle' => [
        'type' => 'string',
        'description' => 'The entity bundle, such as article.',
      ],
    ],
    'required' => ['entity_type_id', 'bundle'],
  ],
  outputSchema: [
    'type' => 'object',
    'properties' => [
      'success' => ['type' => 'boolean'],
      'message' => ['type' => 'string'],
      'outputs' => ['type' => 'object'],
    ],
    'required' => ['success', 'message', 'outputs'],
  ],
  readOnly: TRUE,
  destructive: FALSE,
  idempotent: TRUE,
  openWorld: FALSE,
)]
class ToolBeltContentFieldDefinitionsTool extends ToolBeltContentBridgeBase {

  /**
   * {@inheritdoc}
   */
  protected function doExecute(array $arguments, ClientGateway $gateway): mixed {
    try {
      $tool = $this->executeTool('tool_belt:entity_field_value_definitions', [
        'entity_type_id' => $arguments['entity_type_id'] ?? '',
        'bundle' => $arguments['bundle'] ?? '',
      ]);
      return [
        'success' => TRUE,
        'message' => (string) $tool->getResultMessage(),
        'outputs' => $tool->getOutputValues(),
      ];
    }
    catch (\Throwable $exception) {
      return [
        'success' => FALSE,
        'message' => $exception->getMessage(),
        'outputs' => [],
      ];
    }
  }

}
