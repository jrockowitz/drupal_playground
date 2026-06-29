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
class TermReferenceHooksKernelTest extends TermReferenceManagerKernelBase {

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
  public function testFieldConfigHooksClearLocalTasks(): void {
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

    // Check that the OOP hook clears stale discovery and marks routes for rebuild.
    $this->assertTrue($this->routeBuilderNeedsRebuild());
    $this->assertArrayHasKey('node.field_tags', $discovery->getFieldsForVocabulary('tags'));
    $this->setRouteBuilderNeedsRebuild(FALSE);

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
    $this->assertTrue($this->routeBuilderNeedsRebuild());
  }

  /**
   * Gets whether the route builder needs rebuild.
   *
   * @return bool
   *   TRUE if the route builder needs rebuild.
   */
  protected function routeBuilderNeedsRebuild(): bool {
    $route_builder = $this->getProxiedRouteBuilder();
    $property = new \ReflectionProperty($route_builder, 'rebuildNeeded');
    return (bool) $property->getValue($route_builder);
  }

  /**
   * Sets whether the route builder needs rebuild.
   *
   * @param bool $needs_rebuild
   *   TRUE if the route builder needs rebuild.
   */
  protected function setRouteBuilderNeedsRebuild(bool $needs_rebuild): void {
    $route_builder = $this->getProxiedRouteBuilder();
    $property = new \ReflectionProperty($route_builder, 'rebuildNeeded');
    $property->setValue($route_builder, $needs_rebuild);
  }

  /**
   * Gets the proxied route builder service.
   *
   * @return object
   *   The proxied route builder service.
   */
  protected function getProxiedRouteBuilder(): object {
    $proxy = $this->container->get('router.builder');
    $property = new \ReflectionProperty($proxy, 'service');
    return $property->getValue($proxy);
  }

}
