<?php

namespace Drupal\Tests\term_reference\Kernel;

use Drupal\node\Entity\Node;
use Drupal\taxonomy\Entity\Term;
use Drupal\term_reference\TermReferenceManagerInterface;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the term reference manager service.
 */
#[Group('term_reference')]
#[RunTestsInSeparateProcesses]
class TermReferenceManagerKernelTest extends TermReferenceManagerKernelBase {

  /**
   * The manager service under test.
   */
  protected TermReferenceManagerInterface $manager;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installSchema('node', ['node_access']);
    $this->createFixtureFields();
    $this->setCurrentUser($this->createUser(['access content']));
    $this->manager = $this->container->get('term_reference.manager');
  }

  /**
   * Tests each public manager method.
   */
  public function testManagerMethods(): void {
    $term = Term::create([
      'vid' => 'tags',
      'name' => 'Blue',
    ]);
    $term->save();
    $other_term = Term::create([
      'vid' => 'tags',
      'name' => 'Green',
    ]);
    $other_term->save();
    $node = Node::create([
      'type' => 'page',
      'title' => 'Page reference',
      'status' => 1,
      'field_tags' => [
        ['target_id' => $other_term->id()],
      ],
    ]);
    $node->save();
    $field = $this->container->get('term_reference.discovery')
      ->getField('tags', 'node', 'field_tags');

    // Check that loadReferencingEntities() starts empty for this term.
    $this->assertSame([], $this->manager->loadReferencingEntities($term, $field));

    $this->manager->addReference($node, $term, 'field_tags');
    $node = Node::load($node->id());

    // Check that addReference() appends the term without removing others.
    $this->assertSame((string) $other_term->id(), (string) $node->get('field_tags')->get(0)->getValue()['target_id']);
    $this->assertSame((string) $term->id(), (string) $node->get('field_tags')->get(1)->getValue()['target_id']);

    $referencing_entities = $this->manager->loadReferencingEntities($term, $field);

    // Check that loadReferencingEntities() returns entities referencing the term.
    $this->assertArrayHasKey($node->id(), $referencing_entities);
    $this->assertSame('Page reference', $referencing_entities[$node->id()]->label());

    $this->manager->removeReference($node, $term, 'field_tags');
    $node = Node::load($node->id());

    // Check that removeReference() clears only the selected term.
    $this->assertCount(1, $node->get('field_tags'));
    $this->assertSame((string) $other_term->id(), $node->get('field_tags')->target_id);
  }

  /**
   * Creates field fixtures for manager tests.
   */
  protected function createFixtureFields(): void {
    $this->createVocabulary('tags', 'Tags');
    $this->createNodeType('page', 'Basic page');
    $this->createTaxonomyFieldStorage();
    $this->createTaxonomyField();
  }

}
