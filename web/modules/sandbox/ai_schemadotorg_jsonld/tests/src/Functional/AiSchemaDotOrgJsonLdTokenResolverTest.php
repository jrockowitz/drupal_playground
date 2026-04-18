<?php

declare(strict_types=1);

namespace Drupal\Tests\ai_schemadotorg_jsonld\Functional;

use Drupal\media\Entity\Media;
use Drupal\media\Entity\MediaType;
use Drupal\node\Entity\Node;
use Drupal\taxonomy\Entity\Term;
use Drupal\taxonomy\Entity\Vocabulary;
use Drupal\Tests\BrowserTestBase;

/**
 * Tests AiSchemaDotOrgJsonLdTokenResolver.
 *
 * @group ai_schemadotorg_jsonld
 */
class AiSchemaDotOrgJsonLdTokenResolverTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'media',
    'media_test_source',
    'node',
    'token',
    'field',
    'file',
    'text',
    'filter',
    'taxonomy',
    'field_widget_actions',
    'json_field',
    'ai',
    'ai_automators',
    'ai_schemadotorg_jsonld',
  ];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->drupalCreateContentType(['type' => 'page', 'name' => 'Basic page']);
    MediaType::create([
      'id' => 'test',
      'label' => 'Test media',
      'source' => 'test',
    ])->save();
    Vocabulary::create([
      'vid' => 'tags',
      'name' => 'Tags',
    ])->save();
    $this->config('ai_schemadotorg_jsonld.settings')
      ->set('entity_types.media.prompt', 'Prompt')
      ->set('entity_types.media.default_jsonld', '')
      ->set('entity_types.taxonomy_term.prompt', 'Prompt')
      ->set('entity_types.taxonomy_term.default_jsonld', '')
      ->save();
    token_clear_cache();
  }

  /**
   * Tests the [node:ai_schemadotorg_jsonld:content] token.
   */
  public function testContentToken(): void {
    $body_text = 'The quick brown fox jumps over the lazy dog.';
    $node = Node::create([
      'type' => 'page',
      'title' => 'Test page',
      'body' => ['value' => $body_text, 'format' => 'plain_text'],
      'status' => 1,
    ]);
    $node->save();

    /** @var \Drupal\Core\Utility\Token $token_service */
    $token_service = $this->container->get('token');
    $result = $token_service->replace(
      '[node:ai_schemadotorg_jsonld:content]',
      ['node' => $node],
      ['clear' => TRUE]
    );

    // Check that the rendered output contains expected body text.
    $this->assertStringContainsString($body_text, $result);

    // Check that root-relative URLs have been converted to absolute URLs.
    $this->assertStringNotContainsString('href="/', $result);
    $this->assertStringNotContainsString('src="/', $result);

    // Check that rendering was performed as anonymous (no admin markup).
    $this->assertStringNotContainsString('contextual-links', $result);
    $this->assertStringNotContainsString('edit-in-place', $result);

    $term = Term::create([
      'vid' => 'tags',
      'name' => 'Test term',
      'status' => 1,
    ]);
    $term->save();

    $term_result = $token_service->replace(
      '[term:ai_schemadotorg_jsonld:content]',
      ['term' => $term],
      ['clear' => TRUE]
    );

    // Check that taxonomy terms use the term token namespace.
    $this->assertStringContainsString('Test term', $term_result);
  }

  /**
   * Tests the [media:ai_schemadotorg_jsonld:content] token.
   */
  public function testMediaContentToken(): void {
    $media = Media::create([
      'bundle' => 'test',
      'name' => 'Test media item',
      'status' => 1,
    ]);
    $media->save();

    /** @var \Drupal\Core\Utility\Token $token_service */
    $token_service = $this->container->get('token');
    $result = $token_service->replace(
      '[media:ai_schemadotorg_jsonld:content]',
      ['media' => $media],
      ['clear' => TRUE]
    );

    // Check that the rendered output is not empty.
    $this->assertNotSame('', trim($result));

    // Check that root-relative URLs have been converted to absolute URLs.
    $this->assertStringNotContainsString('href=\"/', $result);
    $this->assertStringNotContainsString('src=\"/', $result);

    // Check that rendering was performed as anonymous (no admin markup).
    $this->assertStringNotContainsString('contextual-links', $result);
    $this->assertStringNotContainsString('edit-in-place', $result);
  }

}
