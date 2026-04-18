<?php

declare(strict_types=1);

namespace Drupal\Tests\ai_schemadotorg_jsonld\Kernel;

use Drupal\ai_schemadotorg_jsonld\AiSchemaDotOrgJsonLdBuilderInterface;
use Drupal\ai_schemadotorg_jsonld\AiSchemaDotOrgJsonLdManagerInterface;
use Drupal\field\Entity\FieldStorageConfig;
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
    $this->installEntitySchema('field_storage_config');
    $this->installConfig(['system', 'node', 'media', 'block_content', 'taxonomy', 'ai_schemadotorg_jsonld']);
  }

  /**
   * Tests entity type management behavior.
   */
  public function testEntityTypeManagement(): void {
    /** @var \Drupal\ai_schemadotorg_jsonld\AiSchemaDotOrgJsonLdManagerInterface $manager */
    $manager = $this->container->get(AiSchemaDotOrgJsonLdManagerInterface::class);

    // Check that unsupported canonical entity types are excluded.
    $supported_entity_types = $manager->getSupportedEntityTypes();
    $this->assertArrayNotHasKey('shortcut', $supported_entity_types);
    $this->assertArrayHasKey('user', $supported_entity_types);

    // Check that unchecked entity types without field storage are removed.
    $manager->addEntityTypes(['media', 'taxonomy_term', 'user']);
    $manager->syncEntityTypes(['node']);

    $entity_type_settings = $this->config('ai_schemadotorg_jsonld.settings')->get('entity_types');

    $this->assertSame(['node'], array_keys($entity_type_settings));

    // Check that unchecked entity types with field storage are retained.
    $manager->addEntityTypes(['media', 'taxonomy_term', 'user']);

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
    $this->assertArrayNotHasKey('taxonomy_term', $entity_type_settings);
    $this->assertArrayNotHasKey('user', $entity_type_settings);

    // Check that node stays first and remaining synced entity types are sorted.
    $manager->addEntityTypes(['block_content', 'taxonomy_term', 'user']);
    $manager->syncEntityTypes(['node', 'taxonomy_term', 'block_content', 'media', 'user']);

    $entity_type_settings = $this->config('ai_schemadotorg_jsonld.settings')->get('entity_types');

    $this->assertSame(['block_content', 'media', 'node', 'taxonomy_term', 'user'], array_keys($entity_type_settings));

    // Check that only node is seeded during installation after a config reset.
    $this->container->get('config.storage')->delete('ai_schemadotorg_jsonld.settings');
    $this->installConfig(['ai_schemadotorg_jsonld']);

    $entity_type_settings = $this->config('ai_schemadotorg_jsonld.settings')->get('entity_types');

    $this->assertSame(['node'], array_keys($entity_type_settings));

    // Check that existing settings are preserved when addEntityTypes runs.
    $this->config('ai_schemadotorg_jsonld.settings')
      ->set('entity_types.node.prompt', 'Custom node prompt')
      ->set('entity_types.node.default_jsonld', '{"@type":"WebPage"}')
      ->save();

    $manager->addEntityTypes(['taxonomy_term', 'block_content', 'media', 'user']);

    $entity_type_settings = $this->config('ai_schemadotorg_jsonld.settings')->get('entity_types');

    // Check that taxonomy term settings were added.
    $this->assertArrayHasKey('taxonomy_term', $entity_type_settings);

    // Check that block content settings were added.
    $this->assertArrayHasKey('block_content', $entity_type_settings);

    // Check that media settings were added.
    $this->assertArrayHasKey('media', $entity_type_settings);

    // Check that user settings were added.
    $this->assertArrayHasKey('user', $entity_type_settings);

    // Check that entity type settings are sorted by key.
    $this->assertSame(['block_content', 'media', 'node', 'taxonomy_term', 'user'], array_keys($entity_type_settings));

    // Check that taxonomy terms use the term token namespace.
    $this->assertStringContainsString('[term:url]', $entity_type_settings['taxonomy_term']['prompt']);
    $this->assertStringContainsString('[term:name]', $entity_type_settings['taxonomy_term']['prompt']);

    // Check that block content uses the expected token namespace.
    $this->assertStringContainsString('[block_content:url]', $entity_type_settings['block_content']['prompt']);

    // Check that media uses the expected token namespace.
    $this->assertStringContainsString('[media:url]', $entity_type_settings['media']['prompt']);

    // Check that user uses the expected token namespace.
    $this->assertStringContainsString('[user:url]', $entity_type_settings['user']['prompt']);

    // Check that newly added entity types default to empty JSON-LD.
    $this->assertSame('', $entity_type_settings['taxonomy_term']['default_jsonld']);
    $this->assertSame('', $entity_type_settings['block_content']['default_jsonld']);
    $this->assertSame('', $entity_type_settings['media']['default_jsonld']);
    $this->assertSame('', $entity_type_settings['user']['default_jsonld']);

    $manager->addEntityTypes(['node', 'media']);

    $entity_type_settings = $this->config('ai_schemadotorg_jsonld.settings')->get('entity_types');

    $this->assertSame('Custom node prompt', $entity_type_settings['node']['prompt']);
    $this->assertSame('{"@type":"WebPage"}', $entity_type_settings['node']['default_jsonld']);
    $this->assertArrayHasKey('media', $entity_type_settings);

    // Check that a file-based prompt is used instead of the generated default.
    $app_root = $this->container->getParameter('app.root');
    $module_relative_path = $this->container->get('extension.path.resolver')->getPath('module', 'ai_schemadotorg_jsonld');
    $prompt_file = $app_root . '/' . $module_relative_path . '/prompts/entity_types/ai_schemadotorg_jsonld.user.prompt.txt';

    $this->assertFileExists($prompt_file);

    $this->container->get('config.storage')->delete('ai_schemadotorg_jsonld.settings');
    $this->installConfig(['ai_schemadotorg_jsonld']);
    $manager->addEntityTypes(['user']);

    $entity_type_settings = $this->config('ai_schemadotorg_jsonld.settings')->get('entity_types');

    // Check that the file content is used as the user prompt.
    $this->assertSame(trim((string) file_get_contents($prompt_file)), $entity_type_settings['user']['prompt']);
  }

}
