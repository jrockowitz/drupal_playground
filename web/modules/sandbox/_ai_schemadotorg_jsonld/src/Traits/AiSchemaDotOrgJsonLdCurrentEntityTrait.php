<?php

declare(strict_types=1);

namespace Drupal\ai_schemadotorg_jsonld\Traits;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Routing\RouteMatchInterface;

/**
 * Provides the current canonical content entity for a route match.
 */
trait AiSchemaDotOrgJsonLdCurrentEntityTrait {

  /**
   * Returns the current canonical content entity, or NULL.
   *
   * Canonical routes follow the pattern: entity.{entity_type_id}.canonical.
   *
   * @param \Drupal\Core\Routing\RouteMatchInterface $route_match
   *   The current route match.
   */
  protected function getCurrentEntity(RouteMatchInterface $route_match): ?ContentEntityInterface {
    $route_name = $route_match->getRouteName();
    if (!$route_name) {
      return NULL;
    }

    if (!preg_match('/^entity\.(\w+)\.canonical$/', $route_name, $matches)) {
      return NULL;
    }

    $entity = $route_match->getParameter($matches[1]);
    return $entity instanceof ContentEntityInterface ? $entity : NULL;
  }

}
