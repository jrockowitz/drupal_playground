<?php

declare(strict_types=1);

namespace Drupal\ai_schemadotorg_jsonld_breadcrumb;

use Drupal\Core\Render\BubbleableMetadata;
use Drupal\Core\Routing\RouteMatchInterface;

/**
 * Builds breadcrumb JSON-LD data for the current route.
 */
interface AiSchemaDotOrgJsonLdBreadcrumbManagerInterface {

  /**
   * Builds BreadcrumbList JSON-LD data.
   *
   * @param \Drupal\Core\Routing\RouteMatchInterface $routeMatch
   *   The current route match.
   * @param \Drupal\Core\Render\BubbleableMetadata $bubbleableMetadata
   *   Bubbleable metadata for the breadcrumb dependencies.
   *
   * @return array|null
   *   The BreadcrumbList JSON-LD array, or NULL when unavailable.
   */
  public function build(RouteMatchInterface $routeMatch, BubbleableMetadata $bubbleableMetadata): ?array;

}
