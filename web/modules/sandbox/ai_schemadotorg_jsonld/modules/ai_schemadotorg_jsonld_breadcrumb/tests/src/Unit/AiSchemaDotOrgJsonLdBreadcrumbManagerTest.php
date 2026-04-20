<?php

declare(strict_types=1);

namespace Drupal\Tests\ai_schemadotorg_jsonld_breadcrumb\Unit;

use Drupal\ai_schemadotorg_jsonld_breadcrumb\AiSchemaDotOrgJsonLdBreadcrumbManager;
use Drupal\Core\Breadcrumb\Breadcrumb;
use Drupal\Core\Breadcrumb\ChainBreadcrumbBuilderInterface;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Link;
use Drupal\Core\Render\BubbleableMetadata;
use Drupal\Core\Render\Markup;
use Drupal\Core\Render\RendererInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Url;
use Drupal\Tests\UnitTestCase;

/**
 * Tests AiSchemaDotOrgJsonLdBreadcrumbManager.
 *
 * @group ai_schemadotorg_jsonld
 */
class AiSchemaDotOrgJsonLdBreadcrumbManagerTest extends UnitTestCase {

  /**
   * Tests build().
   */
  public function testBuild(): void {
    $renderer = $this->createMock(RendererInterface::class);
    $bubbleable_metadata = new BubbleableMetadata();

    // Check that non-applicable routes return no breadcrumb JSON-LD.
    $non_applicable_breadcrumb_builder = $this->createMock(ChainBreadcrumbBuilderInterface::class);
    $non_applicable_route_match = $this->createMock(RouteMatchInterface::class);
    $non_applicable_breadcrumb_builder->expects($this->once())
      ->method('applies')
      ->with($non_applicable_route_match)
      ->willReturn(FALSE);

    $non_applicable_breadcrumb_manager = new AiSchemaDotOrgJsonLdBreadcrumbManager($renderer, $non_applicable_breadcrumb_builder);
    $this->assertNull($non_applicable_breadcrumb_manager->build($non_applicable_route_match, $bubbleable_metadata));

    // Check that empty breadcrumbs return no breadcrumb JSON-LD.
    $empty_breadcrumb_builder = $this->createMock(ChainBreadcrumbBuilderInterface::class);
    $empty_route_match = $this->createMock(RouteMatchInterface::class);
    $empty_breadcrumb = new Breadcrumb();

    $empty_breadcrumb_builder->method('applies')->willReturn(TRUE);
    $empty_breadcrumb_builder->expects($this->once())
      ->method('build')
      ->with($empty_route_match)
      ->willReturn($empty_breadcrumb);

    $empty_breadcrumb_manager = new AiSchemaDotOrgJsonLdBreadcrumbManager($renderer, $empty_breadcrumb_builder);
    $this->assertNull($empty_breadcrumb_manager->build($empty_route_match, $bubbleable_metadata));

    // Check that the current canonical entity is appended to the breadcrumb.
    $breadcrumb_builder = $this->createMock(ChainBreadcrumbBuilderInterface::class);
    $route_match = $this->createMock(RouteMatchInterface::class);
    $entity = $this->createMock(ContentEntityInterface::class);
    $home_url = $this->createMock(Url::class);
    $section_url = $this->createMock(Url::class);
    $canonical_url = $this->createMock(Url::class);

    $home_url->method('setAbsolute')->willReturnSelf();
    $home_url->method('toString')->willReturn('https://example.com/');

    $section_url->method('setAbsolute')->willReturnSelf();
    $section_url->method('toString')->willReturn('https://example.com/section');

    $canonical_url->method('setAbsolute')->willReturnSelf();
    $canonical_url->method('toString')->willReturn('https://example.com/current-page');

    $breadcrumb = (new Breadcrumb())->setLinks([
      Link::fromTextAndUrl('Home', $home_url),
      Link::fromTextAndUrl(
        ['#markup' => Markup::create('Section')],
        $section_url
      ),
    ]);

    $breadcrumb_builder->method('applies')->willReturn(TRUE);
    $breadcrumb_builder->expects($this->once())
      ->method('build')
      ->with($route_match)
      ->willReturn($breadcrumb);

    $renderer->expects($this->once())
      ->method('renderInIsolation')
      ->willReturn('Section');

    $route_match->method('getRouteName')
      ->willReturn('entity.node.canonical');
    $route_match->method('getParameter')
      ->willReturnMap([
        ['node', $entity],
      ]);

    $entity->method('getCacheContexts')->willReturn([]);
    $entity->method('getCacheTags')->willReturn([]);
    $entity->method('getCacheMaxAge')->willReturn(-1);
    $entity->expects($this->once())
      ->method('label')
      ->willReturn('Current page');
    $entity->method('toUrl')
      ->with('canonical')
      ->willReturn($canonical_url);

    $breadcrumb_manager = new AiSchemaDotOrgJsonLdBreadcrumbManager($renderer, $breadcrumb_builder);
    $result = $breadcrumb_manager->build($route_match, $bubbleable_metadata);

    // Check that the breadcrumb JSON-LD contains the current entity.
    $this->assertSame('BreadcrumbList', $result['@type']);
    $this->assertCount(3, $result['itemListElement']);
    $this->assertSame('Home', $result['itemListElement'][0]['item']['name']);
    $this->assertSame('Section', $result['itemListElement'][1]['item']['name']);
    $this->assertSame('Current page', $result['itemListElement'][2]['item']['name']);
    $this->assertSame('https://example.com/current-page', $result['itemListElement'][2]['item']['@id']);
  }

}
