<?php

declare(strict_types=1);

namespace Drupal\Tests\ai_schemadotorg_jsonld\Kernel;

use Drupal\ai_schemadotorg_jsonld\AiSchemaDotOrgJsonLdBuilderInterface;
use Drupal\ai_schemadotorg_jsonld\AiSchemaDotOrgJsonLdManagerInterface;
use Drupal\field\Entity\FieldStorageConfig;

/**
 * Tests AiSchemaDotOrgJsonLdManager.
 *
 * @group ai_schemadotorg_jsonld
 */
class AiSchemaDotOrgJsonLdManagerTest extends AiSchemaDotOrgJsonLdTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'media',
  ];

  /**
   * Tests entity type management behavior for node and media.
   */
  public function testEntityTypeManagement(): void {
    /** @var \Drupal\ai_schemadotorg_jsonld\AiSchemaDotOrgJsonLdManagerInterface $manager */
    $manager = $this->container->get(AiSchemaDotOrgJsonLdManagerInterface::class);

    // Check that node and media are supported entity types.
    $supported_entity_types = $manager->getSupportedEntityTypes();
    $this->assertArrayHasKey('node', $supported_entity_types);
    $this->assertArrayHasKey('media', $supported_entity_types);

    // Check that unchecked entity types without field storage are removed.
    $manager->addEntityTypes(['media']);
    $manager->syncEntityTypes(['node']);

    $entity_type_settings = $this->config('ai_schemadotorg_jsonld.settings')->get('entity_types');

    $this->assertSame(['node'], array_keys($entity_type_settings));

    // Check that unchecked entity types with field storage are retained.
    $manager->addEntityTypes(['media']);

    FieldStorageConfig::create([
      'field_name' => AiSchemaDotOrgJsonLdBuilderInterface::FIELD_NAME,
      'entity_type' => 'media',
      'type' => 'json_native',
      'cardinality' => 1,
      'translatable' => TRUE,
    ])->save();

    $manager->syncEntityTypes(['node']);

    $entity_type_settings = $this->config('ai_schemadotorg_jsonld.settings')->get('entity_types');

    $this->assertSame(['media', 'node'], array_keys($entity_type_settings));

    // Check that only node is seeded during installation after a config reset.
    $this->container->get('config.storage')->delete('ai_schemadotorg_jsonld.settings');
    $this->installConfig(['ai_schemadotorg_jsonld']);

    $entity_type_settings = $this->config('ai_schemadotorg_jsonld.settings')->get('entity_types');

    $this->assertSame(['node'], array_keys($entity_type_settings));

    // Check that existing settings are preserved when addEntityTypes runs.
    $this->config('ai_schemadotorg_jsonld.settings')
      ->set('entity_types.node.default_prompt', 'Custom node prompt')
      ->set('entity_types.node.default_jsonld', '{"@type":"WebPage"}')
      ->save();

    $manager->addEntityTypes(['media']);

    $entity_type_settings = $this->config('ai_schemadotorg_jsonld.settings')->get('entity_types');

    // Check that media settings were added.
    $this->assertArrayHasKey('media', $entity_type_settings);

    // Check that entity type settings are sorted by key.
    $this->assertSame(['media', 'node'], array_keys($entity_type_settings));

    // Check that media uses the expected token namespace.
    $this->assertStringContainsString('[media:url]', $entity_type_settings['media']['default_prompt']);

    // Check that newly added entity types default to empty JSON-LD.
    $this->assertSame('', $entity_type_settings['media']['default_jsonld']);

    $manager->addEntityTypes(['node', 'media']);

    $entity_type_settings = $this->config('ai_schemadotorg_jsonld.settings')->get('entity_types');

    $this->assertSame('Custom node prompt', $entity_type_settings['node']['default_prompt']);
    $this->assertSame('{"@type":"WebPage"}', $entity_type_settings['node']['default_jsonld']);
    $this->assertArrayHasKey('media', $entity_type_settings);
  }

}
