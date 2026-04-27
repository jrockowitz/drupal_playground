<?php

declare(strict_types=1);

namespace Drupal\ai_schemadotorg_jsonld\Plugin\ConfigAction;

use Drupal\ai_schemadotorg_jsonld\AiSchemaDotOrgJsonLdBuilderInterface;
use Drupal\Core\Config\Action\Attribute\ConfigAction;
use Drupal\Core\Config\Action\ConfigActionPluginInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Adds the Schema.org JSON-LD field to configured bundles.
 */
#[ConfigAction(
  id: 'addField',
  admin_label: new TranslatableMarkup('Add Schema.org JSON-LD field'),
  entity_types: ['ai_schemadotorg_jsonld.settings'],
)]
class AddField implements ConfigActionPluginInterface, ContainerFactoryPluginInterface {

  /**
   * Constructs an AddField config action.
   *
   * @param \Drupal\ai_schemadotorg_jsonld\AiSchemaDotOrgJsonLdBuilderInterface $builder
   *   The Schema.org JSON-LD builder.
   */
  public function __construct(
    protected readonly AiSchemaDotOrgJsonLdBuilderInterface $builder,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    /** @phpstan-ignore new.static */
    return new static(
      $container->get(AiSchemaDotOrgJsonLdBuilderInterface::class),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function apply(string $configName, mixed $value): void {
    if (!is_array($value) || !isset($value['entity_type']) || !is_string($value['entity_type'])) {
      throw new \InvalidArgumentException('The addField config action requires an entity_type string.');
    }
    if (!isset($value['bundles']) || !is_array($value['bundles']) || $value['bundles'] === []) {
      throw new \InvalidArgumentException('The addField config action requires a non-empty bundles array.');
    }

    $this->builder->addFieldToBundles($value['entity_type'], $value['bundles']);
  }

}
