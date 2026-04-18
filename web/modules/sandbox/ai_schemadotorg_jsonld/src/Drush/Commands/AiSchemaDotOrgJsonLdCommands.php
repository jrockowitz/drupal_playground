<?php

declare(strict_types=1);

namespace Drupal\ai_schemadotorg_jsonld\Drush\Commands;

use Drupal\ai_schemadotorg_jsonld\AiSchemaDotOrgJsonLdBuilderInterface;
use Drupal\ai_schemadotorg_jsonld\AiSchemaDotOrgJsonLdManagerInterface;
use Drupal\Core\Entity\ContentEntityTypeInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
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
   * @param \Drupal\ai_schemadotorg_jsonld\AiSchemaDotOrgJsonLdManagerInterface $manager
   *   The Schema.org JSON-LD manager.
   * @param \Drupal\ai_schemadotorg_jsonld\AiSchemaDotOrgJsonLdBuilderInterface $builder
   *   The Schema.org JSON-LD builder.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   */
  public function __construct(
    protected readonly AiSchemaDotOrgJsonLdManagerInterface $manager,
    protected readonly AiSchemaDotOrgJsonLdBuilderInterface $builder,
    protected readonly EntityTypeManagerInterface $entityTypeManager,
  ) {
    parent::__construct();
  }

  /**
   * Adds the Schema.org JSON-LD field to an entity bundle.
   */
  #[CLI\Command(name: 'ai_schemadotorg_jsonld:add-field')]
  #[CLI\Argument(name: 'entity_type', description: 'The content entity type ID.')]
  #[CLI\Argument(name: 'bundle', description: 'The bundle ID, or the synthetic bundle equal to the entity type ID for non-bundle entities.')]
  #[CLI\Usage(name: 'drush ai_schemadotorg_jsonld:add-field node page', description: 'Adds the Schema.org JSON-LD field to the page node bundle.')]
  #[CLI\Usage(name: 'drush ai_schemadotorg_jsonld:add-field user user', description: 'Adds the Schema.org JSON-LD field to the synthetic user bundle.')]
  public function addField(string $entity_type, string $bundle): void {
    $entity_type_definition = $this->getSupportedEntityTypeDefinition($entity_type);
    $this->validateBundle($entity_type_definition, $bundle);

    $this->manager->addEntityTypes([$entity_type]);
    $this->builder->addFieldToEntity($entity_type, $bundle);

    $this->logger()->success('Added Schema.org JSON-LD field to ' . $entity_type . '.' . $bundle . '.');
  }

  /**
   * Returns a supported content entity type definition.
   *
   * @param string $entity_type
   *   The content entity type ID.
   */
  protected function getSupportedEntityTypeDefinition(string $entity_type): ContentEntityTypeInterface {
    $supported_entity_types = $this->manager->getSupportedEntityTypes();
    $entity_type_definition = $supported_entity_types[$entity_type] ?? NULL;

    if (!$entity_type_definition instanceof ContentEntityTypeInterface) {
      throw new \InvalidArgumentException('The entity type ' . $entity_type . ' is not supported.');
    }

    return $entity_type_definition;
  }

  /**
   * Validates the provided bundle for the entity type.
   *
   * @param \Drupal\Core\Entity\ContentEntityTypeInterface $entity_type_definition
   *   The content entity type definition.
   * @param string $bundle
   *   The bundle ID.
   */
  protected function validateBundle(ContentEntityTypeInterface $entity_type_definition, string $bundle): void {
    $entity_type_id = $entity_type_definition->id();
    $bundle_entity_type_id = $entity_type_definition->getBundleEntityType();

    if (!$bundle_entity_type_id) {
      if ($bundle !== $entity_type_id) {
        throw new \InvalidArgumentException('The non-bundle entity type ' . $entity_type_id . ' requires the synthetic bundle ' . $entity_type_id . '.');
      }
      return;
    }

    $bundle_entity = $this->entityTypeManager
      ->getStorage($bundle_entity_type_id)
      ->load($bundle);

    if (!$bundle_entity) {
      throw new \InvalidArgumentException('The bundle ' . $bundle . ' does not exist for the entity type ' . $entity_type_id . '.');
    }
  }

}
