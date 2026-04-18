<?php

declare(strict_types=1);

namespace Drupal\Tests\ai_schemadotorg_jsonld\Kernel;

use Drupal\ai_schemadotorg_jsonld\AiSchemaDotOrgJsonLdManagerInterface;
use Drupal\KernelTests\KernelTestBase;

/**
 * Tests AiSchemaDotOrgJsonLdManager.
 *
 * @group ai_schemadotorg_jsonld
 */
class AiSchemaDotOrgJsonLdManagerTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'shortcut',
    'node',
    'media',
    'field',
    'file',
    'options',
    'token',
    'taxonomy',
    'block_content',
    'field_widget_actions',
    'json_field',
    'ai',
    'ai_automators',
    'ai_schemadotorg_jsonld',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installConfig(['system', 'node', 'media', 'block_content', 'taxonomy', 'ai_schemadotorg_jsonld']);
  }

  /**
   * Tests addEntityTypes adds sorted default settings for new entity types.
   */
  public function testAddEntityTypes(): void {
    /** @var \Drupal\ai_schemadotorg_jsonld\AiSchemaDotOrgJsonLdManagerInterface $manager */
    $manager = $this->container->get(AiSchemaDotOrgJsonLdManagerInterface::class);

    $entity_type_settings = $this->config('ai_schemadotorg_jsonld.settings')->get('entity_types');

    // Check that only node is seeded during installation.
    $this->assertSame(['node'], array_keys($entity_type_settings));

    $manager->addEntityTypes(['taxonomy_term', 'block_content', 'media']);

    $entity_type_settings = $this->config('ai_schemadotorg_jsonld.settings')->get('entity_types');

    // Check that taxonomy term settings were added.
    $this->assertArrayHasKey('taxonomy_term', $entity_type_settings);

    // Check that block content settings were added.
    $this->assertArrayHasKey('block_content', $entity_type_settings);

    // Check that media settings were added.
    $this->assertArrayHasKey('media', $entity_type_settings);

    // Check that entity type settings are sorted by key.
    $this->assertSame(['block_content', 'media', 'node', 'taxonomy_term'], array_keys($entity_type_settings));

    // Check that taxonomy terms use the term token namespace.
    $this->assertStringContainsString('[term:url]', $entity_type_settings['taxonomy_term']['prompt']);
    $this->assertStringContainsString('[term:name]', $entity_type_settings['taxonomy_term']['prompt']);

    // Check that block content uses the expected token namespace.
    $this->assertStringContainsString('[block_content:url]', $entity_type_settings['block_content']['prompt']);

    // Check that media uses the expected token namespace.
    $this->assertStringContainsString('[media:url]', $entity_type_settings['media']['prompt']);

    // Check that newly added entity types default to empty JSON-LD.
    $this->assertSame('', $entity_type_settings['taxonomy_term']['default_jsonld']);
    $this->assertSame('', $entity_type_settings['block_content']['default_jsonld']);
    $this->assertSame('', $entity_type_settings['media']['default_jsonld']);

    // Check that unsupported canonical entity types are excluded.
    $supported_entity_types = $manager->getSupportedEntityTypes();
    $this->assertArrayNotHasKey('shortcut', $supported_entity_types);
  }

}
