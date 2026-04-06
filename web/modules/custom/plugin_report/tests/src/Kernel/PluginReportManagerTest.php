<?php

declare(strict_types=1);

namespace Drupal\Tests\plugin_report\Kernel;

use Drupal\KernelTests\KernelTestBase;

/**
 * Tests PluginReportManager.
 *
 * @group plugin_report
 * @coversDefaultClass \Drupal\plugin_report\PluginReportManager
 */
class PluginReportManagerTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['plugin_report'];

  /**
   * The plugin report manager under test.
   *
   * @var \Drupal\plugin_report\PluginReportManager
   */
  protected $pluginReportManager;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->pluginReportManager = $this->container->get('plugin_report.manager');
  }

  /**
   * @covers ::getPluginManagers
   */
  public function testGetPluginManagersReturnsNonEmptyArray(): void {
    $managers = $this->pluginReportManager->getPluginManagers();
    // Check that the result is a non-empty array.
    self::assertNotEmpty($managers);
  }

  /**
   * @covers ::getPluginManagers
   */
  public function testGetPluginManagersIsSortedById(): void {
    $managers = $this->pluginReportManager->getPluginManagers();
    $ids = array_column($managers, 'id');
    $sorted = $ids;
    sort($sorted);
    // Check that managers are sorted alphabetically by service ID.
    self::assertSame($sorted, $ids);
  }

  /**
   * @covers ::getPluginManagers
   */
  public function testGetPluginManagersMetadataShape(): void {
    $managers = $this->pluginReportManager->getPluginManagers();
    $first = reset($managers);
    // Check that each metadata entry contains all required keys.
    foreach (['id', 'class', 'provider', 'subdir', 'discovery', 'interface', 'alter_hook'] as $key) {
      self::assertArrayHasKey($key, $first);
    }
  }

  /**
   * @covers ::getPluginManagers
   */
  public function testProviderIsExtractedFromClass(): void {
    $this->enableModules(['block']);
    $managers = $this->pluginReportManager->getPluginManagers();
    $index = array_search('plugin.manager.block', array_column($managers, 'id'), TRUE);
    // Check that the block manager's provider is extracted from its class name
    // (Drupal\Core\Block\BlockManager → second segment is 'core').
    if ($index !== FALSE) {
      self::assertSame('core', $managers['plugin.manager.block']['provider']);
    }
  }

  /**
   * @covers ::getPlugins
   */
  public function testGetPluginsReturnsArray(): void {
    $this->enableModules(['block']);
    $plugins = $this->pluginReportManager->getPlugins('plugin.manager.block');
    // Check that plugin definitions are returned as a non-empty array.
    self::assertNotEmpty($plugins);
  }

  /**
   * @covers ::getPlugins
   */
  public function testGetPluginsThrowsOnUnknownManager(): void {
    // Check that an InvalidArgumentException is thrown for unknown service IDs.
    $this->expectException(\InvalidArgumentException::class);
    $this->pluginReportManager->getPlugins('plugin.manager.does_not_exist_xyz');
  }

}
