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
   * Tests getCurrentEntity().
   */
  public function testGetCurrentEntity(): void {
    // Check that a NULL route name returns no entity.
    $null_route_match = $this->createMock(RouteMatchInterface::class);
    $null_route_match->method('getRouteName')->willReturn(NULL);
    $this->assertNull($this->fixture->resolve($null_route_match));

    // Check that non-canonical routes return no entity.
    $non_canonical_route_match = $this->createMock(RouteMatchInterface::class);
    $non_canonical_route_match->method('getRouteName')->willReturn('view.frontpage.page_1');
    $this->assertNull($this->fixture->resolve($non_canonical_route_match));

    // Check that canonical routes without an entity parameter return no entity.
    $missing_parameter_route_match = $this->createMock(RouteMatchInterface::class);
    $missing_parameter_route_match->method('getRouteName')->willReturn('entity.node.canonical');
    $missing_parameter_route_match->method('getParameter')->with('node')->willReturn(NULL);
    $this->assertNull($this->fixture->resolve($missing_parameter_route_match));

    // Check that canonical routes with a non-content entity return no entity.
    $invalid_parameter_route_match = $this->createMock(RouteMatchInterface::class);
    $invalid_parameter_route_match->method('getRouteName')->willReturn('entity.node.canonical');
    $invalid_parameter_route_match->method('getParameter')->with('node')->willReturn(new \stdClass());
    $this->assertNull($this->fixture->resolve($invalid_parameter_route_match));

    // Check that canonical routes return the matching content entity.
    $valid_parameter_route_match = $this->createMock(RouteMatchInterface::class);
    $entity = $this->createMock(ContentEntityInterface::class);
    $valid_parameter_route_match->method('getRouteName')->willReturn('entity.node.canonical');
    $valid_parameter_route_match->method('getParameter')->with('node')->willReturn($entity);
    $this->assertSame($entity, $this->fixture->resolve($valid_parameter_route_match));
  }

}
