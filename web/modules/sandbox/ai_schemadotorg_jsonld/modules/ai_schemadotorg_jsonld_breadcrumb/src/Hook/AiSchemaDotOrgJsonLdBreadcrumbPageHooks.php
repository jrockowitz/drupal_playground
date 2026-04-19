<?php

declare(strict_types=1);

namespace Drupal\ai_schemadotorg_jsonld_breadcrumb\Hook;

use Drupal\ai_schemadotorg_jsonld_breadcrumb\AiSchemaDotOrgJsonLdBreadcrumbManagerInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Render\BubbleableMetadata;
use Drupal\Core\Routing\RouteMatchInterface;

/**
 * Page hook implementations for breadcrumb JSON-LD.
 */
class AiSchemaDotOrgJsonLdBreadcrumbPageHooks {

  /**
   * Constructs an AiSchemaDotOrgJsonLdBreadcrumbPageHooks object.
   *
   * @param \Drupal\Core\Routing\RouteMatchInterface $routeMatch
   *   The current route match.
   * @param \Drupal\ai_schemadotorg_jsonld_breadcrumb\AiSchemaDotOrgJsonLdBreadcrumbManagerInterface $breadcrumbManager
   *   The breadcrumb manager.
   */
  public function __construct(
    protected readonly RouteMatchInterface $routeMatch,
    protected readonly AiSchemaDotOrgJsonLdBreadcrumbManagerInterface $breadcrumbManager,
  ) {}

  /**
   * Implements hook_page_attachments().
   */
  #[Hook('page_attachments')]
  public function pageAttachments(array &$attachments): void {
    $bubbleable_metadata = new BubbleableMetadata();
    $breadcrumb_data = $this->breadcrumbManager->build($this->routeMatch, $bubbleable_metadata);
    if ($breadcrumb_data === NULL) {
      return;
    }

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
