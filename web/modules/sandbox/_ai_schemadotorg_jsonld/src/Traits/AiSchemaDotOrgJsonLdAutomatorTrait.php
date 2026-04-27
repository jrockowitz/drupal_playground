<?php

declare(strict_types=1);

namespace Drupal\ai_schemadotorg_jsonld\Traits;

use Drupal\ai_schemadotorg_jsonld\AiSchemaDotOrgJsonLdBuilderInterface;

/**
 * Provides a helper to identify JSON-LD automator requests by their tags.
 */
trait AiSchemaDotOrgJsonLdAutomatorTrait {

  /**
   * Returns TRUE when the tags belong to this module's JSON-LD automator.
   *
   * @param array $tags
   *   The request tags.
   */
  protected function hasTags(array $tags): bool {
    return in_array('ai_automator', $tags)
      && in_array('ai_automator:field_name:' . AiSchemaDotOrgJsonLdBuilderInterface::FIELD_NAME, $tags);
  }

}
