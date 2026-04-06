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
          'id' => $serviceId,
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
    return $this->container->get($pluginManagerId)->getDefinitions();
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
    // getServiceIds() is declared on Drupal\Component\DependencyInjection\ContainerInterface,
    // which extends the Symfony ContainerInterface used for the type hint here.
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
