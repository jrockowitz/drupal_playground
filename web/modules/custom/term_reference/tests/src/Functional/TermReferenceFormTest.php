<?php

namespace Drupal\Tests\term_reference\Functional;

use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\node\Entity\NodeType;
use Drupal\taxonomy\Entity\Vocabulary;
use Drupal\Tests\BrowserTestBase;
use Drupal\Tests\media\Traits\MediaTypeCreationTrait;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests taxonomy term reference management.
 */
#[Group('term_reference')]
#[RunTestsInSeparateProcesses]
class TermReferenceFormTest extends BrowserTestBase {

  use MediaTypeCreationTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'block',
    'field',
    'media',
    'node',
    'taxonomy',
    'term_reference',
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

    $this->drupalPlaceBlock('local_tasks_block');

    Vocabulary::create([
      'vid' => 'tags',
      'name' => 'Tags',
    ])->save();

    NodeType::create([
      'type' => 'page',
      'name' => 'Basic page',
    ])->save();
    NodeType::create([
      'type' => 'article',
      'name' => 'Article',
    ])->save();
    $this->createMediaType('image', [
      'id' => 'image',
      'label' => 'Image',
    ]);

    FieldStorageConfig::create([
      'field_name' => 'field_tags',
      'entity_type' => 'node',
      'type' => 'entity_reference',
      'cardinality' => -1,
      'settings' => [
        'target_type' => 'taxonomy_term',
      ],
    ])->save();
    foreach (['article', 'page'] as $bundle) {
      FieldConfig::create([
        'field_name' => 'field_tags',
        'entity_type' => 'node',
        'bundle' => $bundle,
        'label' => 'Tags',
        'settings' => [
          'handler' => 'default:taxonomy_term',
          'handler_settings' => [
            'target_bundles' => [
              'tags' => 'tags',
            ],
          ],
        ],
      ])->save();
    }

    FieldStorageConfig::create([
      'field_name' => 'field_tags',
      'entity_type' => 'media',
      'type' => 'entity_reference',
      'cardinality' => -1,
      'settings' => [
        'target_type' => 'taxonomy_term',
      ],
    ])->save();
    FieldConfig::create([
      'field_name' => 'field_tags',
      'entity_type' => 'media',
      'bundle' => 'image',
      'label' => 'Tags',
      'settings' => [
        'handler' => 'default:taxonomy_term',
        'handler_settings' => [
          'target_bundles' => [
            'tags' => 'tags',
          ],
        ],
      ],
    ])->save();

