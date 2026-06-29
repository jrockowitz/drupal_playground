<?php

namespace Drupal\Tests\term_reference\Kernel;

use Drupal\Core\Session\AnonymousUserSession;
use Drupal\node\Entity\Node;
use Drupal\taxonomy\Entity\Term;
use Drupal\term_reference\TermReferenceAccessInterface;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the term reference access service.
 */
#[Group('term_reference')]
#[RunTestsInSeparateProcesses]
class TermReferenceAccessKernelTest extends TermReferenceManagerKernelBase {

  /**
   * The access service under test.
   */
  protected TermReferenceAccessInterface $access;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installSchema('node', ['node_access']);
    $this->createFixtureFields();
    $this->access = $this->container->get('term_reference.access');
  }

  /**
   * Tests each public access method.
   */
  public function testAccessMethods(): void {
    $term = Term::create([
      'vid' => 'tags',
      'name' => 'Blue',
    ]);
    $term->save();
    $page = Node::create([
      'type' => 'page',
      'title' => 'Page reference',
      'status' => 1,
    ]);
    $page->save();
    $article = Node::create([
      'type' => 'article',
      'title' => 'Article reference',
      'status' => 1,
    ]);
    $article->save();
    $field = $this->container->get('term_reference.discovery')
      ->getField('tags', 'node', 'field_tags');
    $page_only_field = $field;
    unset($page_only_field['bundles']['article']);

    $editor = $this->createUser([
      'access content',
      'edit any article content',
      'edit any page content',
      'edit terms in tags',
    ]);
    $viewer = new AnonymousUserSession();

    // Check that overviewAccess() allows accounts with term and field access.
    $this->assertTrue($this->access->overviewAccess($editor, $term)->isAllowed());
    $this->assertFalse($this->access->overviewAccess($viewer, $term)->isAllowed());

    // Check that routeAccess() allows valid fields and forbids invalid fields.
    $this->assertTrue($this->access->routeAccess($editor, $term, 'node.field_tags')->isAllowed());
    $this->assertTrue($this->access->routeAccess($editor, $term, 'node.field_missing')->isForbidden());

    // Check that fieldAccess() requires term update and field edit access.
    $this->assertTrue($this->access->fieldAccess($editor, $term, $field)->isAllowed());
    $this->assertFalse($this->access->fieldAccess($viewer, $term, $field)->isAllowed());

    // Check that entityCanBeManaged() enforces entity update and field access.
    $this->assertTrue($this->access->entityCanBeManaged($page, $field, $editor));
    $this->assertFalse($this->access->entityCanBeManaged($article, $page_only_field, $editor));
    $this->assertFalse($this->access->entityCanBeManaged($page, [
      'entity_type_id' => 'node',
      'field_name' => 'field_missing',
      'bundles' => [
        'page' => [
          'id' => 'page',
          'label' => 'Basic page',
        ],
      ],
    ], $editor));
  }

  /**
   * Creates field fixtures for access tests.
   */
  protected function createFixtureFields(): void {
    $this->createVocabulary('tags', 'Tags');
    foreach ([
      'article' => 'Article',
      'page' => 'Basic page',
    ] as $bundle => $label) {
      $this->createNodeType($bundle, $label);
    }
    $this->createTaxonomyFieldStorage();
    foreach (['article', 'page'] as $bundle) {
      $this->createTaxonomyField(bundle: $bundle);
    }
  }

}
