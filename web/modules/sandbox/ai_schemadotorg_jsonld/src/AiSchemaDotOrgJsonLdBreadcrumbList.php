<?php

declare(strict_types=1);

namespace Drupal\ai_schemadotorg_jsonld;

use Drupal\Core\Breadcrumb\ChainBreadcrumbBuilderInterface;
use Drupal\Core\Render\BubbleableMetadata;
use Drupal\Core\Render\RendererInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Url;

/**
 * Builds a BreadcrumbList JSON-LD array for the current page.
 *
 * Modelled on SchemaDotOrgJsonLdBreadcrumbManager but without any dependency
 * on the schemadotorg module. Named BreadcrumbList (not BreadcrumbBuilder) to
 * signal it produces data, not a Drupal breadcrumb object.
 */
class AiSchemaDotOrgJsonLdBreadcrumbList implements AiSchemaDotOrgJsonLdBreadcrumbListInterface {

  /**
   * Constructs an AiSchemaDotOrgJsonLdBreadcrumbList object.
   *
   * @param \Drupal\Core\Render\RendererInterface $renderer
   *   The renderer service.
   * @param \Drupal\Core\Breadcrumb\ChainBreadcrumbBuilderInterface $breadcrumb
   *   The chain breadcrumb builder.
   */
  public function __construct(
    protected readonly RendererInterface $renderer,
    protected readonly ChainBreadcrumbBuilderInterface $breadcrumb,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function build(RouteMatchInterface $route_match, BubbleableMetadata $bubbleable_metadata): ?array {
    if (!$this->breadcrumb->applies($route_match)) {
      return NULL;
    }

    $breadcrumb = $this->breadcrumb->build($route_match);
    $links = $breadcrumb->getLinks();
    if (empty($links)) {
      return NULL;
    }

    $bubbleable_metadata->addCacheableDependency($breadcrumb);

    $items = [];
    $position = 1;
    foreach ($links as $link) {
      $id = $link->getUrl()->setAbsolute()->toString();
      $text = $link->getText();
      if (is_array($text)) {
        $text = $this->renderer->renderInIsolation($text);
      }

      $items[] = [
        '@type' => 'ListItem',
        'position' => $position,
        'item' => [
          '@id' => $id,
          'name' => (string) $text,
        ],
      ];
      $position++;
    }

    // Append the current route's node as the final list item.
    $node = $route_match->getParameter('node');
    if ($node) {
      $items[] = [
        '@type' => 'ListItem',
        'position' => $position,
        'item' => [
          '@id' => Url::fromRouteMatch($route_match)->setAbsolute()->toString(),
          'name' => $node->label(),
        ],
      ];
    }

    return [
      '@context' => 'https://schema.org',
      '@type' => 'BreadcrumbList',
      'itemListElement' => $items,
    ];
  }

}
