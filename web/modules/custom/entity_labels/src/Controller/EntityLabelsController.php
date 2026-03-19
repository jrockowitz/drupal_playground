<?php

declare(strict_types=1);

namespace Drupal\entity_labels\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Url;
use Drupal\entity_labels\EntityLabelsTypeTrait;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Handles all entity-labels report and export routes for both types.
 */
class EntityLabelsController extends ControllerBase {

  use EntityLabelsTypeTrait;

  /**
   * Constructs an EntityLabelsController.
   */
  public function __construct(
    private readonly string $type,
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
    $header = $this->getExporter()->getHeader();
    $header = array_combine($header, $header);

    $rows = $this->getExporter()->getData($entity_type, $bundle);

    // If there are no rows, assume there is the entity type or bundle is invalid.
    if (empty($rows) && ($entity_type || $bundle)) {
      throw new NotFoundHttpException();
    }

    foreach ($rows as &$row) {
      $row = array_intersect_key($row, $header);

      // Track row entity type and bundle since we are overwriting their values.
      $row_entity_type = $row['entity_type'] ?? NULL;
      $row_bundle = $row['bundle'] ?? NULL;

      // Link the entity type.
      if ($row['entity_type']) {
        $row['entity_type'] = [
          'data' => [
            '#type' => 'link',
            '#title' => $row['entity_type'],
            '#url' => Url::fromRoute(
              $this->getReportRoute(),
              ['entity_type' => $row['entity_type']],
            ),
          ],
        ];
      }

      // Link the entity type and bundle for fields only.
      if (!empty($row['bundle']) && $this->type === 'field') {
        $row['bundle'] = [
          'data' => [
            '#type' => 'link',
            '#title' => $row['bundle'],
            '#url' => Url::fromRoute(
              $this->getReportRoute(),
              ['entity_type' => $row_entity_type, 'bundle' => $row_bundle],
            ),
          ],
        ];
      }

      // Convert allowed value to a list.
      if (!empty($row['allowed_values'])) {
        $row['allowed_values'] = [
          'data' => [
            '#theme' => 'item_list',
            '#items' => explode(';', $row['allowed_values']),
          ],
        ];
      }

      // Display field groups as bold text with a gray background.
      $field_type = $row['field_type'] ?? NULL;
      if ($field_type === 'field_group') {
        $row = [
          'data' => $row,
          'style' => 'background-color: #eee; font-weight: bold;',
        ];
      }
    }

    $build = [];

    // Table.
    $build['table'] = [
      '#type' => 'table',
      '#header' => $header,
      '#rows' => $rows,
      '#sticky' => TRUE,
      '#empty' => $this->t('No @types found.', ['@types' => $this->getPluralLabel()]),
    ];

    // Download CSV button.
    $build['export_link'] = [
      '#type' => 'link',
      '#title' => $this->t('⇩ Download CSV'),
      '#url' => Url::fromRoute(
        $this->getExportRoute(),
        [],
        [
          'query' => array_filter([
            'entity_type' => $entity_type,
            'bundle' => $bundle,
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
    $filename = $this->getFilename($entity_type, $bundle);

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
  public function title(?string $entity_type = NULL, ?string $bundle = NULL): string {
    $title_parts = [
      $this->t('@type labels', ['@type' => $this->getSingularLabel()]),
    ];

    if ($entity_type) {
      $entity_type_definition = $this->entityTypeManager()->getDefinition($entity_type);
      $title_parts[] = $entity_type_definition->getLabel();
      if ($bundle) {
        $bundle_label = $this->bundleInfo->getBundleLabels($entity_type)[$bundle];
        if ($bundle_label) {
          $title_parts[] = $bundle_label;
        }
      }
    }

    return implode(': ', $title_parts);
  }

  /**
   * Builds the download filename for the current type and scope.
   */
  private function getFilename(?string $entity_type, ?string $bundle): string {
    $parts = array_filter([
      'entity-labels',
      $this->getPluralName(),
      $entity_type,
      $bundle,
    ]);
    return implode('-', $parts) . '.csv';
  }

}
