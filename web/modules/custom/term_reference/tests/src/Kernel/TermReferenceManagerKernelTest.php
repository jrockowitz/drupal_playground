<?php

namespace Drupal\Tests\term_reference\Kernel;

use Drupal\Core\Session\AccountInterface;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\KernelTests\KernelTestBase;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use Drupal\taxonomy\Entity\Term;
use Drupal\taxonomy\Entity\Vocabulary;
use Drupal\term_reference\TermReferenceManagerInterface;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the term reference manager service.
 */
#[Group('term_reference')]
#[RunTestsInSeparateProcesses]
class TermReferenceManagerKernelTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'field',
    'node',
    'system',
    'taxonomy',
    'term_reference',
    'text',
    'user',
  ];

  /**
   * The manager service under test.
   */
  protected TermReferenceManagerInterface $manager;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('node');
    $this->installEntitySchema('taxonomy_term');
    $this->installEntitySchema('user');
    $this->installSchema('node', ['node_access']);
    $this->installConfig(['node', 'system', 'taxonomy', 'user']);
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
      ->getReferenceField('tags', 'node', 'field_tags');

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
    Vocabulary::create([
      'vid' => 'tags',
      'name' => 'Tags',
    ])->save();
    NodeType::create([
      'type' => 'page',
      'name' => 'Basic page',
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
    FieldConfig::create([
      'field_name' => 'field_tags',
      'entity_type' => 'node',
      'bundle' => 'page',
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

  /**
   * Creates and switches to a user.
   *
   * @param array $permissions
   *   The permissions.
   *
   * @return \Drupal\Core\Session\AccountInterface
   *   The created account.
   */
  protected function createUser(array $permissions): AccountInterface {
    $user = $this->container->get('entity_type.manager')->getStorage('user')->create([
      'name' => $this->randomMachineName(),
      'mail' => $this->randomMachineName() . '@example.com',
      'status' => 1,
    ]);
    $user->save();
    $role = $this->container->get('entity_type.manager')->getStorage('user_role')->load('authenticated');
    foreach ($permissions as $permission) {
      $role->grantPermission($permission);
    }
    $role->save();
    return $user;
  }

  /**
   * Sets the current user.
   *
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The account.
   */
  protected function setCurrentUser(AccountInterface $account): void {
    $this->container->get('current_user')->setAccount($account);
  }

}
