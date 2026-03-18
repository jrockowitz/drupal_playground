<?php

declare(strict_types=1);

namespace Drupal\entity_labels\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Url;
use Drupal\entity_labels\EntityLabelsTypeTrait;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Handles all entity-labels report, export routes for both types.
 */
class EntityLabelsController extends ControllerBase {

  use EntityLabelsTypeTrait;

  /**
   * Constructs an EntityLabelsController.
   */
  public function __construct(
    protected readonly string $type,
    private readonly EntityTypeBundleInfoInterface $bundleInfo,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    $type = $container->get('request_stack')
      ->getCurrentRequest()->attributes->get('type', 'entity');
    // @phpstan-ignore new.static
    return new static($type, $container->get('entity_type.bundle.info'));
  }

  /**
   * Builds the report table render array for the current type and scope.
   *
   * @return array
   *   A render array containing a table, optional notes, and a CSV link.
   */
  public function report(
    ?string $entity_type = NULL,
    ?string $bundle = NULL,
  ): array {
    $rows = $this->getExporter()->getData($entity_type, $bundle);

    $columns = $this->getExporter()->getHeader();
    $header = $columns;

    $table_rows = [];
    foreach ($rows as $row) {
      $cells = [];

      foreach ($columns as $col) {
        $value = $row[$col] ?? '';

        // entity_type cell: always linked to the type-filtered report.
        if ($col === 'entity_type' && (string) $value !== '') {
          $cells[] = [
            'data' => [
              '#type'  => 'link',
              '#title' => $value,
              '#url'   => Url::fromRoute(
                $this->getReportRoute(),
                ['entity_type' => $value],
              ),
            ],
          ];
          continue;
        }

        // Bundle cell: linked only on the Fields tab.
        if ($col === 'bundle'
          && $this->type === 'field'
          && (string) $value !== ''
        ) {
          $cells[] = [
            'data' => [
              '#type'  => 'link',
              '#title' => $value,
              '#url'   => Url::fromRoute(
                $this->getReportRoute(),
                [
                  'entity_type' => $row['entity_type'],
                  'bundle'      => $value,
                ],
              ),
            ],
          ];
          continue;
        }

        // allowed_values: render semicolon-delimited string as a bullet list.
        if ($col === 'allowed_values' && (string) $value !== '') {
          $items = explode(';', (string) $value);
          $cells[] = [
            'data' => [
              '#theme' => 'item_list',
              '#items' => $items,
            ],
          ];
          continue;
        }

        $cells[] = (string) $value;
      }

      $table_rows[] = ['data' => $cells];
    }

    $build = [];

    $build['table'] = [
      '#type'   => 'table',
      '#header' => $header,
      '#rows'   => $table_rows,
      '#empty'  => $this->t('No data found.'),
    ];

    // Download CSV button.
    $build['export_link'] = [
      '#type'       => 'link',
      '#title'      => $this->t('⇩ Download CSV'),
      '#url'        => Url::fromRoute(
        $this->getExportRoute(),
        [],
        [
          'query' => array_filter([
            'entity_type' => $entity_type,
            'bundle'      => $bundle,
          ]),
        ],
      ),
      '#attributes' => ['class' => ['button']],
    ];

    return $build;
  }

  /**
   * Streams the current-scope data as a CSV file download.
   */
  public function export(Request $request): StreamedResponse {
    $entity_type = $request->query->getString('entity_type') ?: NULL;
    $bundle = $request->query->getString('bundle') ?: NULL;

    $rows = $this->getExporter()->export($entity_type, $bundle);
    $filename = $this->buildFilename($entity_type, $bundle);

    $response = new StreamedResponse(static function () use ($rows): void {
      $handle = fopen('php://output', 'w');
      if ($handle !== FALSE) {
        foreach ($rows as $row) {
          fputcsv($handle, $row);
        }
        fclose($handle);
      }
    });

    $response->headers->set('Content-Type', 'text/csv; charset=utf-8');
    $response->headers->set(
      'Content-Disposition',
      'attachment; filename="' . $filename . '"',
    );

    return $response;
  }

  /**
   * Returns the page title reflecting the current drill-down level.
   */
  public function title(
    ?string $entity_type = NULL,
    ?string $bundle = NULL,
  ): TranslatableMarkup {
    if ($entity_type !== NULL && $bundle !== NULL) {
      $bundles = $this->bundleInfo->getBundleInfo($entity_type);
      $label = $bundles[$bundle]['label'] ?? $bundle;
      return $this->t('@type: @label', [
        '@type'  => $this->getPluralLabel(),
        '@label' => $label,
      ]);
    }

    if ($entity_type !== NULL) {
      $definition = $this->entityTypeManager()
        ->getDefinition($entity_type, FALSE);
      $label = $definition ? (string) $definition->getLabel() : $entity_type;
      return $this->t('@type: @label', [
        '@type'  => $this->getPluralLabel(),
        '@label' => $label,
      ]);
    }

    return $this->getPluralLabel();
  }

  /**
   * Builds the download filename for the current type and scope.
   */
  private function buildFilename(
    ?string $entity_type,
    ?string $bundle,
  ): string {
    $name = $this->getPluralName();
    $parts = ['entity-labels', $name];
    if ($entity_type !== NULL) {
      $parts[] = $entity_type;
    }
    if ($bundle !== NULL) {
      $parts[] = $bundle;
    }
    return implode('-', $parts) . '.csv';
  }

}
