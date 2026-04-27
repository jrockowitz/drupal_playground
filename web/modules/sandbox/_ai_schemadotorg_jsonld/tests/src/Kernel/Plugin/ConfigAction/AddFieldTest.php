<?php

declare(strict_types=1);

namespace Drupal\Tests\ai_schemadotorg_jsonld\Kernel\Plugin\ConfigAction;

use Drupal\Core\Config\Action\ConfigActionPluginInterface;
use Drupal\node\Entity\NodeType;
use Drupal\Tests\ai_schemadotorg_jsonld\Kernel\AiSchemaDotOrgJsonLdTestBase;

/**
 * Tests the addField config action plugin.
 *
 * @group ai_schemadotorg_jsonld
 * @covers \Drupal\ai_schemadotorg_jsonld\Plugin\ConfigAction\AddField
 */
class AddFieldTest extends AiSchemaDotOrgJsonLdTestBase {

  /**
   * The addField config action plugin.
   */
  protected ConfigActionPluginInterface $action;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $plugin_manager = $this->container->get('plugin.manager.config_action');
    $this->action = $plugin_manager->createInstance('addField');
  }

  /**
   * Tests the addField action with explicit bundles.
   */
  public function testAddFieldConfigActionWithExplicitBundles(): void {
    NodeType::create(['type' => 'page', 'name' => 'Basic page'])->save();
    NodeType::create(['type' => 'article', 'name' => 'Article'])->save();

    $this->action->apply('ai_schemadotorg_jsonld.settings', [
      'entity_type' => 'node',
      'bundles' => ['page'],
    ]);

    $this->assertNotNull($this->entityTypeManager->getStorage('field_config')->load('node.page.field_schemadotorg_jsonld'));
    $this->assertNull($this->entityTypeManager->getStorage('field_config')->load('node.article.field_schemadotorg_jsonld'));
  }

  /**
   * Tests the addField action with wildcard bundles.
   */
  public function testAddFieldConfigActionWithWildcardBundles(): void {
    NodeType::create(['type' => 'page', 'name' => 'Basic page'])->save();
    NodeType::create(['type' => 'article', 'name' => 'Article'])->save();

    $this->action->apply('ai_schemadotorg_jsonld.settings', [
      'entity_type' => 'node',
      'bundles' => ['*'],
    ]);

    $this->assertNotNull($this->entityTypeManager->getStorage('field_config')->load('node.page.field_schemadotorg_jsonld'));
    $this->assertNotNull($this->entityTypeManager->getStorage('field_config')->load('node.article.field_schemadotorg_jsonld'));
  }

  /**
   * Tests the addField action rejects invalid payloads.
   */
  public function testAddFieldConfigActionRejectsInvalidPayload(): void {
    $this->expectException(\InvalidArgumentException::class);
    $this->expectExceptionMessage('The addField config action requires a non-empty bundles array.');

    $this->action->apply('ai_schemadotorg_jsonld.settings', [
      'entity_type' => 'node',
      'bundles' => [],
    ]);
  }

}
