<?php

declare(strict_types=1);

namespace Drupal\ai_schemadotorg_jsonld\Hook;

use Drupal\ai_schemadotorg_jsonld\AiSchemaDotOrgJsonLdBuilderInterface;
use Drupal\ai_schemadotorg_jsonld\Traits\AiSchemaDotOrgJsonLdCurrentEntityTrait;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Routing\RouteMatchInterface;

/**
 * Page hook implementations for the ai_schemadotorg_jsonld module.
 */
class AiSchemaDotOrgJsonLdPageHooks {

  use AiSchemaDotOrgJsonLdCurrentEntityTrait;

  /**
   * Constructs an AiSchemaDotOrgJsonLdPageHooks object.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   *   The config factory.
   * @param \Drupal\Core\Routing\RouteMatchInterface $routeMatch
   *   The current route match.
   */
  public function __construct(
    protected readonly ConfigFactoryInterface $configFactory,
    protected readonly RouteMatchInterface $routeMatch,
  ) {}

  /**
   * Implements hook_page_attachments().
   */
  #[Hook('page_attachments')]
  public function pageAttachments(array &$attachments): void {
    $config = $this->configFactory->get('ai_schemadotorg_jsonld.settings');

    // Attach entity field JSON-LD on canonical entity routes.
    $entity = $this->getCurrentEntity($this->routeMatch);
    if (!$entity instanceof ContentEntityInterface) {
      return;
    }
    if (!$entity->hasField(AiSchemaDotOrgJsonLdBuilderInterface::FIELD_NAME)) {
      return;
    }
    $field_value = $entity->get(AiSchemaDotOrgJsonLdBuilderInterface::FIELD_NAME)->value;

    $default_jsonld = $config->get('entity_types.' . $entity->getEntityTypeId() . '.default_jsonld');
    if (!empty($default_jsonld)) {
      $attachments['#attached']['html_head'][] = [
        [
          '#type' => 'html_tag',
          '#tag' => 'script',
          '#value' => $this->compactJson($default_jsonld),
          '#attributes' => ['type' => 'application/ld+json'],
        ],
        'ai_schemadotorg_jsonld_default_' . $entity->getEntityTypeId(),
      ];
    }

    if (!empty($field_value)) {
      $attachments['#attached']['html_head'][] = [
        [
          '#type' => 'html_tag',
          '#tag' => 'script',
          '#value' => $this->compactJson($field_value),
          '#attributes' => ['type' => 'application/ld+json'],
        ],
        'ai_schemadotorg_jsonld_' . $entity->getEntityTypeId() . '_' . $entity->id(),
      ];
    }
  }

  /**
   * Decodes and re-encodes a JSON string to produce compact, whitespace-free output.
   *
   * @param string $json
   *   The JSON string to compact.
   *
   * @return string
   *   The compacted JSON string, or the original string if it cannot be decoded.
   */
  protected function compactJson(string $json): string {
    $decoded = json_decode($json, TRUE);
    return ($decoded)
      ? json_encode($decoded, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
      : $json;
  }

}
