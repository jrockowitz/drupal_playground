<?php

declare(strict_types=1);

namespace Drupal\Tests\entity_labels\Unit;

use Drupal\Core\Cache\Cache;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Link;
use Drupal\entity_labels\Breadcrumb\EntityLabelsBreadcrumbBuilder;
use Drupal\Tests\UnitTestCase;
use Symfony\Component\DependencyInjection\Container;

/**
 * @coversDefaultClass \Drupal\entity_labels\Breadcrumb\EntityLabelsBreadcrumbBuilder
 * @group entity_labels
 */
class EntityLabelsBreadcrumbBuilderTest extends UnitTestCase {

  /**
   * The entity type manager mock.
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * The bundle info manager mock.
   */
  protected EntityTypeBundleInfoInterface $bundleInfoManager;

  /**
   * The breadcrumb builder under test.
   */
  protected EntityLabelsBreadcrumbBuilder $builder;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    // Mock cache_contexts_manager so Breadcrumb::addCacheContexts() works.
    // @see \Drupal\Core\Breadcrumb\Breadcrumb
    // @see \Drupal\Core\Cache\RefinableCacheableDependencyTrait
    $cache_contexts_manager = $this->createMock('Drupal\Core\Cache\Context\CacheContextsManager');
    $cache_contexts_manager->method('assertValidTokens')->willReturn(TRUE);
    $container = new Container();
    $container->set('cache_contexts_manager', $cache_contexts_manager);
    \Drupal::setContainer($container);

    $this->entityTypeManager = $this->createMock(EntityTypeManagerInterface::class);
    $this->bundleInfoManager = $this->createMock(EntityTypeBundleInfoInterface::class);

    $this->builder = new EntityLabelsBreadcrumbBuilder(
      $this->entityTypeManager,
      $this->bundleInfoManager,
    );
    $this->builder->setStringTranslation($this->getStringTranslationStub());
  }

  /**
   * Tests EntityLabelsBreadcrumbBuilder::applies().
   *
   * @param bool $expected
   *   Expected return value of applies().
   * @param string|null $route_name
   *   Route name to test with.
   *
   * @dataProvider providerTestApplies
   * @covers ::applies
   */
  public function testApplies(bool $expected, ?string $route_name = NULL): void {
    $route_match = $this->createMock('Drupal\Core\Routing\RouteMatchInterface');
    $route_match->expects($this->once())
      ->method('getRouteName')
      ->willReturn($route_name);

    $this->assertEquals($expected, $this->builder->applies($route_match));
  }

  /**
   * Provides test data for testApplies().
   *
   * @return array
   *   Array of [expected, route_name] pairs.
   */
  public static function providerTestApplies(): array {
    return [
      [FALSE],
      [FALSE, 'entity'],
      [FALSE, 'entity_label'],
      [FALSE, 'entity_labels'],
      [TRUE, 'entity_labels.entity.report'],
      [TRUE, 'entity_labels.field.report'],
      [TRUE, 'entity_labels.entity.export'],
      [TRUE, 'entity_labels.field.import'],
    ];
  }

  /**
   * Tests EntityLabelsBreadcrumbBuilder::build().
   *
   * Covers three scenarios:
   * - Global report (no route parameters): active crumb is unlinked.
   * - Entity-type drill-down: type crumb is linked, entity type is active.
   * - Bundle drill-down (field type): type crumb linked, type linked,
   *   bundle active.
   *
   * @covers ::build
   */
  public function testBuild(): void {
    // --- No params (global entity report) ---
    $route_match = $this->createMock('Drupal\Core\Routing\RouteMatchInterface');
    $route_match->method('getRawParameter')->with('type')->willReturn(NULL);
    $route_match->method('getParameter')->willReturnMap([
      ['entity_type', NULL],
      ['bundle', NULL],
    ]);

    $breadcrumb = $this->builder->build($route_match);

    // Always-present trail ends with unlinked 'Entities'.
    $this->assertEquals([
      ['Home', '<front>'],
      ['Administration', 'system.admin'],
      ['Reports', 'system.admin_reports'],
      ['Entity labels', 'entity_labels.entity.report'],
      ['Entities', '<none>'],
    ], $this->extractLinkData($breadcrumb->getLinks()));

    // Cache contexts and max-age.
    $this->assertEquals(['route'], $breadcrumb->getCacheContexts());
    $this->assertEquals(Cache::PERMANENT, $breadcrumb->getCacheMaxAge());

    // --- Entity-type drill-down (entity report, node) ---
    $entity_type_definition = $this->createMock(EntityTypeInterface::class);
    $entity_type_definition->method('getLabel')->willReturn('Content');
    $this->entityTypeManager
      ->method('getDefinition')
      ->with('node', FALSE)
      ->willReturn($entity_type_definition);

    $route_match = $this->createMock('Drupal\Core\Routing\RouteMatchInterface');
    $route_match->method('getRawParameter')->with('type')->willReturn(NULL);
    $route_match->method('getParameter')->willReturnMap([
      ['entity_type', 'node'],
      ['bundle', NULL],
    ]);

    $breadcrumb = $this->builder->build($route_match);

    // 'Entities' is now linked; 'Content' is the unlinked active crumb.
    $this->assertEquals([
      ['Home', '<front>'],
      ['Administration', 'system.admin'],
      ['Reports', 'system.admin_reports'],
      ['Entity labels', 'entity_labels.entity.report'],
      ['Entities', 'entity_labels.entity.report'],
      ['Content', '<none>'],
    ], $this->extractLinkData($breadcrumb->getLinks()));

    // --- Bundle drill-down (field report, node/article) ---
    $this->bundleInfoManager
      ->method('getBundleInfo')
      ->with('node')
      ->willReturn([
        'article' => ['label' => 'Article'],
        'page' => ['label' => 'Page'],
      ]);

    $route_match = $this->createMock('Drupal\Core\Routing\RouteMatchInterface');
    $route_match->method('getRawParameter')->with('type')->willReturn('field');
    $route_match->method('getParameter')->willReturnMap([
      ['entity_type', 'node'],
      ['bundle', 'article'],
    ]);

    $breadcrumb = $this->builder->build($route_match);

    // 'Fields' linked, 'Content' linked with entity_type param, 'Article'
    // active.
    $links = $breadcrumb->getLinks();
    $this->assertCount(7, $links);
    $this->assertEquals([
      ['Home', '<front>'],
      ['Administration', 'system.admin'],
      ['Reports', 'system.admin_reports'],
      ['Entity labels', 'entity_labels.entity.report'],
      ['Fields', 'entity_labels.field.report'],
      ['Content', 'entity_labels.field.report'],
      ['Article', '<none>'],
    ], $this->extractLinkData($links));
  }

  /**
   * Extracts [text, route_name] pairs from a Link array for assertion.
   *
   * Casting getText() to string handles both plain strings and
   * TranslatableMarkup returned by the builder's t() calls.
   *
   * @param \Drupal\Core\Link[] $links
   *   The links to extract data from.
   *
   * @return array[]
   *   Array of [text, route_name] pairs.
   */
  private function extractLinkData(array $links): array {
    return array_map(static function (Link $link): array {
      return [(string) $link->getText(), $link->getUrl()->getRouteName()];
    }, $links);
  }

}
