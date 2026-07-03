<?php

declare(strict_types=1);

namespace Drupal\drupal_playground_ai_mcp_tool_bridge\Plugin\Tool;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\Session\AccountSwitcherInterface;
use Drupal\mcp_server\Plugin\ToolPluginBase;
use Drupal\tool\Tool\ToolInterface;
use Drupal\tool\Tool\ToolManager;
use Mcp\Server\ClientGateway;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Base class for MCP tools that call Tool Belt Tool API plugins.
 */
abstract class ToolBeltContentBridgeBase extends ToolPluginBase {

  /**
   * Constructs a ToolBeltContentBridgeBase object.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin ID for the plugin instance.
   * @param mixed $plugin_definition
   *   The typed plugin definition.
   * @param \Drupal\Core\Session\AccountProxyInterface $currentUser
   *   The current user.
   * @param \Drupal\tool\Tool\ToolManager $toolManager
   *   The Tool API plugin manager.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   * @param \Drupal\Core\Session\AccountSwitcherInterface $accountSwitcher
   *   The account switcher.
   */
  public function __construct(
    array $configuration,
    string $plugin_id,
    mixed $plugin_definition,
    AccountProxyInterface $currentUser,
    protected ToolManager $toolManager,
    protected EntityTypeManagerInterface $entityTypeManager,
    protected AccountSwitcherInterface $accountSwitcher,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $currentUser);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(
    ContainerInterface $container,
    array $configuration,
    $plugin_id,
    $plugin_definition,
  ): static {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('current_user'),
      $container->get('plugin.manager.tool'),
      $container->get('entity_type.manager'),
      $container->get('account_switcher'),
    );
  }

  /**
   * {@inheritdoc}
   */
  protected function defaultConfiguration(): array {
    return [
      'enabled' => TRUE,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function checkAccess(AccountInterface $account): AccessResultInterface {
    if (PHP_SAPI === 'cli') {
      return AccessResult::allowed();
    }
    return AccessResult::allowedIfHasPermission($account, 'access mcp server');
  }

  /**
   * Executes the bridge tool as an administrator for local STDIO sessions.
   *
   * @param array $arguments
   *   The MCP tool arguments.
   * @param \Mcp\Server\ClientGateway $gateway
   *   The MCP client gateway.
   *
   * @return mixed
   *   The MCP tool result.
   */
  public function execute(array $arguments, ClientGateway $gateway): mixed {
    $switch_account = NULL;
    if (PHP_SAPI === 'cli' && (int) $this->currentUser->id() !== 1) {
      $switch_account = $this->entityTypeManager->getStorage('user')->load(1);
    }

    if ($switch_account instanceof AccountInterface) {
      $this->accountSwitcher->switchTo($switch_account);
    }

    try {
      return $this->doExecute($arguments, $gateway);
    }
    finally {
      if ($switch_account instanceof AccountInterface) {
        $this->accountSwitcher->switchBack();
      }
    }
  }

  /**
   * Executes the bridge tool after account handling.
   *
   * @param array $arguments
   *   The MCP tool arguments.
   * @param \Mcp\Server\ClientGateway $gateway
   *   The MCP client gateway.
   *
   * @return mixed
   *   The MCP tool result.
   */
  abstract protected function doExecute(array $arguments, ClientGateway $gateway): mixed;

  /**
   * Executes an allowlisted Tool API plugin.
   *
   * @param string $tool_id
   *   The Tool API plugin ID.
   * @param array $input_values
   *   The Tool API input values.
   *
   * @return \Drupal\tool\Tool\ToolInterface
   *   The executed tool plugin.
   */
  protected function executeTool(string $tool_id, array $input_values): ToolInterface {
    /** @var \Drupal\tool\Tool\ToolInterface $tool */
    $tool = $this->toolManager->createInstance($tool_id);
    foreach ($input_values as $name => $value) {
      $tool->setInputValue($name, $value);
    }
    if (!$tool->access()) {
      throw new \RuntimeException(sprintf('Access denied for Tool API tool "%s".', $tool_id));
    }
    $tool->execute();
    if (!$tool->getResultStatus()) {
      throw new \RuntimeException((string) $tool->getResultMessage());
    }
    return $tool;
  }

  /**
   * Converts a content entity into a JSON-friendly summary.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The content entity.
   *
   * @return array
   *   The entity summary.
   */
  protected function summarizeEntity(ContentEntityInterface $entity): array {
    return [
      'entity_type_id' => $entity->getEntityTypeId(),
      'entity_id' => (int) $entity->id(),
      'uuid' => $entity->uuid(),
      'bundle' => $entity->bundle(),
      'label' => $entity->label(),
      'status' => method_exists($entity, 'isPublished') ? (int) $entity->isPublished() : NULL,
      'url' => $entity->toUrl()->toString(),
    ];
  }

}