    // Rebuild so the web server process sees field-derived local tasks.
    $this->rebuildAll();
    $this->container->get('plugin.manager.menu.local_task')->clearCachedDefinitions();
  }

  /**
   * Tests discovering, adding, listing, and removing term references.
   */
  public function testTermReferenceManagement(): void {
    $term = $this->container->get('entity_type.manager')
      ->getStorage('taxonomy_term')
      ->create([
        'vid' => 'tags',
        'name' => 'Blue',
      ]);
    $term->save();

    $fields = $this->container->get('Drupal\term_reference\TermReferenceDiscoveryInterface')
      ->getFieldsForVocabulary('tags');

    // Check that content and media fields are discovered.
    $this->assertArrayHasKey('node.field_tags', $fields);
    $this->assertArrayHasKey('media.field_tags', $fields);
    $this->assertSame(['article', 'page'], array_keys($fields['node.field_tags']['bundles']));
    $this->assertSame('Tags', $fields['node.field_tags']['field_label']);
    $this->assertSame('Content', $fields['node.field_tags']['entity_type_label_plural']);

    $account = $this->drupalCreateUser([
      'access content',
      'access media overview',
      'administer taxonomy',
      'create article content',
      'create media',
      'create page content',
      'edit any article content',
      'edit any image media',
      'edit any page content',
      'edit terms in tags',
      'view media',
    ]);
    $this->drupalLogin($account);

    $node_storage = $this->container->get('entity_type.manager')->getStorage('node');
    $page = $node_storage->create([
      'type' => 'page',
      'title' => 'Page reference',
      'status' => 1,
    ]);
    $page->save();
    $article = $node_storage->create([
      'type' => 'article',
      'title' => 'Article reference',
      'status' => 1,
    ]);
    $article->save();

    $media_storage = $this->container->get('entity_type.manager')->getStorage('media');
    $media = $media_storage->create([
      'bundle' => 'image',
      'name' => 'Image reference',
      'status' => 1,
    ]);
    $media->save();

    $access = $this->container->get('Drupal\term_reference\Access\TermReferenceAccessCheck')
      ->overviewAccess($account, $term);
    $this->assertTrue($access->isAllowed());
    $local_tasks = $this->container->get('plugin.manager.menu.local_task')->getDefinitions();
    $this->assertArrayHasKey('term_reference.reference_tasks:node.field_tags', $local_tasks);
    $this->assertStringNotContainsString(
      'weight:',
      file_get_contents(DRUPAL_ROOT . '/modules/custom/term_reference/term_reference.links.task.yml')
    );

    $this->drupalGet($term->toUrl());
    $this->assertSession()->statusCodeEquals(200);

    // Check that the References task and generated secondary tasks are visible.
    $this->assertSession()->linkExists('References');
    $this->clickLink('References');
    $this->assertSession()->linkExists('Tags (Content)');
    $this->assertSession()->linkExists('Tags (Media)');

    $this->clickLink('Tags (Content)');
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->addressEquals('/taxonomy/term/' . $term->id() . '/references/node.field_tags');
    $this->assertSession()->titleEquals('Blue | Drupal');
    $this->assertSession()->pageTextNotContains('Field summary');
    $this->assertSession()->pageTextContains('Add Content references to Blue');
    $this->assertSession()->elementExists('css', 'fieldset legend:contains("Add Content references to Blue")');
    $this->assertSession()->pageTextContains('Enter one or more existing Content entities. Eligible bundles: Article, Basic page.');

    $this->submitForm([
      'entities' => 'Page reference (' . $page->id() . '), Article reference (' . $article->id() . ')',
    ], 'Add');

    // Check that both selected entities now reference the term.
    $node_storage->resetCache([$page->id(), $article->id()]);
    $page = $node_storage->load($page->id());
    $article = $node_storage->load($article->id());
    $this->assertSame((string) $term->id(), $page->get('field_tags')->target_id);
    $this->assertSame((string) $term->id(), $article->get('field_tags')->target_id);
    $this->assertSession()->pageTextContains('Page reference');
    $this->assertSession()->pageTextContains((string) $page->id());
    $this->assertSession()->pageTextContains('Article reference');
    $this->assertSession()->pageTextContains((string) $article->id());
    $this->assertSession()->pageTextContains('Basic page');
    $this->assertSession()->pageTextContains('Published');
    $this->assertSession()->linkExists('View');
    $this->assertSession()->linkExists('Edit');

    $this->submitForm([
      'references[' . $page->id() . '][remove]' => TRUE,
    ], 'Remove');

    // Check that removing clears only the selected term.
    $node_storage->resetCache([$page->id()]);
    $page = $node_storage->load($page->id());
    $this->assertTrue($page->get('field_tags')->isEmpty());
    $node_storage->resetCache([$article->id()]);
    $article = $node_storage->load($article->id());

    // Check that removing one selected reference leaves other references intact.
    $this->assertSame((string) $term->id(), $article->get('field_tags')->target_id);

    $this->drupalGet('/taxonomy/term/' . $term->id() . '/references/media.field_tags');
    $this->assertSession()->titleEquals('Blue | Drupal');
    $this->submitForm([
      'entities' => 'Image reference (' . $media->id() . ')',
    ], 'Add');

    // Check that media references are managed separately.
    $media_storage->resetCache([$media->id()]);
    $media = $media_storage->load($media->id());
    $this->assertSame((string) $term->id(), $media->get('field_tags')->target_id);

    $limited_account = $this->drupalCreateUser([
      'access content',
    ]);
    $this->drupalLogin($limited_account);
    $this->drupalGet('/taxonomy/term/' . $term->id() . '/references/node.field_tags');

    // Check that users without management access cannot use the route.
    $this->assertSession()->statusCodeEquals(403);
  }

}
