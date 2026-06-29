<?php

namespace Drupal\Tests\term_reference\Kernel;

use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\KernelTests\KernelTestBase;
use Drupal\node\Entity\NodeType;
use Drupal\taxonomy\Entity\Vocabulary;
use Drupal\term_reference\TermReferenceDiscoveryInterface;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the term reference discovery service.
 */
#[Group('term_reference')]
#[RunTestsInSeparateProcesses]
class TermReferenceDiscoveryKernelTest extends KernelTestBase {

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
   * The discovery service under test.
   */
  protected TermReferenceDiscoveryInterface $discovery;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('node');
    $this->installEntitySchema('taxonomy_term');
    $this->installEntitySchema('user');
    $this->installConfig(['node', 'system', 'taxonomy', 'user']);
    $this->createFixtureFields();
    $this->discovery = $this->container->get('term_reference.discovery');
  }

  /**
   * Tests each public discovery method.
   */
  public function testDiscoveryMethods(): void {
    $all_fields = $this->discovery->getAllReferenceFields();

    // Check that getAllReferenceFields() discovers taxonomy term references.
    $this->assertArrayHasKey('node.field_tags', $all_fields);
    $this->assertSame('node.field_tags', $all_fields['node.field_tags']['id']);
    $this->assertSame('Content', $all_fields['node.field_tags']['entity_type_label_plural']);

    $tag_fields = $this->discovery->getReferenceFieldsForVocabulary('tags');

    // Check that getReferenceFieldsForVocabulary() filters to matching fields.
    $this->assertArrayHasKey('node.field_tags', $tag_fields);
    $this->assertSame(['article', 'page'], array_keys($tag_fields['node.field_tags']['bundles']));
    $this->assertSame('Tags', $tag_fields['node.field_tags']['field_label']);

    $cache_backend = $this->container->get('cache.discovery');

    // Check that clearCachedReferenceFields() invalidates cached discovery.
    $this->assertNotFalse($cache_backend->get('term_reference:reference_fields:tags'));
    $this->discovery->clearCachedReferenceFields();
    $this->assertFalse($cache_backend->get('term_reference:reference_fields:tags'));

    $topic_fields = $this->discovery->getReferenceFieldsForVocabulary('topics');

    // Check that unrelated vocabulary filtering excludes the tags field.
    $this->assertArrayNotHasKey('node.field_tags', $topic_fields);

    $field = $this->discovery->getReferenceField('tags', 'node', 'field_tags');

    // Check that getReferenceField() returns one matching field or NULL.
    $this->assertIsArray($field);
    $this->assertSame('field_tags', $field['field_name']);
    $this->assertNull($this->discovery->getReferenceField('tags', 'node', 'field_missing'));
  }

  /**
   * Creates field fixtures for discovery.
   */
  protected function createFixtureFields(): void {
    Vocabulary::create([
      'vid' => 'tags',
      'name' => 'Tags',
    ])->save();
    Vocabulary::create([
      'vid' => 'topics',
      'name' => 'Topics',
    ])->save();
    foreach ([
      'article' => 'Article',
      'page' => 'Basic page',
    ] as $bundle => $label) {
      NodeType::create([
        'type' => $bundle,
        'name' => $label,
      ])->save();
    }
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
  }

}
