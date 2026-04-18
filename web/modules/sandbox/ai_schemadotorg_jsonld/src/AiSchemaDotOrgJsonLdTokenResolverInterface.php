<?php

declare(strict_types=1);

namespace Drupal\ai_schemadotorg_jsonld;

use Drupal\Core\Entity\ContentEntityInterface;

/**
 * Interface for the AI Schema.org JSON-LD token resolver service.
 */
interface AiSchemaDotOrgJsonLdTokenResolverInterface {

  /**
   * Resolves the [entity:ai_schemadotorg_jsonld:content] token value.
   *
   * Renders the entity as the anonymous user in the site default theme,
   * then post-processes the HTML for LLM consumption.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The entity to render.
   *
   * @return string
   *   The post-processed HTML of the rendered entity.
   */
  public function resolve(ContentEntityInterface $entity): string;

}
