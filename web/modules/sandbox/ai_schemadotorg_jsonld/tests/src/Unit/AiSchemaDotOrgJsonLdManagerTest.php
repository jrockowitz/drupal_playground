<?php

declare(strict_types=1);

namespace Drupal\Tests\ai_schemadotorg_jsonld\Unit;

use Drupal\ai_schemadotorg_jsonld\AiSchemaDotOrgJsonLdBuilderInterface;
use Drupal\ai_schemadotorg_jsonld\AiSchemaDotOrgJsonLdManager;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\ContentEntityTypeInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Tests\UnitTestCase;
use Drupal\token\TokenEntityMapperInterface;

/**
 * Tests AiSchemaDotOrgJsonLdManager::buildDefaultPrompt() fallback generation.
 *
 * @group ai_schemadotorg_jsonld
 */
class AiSchemaDotOrgJsonLdManagerTest extends UnitTestCase {

  /**
   * Returns a manager subclass whose getPromptFilePath returns a nonexistent path.
   *
   * BuildDefaultPrompt is exposed as public so tests can call it directly.
   */
  protected function createManager(TokenEntityMapperInterface $token_entity_mapper): object {
    $config_factory = $this->createMock(ConfigFactoryInterface::class);
    $entity_type_manager = $this->createMock(EntityTypeManagerInterface::class);

    return new class($config_factory, $entity_type_manager, $token_entity_mapper) extends AiSchemaDotOrgJsonLdManager {

      /**
       * {@inheritdoc}
       */
      public function buildDefaultPrompt(string $entity_type_id, ContentEntityTypeInterface $entity_type): string {
        return parent::buildDefaultPrompt($entity_type_id, $entity_type);
      }

      /**
       * {@inheritdoc}
       */
      protected function getPromptFilePath(string $entity_type_id): string {
        return '/nonexistent/ai_schemadotorg_jsonld.' . $entity_type_id . '.prompt.txt';
      }

    };
  }

  /**
   * Returns a mock ContentEntityTypeInterface with the given keys and label.
   */
  protected function createEntityTypeMock(string $label, string $bundle_key, string $label_key): ContentEntityTypeInterface {
    $entity_type = $this->createMock(ContentEntityTypeInterface::class);
    $entity_type->method('getLabel')->willReturn($label);
    $entity_type->method('getKey')->willReturnMap([
      ['bundle', $bundle_key],
      ['label', $label_key],
    ]);
    return $entity_type;
  }

  /**
   * Tests that the fallback prompt contains the expected tokens for each entity type.
   */
  public function testBuildDefaultPromptFallback(): void {
    $token_entity_mapper = $this->createMock(TokenEntityMapperInterface::class);
    $token_entity_mapper->method('getTokenTypeForEntityType')->willReturnMap([
      ['block_content', TRUE, 'block_content'],
      ['media', TRUE, 'media'],
      ['node', TRUE, 'node'],
      ['taxonomy_term', TRUE, 'term'],
      ['user', TRUE, 'user'],
    ]);

    $manager = $this->createManager($token_entity_mapper);
    $field_name = AiSchemaDotOrgJsonLdBuilderInterface::FIELD_NAME;

    // Check that the node fallback prompt includes content-type, url, and title tokens.
    $node_entity_type = $this->createEntityTypeMock('Content', 'type', 'title');
    $node_prompt = $manager->buildDefaultPrompt('node', $node_entity_type);
    $this->assertStringContainsString('Type: [node:content-type]', $node_prompt);
    $this->assertStringContainsString('URL: [node:url]', $node_prompt);
    $this->assertStringContainsString('Title: [node:title]', $node_prompt);
    $this->assertStringContainsString('[node:' . $field_name . ']', $node_prompt);

    // Check that the taxonomy_term fallback prompt includes vocabulary:name and name tokens.
    $term_entity_type = $this->createEntityTypeMock('Taxonomy term', 'vid', 'name');
    $term_prompt = $manager->buildDefaultPrompt('taxonomy_term', $term_entity_type);
    $this->assertStringContainsString('Type: [term:vocabulary:name]', $term_prompt);
    $this->assertStringContainsString('URL: [term:url]', $term_prompt);
    $this->assertStringContainsString('Title: [term:name]', $term_prompt);
    $this->assertStringContainsString('[term:' . $field_name . ']', $term_prompt);

    // Check that the user fallback prompt has no Type line (no bundle) but includes url and name tokens.
    $user_entity_type = $this->createEntityTypeMock('User', '', 'name');
    $user_prompt = $manager->buildDefaultPrompt('user', $user_entity_type);
    $this->assertStringNotContainsString('Type:', $user_prompt);
    $this->assertStringContainsString('URL: [user:url]', $user_prompt);
    $this->assertStringContainsString('Title: [user:name]', $user_prompt);
    $this->assertStringContainsString('[user:' . $field_name . ']', $user_prompt);

    // Check that the media fallback prompt includes bundle, url, and name tokens.
    $media_entity_type = $this->createEntityTypeMock('Media', 'bundle', 'name');
    $media_prompt = $manager->buildDefaultPrompt('media', $media_entity_type);
    $this->assertStringContainsString('Type: [media:bundle]', $media_prompt);
    $this->assertStringContainsString('URL: [media:url]', $media_prompt);
    $this->assertStringContainsString('Title: [media:name]', $media_prompt);
    $this->assertStringContainsString('[media:' . $field_name . ']', $media_prompt);

    // Check that the block_content fallback prompt includes type, url, and info tokens.
    $block_content_entity_type = $this->createEntityTypeMock('Custom block', 'type', 'info');
    $block_content_prompt = $manager->buildDefaultPrompt('block_content', $block_content_entity_type);
    $this->assertStringContainsString('Type: [block_content:type]', $block_content_prompt);
    $this->assertStringContainsString('URL: [block_content:url]', $block_content_prompt);
    $this->assertStringContainsString('Title: [block_content:info]', $block_content_prompt);
    $this->assertStringContainsString('[block_content:' . $field_name . ']', $block_content_prompt);
  }

}
