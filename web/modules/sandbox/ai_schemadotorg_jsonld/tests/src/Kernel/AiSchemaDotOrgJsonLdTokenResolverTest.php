<?php

declare(strict_types=1);

namespace Drupal\Tests\ai_schemadotorg_jsonld\Kernel;

use Drupal\ai_schemadotorg_jsonld\AiSchemaDotOrgJsonLdTokenResolver;
use Drupal\Component\Render\FormattableMarkup;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\filter\Entity\FilterFormat;
use Drupal\KernelTests\KernelTestBase;
use Drupal\media\Entity\Media;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use Drupal\taxonomy\Entity\Term;
use Drupal\taxonomy\Entity\Vocabulary;
use Drupal\Tests\media\Traits\MediaTypeCreationTrait;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;

/**
 * Tests AiSchemaDotOrgJsonLdTokenResolver.
 *
 * @group ai_schemadotorg_jsonld
 */
class AiSchemaDotOrgJsonLdTokenResolverTest extends KernelTestBase {

  use MediaTypeCreationTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'field',
    'file',
    'text',
    'filter',
    'node',
    'taxonomy',
    'media',
    'media_test_source',
    'image',
    'token',
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

    $this->installEntitySchema('user');
    $this->installEntitySchema('file');
    $this->installEntitySchema('node');
    $this->installEntitySchema('taxonomy_term');
    $this->installEntitySchema('media');
    $this->installEntitySchema('field_storage_config');
    $this->installEntitySchema('field_config');
    $this->installSchema('node', ['node_access']);
    $this->installSchema('file', ['file_usage']);
    $this->installConfig(['system', 'field', 'filter', 'node', 'taxonomy', 'media', 'image', 'ai_schemadotorg_jsonld']);

    $this->container->get('theme_installer')->install(['stark']);
    $this->config('system.theme')->set('default', 'stark')->save();

    $request = Request::create('https://drupal-playground.ddev.site/token-resolver');
    $request->setSession(new Session(new MockArraySessionStorage()));
    $this->container->get('request_stack')->push($request);

    NodeType::create([
      'type' => 'page',
      'name' => 'Basic page',
    ])->save();

    FieldStorageConfig::create([
      'field_name' => 'body',
      'entity_type' => 'node',
      'type' => 'text_with_summary',
      'translatable' => TRUE,
    ])->save();

    FieldConfig::create([
      'field_storage' => FieldStorageConfig::loadByName('node', 'body'),
      'field_name' => 'body',
      'entity_type' => 'node',
      'bundle' => 'page',
      'label' => 'Body',
      'settings' => [
        'display_summary' => TRUE,
        'allowed_formats' => [],
      ],
    ])->save();

    $this->container->get('entity_display.repository')
      ->getViewDisplay('node', 'page')
      ->setComponent('body', [
        'label' => 'hidden',
        'type' => 'text_default',
      ])
      ->save();

    FilterFormat::create([
      'format' => 'full_html',
      'name' => 'Full HTML',
    ])->save();

    $this->createMediaType('test', [
      'id' => 'test',
      'label' => 'Test media',
    ]);

    Vocabulary::create([
      'vid' => 'tags',
      'name' => 'Tags',
    ])->save();

    $this->config('ai_schemadotorg_jsonld.settings')
      ->set('entity_types.media.default_prompt', 'Prompt')
      ->set('entity_types.media.default_jsonld', '')
      ->set('entity_types.taxonomy_term.default_prompt', 'Prompt')
      ->set('entity_types.taxonomy_term.default_jsonld', '')
      ->save();

    token_clear_cache();
  }

  /**
   * Tests content tokens for node, taxonomy term, and media entities.
   */
  public function testContentToken(): void {
    $body_text = 'The quick brown fox jumps over the lazy dog.';
    $node = Node::create([
      'type' => 'page',
      'title' => 'Test page',
      'body' => [
        'value' => '<p>' . $body_text . '</p><p><a href="/internal-path" onclick="alert(1)">Internal link</a></p><img src="/example.png" alt="Example" /><script>alert("unsafe")</script>',
        'format' => 'full_html',
      ],
      'status' => 1,
    ]);
    $node->save();

    $term = Term::create([
      'vid' => 'tags',
      'name' => 'Test term',
      'status' => 1,
    ]);
    $term->save();

    $media = Media::create([
      'bundle' => 'test',
      'name' => 'Test media item',
      'status' => 1,
      'field_media_test' => 'Media source value',
    ]);
    $media->save();

    /** @var \Drupal\Core\Utility\Token $token_service */
    $token_service = $this->container->get('token');
    $resolver = $this->container->get('ai_schemadotorg_jsonld.token_resolver');
    $method = new \ReflectionMethod(AiSchemaDotOrgJsonLdTokenResolver::class, 'resolve');
    $return_type = $method->getReturnType();
    $this->assertNotNull($return_type);
    $this->assertInstanceOf(\ReflectionNamedType::class, $return_type);
    $this->assertSame(FormattableMarkup::class, $return_type->getName());

    $resolved_node = (string) $resolver->resolve($node);
    $this->assertStringContainsString($body_text, $resolved_node);

    $node_result = $token_service->replace(
      '[node:ai_schemadotorg_jsonld:content]',
      ['node' => $node],
      ['clear' => TRUE]
    );

    // Check that the rendered output contains expected body text.
    $this->assertStringContainsString($body_text, $node_result);

    // Check that root-relative URLs have been converted to absolute URLs.
    $this->assertStringContainsString('https://drupal-playground.ddev.site/internal-path', $node_result);
    $this->assertStringNotContainsString('href="/', $node_result);
    $this->assertStringNotContainsString('src="/', $node_result);
    $this->assertStringNotContainsString('<img', $node_result);

    // Check that unsafe tags and attributes are filtered from the final output.
    $this->assertStringNotContainsString('<script', $node_result);
    $this->assertStringNotContainsString('onclick=', $node_result);

    // Check that rendering was performed as anonymous (no admin markup).
    $this->assertStringNotContainsString('contextual-links', $node_result);
    $this->assertStringNotContainsString('edit-in-place', $node_result);

    $term_result = $token_service->replace(
      '[term:ai_schemadotorg_jsonld:content]',
      ['term' => $term],
      ['clear' => TRUE]
    );

    // Check that taxonomy terms use the term token namespace.
    $this->assertStringContainsString('Test term', $term_result);

    $media_result = $token_service->replace(
      '[media:ai_schemadotorg_jsonld:content]',
      ['media' => $media],
      ['clear' => TRUE]
    );

    // Check that the rendered output is not empty.
    $this->assertNotSame('', trim($media_result));

    // Check that rendering was performed as anonymous (no admin markup).
    $this->assertStringNotContainsString('contextual-links', $media_result);
    $this->assertStringNotContainsString('edit-in-place', $media_result);
  }

}
