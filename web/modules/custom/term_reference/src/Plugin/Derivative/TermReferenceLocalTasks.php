<?php

namespace Drupal\term_reference\Plugin\Derivative;

use Drupal\Component\Plugin\Derivative\DeriverBase;
use Drupal\Core\Plugin\Discovery\ContainerDeriverInterface;
use Drupal\term_reference\TermReferenceDiscoveryInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Derives local tasks for term fields.
 */
class TermReferenceLocalTasks extends DeriverBase implements ContainerDeriverInterface {

  /**
   * Constructs a TermReferenceLocalTasks object.
   *
   * @param \Drupal\term_reference\TermReferenceDiscoveryInterface $termReferenceDiscovery
   *   The term reference discovery service.
   */
  public function __construct(
    protected TermReferenceDiscoveryInterface $termReferenceDiscovery,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, $base_plugin_id): static {
    return new static(
      $container->get('term_reference.discovery')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getDerivativeDefinitions($base_plugin_definition): array {
    foreach ($this->termReferenceDiscovery->getAllFields() as $field_id => $field) {
      $this->derivatives[$field_id] = [
        'route_name' => 'term_reference.reference',
        'route_parameters' => [
          'field' => $field['id'],
        ],
        'title' => $field['field_label'] . ' (' . $field['entity_type_label'] . ')',
        'parent_id' => 'term_reference.references',
        'weight' => 0,
      ] + $base_plugin_definition;
    }
    return $this->derivatives;
  }

}
