<?php

declare(strict_types=1);

namespace Drupal\drupal_playground_ai_mcp_tool_bridge\Plugin\Derivative;

use Drupal\Component\Plugin\Derivative\DeriverBase;
use Drupal\Core\Plugin\Context\ContextDefinitionInterface;
use Drupal\Core\Plugin\Discovery\ContainerDeriverInterface;
use Drupal\tool\Tool\ToolManager;
use Drupal\tool\TypedData\ListContextDefinition;
use Drupal\tool\TypedData\MapContextDefinition;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Derives one MCP tool wrapper for each enabled Tool Belt tool.
 */
class ToolBeltToolDeriver extends DeriverBase implements ContainerDeriverInterface {

  /**
   * Constructs a ToolBeltToolDeriver object.
   *
   * @param \Drupal\tool\Tool\ToolManager $toolManager
   *   The Tool API plugin manager.
   */
  public function __construct(
    protected ToolManager $toolManager,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, $base_plugin_id): static {
    return new static(
      $container->get('plugin.manager.tool'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getDerivativeDefinitions($base_plugin_definition): array {
    /** @var \Drupal\mcp_server\Definition\ToolDefinition $base_plugin_definition */
    $this->derivatives = [];

    foreach ($this->toolManager->getDefinitions() as $tool_id => $tool_definition) {
      if (!str_starts_with($tool_id, 'tool_belt:')) {
        continue;
      }

      $derivative_id = self::encodeToolId($tool_id);
      $this->derivatives[$derivative_id] = $base_plugin_definition->withDerivative(
        derivativeId: $derivative_id,
        toolId: $tool_id,
        description: sprintf(
          '%s Tool Belt operation: %s',
          (string) $tool_definition->getLabel(),
          (string) $tool_definition->getDescription(),
        ),
        inputSchema: $this->buildInputSchema($tool_definition->getInputDefinitions(TRUE)),
        destructive: $tool_definition->isDestructive() || $tool_definition->getOperation()->isModifying(),
      );
    }

    return $this->derivatives;
  }

  /**
   * Encodes a Tool API tool ID into an MCP-safe derivative ID.
   *
   * @param string $tool_id
   *   The Tool API tool ID.
   *
   * @return string
   *   The MCP-safe derivative ID.
   */
  public static function encodeToolId(string $tool_id): string {
    return str_replace(':', '__', $tool_id);
  }

  /**
   * Builds an MCP JSON schema from Tool API input definitions.
   *
   * @param array $input_definitions
   *   The Tool API input definitions.
   *
   * @return array
   *   The MCP input schema.
   */
  protected function buildInputSchema(array $input_definitions): array {
    $properties = [];
    $required = [];
    foreach ($input_definitions as $name => $definition) {
      assert($definition instanceof ContextDefinitionInterface);
      $properties[$name] = $this->buildSchemaForDefinition($definition);
      if ($definition->isRequired()) {
        $required[] = $name;
      }
    }

    $schema = [
      'type' => 'object',
    ];
    if ($properties !== []) {
      $schema['properties'] = $properties;
    }
    if ($required !== []) {
      $schema['required'] = $required;
    }
    return $schema;
  }

  /**
   * Builds an MCP JSON schema for a single Tool API definition.
   *
   * @param \Drupal\Core\Plugin\Context\ContextDefinitionInterface $definition
   *   The Tool API input definition.
   *
   * @return array
   *   The JSON schema.
   */
  protected function buildSchemaForDefinition(ContextDefinitionInterface $definition): array {
    if ($definition->isMultiple()) {
      return [
        'type' => 'array',
        'description' => (string) $definition->getDescription(),
        'items' => $this->buildSingleValueSchema($definition),
      ];
    }
    return $this->buildSingleValueSchema($definition);
  }

  /**
   * Builds a JSON schema for a single value.
   *
   * @param \Drupal\Core\Plugin\Context\ContextDefinitionInterface $definition
   *   The Tool API input definition.
   *
   * @return array
   *   The JSON schema.
   */
  protected function buildSingleValueSchema(ContextDefinitionInterface $definition): array {
    $data_type = $definition->getDataType();
    $schema = [
      'description' => (string) $definition->getDescription(),
    ];

    if ($data_type === 'entity') {
      return $schema + [
        'type' => 'object',
        'properties' => [
          'entity_type_id' => [
            'type' => 'string',
            'description' => 'The entity type ID.',
          ],
          'entity_id' => [
            'type' => 'integer',
            'description' => 'The entity ID.',
          ],
        ],
        'required' => ['entity_type_id', 'entity_id'],
      ];
    }

    if ($definition instanceof MapContextDefinition || $data_type === 'map') {
      return $schema + [
        'type' => 'object',
        'additionalProperties' => TRUE,
      ];
    }

    if ($definition instanceof ListContextDefinition || $data_type === 'list') {
      return $schema + [
        'type' => 'array',
        'items' => ['type' => 'string'],
      ];
    }

    return $schema + [
      'type' => match ($data_type) {
        'boolean' => 'boolean',
        'float', 'decimal' => 'number',
        'integer' => 'integer',
        default => 'string',
      },
    ];
  }

}
