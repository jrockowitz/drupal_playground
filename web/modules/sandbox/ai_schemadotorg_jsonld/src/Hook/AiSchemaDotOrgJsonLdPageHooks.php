<?php

declare(strict_types=1);

namespace Drupal\ai_schemadotorg_jsonld\Hook;

use Drupal\ai_schemadotorg_jsonld\AiSchemaDotOrgJsonLdBreadcrumbListInterface;
use Drupal\ai_schemadotorg_jsonld\AiSchemaDotOrgJsonLdBuilderInterface;
use Drupal\ai_schemadotorg_jsonld\Traits\AiSchemaDotOrgJsonLdCurrentEntityTrait;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Render\BubbleableMetadata;
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
   * @param \Drupal\ai_schemadotorg_jsonld\AiSchemaDotOrgJsonLdBreadcrumbListInterface $breadcrumbList
   *   The breadcrumb list service.
   */
  public function __construct(
    protected readonly ConfigFactoryInterface $configFactory,
    protected readonly RouteMatchInterface $routeMatch,
    protected readonly AiSchemaDotOrgJsonLdBreadcrumbListInterface $breadcrumbList,
  ) {}

  /**
   * Implements hook_page_attachments().
   */
  #[Hook('page_attachments')]
  public function pageAttachments(array &$attachments): void {
    $config = $this->configFactory->get('ai_schemadotorg_jsonld.settings');

    // Attach breadcrumb JSON-LD.
    if ($config->get('breadcrumb_jsonld')) {
      $bubbleable_metadata = new BubbleableMetadata();
      $breadcrumb_data = $this->breadcrumbList->build($this->routeMatch, $bubbleable_metadata);
      if ($breadcrumb_data !== NULL) {
        $bubbleable_metadata->applyTo($attachments);
        $attachments['#attached']['html_head'][] = [
          [
            '#type' => 'html_tag',
            '#tag' => 'script',
            '#value' => json_encode($breadcrumb_data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            '#attributes' => ['type' => 'application/ld+json'],
          ],
          'ai_schemadotorg_jsonld_breadcrumb',
        ];
      }
    }

    // Attach entity field JSON-LD on canonical entity routes.
    $entity = $this->getCurrentEntity($this->routeMatch);
    if (!$entity instanceof ContentEntityInterface) {
      return;
    }
    if (!$entity->hasField(AiSchemaDotOrgJsonLdBuilderInterface::FIELD_NAME)) {
      return;
    }
    $field_value = $entity->get(AiSchemaDotOrgJsonLdBuilderInterface::FIELD_NAME)->value;
    if (empty($field_value)) {
      $field_value = NULL;
    }

    $default_jsonld = $config->get('entity_types.' . $entity->getEntityTypeId() . '.default_jsonld');
    if (!empty($default_jsonld)) {
      $attachments['#attached']['html_head'][] = [
        [
          '#type' => 'html_tag',
          '#tag' => 'script',
          '#value' => $default_jsonld,
          '#attributes' => ['type' => 'application/ld+json'],
        ],
        'ai_schemadotorg_jsonld_default_' . $entity->getEntityTypeId(),
      ];
    }

    if ($field_value !== NULL) {
      $attachments['#attached']['html_head'][] = [
        [
          '#type' => 'html_tag',
          '#tag' => 'script',
          '#value' => $field_value,
          '#attributes' => ['type' => 'application/ld+json'],
        ],
        'ai_schemadotorg_jsonld_' . $entity->getEntityTypeId() . '_' . $entity->id(),
      ];
    }
  }

}
