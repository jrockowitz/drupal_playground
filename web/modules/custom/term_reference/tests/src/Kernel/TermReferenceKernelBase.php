<?php

namespace Drupal\Tests\term_reference\Kernel;

use Drupal\Core\Session\AccountInterface;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\KernelTests\KernelTestBase;
use Drupal\node\Entity\NodeType;
use Drupal\taxonomy\Entity\Vocabulary;

/**
 * Provides shared setup and fixture helpers for Term Reference kernel tests.
 */
abstract class TermReferenceKernelBase extends KernelTestBase {

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
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('node');
    $this->installEntitySchema('taxonomy_term');
    $this->installEntitySchema('user');
    $this->installSchema('node', ['node_access']);
    $this->installConfig(['node', 'system', 'taxonomy', 'user']);
  }

  /**
   * Creates a vocabulary.
   *
   * @param string $vocabulary_id
   *   The vocabulary ID.
   * @param string $label
   *   The vocabulary label.
   */
  protected function createVocabulary(string $vocabulary_id, string $label): void {
    Vocabulary::create([
      'vid' => $vocabulary_id,
      'name' => $label,
    ])->save();
  }

  /**
   * Creates a node type.
   *
   * @param string $bundle
   *   The node bundle.
   * @param string $label
   *   The node type label.
   */
  protected function createNodeType(string $bundle, string $label): void {
    NodeType::create([
      'type' => $bundle,
      'name' => $label,
    ])->save();
  }

  /**
   * Creates reusable taxonomy field storage.
   *
   * @param string $entity_type_id
   *   The entity type ID.
   * @param string $field_name
   *   The field name.
   */
  protected function createTaxonomyFieldStorage(string $entity_type_id = 'node', string $field_name = 'field_tags'): void {
    FieldStorageConfig::create([
      'field_name' => $field_name,
      'entity_type' => $entity_type_id,
      'type' => 'entity_reference',
      'cardinality' => -1,
      'settings' => [
        'target_type' => 'taxonomy_term',
      ],
    ])->save();
  }

  /**
   * Creates a taxonomy field instance.
   *
   * @param string $entity_type_id
   *   The entity type ID.
   * @param string $bundle
   *   The bundle ID.
   * @param string $field_name
   *   The field name.
   * @param string $label
   *   The field label.
   * @param array $target_bundles
   *   The target vocabulary IDs.
   */
  protected function createTaxonomyField(string $entity_type_id = 'node', string $bundle = 'page', string $field_name = 'field_tags', string $label = 'Tags', array $target_bundles = ['tags' => 'tags']): void {
    FieldConfig::create([
      'field_name' => $field_name,
      'entity_type' => $entity_type_id,
      'bundle' => $bundle,
      'label' => $label,
      'settings' => [
        'handler' => 'default:taxonomy_term',
        'handler_settings' => [
          'target_bundles' => $target_bundles,
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
