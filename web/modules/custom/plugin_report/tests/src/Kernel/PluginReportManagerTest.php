<?php

declare(strict_types=1);

namespace Drupal\Tests\plugin_report\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\plugin_report\PluginReportManagerInterface;

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
   */
  protected PluginReportManagerInterface $pluginReportManager;

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
    // Check that the block manager's provider is extracted from its class name
    // (Drupal\Core\Block\BlockManager → second segment is 'core').
    self::assertArrayHasKey('plugin.manager.block', $managers);
    self::assertSame('core', $managers['plugin.manager.block']['provider']);
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

  /**
   * @covers ::getPluginManagers
   */
  public function testGetPluginManagersKeysMatchIdValues(): void {
    $managers = $this->pluginReportManager->getPluginManagers();
    foreach ($managers as $key => $manager) {
      // Check that each manager's array key matches its 'id' value.
      self::assertSame($key, $manager['id']);
    }
  }

  /**
   * @covers ::getPlugins
   */
  public function testGetPluginsInjectsIdKey(): void {
    $this->enableModules(['block']);
    $plugins = $this->pluginReportManager->getPlugins('plugin.manager.block');
    $pluginId = array_key_first($plugins);
    $first = $plugins[$pluginId];
    // Check that plugin definitions have an 'id' key injected from the array key.
    self::assertArrayHasKey('id', $first);
    self::assertSame($pluginId, $first['id']);
  }

}
