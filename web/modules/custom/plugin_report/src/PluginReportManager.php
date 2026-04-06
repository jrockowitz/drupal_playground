<?php

declare(strict_types=1);

namespace Drupal\plugin_report;

use Drupal\Core\Plugin\DefaultPluginManager;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Discovers DefaultPluginManager services and their plugin definitions.
 */
class PluginReportManager implements PluginReportManagerInterface {

  /**
   * Inverted map of container aliases: actual service ID => alias.
   */
  protected array $aliases;

  /**
   * Constructs a PluginReportManager.
   *
   * @param \Symfony\Component\DependencyInjection\ContainerInterface $container
   *   The service container.
   */
  public function __construct(
    protected ContainerInterface $container,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function getPluginManagers(): array {
    $managers = [];
    // @phpstan-ignore-next-line method.notFound
    $serviceIds = $this->container->getServiceIds();

    foreach ($serviceIds as $serviceId) {
      /** @var \Drupal\Core\Plugin\DefaultPluginManager|null $service */
      $service = $this->container->get($serviceId);
      if (!$service instanceof DefaultPluginManager) {
        continue;
      }

      $class = $service::class;
      $id = $this->getPluginManagerServiceId($serviceId);

      $managers[$id] = [
        'id' => $id,
        'class' => $class,
        'provider' => $this->extractProvider($class),
        'subdir' => $this->readProtected($service, 'subdir'),
        'discovery' => $this->readProtected($service, 'pluginDefinitionAttributeName')
          ?: $this->readProtected($service, 'pluginDefinitionAnnotationName'),
        'interface' => $this->readProtected($service, 'pluginInterface'),
        'alter_hook' => $this->readProtected($service, 'alterHook'),
      ];
    }

    ksort($managers);

    return $managers;
  }

  /**
   * {@inheritdoc}
   */
  public function getPlugins(string $pluginManagerId): array {
    if (!$this->container->has($pluginManagerId)) {
      throw new \InvalidArgumentException(
        sprintf('"%s" is not a known DefaultPluginManager service.', $pluginManagerId)
      );
    }

    $plugins = $this->container->get($pluginManagerId)->getDefinitions();
    foreach ($plugins as $pluginId => $definition) {
      $definition = (array) $definition;
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
  protected function getPluginManagerServiceId(string $serviceId): string {
    if (!isset($this->aliases)) {
      $this->aliases = [];
      $refection = new \ReflectionProperty(get_class($this->container), 'aliases');
      $aliases = (array) $refection->getValue($this->container);
      $this->aliases = array_flip($aliases);
    }

    if (!str_contains($serviceId, '\\')) {
      return $serviceId;
    }

    return $this->aliases[$serviceId] ?? $serviceId;
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
    catch (\Exception) {
      return NULL;
    }
  }

}
