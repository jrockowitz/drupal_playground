<?php

declare(strict_types=1);

namespace Drupal\drupal_playground_ai_mcp_tool_bridge\Plugin\Tool;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Plugin\Context\ContextDefinitionInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\mcp_server\Attribute\Tool;
use Drupal\tool\Tool\ToolDefinition;
use Mcp\Schema\Content\TextContent;
use Mcp\Schema\Result\CallToolResult;
use Mcp\Server\ClientGateway;

/**
 * Dynamically exposes enabled Tool Belt tools as MCP tools.
 */
#[Tool(
  id: 'tool_belt_dynamic',
  label: new TranslatableMarkup('Dynamic Tool Belt Tool'),
  description: new TranslatableMarkup('Dynamically exposes enabled Tool Belt tools as MCP tools.'),
  module_dependencies: ['tool_belt'],
  deriver: \Drupal\drupal_playground_ai_mcp_tool_bridge\Plugin\Derivative\ToolBeltToolDeriver::class,
  outputSchema: [
    'type' => 'object',
    'properties' => [
      'success' => ['type' => 'boolean'],
      'message' => ['type' => 'string'],
      'tool_id' => ['type' => 'string'],
      'outputs' => ['type' => 'object'],
    ],
    'required' => ['success', 'message', 'tool_id', 'outputs'],
  ],
  openWorld: FALSE,
)]
class DynamicToolBeltTool extends ToolBeltContentBridgeBase {

  /**
   * {@inheritdoc}
   */
  protected function doExecute(array $arguments, ClientGateway $gateway): mixed {
    $tool_id = $this->pluginDefinition->toolId;
    if ($tool_id === NULL) {
      return [
        'success' => FALSE,
        'message' => 'Missing Tool Belt tool ID.',
        'tool_id' => '',
        'outputs' => [],
      ];
    }

    try {
      $tool_definition = $this->toolManager->getDefinition($tool_id);
      assert($tool_definition instanceof ToolDefinition);
      $prepared_arguments = $this->prepareArguments($tool_definition, $arguments);
      $tool = $this->executeTool($tool_id, $prepared_arguments);
      return $this->buildResult([
        'success' => TRUE,
        'message' => (string) $tool->getResultMessage(),
        'tool_id' => $tool_id,
        'outputs' => $this->normalizeOutputs($tool->getOutputValues()),
      ]);
    }
    catch (\Throwable $exception) {
      return $this->buildResult([
        'success' => FALSE,
        'message' => $exception->getMessage(),
        'tool_id' => $tool_id,
        'outputs' => [],
      ], TRUE);
    }
  }

  /**
   * Builds an explicit MCP call result for dynamic Tool Belt tools.
   *
   * @param array $data
   *   The structured result data.
   * @param bool $is_error
   *   Whether the result is an error.
   *
   * @return \Mcp\Schema\Result\CallToolResult
   *   The MCP call result.
   */
  protected function buildResult(array $data, bool $is_error = FALSE): CallToolResult {
    return new CallToolResult(
      [new TextContent(json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR))],
      $is_error,
      $data,
    );
  }

  /**
   * Prepares MCP arguments for a Tool API plugin.
   *
   * @param \Drupal\tool\Tool\ToolDefinition $tool_definition
   *   The Tool API definition.
   * @param array $arguments
   *   The MCP arguments.
   *
   * @return array
   *   Prepared Tool API arguments.
   */
  protected function prepareArguments(ToolDefinition $tool_definition, array $arguments): array {
    $prepared_arguments = [];
    foreach ($tool_definition->getInputDefinitions(TRUE) as $name => $definition) {
      if (!array_key_exists($name, $arguments)) {
        continue;
      }
      $prepared_arguments[$name] = $this->prepareValue($definition, $arguments[$name]);
    }
    return $prepared_arguments;
  }

  /**
   * Prepares a single MCP argument value for Tool API.
   *
   * @param \Drupal\Core\Plugin\Context\ContextDefinitionInterface $definition
   *   The Tool API definition.
   * @param mixed $value
   *   The MCP value.
   *
   * @return mixed
   *   The prepared value.
   */
  protected function prepareValue(ContextDefinitionInterface $definition, mixed $value): mixed {
    if ($definition->isMultiple() && is_array($value) && $definition->getDataType() === 'entity') {
      return array_map(fn (mixed $item): mixed => $this->loadEntityReference($item), $value);
    }
    if ($definition->getDataType() === 'entity') {
      return $this->loadEntityReference($value);
    }
    return $value;
  }

  /**
   * Loads an entity from an MCP entity reference.
   *
   * @param mixed $value
   *   The entity reference.
   *
   * @return \Drupal\Core\Entity\EntityInterface
   *   The loaded entity.
   */
  protected function loadEntityReference(mixed $value): EntityInterface {
    if ($value instanceof EntityInterface) {
      return $value;
    }
    if (!is_array($value) || !isset($value['entity_type_id'], $value['entity_id'])) {
      throw new \InvalidArgumentException('Entity inputs must be objects with entity_type_id and entity_id.');
    }
    $entity = $this->entityTypeManager
      ->getStorage((string) $value['entity_type_id'])
      ->load((int) $value['entity_id']);
    if (!$entity instanceof EntityInterface) {
      throw new \InvalidArgumentException(sprintf('Entity "%s:%s" was not found.', $value['entity_type_id'], $value['entity_id']));
    }
    return $entity;
  }

  /**
   * Converts Tool API outputs into MCP-safe JSON values.
   *
   * @param array $outputs
   *   The Tool API outputs.
   *
   * @return array
   *   The normalized outputs.
   */
  protected function normalizeOutputs(array $outputs): array {
    return array_map(fn (mixed $value): mixed => $this->normalizeOutputValue($value), $outputs);
  }

  /**
   * Converts a Tool API output value into an MCP-safe JSON value.
   *
   * @param mixed $value
   *   The output value.
   *
   * @return mixed
   *   The normalized value.
   */
  protected function normalizeOutputValue(mixed $value): mixed {
    if ($value instanceof ContentEntityInterface) {
      return $this->summarizeEntity($value);
    }
    if ($value instanceof EntityInterface) {
      return [
        'entity_type_id' => $value->getEntityTypeId(),
        'entity_id' => $value->id() !== NULL ? (int) $value->id() : NULL,
        'uuid' => $value->uuid(),
        'label' => $value->label(),
      ];
    }
    if (is_array($value)) {
      return array_map(fn (mixed $item): mixed => $this->normalizeOutputValue($item), $value);
    }
    if (is_object($value)) {
      if ($value instanceof \Stringable || method_exists($value, '__toString')) {
        return (string) $value;
      }
      if ($value instanceof \JsonSerializable) {
        return $this->normalizeOutputValue($value->jsonSerialize());
      }
      if (method_exists($value, 'toString')) {
        return (string) $value->toString();
      }
      return $value::class;
    }
    return $value;
  }

}
