<?php

namespace Drupal\Tests\term_reference\FunctionalJavascript;

use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\FunctionalJavascriptTests\WebDriverTestBase;
use Drupal\node\Entity\NodeType;
use Drupal\taxonomy\Entity\Vocabulary;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests AJAX term reference management.
 */
#[Group('term_reference')]
#[RunTestsInSeparateProcesses]
class TermReferenceFormAjaxTest extends WebDriverTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'field',
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

    // Rebuild so the web server process sees field-derived local tasks.
    $this->rebuildAll();
    $this->container->get('plugin.manager.menu.local_task')->clearCachedDefinitions();
  }

  /**
   * Tests adding and removing references with AJAX.
   */
  public function testAjaxReferenceManagement(): void {
    $term = $this->container->get('entity_type.manager')
      ->getStorage('taxonomy_term')
      ->create([
        'vid' => 'tags',
        'name' => 'Blue',
      ]);
    $term->save();

    $account = $this->drupalCreateUser([
      'access content',
      'administer taxonomy',
      'create article content',
      'create page content',
      'edit any article content',
      'edit any page content',
      'edit terms in tags',
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

    $this->drupalGet('/taxonomy/term/' . $term->id() . '/references/node.field_tags');
    $page_session = $this->getSession()->getPage();
    $assert_session = $this->assertSession();
    $assert_session->pageTextContains('Add Content references to Blue');
    $assert_session->elementExists('css', '#term-reference-form-wrapper');
    $assert_session->pageTextContains('No references are available.');

    $page_session->fillField('entities', 'Page reference (' . $page->id() . '), Article reference (' . $article->id() . ')');
    $page_session->pressButton('Add');
    $assert_session->assertWaitOnAjaxRequest();

    // Check that adding references refreshes the table without leaving the route.
    $assert_session->addressEquals('/taxonomy/term/' . $term->id() . '/references/node.field_tags');
    $this->assertNotEmpty($assert_session->waitForElement('css', '#term-reference-form-wrapper [data-drupal-messages]'));
    $assert_session->pageTextContains('2 entities now reference Blue.');
    $assert_session->pageTextContains('Page reference');
    $assert_session->pageTextContains('Article reference');
    $this->assertJsCondition('document.activeElement === document.querySelector("[data-drupal-selector=\'edit-entities\']")');

    $node_storage->resetCache([$page->id(), $article->id()]);
    $page = $node_storage->load($page->id());
    $article = $node_storage->load($article->id());
    $this->assertSame((string) $term->id(), $page->get('field_tags')->target_id);
    $this->assertSame((string) $term->id(), $article->get('field_tags')->target_id);

    $page_session->checkField('references[' . $page->id() . '][remove]');
    $page_session->pressButton('Remove');
    $assert_session->assertWaitOnAjaxRequest();

    // Check that removing references refreshes the table and focus target.
    $assert_session->pageTextContains('Removed 1 reference from Blue.');
    $this->assertTrue($assert_session->waitForElementRemoved('xpath', '//*[contains(text(), "Page reference")]'));
    $assert_session->pageTextContains('Article reference');
    $this->assertJsCondition('document.activeElement === document.querySelector("#term-reference-existing")');

    $node_storage->resetCache([$page->id(), $article->id()]);
    $page = $node_storage->load($page->id());
    $article = $node_storage->load($article->id());
    $this->assertTrue($page->get('field_tags')->isEmpty());
    $this->assertSame((string) $term->id(), $article->get('field_tags')->target_id);
  }

}
