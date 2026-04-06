<?php

declare(strict_types=1);

namespace Drupal\plugin_report;

/**
 * Defines the interface for the Plugin Report manager.
 */
interface PluginReportManagerInterface {

  /**
   * Returns metadata about all DefaultPluginManager services.
   *
   * @return array<string, array<string, mixed>>
   *   Associative array of metadata arrays keyed and sorted by service ID.
   *   Each entry has keys: id, class, provider, subdir, discovery, interface,
   *   alter_hook.
   */
  public function getPluginManagers(): array;

  /**
   * Returns all plugin definitions for the given plugin manager service ID.
   *
   * @param string $pluginManagerId
   *   The service ID.
   *
   * @return array<string, array<string, mixed>>
   *   Plugin definitions keyed by plugin ID.
   *
   * @throws \InvalidArgumentException
   *   If the service ID is not a known DefaultPluginManager.
   */
  public function getPlugins(string $pluginManagerId): array;

}
