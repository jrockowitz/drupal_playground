<?php

namespace Drupal\Tests\term_reference\Kernel;

use Drupal\term_reference\TermReferenceDiscoveryInterface;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the term reference discovery service.
 */
#[Group('term_reference')]
#[RunTestsInSeparateProcesses]
class TermReferenceDiscoveryKernelTest extends TermReferenceManagerKernelBase {

  /**
   * The discovery service under test.
   */
  protected TermReferenceDiscoveryInterface $discovery;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->createFixtureFields();
    $this->discovery = $this->container->get('term_reference.discovery');
  }

  /**
   * Tests each public discovery method.
   */
  public function testDiscoveryMethods(): void {
    $all_fields = $this->discovery->getAllFields();

    // Check that getAllFields() discovers taxonomy term references.
    $this->assertArrayHasKey('node.field_tags', $all_fields);
    $this->assertSame('node.field_tags', $all_fields['node.field_tags']['id']);
    $this->assertSame('Content', $all_fields['node.field_tags']['entity_type_label_plural']);

    $tag_fields = $this->discovery->getFieldsForVocabulary('tags');

    // Check that getFieldsForVocabulary() filters to matching fields.
    $this->assertArrayHasKey('node.field_tags', $tag_fields);
    $this->assertSame(['article', 'page'], array_keys($tag_fields['node.field_tags']['bundles']));
    $this->assertSame('Tags', $tag_fields['node.field_tags']['field_label']);

    $cache_backend = $this->container->get('cache.discovery');

    // Check that clearCachedFields() invalidates cached discovery.
    $this->assertNotFalse($cache_backend->get('term_reference:fields:tags'));
    $this->discovery->clearCachedFields();
    $this->assertFalse($cache_backend->get('term_reference:fields:tags'));

    $topic_fields = $this->discovery->getFieldsForVocabulary('topics');

    // Check that unrelated vocabulary filtering excludes the tags field.
    $this->assertArrayNotHasKey('node.field_tags', $topic_fields);

    $field = $this->discovery->getField('tags', 'node', 'field_tags');

    // Check that getField() returns one matching field or NULL.
    $this->assertIsArray($field);
    $this->assertSame('field_tags', $field['field_name']);
    $this->assertNull($this->discovery->getField('tags', 'node', 'field_missing'));
  }

  /**
   * Creates field fixtures for discovery.
   */
  protected function createFixtureFields(): void {
    $this->createVocabulary('tags', 'Tags');
    $this->createVocabulary('topics', 'Topics');
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
