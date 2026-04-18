<?php

declare(strict_types=1);

namespace Drupal\Tests\ai_schemadotorg_jsonld\Unit;

use Drupal\ai_schemadotorg_jsonld\Traits\AiSchemaDotOrgJsonLdCurrentEntityTrait;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Tests\UnitTestCase;

/**
 * Tests AiSchemaDotOrgJsonLdCurrentEntityTrait.
 *
 * @group ai_schemadotorg_jsonld
 */
class AiSchemaDotOrgJsonLdCurrentEntityTraitTest extends UnitTestCase {

  /**
   * The trait test fixture.
   */
  private object $fixture;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->fixture = new class() {

      use AiSchemaDotOrgJsonLdCurrentEntityTrait;

      /**
       * Returns the current entity for the supplied route match.
       */
      public function resolve(RouteMatchInterface $route_match): ?ContentEntityInterface {
        return $this->getCurrentEntity($route_match);
      }

    };
  }

  /**
   * Tests that a NULL route name returns no entity.
   */
  public function testGetCurrentEntityWithNullRouteName(): void {
    $route_match = $this->createMock(RouteMatchInterface::class);
    $route_match->method('getRouteName')->willReturn(NULL);

    $this->assertNull($this->fixture->resolve($route_match));
  }

  /**
   * Tests that non-canonical routes return no entity.
   */
  public function testGetCurrentEntityWithNonCanonicalRoute(): void {
    $route_match = $this->createMock(RouteMatchInterface::class);
    $route_match->method('getRouteName')->willReturn('view.frontpage.page_1');

    $this->assertNull($this->fixture->resolve($route_match));
  }

  /**
   * Tests that canonical routes without an entity parameter return no entity.
   */
  public function testGetCurrentEntityWithoutParameter(): void {
    $route_match = $this->createMock(RouteMatchInterface::class);
    $route_match->method('getRouteName')->willReturn('entity.node.canonical');
    $route_match->method('getParameter')->with('node')->willReturn(NULL);

    $this->assertNull($this->fixture->resolve($route_match));
  }

  /**
   * Tests that canonical routes with a non-content entity return no entity.
   */
  public function testGetCurrentEntityWithInvalidParameter(): void {
    $route_match = $this->createMock(RouteMatchInterface::class);
    $route_match->method('getRouteName')->willReturn('entity.node.canonical');
    $route_match->method('getParameter')->with('node')->willReturn(new \stdClass());

    $this->assertNull($this->fixture->resolve($route_match));
  }

  /**
   * Tests that canonical routes return the matching content entity.
   */
  public function testGetCurrentEntityWithValidParameter(): void {
    $route_match = $this->createMock(RouteMatchInterface::class);
    $entity = $this->createMock(ContentEntityInterface::class);
    $route_match->method('getRouteName')->willReturn('entity.node.canonical');
    $route_match->method('getParameter')->with('node')->willReturn($entity);

    $this->assertSame($entity, $this->fixture->resolve($route_match));
  }

}
