<?php

declare(strict_types=1);

namespace Drupal\plugin_report;

use Drupal\Core\Plugin\DefaultPluginManager;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Discovers DefaultPluginManager services and their plugin definitions.
 */
final class PluginReportManager {

  /**
   * The service container.
   */
  protected ContainerInterface $container;

  /**
   * Inverted map of container aliases: actual service ID => alias.
   *
   * Populated lazily by resolveServiceId().
   *
   * @var array<string, string>|null
   */
  private ?array $aliasMap = NULL;

  /**
   * Constructs a PluginReportManager.
   *
   * @param \Symfony\Component\DependencyInjection\ContainerInterface $container
   *   The service container.
   */
  public function __construct(ContainerInterface $container) {
    $this->container = $container;
  }

  /**
   * Returns metadata about all DefaultPluginManager services.
   *
   * Iterates every service ID in the container, instantiates it, and keeps
   * those that are instances of DefaultPluginManager. Protected properties are
   * read via Reflection to avoid calling getDefinitions() prematurely.
   *
   * @return array<int, array<string, mixed>>
   *   Indexed array of metadata arrays sorted by service ID. Each entry has
   *   keys: id, class, provider, subdir, discovery, interface, alter_hook.
   */
  public function getPluginManagers(): array {
    $managers = [];
    foreach ($this->getContainerServiceIds() as $serviceId) {
      try {
        $service = $this->container->get($serviceId);
        if (!$service instanceof DefaultPluginManager) {
          continue;
        }
        $class = $service::class;
        $managers[] = [
          'id' => $this->resolveServiceId($serviceId),
          'class' => $class,
          'provider' => $this->extractProvider($class),
          'subdir' => $this->readProtected($service, 'subdir'),
          'discovery' => $this->readProtected($service, 'pluginDefinitionAttributeName')
            ?: $this->readProtected($service, 'pluginDefinitionAnnotationName'),
          'interface' => $this->readProtected($service, 'pluginInterface'),
          'alter_hook' => $this->readProtected($service, 'alterHook'),
        ];
      }
      catch (\Throwable) {
        continue;
      }
    }
    usort($managers, static fn(array $a, array $b): int => strcmp((string) $a['id'], (string) $b['id']));
    return $managers;
  }

  /**
   * Returns all plugin definitions for the given plugin manager service ID.
   *
   * @param string $pluginManagerId
   *   The service ID.
   *
   * @return array
   *   Plugin definitions keyed by plugin ID, exactly as returned by
   *   getDefinitions(). Non-scalar values are not pre-processed.
   *
   * @throws \InvalidArgumentException
   *   If the service ID is not a known DefaultPluginManager.
   */
  public function getPlugins(string $pluginManagerId): array {
    $ids = array_column($this->getPluginManagers(), 'id');
    if (!in_array($pluginManagerId, $ids, TRUE)) {
      throw new \InvalidArgumentException(
        sprintf('"%s" is not a known DefaultPluginManager service.', $pluginManagerId)
      );
    }
    $plugins = $this->container->get($pluginManagerId)->getDefinitions();
    foreach ($plugins as $pluginId => $definition) {
      if (is_object($definition)) {
        $definition = (array) $definition;
      }
      $definition['id'] = $pluginId;
      $plugins[$pluginId] = $definition;
    }
    return $plugins;
  }

  /**
   * Resolves a service ID to its alias if one exists.
   *
   * Some modules register plugin managers under their FQCN as the primary
   * service ID and define a conventional alias name. This method inverts the
   * container's alias map to surface the preferred name.
   *
   * @param string $serviceId
   *   The raw service ID from getServiceIds().
   *
   * @return string
   *   The alias if found, otherwise the original service ID.
   */
  private function resolveServiceId(string $serviceId): string {
    if (!isset($this->aliasMap)) {
      $this->aliasMap = [];
      try {
        $ref = new \ReflectionProperty(get_class($this->container), 'aliases');
        /** @var array<string, string> $aliases */
        $aliases = $ref->getValue($this->container);
        foreach ($aliases as $alias => $target) {
          $this->aliasMap[$target] = $alias;
        }
      }
      catch (\ReflectionException) {
        // Leave aliasMap empty; fall back to raw service IDs.
      }
    }
    if (!str_contains($serviceId, '\\')) {
      return $serviceId;
    }
    return $this->aliasMap[$serviceId] ?? $serviceId;
  }

  /**
   * Returns all service IDs from the container.
   *
   * Wraps the Drupal-specific getServiceIds() method, which is not declared on
   * the Symfony ContainerInterface but is available on all Drupal containers.
   *
   * @return list<string>
   *   All service IDs registered in the container.
   */
  private function getContainerServiceIds(): array {
    // getServiceIds() is declared on Drupal's ContainerInterface, which
    // extends the Symfony ContainerInterface used for the type hint here.
    // @phpstan-ignore method.notFound
    return $this->container->getServiceIds();
  }

  /**
   * Extracts the provider (module name) from a fully-qualified class name.
   *
   * The second namespace segment of Drupal-namespaced classes is the module
   * machine name (e.g., Drupal\block\...) → 'block'.
   *
   * @param string $class
   *   Fully-qualified class name.
   *
   * @return string
   *   The provider, or an empty string if indeterminate.
   */
  protected function extractProvider(string $class): string {
    $parts = explode('\\', $class);
    return $parts[1] ?? '';
  }

  /**
   * Reads a protected property from a DefaultPluginManager via Reflection.
   *
   * Properties are declared on DefaultPluginManager itself, so reflection
   * always targets that class to ensure accessibility regardless of the
   * concrete subclass.
   *
   * @param \Drupal\Core\Plugin\DefaultPluginManager $manager
   *   The plugin manager instance.
   * @param string $property
   *   The property name.
   *
   * @return mixed
   *   The property value, or NULL if not accessible.
   */
  protected function readProtected(DefaultPluginManager $manager, string $property): mixed {
    try {
      $ref = new \ReflectionProperty(DefaultPluginManager::class, $property);
      return $ref->getValue($manager);
    }
    catch (\ReflectionException) {
      return NULL;
    }
  }

}
