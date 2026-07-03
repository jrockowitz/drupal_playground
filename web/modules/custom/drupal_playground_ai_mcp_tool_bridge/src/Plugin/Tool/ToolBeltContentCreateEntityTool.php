<?php

declare(strict_types=1);

namespace Drupal\drupal_playground_ai_mcp_tool_bridge\Plugin\Tool;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\mcp_server\Attribute\Tool;
use Mcp\Server\ClientGateway;

/**
 * Creates content entities through Tool Belt Tool API tools.
 */
#[Tool(
  id: 'tool_belt_content_create_entity',
  label: new TranslatableMarkup('Tool Belt Content Create Entity'),
  description: new TranslatableMarkup('Creates and saves a content entity using Tool Belt entity tools.'),
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
        'description' => 'The entity bundle, such as article or page.',
      ],
      'base_fields' => [
        'type' => 'object',
        'description' => 'Base field values passed to Tool Belt entity_stub.',
        'additionalProperties' => TRUE,
      ],
      'field_values' => [
        'type' => 'object',
        'description' => 'Additional field values keyed by field name.',
        'additionalProperties' => TRUE,
      ],
    ],
    'required' => [
      'entity_type_id',
      'bundle',
      'base_fields',
    ],
  ],
  outputSchema: [
    'type' => 'object',
    'properties' => [
      'success' => ['type' => 'boolean'],
      'message' => ['type' => 'string'],
      'entity_type_id' => ['type' => 'string'],
      'entity_id' => ['type' => 'integer'],
      'uuid' => ['type' => 'string'],
      'bundle' => ['type' => 'string'],
      'label' => ['type' => 'string'],
      'status' => ['type' => ['integer', 'null']],
      'url' => ['type' => 'string'],
    ],
    'required' => [
      'success',
      'message',
      'entity_type_id',
      'entity_id',
      'uuid',
      'bundle',
      'label',
      'url',
    ],
  ],
  readOnly: FALSE,
  destructive: FALSE,
  idempotent: FALSE,
  openWorld: FALSE,
)]
class ToolBeltContentCreateEntityTool extends ToolBeltContentBridgeBase {

  /**
   * {@inheritdoc}
   */
  protected function doExecute(array $arguments, ClientGateway $gateway): mixed {
    try {
      $stub_tool = $this->executeTool('tool_belt:entity_stub', [
        'entity_type_id' => $arguments['entity_type_id'] ?? '',
        'bundle' => $arguments['bundle'] ?? '',
        'base_fields' => $arguments['base_fields'] ?? [],
      ]);
      $entity = $stub_tool->getOutputValue('created_entity');
      if (!$entity instanceof ContentEntityInterface) {
        throw new \RuntimeException('Tool Belt did not return a content entity.');
      }

      foreach (($arguments['field_values'] ?? []) as $field_name => $field_value) {
        $field_tool = $this->executeTool('tool_belt:field_set_value', [
          'entity' => $entity,
          'field_name' => $field_name,
          'value' => $field_value,
        ]);
        $entity = $field_tool->getOutputValue('updated_entity');
        if (!$entity instanceof ContentEntityInterface) {
          throw new \RuntimeException(sprintf('Tool Belt did not return an updated entity for field "%s".', $field_name));
        }
      }

      $save_tool = $this->executeTool('tool_belt:entity_save', [
        'entity' => $entity,
      ]);
      $saved_entity = $save_tool->getOutputValue('saved_entity');
      if (!$saved_entity instanceof ContentEntityInterface) {
        throw new \RuntimeException('Tool Belt did not return a saved content entity.');
      }

      return [
        'success' => TRUE,
        'message' => (string) $save_tool->getResultMessage(),
      ] + $this->summarizeEntity($saved_entity);
    }
    catch (\Throwable $exception) {
      return [
        'success' => FALSE,
        'message' => $exception->getMessage(),
      ];
    }
  }

}
