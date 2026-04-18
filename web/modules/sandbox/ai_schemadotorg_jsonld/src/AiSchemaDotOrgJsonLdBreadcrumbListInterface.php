<?php

declare(strict_types=1);

namespace Drupal\ai_schemadotorg_jsonld;

use Drupal\Core\Render\BubbleableMetadata;
use Drupal\Core\Routing\RouteMatchInterface;

/**
 * Interface for the AI Schema.org JSON-LD breadcrumb list service.
 */
interface AiSchemaDotOrgJsonLdBreadcrumbListInterface {

  /**
   * Builds a BreadcrumbList JSON-LD array for the current page.
   *
   * @param \Drupal\Core\Routing\RouteMatchInterface $route_match
   *   The current route match.
   * @param \Drupal\Core\Render\BubbleableMetadata $bubbleable_metadata
   *   Metadata for cache bubbling.
   *
   * @return array|null
   *   A BreadcrumbList JSON-LD array, or NULL if no breadcrumb applies.
   */
  public function build(RouteMatchInterface $route_match, BubbleableMetadata $bubbleable_metadata): ?array;

}
