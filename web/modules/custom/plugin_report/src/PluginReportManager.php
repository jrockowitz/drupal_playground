<?php

declare(strict_types=1);

namespace Drupal\plugin_report;

use Drupal\Component\Plugin\ConfigurableInterface;
use Drupal\Component\Plugin\PluginInspectionInterface;
use Drupal\Core\Plugin\DefaultPluginManager;
use Drupal\Core\Render\Element\ElementInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Discovers DefaultPluginManager services and their plugin definitions.
 */
class PluginReportManager implements PluginReportManagerInterface {

  /**
   * Inverted map of container aliases: actual service ID => alias.
   *
   * Populated lazily by resolveServiceId(). NULL until first call.
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
      $id = $this->resolveServiceId($serviceId);

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
        sprintf('"%s" is not a known service.', $pluginManagerId)
      );
    }

    /** @var object $service */
    $service = $this->container->get($pluginManagerId);
    if (!$service instanceof DefaultPluginManager) {
      throw new \InvalidArgumentException(
        sprintf('"%s" is not a known DefaultPluginManager service.', $pluginManagerId)
      );
    }

    $plugins = $service->getDefinitions();
    foreach ($plugins as $pluginId => $definition) {
      $definition = (array) $definition;
      $definition['id'] = $pluginId;
      $plugins[$pluginId] = $definition;
    }
    return $plugins;
  }

  /**
   * {@inheritdoc}
   */
  public function getPlugin(string $pluginManagerId, string $pluginId): array {
    $service = $this->container->has($pluginManagerId)
      ? $this->container->get($pluginManagerId)
      : NULL;
    if (!$service instanceof DefaultPluginManager) {
      throw new \InvalidArgumentException(
        sprintf('"%s" is not a known DefaultPluginManager service.', $pluginManagerId)
      );
    }

    $definitions = $service->getDefinitions();
    if (!array_key_exists($pluginId, $definitions)) {
      throw new \InvalidArgumentException(
        sprintf('"%s" is not a known plugin of "%s".', $pluginId, $pluginManagerId)
      );
    }

    $definition = (array) $definitions[$pluginId];
    $definition['id'] = $pluginId;
    $result = ['definition' => $definition];

    try {
      $instance = $service->createInstance($pluginId);
      if ($instance instanceof ConfigurableInterface) {
        $result['defaultConfiguration'] = $instance->defaultConfiguration();
      }
      if ($instance instanceof ElementInterface) {
        $result['getInfo'] = $instance->getInfo();
      }
      if ($instance instanceof PluginInspectionInterface) {
        $result['getPluginDefinition'] = (array) $instance->getPluginDefinition();
      }
    }
    catch (\Exception) {
      // Plugin could not be instantiated without required context — skip runtime data.
    }

    $class = isset($instance) ? get_class($instance) : ($definition['class'] ?? NULL);
    $interfaces = $class ? array_values((array) class_implements($class)) : [];
    sort($interfaces);
    $result['interfaces'] = $interfaces;

    return $result;
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
      $refection = new \ReflectionProperty(get_class($this->container), 'aliases');
      $aliases = (array) $refection->getValue($this->container);
      $this->aliasMap = array_flip($aliases);
    }

    if (!str_contains($serviceId, '\\')) {
      return $serviceId;
    }

    return $this->aliasMap[$serviceId] ?? $serviceId;
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
    return strtolower($parts[1] ?? '');
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
