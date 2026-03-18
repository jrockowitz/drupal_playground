<?php

declare(strict_types=1);

namespace Drupal\entity_labels\Breadcrumb;

use Drupal\Core\Breadcrumb\Breadcrumb;
use Drupal\Core\Breadcrumb\BreadcrumbBuilderInterface;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Link;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Url;

/**
 * Builds breadcrumbs for all six entity_labels routes.
 */
class EntityLabelsBreadcrumbBuilder implements BreadcrumbBuilderInterface {

  use StringTranslationTrait;

  /**
   * Constructs an EntityLabelsBreadcrumbBuilder.
   */
  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly EntityTypeBundleInfoInterface $bundleInfoManager,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function applies(RouteMatchInterface $route_match): bool {
    return str_starts_with($route_match->getRouteName() ?? '', 'entity_labels.');
  }

  /**
   * {@inheritdoc}
   */
  public function build(RouteMatchInterface $route_match): Breadcrumb {
    $breadcrumb = new Breadcrumb();
    $breadcrumb->addCacheContexts(['route']);

    $route_name = $route_match->getRouteName() ?? '';

    // Determine tab type from route name prefix.
    $type = str_starts_with($route_name, 'entity_labels.field.') ? 'field' : 'entity';
    $tab_label = $type === 'field' ? $this->t('Fields') : $this->t('Entities');
    $base_report_route = 'entity_labels.' . $type . '.report';

    // Always-present trail: Home › Administration › Reports › Entity labels.
    $breadcrumb->addLink(
      Link::createFromRoute($this->t('Home'), '<front>'),
    );
    $breadcrumb->addLink(
      Link::createFromRoute($this->t('Administration'), 'system.admin'),
    );
    $breadcrumb->addLink(
      Link::createFromRoute($this->t('Reports'), 'system.admin_reports'),
    );
    // "Entity labels" always links to the entity report with no params.
    $breadcrumb->addLink(
      Link::createFromRoute(
        $this->t('Entity labels'),
        'entity_labels.entity.report',
      ),
    );

    // Read route path parameters (export/import routes have none).
    $entity_type_id = $route_match->getParameter('entity_type');
    $bundle = $route_match->getParameter('bundle');

    if ($entity_type_id !== NULL) {
      // Entities/Fields crumb is linked when drilling deeper.
      $breadcrumb->addLink(
        Link::createFromRoute($tab_label, $base_report_route),
      );

      $definition = $this->entityTypeManager->getDefinition(
        $entity_type_id,
        FALSE,
      );
      $entity_type_label = $definition
        ? (string) $definition->getLabel()
        : $entity_type_id;

      if ($bundle !== NULL) {
        // Entity type crumb links to the base report with entity_type only.
        $breadcrumb->addLink(
          Link::createFromRoute(
            $this->t('@label', ['@label' => $entity_type_label]),
            $base_report_route,
            ['entity_type' => $entity_type_id],
          ),
        );

        // Bundle label is the unlinked active crumb.
        $bundle_info = $this->bundleInfoManager->getBundleInfo($entity_type_id);
        $bundle_label = $bundle_info[$bundle]['label'] ?? $bundle;
        $breadcrumb->addLink(
          Link::fromTextAndUrl(
            $this->t('@label', ['@label' => $bundle_label]),
            Url::fromRoute('<none>'),
          ),
        );
      }
      else {
        // Entity type label is the unlinked active crumb.
        $breadcrumb->addLink(
          Link::fromTextAndUrl(
            $this->t('@label', ['@label' => $entity_type_label]),
            Url::fromRoute('<none>'),
          ),
        );
      }
    }
    else {
      // No params: Entities/Fields is the unlinked active crumb.
      $breadcrumb->addLink(
        Link::fromTextAndUrl($tab_label, Url::fromRoute('<none>')),
      );
    }

    return $breadcrumb;
  }

}
