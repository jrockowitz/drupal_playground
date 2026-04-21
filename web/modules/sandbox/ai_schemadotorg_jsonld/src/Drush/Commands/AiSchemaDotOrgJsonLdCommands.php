<?php

declare(strict_types=1);

namespace Drupal\ai_schemadotorg_jsonld\Drush\Commands;

use Drupal\ai_schemadotorg_jsonld\AiSchemaDotOrgJsonLdBuilderInterface;
use Drush\Attributes as CLI;
use Drush\Commands\AutowireTrait;
use Drush\Commands\DrushCommands;

/**
 * Drush commands for AI Schema.org JSON-LD.
 */
class AiSchemaDotOrgJsonLdCommands extends DrushCommands {

  use AutowireTrait;

  /**
   * Constructs an AiSchemaDotOrgJsonLdCommands object.
   *
   * @param \Drupal\ai_schemadotorg_jsonld\AiSchemaDotOrgJsonLdBuilderInterface $builder
   *   The Schema.org JSON-LD builder.
   */
  public function __construct(
    protected readonly AiSchemaDotOrgJsonLdBuilderInterface $builder,
  ) {
    parent::__construct();
  }

  /**
   * Adds the Schema.org JSON-LD field to an entity bundle.
   */
  #[CLI\Command(name: 'ai_schemadotorg_jsonld:add-field')]
  #[CLI\Argument(name: 'entity_type', description: 'The content entity type ID.')]
  #[CLI\Argument(name: 'bundle', description: 'The bundle ID or * for all current bundles. Omit for entity types without bundles.')]
  #[CLI\Usage(name: 'drush ai_schemadotorg_jsonld:add-field node page', description: 'Adds the Schema.org JSON-LD field to the page node bundle.')]
  #[CLI\Usage(name: 'drush ai_schemadotorg_jsonld:add-field node *', description: 'Adds the Schema.org JSON-LD field to all current node bundles.')]
  #[CLI\Usage(name: 'drush ai_schemadotorg_jsonld:add-field user', description: 'Adds the Schema.org JSON-LD field to the user entity type (no bundle required).')]
  public function addField(string $entity_type, string $bundle = ''): void {
    $bundles = ($bundle === '') ? ['*'] : [$bundle];
    try {
      $this->builder->addFieldToBundles($entity_type, $bundles);
    }
    catch (\Throwable $throwable) {
      throw new \RuntimeException($throwable->getMessage(), 0, $throwable);
    }

    $bundle_label = ($bundle === '') ? '*' : $bundle;
    $this->logger()->success('Added Schema.org JSON-LD field to ' . $entity_type . '.' . $bundle_label . '.');
  }

}
