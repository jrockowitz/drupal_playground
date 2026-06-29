<?php

namespace Drupal\term_reference\Hook;

use Drupal\Component\Plugin\Discovery\CachedDiscoveryInterface;
use Drupal\Core\Field\FieldConfigInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Routing\RouteBuilderInterface;
use Drupal\term_reference\TermReferenceDiscoveryInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Provides hooks for Term Reference.
 */
class TermReferenceHooks {

  /**
   * Constructs a TermReferenceHooks object.
   *
   * @param \Drupal\Component\Plugin\Discovery\CachedDiscoveryInterface $localTaskManager
   *   The local task manager.
   * @param \Drupal\Core\Routing\RouteBuilderInterface $routeBuilder
   *   The route builder.
   * @param \Drupal\term_reference\TermReferenceDiscoveryInterface $termReferenceDiscovery
   *   The term reference discovery service.
   */
  public function __construct(
    #[Autowire(service: 'plugin.manager.menu.local_task')]
    protected CachedDiscoveryInterface $localTaskManager,
    protected RouteBuilderInterface $routeBuilder,
    protected TermReferenceDiscoveryInterface $termReferenceDiscovery,
  ) {}

  /**
   * Clears field config dependent caches.
   *
   * @param \Drupal\Core\Field\FieldConfigInterface $fieldConfig
   *   The field config.
   */
  #[Hook('field_config_insert')]
  #[Hook('field_config_update')]
  #[Hook('field_config_delete')]
  public function fieldConfigClearCache(FieldConfigInterface $fieldConfig): void {
    $this->termReferenceDiscovery->clearCachedFields();
    $this->localTaskManager->clearCachedDefinitions();
    $this->routeBuilder->setRebuildNeeded();
  }

}
