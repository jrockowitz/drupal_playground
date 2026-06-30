<?php

namespace Drupal\term_reference\Hook;

use Drupal\Core\Field\FieldConfigInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\term_reference\TermReferenceDiscoveryInterface;

/**
 * Provides hooks for Term Reference.
 */
class TermReferenceHooks {

  /**
   * Constructs a TermReferenceHooks object.
   *
   * @param \Drupal\term_reference\TermReferenceDiscoveryInterface $termReferenceDiscovery
   *   The term reference discovery service.
   */
  public function __construct(
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
  }

}
