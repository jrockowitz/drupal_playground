<?php

namespace Drupal\Tests\term_reference\Kernel;

use Drupal\field\Entity\FieldConfig;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests Term Reference hook implementations.
 */
#[Group('term_reference')]
#[RunTestsInSeparateProcesses]
class TermReferenceHooksKernelTest extends TermReferenceKernelBase {

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->createVocabulary('tags', 'Tags');
    $this->createVocabulary('topics', 'Topics');
    $this->createNodeType('page', 'Basic page');
    $this->createTaxonomyFieldStorage();
  }

  /**
   * Tests OOP and legacy field config hook paths.
   */
  public function testFieldConfigHooksClearDiscovery(): void {
    $discovery = $this->container->get('term_reference.discovery');
    $this->assertSame([], $discovery->getFieldsForVocabulary('tags'));

    $field_config = FieldConfig::create([
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
    ]);
    $field_config->save();

    // Check that the OOP hook clears stale discovery.
    $this->assertArrayHasKey('node.field_tags', $discovery->getFieldsForVocabulary('tags'));

    $field_config->setLabel('Topics');
    $field_config->setSettings([
      'handler' => 'default:taxonomy_term',
      'handler_settings' => [
        'target_bundles' => [
          'topics' => 'topics',
        ],
      ],
    ]);
    $field_config->save();

    // Check that the OOP hook clears cached fields.
    $this->assertArrayNotHasKey('node.field_tags', $discovery->getFieldsForVocabulary('tags'));

    term_reference_field_config_update($field_config);

    // Check that the legacy hook shim delegates to the same hook service.
    $this->assertArrayNotHasKey('node.field_tags', $discovery->getFieldsForVocabulary('tags'));
  }

}
