<?php

declare(strict_types=1);

namespace Drupal\ai_schemadotorg_jsonld_breadcrumb;

use Drupal\ai_schemadotorg_jsonld\Traits\AiSchemaDotOrgJsonLdCurrentEntityTrait;
use Drupal\Core\Breadcrumb\ChainBreadcrumbBuilderInterface;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Render\BubbleableMetadata;
use Drupal\Core\Render\RendererInterface;
use Drupal\Core\Routing\RouteMatchInterface;

/**
 * Builds a BreadcrumbList JSON-LD array for the current page.
 */
class AiSchemaDotOrgJsonLdBreadcrumbManager implements AiSchemaDotOrgJsonLdBreadcrumbManagerInterface {

  use AiSchemaDotOrgJsonLdCurrentEntityTrait;

  /**
   * Constructs an AiSchemaDotOrgJsonLdBreadcrumbManager object.
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
  public function build(RouteMatchInterface $routeMatch, BubbleableMetadata $bubbleableMetadata): ?array {
    if (!$this->breadcrumb->applies($routeMatch)) {
      return NULL;
    }

    $breadcrumb = $this->breadcrumb->build($routeMatch);
    $links = $breadcrumb->getLinks();
    if (empty($links)) {
      return NULL;
    }

    $bubbleableMetadata->addCacheableDependency($breadcrumb);

    $items = [];
    foreach (array_values($links) as $index => $link) {
      $id = $link->getUrl()->setAbsolute()->toString();
      $text = $link->getText();
      if (is_array($text)) {
        $text = $this->renderer->renderInIsolation($text);
      }

      $items[] = [
        '@type' => 'ListItem',
        'position' => $index + 1,
        'item' => [
          '@id' => $id,
          'name' => (string) $text,
        ],
      ];
    }

    $entity = $this->getCurrentEntity($routeMatch);
    if ($entity instanceof ContentEntityInterface) {
      $bubbleableMetadata->addCacheableDependency($entity);
      $items[] = [
        '@type' => 'ListItem',
        'position' => count($items) + 1,
        'item' => [
          '@id' => $entity->toUrl('canonical')->setAbsolute()->toString(),
          'name' => $entity->label(),
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
